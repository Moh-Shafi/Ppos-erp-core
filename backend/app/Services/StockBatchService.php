<?php

namespace App\Services;

use App\Models\StockBatch;
use App\Models\Product;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class StockBatchService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function createBatch(int $productId, array $data, int $tenantId): StockBatch
    {
        return DB::transaction(function () use ($productId, $data, $tenantId) {
            $product = Product::where('tenant_id', $tenantId)->findOrFail($productId);

            $existing = StockBatch::where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('batch_number', $data['batch_number'])
                ->exists();

            if ($existing) {
                throw new \InvalidArgumentException('Batch number already exists for this product', 422);
            }

            $batch = new StockBatch;
            $batch->tenant_id = $tenantId;
            $batch->product_id = $productId;
            $batch->batch_number = $data['batch_number'];
            $batch->quantity = $data['quantity'];
            $batch->received_date = $data['received_date'];
            $batch->expiry_date = $data['expiry_date'] ?? null;
            $batch->cost_price = $data['cost_price'] ?? null;
            $batch->save();

            $this->auditService->log('stock_batch.created', 'stock_batch', $batch->id, null, $batch->toArray(), tenantId: $tenantId);

            return $batch;
        });
    }

    public function getBatchesForProduct(int $productId, int $tenantId)
    {
        return StockBatch::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->orderBy('received_date', 'desc')
            ->get();
    }

    public function getFefoBatch(int $productId, int $tenantId): ?StockBatch
    {
        return StockBatch::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date', 'asc')
            ->first();
    }
}
