<?php

namespace App\Services;

use App\Models\StockAdjustmentReason;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class AdjustmentReasonService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function listReasons(int $tenantId, array $filters = [])
    {
        $query = StockAdjustmentReason::where('tenant_id', $tenantId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return $query->orderBy('is_system', 'desc')->orderBy('name')->get();
    }

    public function createReason(array $data, int $tenantId): StockAdjustmentReason
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $reason = new StockAdjustmentReason;
            $reason->tenant_id = $tenantId;
            $reason->name = $data['name'];
            $reason->category = $data['category'];
            $reason->is_system = false;
            $reason->is_active = $data['is_active'] ?? true;
            $reason->save();

            $this->auditService->log('adjustment_reason.created', 'stock_adjustment_reason', $reason->id, null, $reason->toArray(), tenantId: $tenantId);

            return $reason;
        });
    }

    public function updateReason(int $id, array $data, int $tenantId): StockAdjustmentReason
    {
        return DB::transaction(function () use ($id, $data, $tenantId) {
            $reason = StockAdjustmentReason::where('tenant_id', $tenantId)->findOrFail($id);

            if ($reason->is_system && isset($data['name'])) {
                throw new \InvalidArgumentException('Cannot modify name of system reason', 422);
            }

            $oldValues = $reason->toArray();
            $reason->update($data);
            $reason->refresh();

            $this->auditService->log('adjustment_reason.updated', 'stock_adjustment_reason', $reason->id, $oldValues, $reason->toArray(), tenantId: $tenantId);

            return $reason;
        });
    }

    public function deleteReason(int $id, int $tenantId): void
    {
        DB::transaction(function () use ($id, $tenantId) {
            $reason = StockAdjustmentReason::where('tenant_id', $tenantId)->findOrFail($id);

            if ($reason->is_system) {
                throw new \InvalidArgumentException('Cannot delete system reason', 422);
            }

            $oldValues = $reason->toArray();
            $reason->delete();

            $this->auditService->log('adjustment_reason.deleted', 'stock_adjustment_reason', $id, $oldValues, null, tenantId: $tenantId);
        });
    }

    public function seedSystemReasons(int $tenantId): void
    {
        $systemReasons = [
            ['name' => 'Damaged Goods', 'category' => 'damaged'],
            ['name' => 'Lost/Missing', 'category' => 'lost'],
            ['name' => 'Found/Surplus', 'category' => 'found'],
            ['name' => 'Recount Adjustment', 'category' => 'recount'],
            ['name' => 'Initial Stock', 'category' => 'initial'],
            ['name' => 'Other', 'category' => 'other'],
        ];

        foreach ($systemReasons as $reason) {
            $existing = StockAdjustmentReason::where('tenant_id', $tenantId)
                ->where('name', $reason['name'])
                ->where('category', $reason['category'])
                ->first();

            if (!$existing) {
                $model = new StockAdjustmentReason;
                $model->tenant_id = $tenantId;
                $model->name = $reason['name'];
                $model->category = $reason['category'];
                $model->is_system = true;
                $model->is_active = true;
                $model->save();
            }
        }
    }
}
