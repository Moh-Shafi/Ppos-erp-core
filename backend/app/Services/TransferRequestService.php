<?php

namespace App\Services;

use App\Models\TransferRequest;
use App\Models\TransferRequestItem;
use App\Models\Store;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\WarehouseStock;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferRequestService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly InventoryService $inventoryService = new InventoryService(),
    ) {}

    public function createRequest(array $data, int $tenantId): TransferRequest
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $this->validateFromTo($data, $tenantId);

            $count = TransferRequest::where('tenant_id', $tenantId)->count() + 1;
            $requestNumber = 'TR-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $request = new TransferRequest;
            $request->tenant_id = $tenantId;
            $request->request_number = $requestNumber;
            $request->from_store_id = $data['from_store_id'] ?? null;
            $request->from_warehouse_id = $data['from_warehouse_id'] ?? null;
            $request->to_store_id = $data['to_store_id'] ?? null;
            $request->to_warehouse_id = $data['to_warehouse_id'] ?? null;
            $request->status = 'draft';
            $request->requested_by = Auth::id();
            $request->note = $data['note'] ?? null;
            $request->save();

            foreach ($data['items'] as $itemData) {
                $product = Product::where('tenant_id', $tenantId)->findOrFail($itemData['product_id']);

                $item = new TransferRequestItem;
                $item->tenant_id = $tenantId;
                $item->transfer_request_id = $request->id;
                $item->product_id = $itemData['product_id'];
                $item->quantity = $itemData['quantity'];
                $item->batch_id = $itemData['batch_id'] ?? null;
                $item->save();
            }

            $this->auditService->log('transfer_request.created', 'transfer_request', $request->id, null, $request->toArray(), tenantId: $tenantId);

            return $request->fresh(['items.product', 'fromStore', 'fromWarehouse', 'toStore', 'toWarehouse', 'requestedBy']);
        });
    }

    public function submit(int $id, int $tenantId): TransferRequest
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $request = TransferRequest::where('tenant_id', $tenantId)->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'draft') {
                throw new \InvalidArgumentException('Can only submit from draft status', 422);
            }

            if ($request->items()->count() === 0) {
                throw new \InvalidArgumentException('Transfer request must have at least one item', 422);
            }

            $request->update(['status' => 'pending']);

            $this->auditService->log('transfer_request.submitted', 'transfer_request', $request->id, null, ['status' => 'pending'], tenantId: $tenantId);

            return $request->fresh(['items.product']);
        });
    }

    public function approve(int $id, int $tenantId): TransferRequest
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $request = TransferRequest::where('tenant_id', $tenantId)->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                throw new \InvalidArgumentException('Can only approve pending requests', 422);
            }

            if ($request->requested_by === Auth::id()) {
                throw new \InvalidArgumentException('Cannot approve your own transfer request', 422);
            }

            $request->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $this->auditService->log('transfer_request.approved', 'transfer_request', $request->id, null, ['status' => 'approved'], tenantId: $tenantId);

            return $request->fresh(['items.product']);
        });
    }

    public function reject(int $id, int $tenantId, ?string $reason = null): TransferRequest
    {
        return DB::transaction(function () use ($id, $tenantId, $reason) {
            $request = TransferRequest::where('tenant_id', $tenantId)->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                throw new \InvalidArgumentException('Can only reject pending requests', 422);
            }

            $request->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            $this->auditService->log('transfer_request.rejected', 'transfer_request', $request->id, null, ['status' => 'rejected'], tenantId: $tenantId);

            return $request->fresh();
        });
    }

    public function startTransit(int $id, int $tenantId): TransferRequest
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $request = TransferRequest::where('tenant_id', $tenantId)->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'approved') {
                throw new \InvalidArgumentException('Can only start transit from approved status', 422);
            }

            foreach ($request->items as $item) {
                $this->deductFromSource($request, $item, $tenantId);
            }

            $request->update(['status' => 'in_transit']);

            $this->auditService->log('transfer_request.transit_started', 'transfer_request', $request->id, null, ['status' => 'in_transit'], tenantId: $tenantId);

            return $request->fresh(['items.product']);
        });
    }

    public function complete(int $id, int $tenantId): TransferRequest
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $request = TransferRequest::where('tenant_id', $tenantId)->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'in_transit') {
                throw new \InvalidArgumentException('Can only complete from in_transit status', 422);
            }

            foreach ($request->items as $item) {
                $this->addToDestination($request, $item, $tenantId);
            }

            $request->update(['status' => 'completed']);

            $this->auditService->log('transfer_request.completed', 'transfer_request', $request->id, null, ['status' => 'completed'], tenantId: $tenantId);

            return $request->fresh(['items.product']);
        });
    }

    public function cancel(int $id, int $tenantId, ?string $reason = null): TransferRequest
    {
        return DB::transaction(function () use ($id, $tenantId, $reason) {
            $request = TransferRequest::where('tenant_id', $tenantId)->where('id', $id)->lockForUpdate()->firstOrFail();

            if (in_array($request->status, ['completed', 'rejected', 'cancelled'])) {
                throw new \InvalidArgumentException('Cannot cancel a completed, rejected, or already cancelled request', 422);
            }

            if ($request->status === 'in_transit') {
                foreach ($request->items as $item) {
                    $this->returnToSource($request, $item, $tenantId);
                }
            }

            $request->update([
                'status' => 'cancelled',
                'rejection_reason' => $reason,
            ]);

            $this->auditService->log('transfer_request.cancelled', 'transfer_request', $request->id, null, ['status' => 'cancelled'], tenantId: $tenantId);

            return $request->fresh();
        });
    }

    private function validateFromTo(array $data, int $tenantId): void
    {
        $hasFrom = !empty($data['from_store_id']) || !empty($data['from_warehouse_id']);
        $hasTo = !empty($data['to_store_id']) || !empty($data['to_warehouse_id']);

        if (!$hasFrom || !$hasTo) {
            throw new \InvalidArgumentException('Must specify exactly one source and one destination', 422);
        }

        $fromStoreId = $data['from_store_id'] ?? null;
        $toStoreId = $data['to_store_id'] ?? null;
        $fromWhId = $data['from_warehouse_id'] ?? null;
        $toWhId = $data['to_warehouse_id'] ?? null;

        if ($fromStoreId && $toStoreId && $fromStoreId === $toStoreId) {
            throw new \InvalidArgumentException('Source and destination cannot be the same', 422);
        }
        if ($fromWhId && $toWhId && $fromWhId === $toWhId) {
            throw new \InvalidArgumentException('Source and destination cannot be the same', 422);
        }

        if ($fromStoreId) {
            Store::where('tenant_id', $tenantId)->findOrFail($fromStoreId);
        }
        if ($fromWhId) {
            Warehouse::where('tenant_id', $tenantId)->findOrFail($fromWhId);
        }
        if ($toStoreId) {
            Store::where('tenant_id', $tenantId)->findOrFail($toStoreId);
        }
        if ($toWhId) {
            Warehouse::where('tenant_id', $tenantId)->findOrFail($toWhId);
        }
    }

    private function deductFromSource(TransferRequest $request, TransferRequestItem $item, int $tenantId): void
    {
        if ($request->from_store_id) {
            $store = Store::where('tenant_id', $tenantId)->findOrFail($request->from_store_id);
            $product = Product::where('tenant_id', $tenantId)->findOrFail($item->product_id);
            $this->inventoryService->decrease($store, $product, $item->quantity, 'transfer_out');
        } elseif ($request->from_warehouse_id) {
            $stock = WarehouseStock::where('tenant_id', $tenantId)
                ->where('warehouse_id', $request->from_warehouse_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->quantity < $item->quantity) {
                throw new \InvalidArgumentException("Insufficient stock in warehouse for product {$item->product_id}", 422);
            }

            $stock->quantity -= $item->quantity;
            $stock->save();
        }
    }

    private function addToDestination(TransferRequest $request, TransferRequestItem $item, int $tenantId): void
    {
        if ($request->to_store_id) {
            $store = Store::where('tenant_id', $tenantId)->findOrFail($request->to_store_id);
            $product = Product::where('tenant_id', $tenantId)->findOrFail($item->product_id);
            $this->inventoryService->increase($store, $product, $item->quantity, 'transfer_in');
        } elseif ($request->to_warehouse_id) {
            $stock = WarehouseStock::where('tenant_id', $tenantId)
                ->where('warehouse_id', $request->to_warehouse_id)
                ->where('product_id', $item->product_id)
                ->where('batch_id', null)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = new WarehouseStock;
                $stock->tenant_id = $tenantId;
                $stock->warehouse_id = $request->to_warehouse_id;
                $stock->product_id = $item->product_id;
                $stock->quantity = 0;
                $stock->batch_id = null;
                $stock->save();
            }
            $stock->quantity += $item->quantity;
            $stock->save();
        }
    }

    private function returnToSource(TransferRequest $request, TransferRequestItem $item, int $tenantId): void
    {
        if ($request->from_store_id) {
            $store = Store::where('tenant_id', $tenantId)->findOrFail($request->from_store_id);
            $product = Product::where('tenant_id', $tenantId)->findOrFail($item->product_id);
            $this->inventoryService->increase($store, $product, $item->quantity, 'transfer_in');
        } elseif ($request->from_warehouse_id) {
            $stock = WarehouseStock::where('tenant_id', $tenantId)
                ->where('warehouse_id', $request->from_warehouse_id)
                ->where('product_id', $item->product_id)
                ->where('batch_id', null)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = new WarehouseStock;
                $stock->tenant_id = $tenantId;
                $stock->warehouse_id = $request->from_warehouse_id;
                $stock->product_id = $item->product_id;
                $stock->quantity = 0;
                $stock->batch_id = null;
                $stock->save();
            }
            $stock->quantity += $item->quantity;
            $stock->save();
        }
    }
}
