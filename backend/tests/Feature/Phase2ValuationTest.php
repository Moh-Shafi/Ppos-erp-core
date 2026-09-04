<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Services\StockValuationService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;

class Phase2ValuationTest extends TestCase
{
    use RefreshDatabase;
    use Phase2TestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase2();
    }

    public function test_fifo_valuation(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $invService = new InventoryService();
        $invService->increase($this->store, $product, 10, 'purchase');
        $invService->increase($this->store, $product, 10, 'purchase');
        $invService->decrease($this->store, $product, 5, 'sale');
        Auth::forgetGuards();

        $valuationService = new StockValuationService();
        $result = $valuationService->calculate($this->tenant->id, 'fifo');

        $this->assertEquals('fifo', $result['method']);
        $this->assertNotEmpty($result['data']);
        $productValuation = collect($result['data'])->firstWhere('product_id', $product->id);
        $this->assertNotNull($productValuation);
        $this->assertEquals(15, $productValuation['quantity']);
    }

    public function test_lifo_valuation(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $invService = new InventoryService();
        $invService->increase($this->store, $product, 10, 'purchase');
        $invService->increase($this->store, $product, 10, 'purchase');
        $invService->decrease($this->store, $product, 5, 'sale');
        Auth::forgetGuards();

        $valuationService = new StockValuationService();
        $result = $valuationService->calculate($this->tenant->id, 'lifo');

        $this->assertEquals('lifo', $result['method']);
        $productValuation = collect($result['data'])->firstWhere('product_id', $product->id);
        $this->assertNotNull($productValuation);
        $this->assertEquals(15, $productValuation['quantity']);
    }

    public function test_average_valuation(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $invService = new InventoryService();
        $invService->increase($this->store, $product, 20, 'purchase');
        Auth::forgetGuards();

        $valuationService = new StockValuationService();
        $result = $valuationService->calculate($this->tenant->id, 'average');

        $this->assertEquals('average', $result['method']);
        $productValuation = collect($result['data'])->firstWhere('product_id', $product->id);
        $this->assertNotNull($productValuation);
        $this->assertEquals(20, $productValuation['quantity']);
    }

    public function test_valuation_with_no_movements(): void
    {
        $valuationService = new StockValuationService();
        $result = $valuationService->calculate($this->tenant->id, 'average');

        $this->assertEmpty($result['data']);
    }

    public function test_valuation_report_api(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $invService = new InventoryService();
        $invService->increase($this->store, $product, 10, 'purchase');
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwner)->getJson('/api/v1/inventory/reports/valuation?method=fifo');
        $response->assertStatus(200);
        $response->assertJsonPath('method', 'fifo');
    }

    public function test_tenant_isolation(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $invService = new InventoryService();
        $invService->increase($this->store, $product, 10, 'purchase');
        Auth::forgetGuards();

        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other-val']);

        $valuationService = new StockValuationService();
        $result = $valuationService->calculate($tenant2->id, 'fifo');

        $this->assertEmpty($result['data']);
    }

    public function test_valuation_disabled_without_feature(): void
    {
        $this->disableFeature('inventory.valuation');

        $response = $this->withToken($this->tokenOwner)->getJson('/api/v1/inventory/reports/valuation?method=fifo');
        $response->assertStatus(403);
    }
}
