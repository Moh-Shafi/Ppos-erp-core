<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Inventory;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;

class Phase2InventoryEnhancedTest extends TestCase
{
    use RefreshDatabase;
    use Phase2TestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase2();
    }

    public function test_inventory_has_maximum_quantity(): void
    {
        $product = $this->createProduct();

        $inventory = new Inventory;
        $inventory->tenant_id = $this->tenant->id;
        $inventory->store_id = $this->store->id;
        $inventory->product_id = $product->id;
        $inventory->quantity = 50;
        $inventory->minimum_quantity = 5;
        $inventory->maximum_quantity = 200;
        $inventory->save();

        $this->assertEquals(200, $inventory->maximum_quantity);
    }

    public function test_low_stock_report(): void
    {
        $product = $this->createProduct();

        $inventory = new Inventory;
        $inventory->tenant_id = $this->tenant->id;
        $inventory->store_id = $this->store->id;
        $inventory->product_id = $product->id;
        $inventory->quantity = 2;
        $inventory->minimum_quantity = 5;
        $inventory->save();

        $response = $this->withToken($this->tokenOwner)->getJson('/api/v1/inventory/reports/low-stock');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_stock_summary_report(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $invService = new InventoryService();
        $invService->increase($this->store, $product, 10, 'initial');
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwner)->getJson('/api/v1/inventory/reports/summary');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_movements_report(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $invService = new InventoryService();
        $invService->increase($this->store, $product, 10, 'purchase');
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwner)->getJson('/api/v1/inventory/reports/movements');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'total', 'per_page', 'current_page', 'last_page']);
    }

    public function test_existing_inventory_tests_still_pass(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $invService = new InventoryService();
        $movement = $invService->increase($this->store, $product, 50, 'purchase');
        $this->assertEquals(50, $movement->after_quantity);

        $movement = $invService->decrease($this->store, $product, 20, 'sale');
        $this->assertEquals(30, $movement->after_quantity);

        $movement = $invService->adjust($this->store, $product, 5);
        $this->assertEquals(35, $movement->after_quantity);
        Auth::forgetGuards();
    }
}
