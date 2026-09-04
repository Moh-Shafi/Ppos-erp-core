<?php

namespace App\Services;

use App\Models\GoodsReceiptNote;
use App\Models\GrnItem;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GrnService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly InventoryService $inventoryService = new InventoryService(),
    ) {}

    public function list(array $filters, int $perPage = 20)
    {
        $query = GoodsReceiptNote::with(['supplier', 'store', 'receivedBy:id,name,email', 'items.product']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): GoodsReceiptNote
    {
        return GoodsReceiptNote::with(['supplier', 'store', 'receivedBy:id,name,email', 'items.product', 'items.batch', 'purchase'])
            ->findOrFail($id);
    }

    public function createFromPo(Purchase $purchase, ?string $note = null): GoodsReceiptNote
    {
        if ($purchase->status !== 'ordered') {
            throw new \DomainException('Purchase must be in ordered status to create GRN');
        }

        $tenantId = $purchase->tenant_id;

        return DB::transaction(function () use ($purchase, $note, $tenantId) {
            $grn = new GoodsReceiptNote;
            $grn->tenant_id = $tenantId;
            $grn->grn_number = $this->generateNumber($tenantId);
            $grn->purchase_id = $purchase->id;
            $grn->store_id = $purchase->store_id;
            $grn->supplier_id = $purchase->supplier_id;
            $grn->status = 'draft';
            $grn->note = $note;
            $grn->save();

            foreach ($purchase->items as $item) {
                GrnItem::create([
                    'tenant_id' => $tenantId,
                    'grn_id' => $grn->id,
                    'product_id' => $item->product_id,
                    'quantity_ordered' => $item->quantity,
                    'quantity_received' => 0,
                    'quantity_rejected' => 0,
                    'unit_cost' => $item->unit_cost,
                ]);
            }

            $this->auditService->log('grn.created', 'goods_receipt_note', $grn->id, null, ['purchase_id' => $purchase->id], tenantId: $tenantId);

            return $grn->fresh(['items.product', 'supplier', 'store']);
        });
    }

    public function create(array $data): GoodsReceiptNote
    {
        $tenantId = Auth::user()->tenant_id;

        return DB::transaction(function () use ($data, $tenantId) {
            $supplier = Supplier::where('tenant_id', $tenantId)->findOrFail($data['supplier_id']);
            $store = Store::where('tenant_id', $tenantId)->findOrFail($data['store_id']);

            $productIds = array_column($data['items'], 'product_id');
            $products = Product::withoutTenantScope()->whereIn('id', $productIds)->get();
            foreach ($products as $product) {
                if ($product->tenant_id !== $tenantId) {
                    throw new \DomainException("Product {$product->id} does not belong to your tenant");
                }
            }

            $grn = new GoodsReceiptNote;
            $grn->tenant_id = $tenantId;
            $grn->grn_number = $this->generateNumber($tenantId);
            $grn->purchase_id = null;
            $grn->store_id = $data['store_id'];
            $grn->supplier_id = $data['supplier_id'];
            $grn->status = 'draft';
            $grn->note = $data['note'] ?? null;
            $grn->save();

            foreach ($data['items'] as $item) {
                GrnItem::create([
                    'tenant_id' => $tenantId,
                    'grn_id' => $grn->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $item['quantity_ordered'] ?? 0,
                    'quantity_received' => 0,
                    'quantity_rejected' => 0,
                    'unit_cost' => $item['unit_cost'],
                ]);
            }

            $this->auditService->log('grn.created', 'goods_receipt_note', $grn->id, null, ['standalone' => true], tenantId: $tenantId);

            return $grn->fresh(['items.product', 'supplier', 'store']);
        });
    }

    public function receive(GoodsReceiptNote $grn, array $items): GoodsReceiptNote
    {
        if ($grn->status !== 'draft') {
            throw new \DomainException('Only draft GRNs can be received');
        }

        $tenantId = $grn->tenant_id;

        return DB::transaction(function () use ($grn, $items, $tenantId) {
            $grn->load('items.product');

            foreach ($items as $itemData) {
                $grnItem = $grn->items->firstWhere('id', $itemData['id']);
                if (!$grnItem) {
                    throw new \DomainException("GRN item {$itemData['id']} not found");
                }

                $qtyReceived = $itemData['quantity_received'] ?? 0;
                $qtyRejected = $itemData['quantity_rejected'] ?? 0;

                if ($grn->purchase_id && ($qtyReceived + $qtyRejected) > $grnItem->quantity_ordered) {
                    throw new \DomainException("Received + rejected cannot exceed ordered quantity for item {$grnItem->id}");
                }

                $grnItem->quantity_received = $qtyReceived;
                $grnItem->quantity_rejected = $qtyRejected;
                $grnItem->rejection_reason = $itemData['rejection_reason'] ?? null;
                $grnItem->batch_id = $itemData['batch_id'] ?? null;
                $grnItem->expiry_date = $itemData['expiry_date'] ?? null;
                $grnItem->note = $itemData['note'] ?? null;
                $grnItem->save();

                if ($qtyReceived > 0) {
                    $store = $grn->store;
                    $product = $grnItem->product;
                    $this->inventoryService->increase(
                        $store,
                        $product,
                        $qtyReceived,
                        'grn',
                        $grn,
                        "GRN {$grn->grn_number}",
                    );
                }
            }

            $grn->status = 'received';
            $grn->received_by = Auth::id();
            $grn->received_date = now()->format('Y-m-d');
            $grn->save();

            if ($grn->purchase_id) {
                $purchase = $grn->purchase;
                $purchase->grn_id = $grn->id;
                $purchase->status = 'received';
                $purchase->save();
            }

            $this->auditService->log('grn.received', 'goods_receipt_note', $grn->id, null, [
                'received_date' => $grn->received_date,
                'items_count' => count($items),
            ], tenantId: $tenantId);

            return $grn->fresh(['items.product', 'supplier', 'store', 'purchase']);
        });
    }

    public function cancel(GoodsReceiptNote $grn): GoodsReceiptNote
    {
        if ($grn->status !== 'draft') {
            throw new \DomainException('Only draft GRNs can be cancelled');
        }

        $grn->status = 'cancelled';
        $grn->save();

        $this->auditService->log('grn.cancelled', 'goods_receipt_note', $grn->id, null, ['status' => 'cancelled'], tenantId: $grn->tenant_id);

        return $grn->fresh(['items.product', 'supplier', 'store']);
    }

    private function generateNumber(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $prefix = "GRN-{$date}-";

        $last = GoodsReceiptNote::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('grn_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->grn_number);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
