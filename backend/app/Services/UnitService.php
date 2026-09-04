<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\UnitConversion;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class UnitService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function createUnit(array $data, int $tenantId): Unit
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $unit = Unit::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'symbol' => $data['symbol'],
                'is_base_unit' => $data['is_base_unit'] ?? false,
            ]);

            $this->auditService->log('unit.created', 'unit', $unit->id, null, $unit->toArray());

            return $unit;
        });
    }

    public function updateUnit(int $id, array $data, int $tenantId): Unit
    {
        return DB::transaction(function () use ($id, $data, $tenantId) {
            $unit = Unit::where('tenant_id', $tenantId)->findOrFail($id);
            $oldValues = $unit->toArray();
            $unit->update($data);
            $unit->refresh();

            $this->auditService->log('unit.updated', 'unit', $unit->id, $oldValues, $unit->toArray());

            return $unit;
        });
    }

    public function deleteUnit(int $id, int $tenantId): void
    {
        DB::transaction(function () use ($id, $tenantId) {
            $unit = Unit::where('tenant_id', $tenantId)->findOrFail($id);

            if ($unit->productsAsBase()->exists() || $unit->productsAsPurchase()->exists()) {
                throw new \InvalidArgumentException('Cannot delete unit that is assigned to products', 422);
            }

            $oldValues = $unit->toArray();
            $unit->delete();

            $this->auditService->log('unit.deleted', 'unit', $unit->id, $oldValues, null);
        });
    }

    public function addConversion(int $fromUnitId, int $toUnitId, float $factor, int $tenantId): UnitConversion
    {
        return DB::transaction(function () use ($fromUnitId, $toUnitId, $factor, $tenantId) {
            $fromUnit = Unit::where('tenant_id', $tenantId)->findOrFail($fromUnitId);
            $toUnit = Unit::where('tenant_id', $tenantId)->findOrFail($toUnitId);

            $conversion = UnitConversion::create([
                'tenant_id' => $tenantId,
                'from_unit_id' => $fromUnitId,
                'to_unit_id' => $toUnitId,
                'factor' => $factor,
            ]);

            return $conversion;
        });
    }

    public function deleteConversion(int $conversionId, int $tenantId): void
    {
        $conversion = UnitConversion::where('tenant_id', $tenantId)->findOrFail($conversionId);
        $conversion->delete();
    }

    public function convert(float $quantity, int $fromUnitId, int $toUnitId, int $tenantId): float
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }

        $conversion = UnitConversion::where('tenant_id', $tenantId)
            ->where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->first();

        if ($conversion) {
            return $quantity * (float) $conversion->factor;
        }

        $reverse = UnitConversion::where('tenant_id', $tenantId)
            ->where('from_unit_id', $toUnitId)
            ->where('to_unit_id', $fromUnitId)
            ->first();

        if ($reverse && (float) $reverse->factor > 0) {
            return $quantity / (float) $reverse->factor;
        }

        throw new \InvalidArgumentException('No conversion defined between these units', 422);
    }
}
