<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\StockBatch;
use App\Models\Product;
use App\Models\Role;
use App\Services\StockBatchService;

class Phase2BatchTest extends TestCase
{
    use RefreshDatabase;
    use Phase2TestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase2();
    }

    public function test_create_batch(): void
    {
        $product = $this->createProduct();

        $service = new StockBatchService();
        $batch = $service->createBatch($product->id, [
            'batch_number' => 'BATCH-001',
            'quantity' => 100,
            'received_date' => '2026-01-15',
            'expiry_date' => '2026-06-15',
            'cost_price' => 5000,
        ], $this->tenant->id);

        $this->assertInstanceOf(StockBatch::class, $batch);
        $this->assertEquals('BATCH-001', $batch->batch_number);
        $this->assertEquals($this->tenant->id, $batch->tenant_id);
    }

    public function test_duplicate_batch_number_rejected(): void
    {
        $product = $this->createProduct();

        $service = new StockBatchService();
        $service->createBatch($product->id, [
            'batch_number' => 'DUP-001',
            'quantity' => 50,
            'received_date' => '2026-01-15',
        ], $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->createBatch($product->id, [
            'batch_number' => 'DUP-001',
            'quantity' => 30,
            'received_date' => '2026-01-20',
        ], $this->tenant->id);
    }

    public function test_batch_with_expiry(): void
    {
        $product = $this->createProduct();

        $service = new StockBatchService();
        $batch = $service->createBatch($product->id, [
            'batch_number' => 'EXP-001',
            'quantity' => 100,
            'received_date' => '2026-01-15',
            'expiry_date' => '2026-12-31',
        ], $this->tenant->id);

        $this->assertNotNull($batch->expiry_date);
        $this->assertEquals('2026-12-31', $batch->expiry_date->format('Y-m-d'));
    }

    public function test_fefo_selection(): void
    {
        $product = $this->createProduct();

        $service = new StockBatchService();

        $service->createBatch($product->id, [
            'batch_number' => 'LATER',
            'quantity' => 50,
            'received_date' => '2026-01-15',
            'expiry_date' => '2026-12-31',
        ], $this->tenant->id);

        $service->createBatch($product->id, [
            'batch_number' => 'EARLIER',
            'quantity' => 50,
            'received_date' => '2026-01-15',
            'expiry_date' => '2026-06-30',
        ], $this->tenant->id);

        $fefoBatch = $service->getFefoBatch($product->id, $this->tenant->id);
        $this->assertNotNull($fefoBatch);
        $this->assertEquals('EARLIER', $fefoBatch->batch_number);
    }

    public function test_api_list_batches(): void
    {
        $product = $this->createProduct();

        $service = new StockBatchService();
        $service->createBatch($product->id, [
            'batch_number' => 'API-001',
            'quantity' => 100,
            'received_date' => '2026-01-15',
        ], $this->tenant->id);

        $response = $this->withToken($this->tokenOwner)->getJson("/api/v1/products/{$product->id}/batches");
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_api_create_batch(): void
    {
        $product = $this->createProduct();

        $response = $this->withToken($this->tokenOwner)->postJson("/api/v1/products/{$product->id}/batches", [
            'batch_number' => 'API-CREATE',
            'quantity' => 200,
            'received_date' => '2026-02-01',
            'expiry_date' => '2026-08-01',
            'cost_price' => 7500,
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('batch.batch_number', 'API-CREATE');
    }

    public function test_cross_tenant_batch_rejected(): void
    {
        $product = $this->createProduct();
        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other-batch']);

        $service = new StockBatchService();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->createBatch($product->id, [
            'batch_number' => 'CROSS-001',
            'quantity' => 10,
            'received_date' => '2026-01-15',
        ], $tenant2->id);
    }

    public function test_batch_endpoints_disabled_without_feature(): void
    {
        $product = $this->createProduct();
        $this->disableFeature('inventory.batch_tracking');

        $response = $this->withToken($this->tokenOwner)->getJson("/api/v1/products/{$product->id}/batches");
        $response->assertStatus(403);
    }
}
