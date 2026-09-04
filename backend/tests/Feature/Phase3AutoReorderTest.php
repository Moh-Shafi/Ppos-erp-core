<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Services\AutoReorderService;
use Illuminate\Support\Facades\Auth;

class Phase3AutoReorderTest extends Phase3TestHelper
{
    private AutoReorderService $autoReorderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase3();
        $this->autoReorderService = new AutoReorderService();
    }

    private function createLowStockProduct(): void
    {
        $product = $this->createProduct();

        $inventory = new Inventory;
        $inventory->tenant_id = $this->tenant->id;
        $inventory->store_id = $this->store->id;
        $inventory->product_id = $product->id;
        $inventory->quantity = 5;
        $inventory->minimum_quantity = 20;
        $inventory->maximum_quantity = 100;
        $inventory->save();
    }

    public function test_low_stock_report(): void
    {
        Auth::login($this->owner);
        $this->createLowStockProduct();

        $report = $this->autoReorderService->report($this->store->id);

        $this->assertEquals(1, $report['count']);
        $this->assertEquals(5, $report['data'][0]['current_stock']);
        $this->assertEquals(20, $report['data'][0]['minimum_quantity']);
    }

    public function test_suggested_qty_with_maximum(): void
    {
        Auth::login($this->owner);
        $this->createLowStockProduct();

        $report = $this->autoReorderService->report($this->store->id);

        $this->assertEquals(95, $report['data'][0]['suggested_qty']); // 100 - 5
    }

    public function test_suggested_qty_without_maximum(): void
    {
        Auth::login($this->owner);
        $product = $this->createProduct();

        $inventory = new Inventory;
        $inventory->tenant_id = $this->tenant->id;
        $inventory->store_id = $this->store->id;
        $inventory->product_id = $product->id;
        $inventory->quantity = 3;
        $inventory->minimum_quantity = 15;
        $inventory->maximum_quantity = null;
        $inventory->save();

        $report = $this->autoReorderService->report($this->store->id);

        $this->assertEquals(30, $report['data'][0]['suggested_qty']); // 15 * 2
    }

    public function test_empty_report_when_no_low_stock(): void
    {
        Auth::login($this->owner);
        $product = $this->createProduct();

        $inventory = new Inventory;
        $inventory->tenant_id = $this->tenant->id;
        $inventory->store_id = $this->store->id;
        $inventory->product_id = $product->id;
        $inventory->quantity = 100;
        $inventory->minimum_quantity = 10;
        $inventory->save();

        $report = $this->autoReorderService->report($this->store->id);

        $this->assertEquals(0, $report['count']);
    }

    public function test_auto_reorder_api(): void
    {
        Auth::login($this->owner);
        $this->createLowStockProduct();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->getJson('/api/v1/auto-reorder/report?store_id=' . $this->store->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'store_id', 'count']);
    }
}
