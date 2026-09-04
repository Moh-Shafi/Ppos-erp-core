<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Product;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function createWarehouse(array $data, int $tenantId): Warehouse
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $warehouse = new Warehouse;
            $warehouse->tenant_id = $tenantId;
            $warehouse->name = $data['name'];
            $warehouse->address = $data['address'] ?? null;
            $warehouse->phone = $data['phone'] ?? null;
            $warehouse->is_active = $data['is_active'] ?? true;
            $warehouse->save();

            $this->auditService->log('warehouse.created', 'warehouse', $warehouse->id, null, $warehouse->toArray(), tenantId: $tenantId);

            return $warehouse;
        });
    }

    public function updateWarehouse(int $id, array $data, int $tenantId): Warehouse
    {
        return DB::transaction(function () use ($id, $data, $tenantId) {
            $warehouse = Warehouse::where('tenant_id', $tenantId)->findOrFail($id);
            $oldValues = $warehouse->toArray();
            $warehouse->update($data);
            $warehouse->refresh();

            $this->auditService->log('warehouse.updated', 'warehouse', $warehouse->id, $oldValues, $warehouse->toArray(), tenantId: $tenantId);

            return $warehouse;
        });
    }

    public function deleteWarehouse(int $id, int $tenantId): void
    {
        DB::transaction(function () use ($id, $tenantId) {
            $warehouse = Warehouse::where('tenant_id', $tenantId)->findOrFail($id);

            if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
                throw new \InvalidArgumentException('Cannot delete warehouse with existing stock', 422);
            }

            $oldValues = $warehouse->toArray();
            $warehouse->delete();

            $this->auditService->log('warehouse.deleted', 'warehouse', $id, $oldValues, null, tenantId: $tenantId);
        });
    }

    public function getStock(int $warehouseId, int $tenantId, array $filters = [])
    {
        $query = WarehouseStock::where('tenant_id', $tenantId)
            ->where('warehouse_id', $warehouseId)
            ->with('product', 'batch');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        if (!empty($filters['low_stock']) && $filters['low_stock']) {
            $query->where('quantity', '<=', 0);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function adjustStock(int $warehouseId, int $productId, int $delta, int $tenantId, ?int $batchId = null, ?int $reasonId = null, ?string $note = null): WarehouseStock
    {
        return DB::transaction(function () use ($warehouseId, $productId, $delta, $tenantId, $batchId, $reasonId, $note) {
            $warehouse = Warehouse::where('tenant_id', $tenantId)->findOrFail($warehouseId);
            $product = Product::where('tenant_id', $tenantId)->findOrFail($productId);

            $stock = WarehouseStock::where('tenant_id', $tenantId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('batch_id', $batchId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = new WarehouseStock;
                $stock->tenant_id = $tenantId;
                $stock->warehouse_id = $warehouseId;
                $stock->product_id = $productId;
                $stock->quantity = 0;
                $stock->batch_id = $batchId;
                $stock->save();
            }

            $newQuantity = $stock->quantity + $delta;

            if ($newQuantity < 0) {
                throw new \InvalidArgumentException(
                    "Insufficient stock. Current: {$stock->quantity}, Requested change: {$delta}"
                );
            }

            $stock->quantity = $newQuantity;
            $stock->save();

            $this->auditService->log('warehouse.stock_adjusted', 'warehouse_stock', $stock->id, null, [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'delta' => $delta,
                'new_quantity' => $newQuantity,
                'batch_id' => $batchId,
                'reason_id' => $reasonId,
                'note' => $note,
            ], tenantId: $tenantId);

            return $stock->fresh(['product', 'batch']);
        });
    }
}
