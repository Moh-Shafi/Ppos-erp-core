<?php

namespace Tests\Feature;

use App\Models\DiscountPreset;
use Illuminate\Support\Facades\Auth;

class Phase4DiscountPresetTest extends Phase4TestHelper
{
    public function test_create_discount_preset_percentage(): void
    {
        $this->setupPhase4();

        Auth::login($this->owner);
        $service = app(\App\Services\DiscountPresetService::class);

        $preset = $service->create([
            'name' => '10% Off',
            'type' => 'percentage',
            'value' => 10,
        ]);

        $this->assertEquals('10% Off', $preset->name);
        $this->assertEquals('percentage', $preset->type);
        $this->assertEquals(10, (float) $preset->value);
        $this->assertTrue($preset->is_active);
    }

    public function test_create_discount_preset_fixed(): void
    {
        $this->setupPhase4();

        Auth::login($this->owner);
        $service = app(\App\Services\DiscountPresetService::class);

        $preset = $service->create([
            'name' => 'Rp 5000 Off',
            'type' => 'fixed',
            'value' => 5000,
        ]);

        $this->assertEquals('fixed', $preset->type);
        $this->assertEquals(5000, (float) $preset->value);
    }

    public function test_list_discount_presets(): void
    {
        $this->setupPhase4();

        Auth::login($this->owner);
        $service = app(\App\Services\DiscountPresetService::class);

        $service->create(['name' => 'A', 'type' => 'percentage', 'value' => 5]);
        $service->create(['name' => 'B', 'type' => 'fixed', 'value' => 1000]);

        $presets = $service->list($this->tenant->id);
        $this->assertCount(2, $presets);
    }

    public function test_list_only_active_presets(): void
    {
        $this->setupPhase4();

        Auth::login($this->owner);
        $service = app(\App\Services\DiscountPresetService::class);

        $service->create(['name' => 'Active', 'type' => 'percentage', 'value' => 5, 'is_active' => true]);
        $service->create(['name' => 'Inactive', 'type' => 'percentage', 'value' => 10, 'is_active' => false]);

        $presets = $service->list($this->tenant->id, true);
        $this->assertCount(1, $presets);
        $this->assertEquals('Active', $presets->first()->name);
    }

    public function test_update_discount_preset(): void
    {
        $this->setupPhase4();

        Auth::login($this->owner);
        $service = app(\App\Services\DiscountPresetService::class);

        $preset = $service->create(['name' => 'Original', 'type' => 'percentage', 'value' => 5]);
        $updated = $service->update($preset->id, ['name' => 'Updated', 'value' => 15]);

        $this->assertEquals('Updated', $updated->name);
        $this->assertEquals(15, (float) $updated->value);
    }

    public function test_delete_discount_preset(): void
    {
        $this->setupPhase4();

        Auth::login($this->owner);
        $service = app(\App\Services\DiscountPresetService::class);

        $preset = $service->create(['name' => 'Delete Me', 'type' => 'percentage', 'value' => 5]);
        $service->delete($preset->id);

        $this->assertDatabaseMissing('discount_presets', ['id' => $preset->id]);
    }

    public function test_update_nonexistent_preset_rejected(): void
    {
        $this->setupPhase4();

        Auth::login($this->owner);
        $service = app(\App\Services\DiscountPresetService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');
        $service->update(999999, ['name' => 'Nope']);
    }

    public function test_discount_preset_tenant_isolated(): void
    {
        $this->setupPhase4();

        Auth::login($this->owner);
        $service = app(\App\Services\DiscountPresetService::class);
        $service->create(['name' => 'Tenant A Preset', 'type' => 'percentage', 'value' => 5]);

        // Tenant B should not see it
        Auth::login($this->ownerB);
        $presets = $service->list($this->tenantB->id);
        $this->assertCount(0, $presets);
    }
}
