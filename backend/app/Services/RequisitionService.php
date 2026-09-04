<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\Store;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequisitionService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly PurchaseService $purchaseService = new PurchaseService(new InventoryService()),
    ) {}

    public function list(array $filters, int $perPage = 20)
    {
        $query = PurchaseRequisition::with(['store', 'requestedBy:id,name,email', 'approvedBy:id,name,email', 'items.product']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): PurchaseRequisition
    {
        return PurchaseRequisition::with(['store', 'requestedBy:id,name,email', 'approvedBy:id,name,email', 'items.product'])
            ->findOrFail($id);
    }

    public function create(array $data): PurchaseRequisition
    {
        $tenantId = Auth::user()->tenant_id;

        return DB::transaction(function () use ($data, $tenantId) {
            $store = Store::where('tenant_id', $tenantId)->findOrFail($data['store_id']);

            $productIds = array_column($data['items'], 'product_id');
            $products = Product::withoutTenantScope()->whereIn('id', $productIds)->get();
            foreach ($products as $product) {
                if ($product->tenant_id !== $tenantId) {
                    throw new \DomainException("Product {$product->id} does not belong to your tenant");
                }
            }

            $requisition = new PurchaseRequisition;
            $requisition->tenant_id = $tenantId;
            $requisition->store_id = $data['store_id'];
            $requisition->request_number = $this->generateNumber($tenantId);
            $requisition->status = 'draft';
            $requisition->requested_by = Auth::id();
            $requisition->note = $data['note'] ?? null;
            $requisition->save();

            foreach ($data['items'] as $item) {
                PurchaseRequisitionItem::create([
                    'tenant_id' => $tenantId,
                    'requisition_id' => $requisition->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'estimated_cost' => $item['estimated_cost'] ?? null,
                    'note' => $item['note'] ?? null,
                ]);
            }

            $this->auditService->log('requisition.created', 'purchase_requisition', $requisition->id, null, $requisition->toArray(), tenantId: $tenantId);

            return $requisition->fresh(['items.product', 'store']);
        });
    }

    public function update(PurchaseRequisition $requisition, array $data): PurchaseRequisition
    {
        if ($requisition->status !== 'draft') {
            throw new \DomainException('Only draft requisitions can be edited');
        }

        $tenantId = $requisition->tenant_id;

        return DB::transaction(function () use ($requisition, $data, $tenantId) {
            if (isset($data['note'])) {
                $requisition->note = $data['note'];
            }

            if (isset($data['items'])) {
                $requisition->items()->delete();
                foreach ($data['items'] as $item) {
                    PurchaseRequisitionItem::create([
                        'tenant_id' => $tenantId,
                        'requisition_id' => $requisition->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'estimated_cost' => $item['estimated_cost'] ?? null,
                        'note' => $item['note'] ?? null,
                    ]);
                }
            }

            $requisition->save();

            return $requisition->fresh(['items.product', 'store']);
        });
    }

    public function submit(PurchaseRequisition $requisition): PurchaseRequisition
    {
        if ($requisition->status !== 'draft') {
            throw new \DomainException('Only draft requisitions can be submitted');
        }

        $requisition->status = 'pending';
        $requisition->save();

        $this->auditService->log('requisition.submitted', 'purchase_requisition', $requisition->id, null, ['status' => 'pending'], tenantId: $requisition->tenant_id);

        return $requisition->fresh(['items.product', 'store']);
    }

    public function approve(PurchaseRequisition $requisition): PurchaseRequisition
    {
        if ($requisition->status !== 'pending') {
            throw new \DomainException('Only pending requisitions can be approved');
        }

        if ($requisition->requested_by === Auth::id()) {
            throw new \DomainException('Cannot approve your own requisition');
        }

        $requisition->status = 'approved';
        $requisition->approved_by = Auth::id();
        $requisition->approved_at = now();
        $requisition->save();

        $this->auditService->log('requisition.approved', 'purchase_requisition', $requisition->id, null, [
            'status' => 'approved',
            'approved_by' => Auth::id(),
        ], tenantId: $requisition->tenant_id);

        return $requisition->fresh(['items.product', 'store']);
    }

    public function reject(PurchaseRequisition $requisition, string $reason): PurchaseRequisition
    {
        if ($requisition->status !== 'pending') {
            throw new \DomainException('Only pending requisitions can be rejected');
        }

        if ($requisition->requested_by === Auth::id()) {
            throw new \DomainException('Cannot reject your own requisition');
        }

        $requisition->status = 'rejected';
        $requisition->rejection_reason = $reason;
        $requisition->save();

        $this->auditService->log('requisition.rejected', 'purchase_requisition', $requisition->id, null, [
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ], tenantId: $requisition->tenant_id);

        return $requisition->fresh(['items.product', 'store']);
    }

    public function cancel(PurchaseRequisition $requisition): PurchaseRequisition
    {
        if (!in_array($requisition->status, ['draft', 'pending'])) {
            throw new \DomainException('Only draft or pending requisitions can be cancelled');
        }

        $requisition->status = 'cancelled';
        $requisition->save();

        $this->auditService->log('requisition.cancelled', 'purchase_requisition', $requisition->id, null, ['status' => 'cancelled'], tenantId: $requisition->tenant_id);

        return $requisition->fresh(['items.product', 'store']);
    }

    public function convertToPo(PurchaseRequisition $requisition, array $data): Purchase
    {
        if ($requisition->status !== 'approved') {
            throw new \DomainException('Only approved requisitions can be converted to PO');
        }

        $tenantId = $requisition->tenant_id;

        $poData = [
            'supplier_id' => $data['supplier_id'],
            'store_id' => $requisition->store_id,
            'purchase_date' => now()->format('Y-m-d'),
            'items' => array_map(function ($item) {
                return [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                ];
            }, $data['items']),
        ];

        $purchase = $this->purchaseService->create($poData);
        $purchase->requisition_id = $requisition->id;
        $purchase->save();

        $this->auditService->log('requisition.converted_to_po', 'purchase_requisition', $requisition->id, null, [
            'purchase_id' => $purchase->id,
        ], tenantId: $tenantId);

        return $purchase->fresh(['items.product', 'supplier', 'store']);
    }

    public function delete(PurchaseRequisition $requisition): void
    {
        if ($requisition->status !== 'draft') {
            throw new \DomainException('Only draft requisitions can be deleted');
        }

        $requisition->items()->delete();
        $requisition->delete();
    }

    private function generateNumber(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $prefix = "PR-{$date}-";

        $last = PurchaseRequisition::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('request_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->request_number);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
