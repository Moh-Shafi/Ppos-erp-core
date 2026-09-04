<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\StockAdjustmentReason;
use App\Services\AdjustmentReasonService;

class Phase2AdjustmentReasonTest extends TestCase
{
    use RefreshDatabase;
    use Phase2TestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase2();
    }

    public function test_system_reasons_seeded(): void
    {
        $service = new AdjustmentReasonService();
        $service->seedSystemReasons($this->tenant->id);

        $this->assertDatabaseHas('stock_adjustment_reasons', ['tenant_id' => $this->tenant->id, 'name' => 'Damaged Goods', 'category' => 'damaged', 'is_system' => true]);
        $this->assertDatabaseHas('stock_adjustment_reasons', ['tenant_id' => $this->tenant->id, 'name' => 'Lost/Missing', 'category' => 'lost', 'is_system' => true]);
        $this->assertDatabaseHas('stock_adjustment_reasons', ['tenant_id' => $this->tenant->id, 'name' => 'Found/Surplus', 'category' => 'found', 'is_system' => true]);
        $this->assertDatabaseHas('stock_adjustment_reasons', ['tenant_id' => $this->tenant->id, 'name' => 'Recount Adjustment', 'category' => 'recount', 'is_system' => true]);
        $this->assertDatabaseHas('stock_adjustment_reasons', ['tenant_id' => $this->tenant->id, 'name' => 'Initial Stock', 'category' => 'initial', 'is_system' => true]);
        $this->assertDatabaseHas('stock_adjustment_reasons', ['tenant_id' => $this->tenant->id, 'name' => 'Other', 'category' => 'other', 'is_system' => true]);
    }

    public function test_create_custom_reason(): void
    {
        $service = new AdjustmentReasonService();
        $reason = $service->createReason([
            'name' => 'Theft',
            'category' => 'lost',
        ], $this->tenant->id);

        $this->assertEquals('Theft', $reason->name);
        $this->assertFalse($reason->is_system);
        $this->assertTrue($reason->is_active);
    }

    public function test_update_custom_reason(): void
    {
        $service = new AdjustmentReasonService();
        $reason = $service->createReason(['name' => 'Old Name', 'category' => 'other'], $this->tenant->id);
        $reason = $service->updateReason($reason->id, ['name' => 'New Name'], $this->tenant->id);

        $this->assertEquals('New Name', $reason->name);
    }

    public function test_delete_custom_reason(): void
    {
        $service = new AdjustmentReasonService();
        $reason = $service->createReason(['name' => 'Delete Me', 'category' => 'other'], $this->tenant->id);
        $service->deleteReason($reason->id, $this->tenant->id);

        $this->assertDatabaseMissing('stock_adjustment_reasons', ['id' => $reason->id]);
    }

    public function test_cannot_delete_system_reason(): void
    {
        $service = new AdjustmentReasonService();
        $service->seedSystemReasons($this->tenant->id);
        $reason = StockAdjustmentReason::where('tenant_id', $this->tenant->id)->where('is_system', true)->first();

        $this->expectException(\InvalidArgumentException::class);
        $service->deleteReason($reason->id, $this->tenant->id);
    }

    public function test_cannot_update_system_reason_name(): void
    {
        $service = new AdjustmentReasonService();
        $service->seedSystemReasons($this->tenant->id);
        $reason = StockAdjustmentReason::where('tenant_id', $this->tenant->id)->where('is_system', true)->first();

        $this->expectException(\InvalidArgumentException::class);
        $service->updateReason($reason->id, ['name' => 'Changed'], $this->tenant->id);
    }

    public function test_toggle_system_reason_active(): void
    {
        $service = new AdjustmentReasonService();
        $service->seedSystemReasons($this->tenant->id);
        $reason = StockAdjustmentReason::where('tenant_id', $this->tenant->id)->where('is_system', true)->first();

        $reason = $service->updateReason($reason->id, ['is_active' => false], $this->tenant->id);
        $this->assertFalse($reason->is_active);
    }

    public function test_api_list_reasons(): void
    {
        $service = new AdjustmentReasonService();
        $service->seedSystemReasons($this->tenant->id);

        $response = $this->withToken($this->tokenOwner)->getJson('/api/v1/adjustment-reasons');
        $response->assertStatus(200);
        $response->assertJsonCount(6, 'data');
    }

    public function test_api_create_reason(): void
    {
        $response = $this->withToken($this->tokenOwner)->postJson('/api/v1/adjustment-reasons', [
            'name' => 'API Reason',
            'category' => 'damaged',
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('reason.name', 'API Reason');
    }

    public function test_adjust_with_reason(): void
    {
        $service = new AdjustmentReasonService();
        $service->seedSystemReasons($this->tenant->id);
        $reason = StockAdjustmentReason::where('tenant_id', $this->tenant->id)->first();

        $product = $this->createProduct();
        $inv = new \App\Models\Inventory;
        $inv->tenant_id = $this->tenant->id;
        $inv->store_id = $this->store->id;
        $inv->product_id = $product->id;
        $inv->quantity = 50;
        $inv->minimum_quantity = 5;
        $inv->save();

        $response = $this->withToken($this->tokenOwner)->postJson('/api/v1/inventory/adjust', [
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'delta' => -5,
            'reason_id' => $reason->id,
            'note' => 'Damaged in storage',
        ]);
        $response->assertStatus(201);
    }

    public function test_movement_records_reason(): void
    {
        $service = new AdjustmentReasonService();
        $service->seedSystemReasons($this->tenant->id);
        $reason = StockAdjustmentReason::where('tenant_id', $this->tenant->id)->first();

        $product = $this->createProduct();
        $inv = new \App\Models\Inventory;
        $inv->tenant_id = $this->tenant->id;
        $inv->store_id = $this->store->id;
        $inv->product_id = $product->id;
        $inv->quantity = 50;
        $inv->minimum_quantity = 5;
        $inv->save();

        $this->withToken($this->tokenOwner)->postJson('/api/v1/inventory/adjust', [
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'delta' => -5,
            'reason_id' => $reason->id,
            'note' => 'Damaged in storage',
        ]);

        $movement = \App\Models\InventoryMovement::where('tenant_id', $this->tenant->id)
            ->where('product_id', $product->id)
            ->where('type', 'adjustment')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals($reason->id, $movement->reason_id);
    }
}
