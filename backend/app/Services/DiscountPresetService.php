<?php

namespace App\Services;

use App\Models\DiscountPreset;
use Illuminate\Support\Facades\Auth;

class DiscountPresetService
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    public function list(int $tenantId, ?bool $isActiveOnly = null)
    {
        $query = DiscountPreset::withoutTenantScope()
            ->where('tenant_id', $tenantId);

        if ($isActiveOnly === true) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    public function create(array $data): DiscountPreset
    {
        $tenantId = Auth::user()->tenant_id;

        $preset = new DiscountPreset;
        $preset->tenant_id = $tenantId;
        $preset->name = $data['name'];
        $preset->type = $data['type'];
        $preset->value = $data['value'];
        $preset->is_active = $data['is_active'] ?? true;
        $preset->sort_order = $data['sort_order'] ?? 0;
        $preset->save();

        $this->auditService->log(
            'pos.discount_preset_created',
            'discount_preset',
            $preset->id,
            null,
            ['name' => $preset->name, 'type' => $preset->type, 'value' => $preset->value],
            tenantId: $tenantId,
        );

        return $preset;
    }

    public function update(int $id, array $data): DiscountPreset
    {
        $tenantId = Auth::user()->tenant_id;

        $preset = DiscountPreset::withoutTenantScope()
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$preset) {
            throw new \DomainException('Discount preset not found');
        }

        $old = $preset->getAttributes();

        if (isset($data['name'])) $preset->name = $data['name'];
        if (isset($data['type'])) $preset->type = $data['type'];
        if (isset($data['value'])) $preset->value = $data['value'];
        if (isset($data['is_active'])) $preset->is_active = $data['is_active'];
        if (isset($data['sort_order'])) $preset->sort_order = $data['sort_order'];
        $preset->save();

        $this->auditService->log(
            'pos.discount_preset_updated',
            'discount_preset',
            $preset->id,
            $old,
            $preset->getAttributes(),
            tenantId: $tenantId,
        );

        return $preset;
    }

    public function delete(int $id): void
    {
        $tenantId = Auth::user()->tenant_id;

        $preset = DiscountPreset::withoutTenantScope()
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$preset) {
            throw new \DomainException('Discount preset not found');
        }

        $presetName = $preset->name;
        $preset->delete();

        $this->auditService->log(
            'pos.discount_preset_deleted',
            'discount_preset',
            $id,
            null,
            ['name' => $presetName],
            tenantId: $tenantId,
        );
    }
}
