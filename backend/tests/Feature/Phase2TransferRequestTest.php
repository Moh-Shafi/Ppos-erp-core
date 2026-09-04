<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Inventory;
use App\Models\TransferRequest;
use App\Services\TransferRequestService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;

class Phase2TransferRequestTest extends TestCase
{
    use RefreshDatabase;
    use Phase2TestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase2();
    }

    public function test_create_request(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id,
            'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'note' => 'Test transfer',
        ], $this->tenant->id);
        Auth::forgetGuards();

        $this->assertInstanceOf(TransferRequest::class, $request);
        $this->assertEquals('draft', $request->status);
        $this->assertCount(1, $request->items);
    }

    public function test_submit_draft(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);
        $request = $service->submit($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('pending', $request->status);
    }

    public function test_approve_pending(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);
        $service->submit($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->actingAs($this->manager, 'sanctum');
        $request = $service->approve($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('approved', $request->status);
        $this->assertEquals($this->manager->id, $request->approved_by);
    }

    public function test_cannot_approve_own_request(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);
        $service->submit($request->id, $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->approve($request->id, $this->tenant->id);
        Auth::forgetGuards();
    }

    public function test_reject_pending(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);
        $service->submit($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->actingAs($this->manager, 'sanctum');
        $request = $service->reject($request->id, $this->tenant->id, 'Not needed');
        Auth::forgetGuards();

        $this->assertEquals('rejected', $request->status);
    }

    public function test_full_store_to_store_workflow(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $inventoryService = new InventoryService();
        $inventoryService->increase($this->store, $product, 20, 'initial');
        Auth::forgetGuards();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ], $this->tenant->id);
        $service->submit($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->actingAs($this->manager, 'sanctum');
        $service->approve($request->id, $this->tenant->id);
        $request = $service->startTransit($request->id, $this->tenant->id);
        $this->assertEquals('in_transit', $request->status);

        $request = $service->complete($request->id, $this->tenant->id);
        $this->assertEquals('completed', $request->status);
        Auth::forgetGuards();

        $inv1 = Inventory::where('tenant_id', $this->tenant->id)->where('store_id', $this->store->id)->where('product_id', $product->id)->first();
        $inv2 = Inventory::where('tenant_id', $this->tenant->id)->where('store_id', $store2->id)->where('product_id', $product->id)->first();

        $this->assertEquals(10, $inv1->quantity);
        $this->assertEquals(10, $inv2->quantity);
    }

    public function test_cancel_from_draft(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);
        $request = $service->cancel($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('cancelled', $request->status);
    }

    public function test_cannot_approve_non_pending(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->approve($request->id, $this->tenant->id);
        Auth::forgetGuards();
    }

    public function test_insufficient_stock_at_transit(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 100]],
        ], $this->tenant->id);
        $service->submit($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->actingAs($this->manager, 'sanctum');
        $service->approve($request->id, $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->startTransit($request->id, $this->tenant->id);
        Auth::forgetGuards();
    }

    public function test_cancel_in_transit_returns_stock(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $inventoryService = new InventoryService();
        $inventoryService->increase($this->store, $product, 20, 'initial');
        Auth::forgetGuards();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ], $this->tenant->id);
        $service->submit($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->actingAs($this->manager, 'sanctum');
        $service->approve($request->id, $this->tenant->id);
        $service->startTransit($request->id, $this->tenant->id);

        $inv1 = Inventory::where('tenant_id', $this->tenant->id)->where('store_id', $this->store->id)->where('product_id', $product->id)->first();
        $this->assertEquals(10, $inv1->quantity);

        $service->cancel($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $inv1 = Inventory::where('tenant_id', $this->tenant->id)->where('store_id', $this->store->id)->where('product_id', $product->id)->first();
        $this->assertEquals(20, $inv1->quantity);
    }

    public function test_tenant_isolation(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);
        Auth::forgetGuards();

        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other-tr']);
        $this->enableFeature($tenant2, 'inventory.transfer_request');
        $ownerRole = \App\Models\Role::where('slug', 'owner')->first();
        $user2 = User::create([
            'tenant_id' => $tenant2->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner2', 'email' => 'owner2@trtest.com', 'password' => 'password',
        ]);
        $token2 = $user2->createToken('test')->plainTextToken;

        $response = $this->withToken($token2)->getJson("/api/v1/transfer-requests/{$request->id}");
        $response->assertStatus(404);
    }

    public function test_cancel_from_pending(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);
        $service->submit($request->id, $this->tenant->id);
        $request = $service->cancel($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('cancelled', $request->status);
    }

    public function test_cannot_complete_non_in_transit(): void
    {
        $store2 = $this->createStore('Store B', 'STR-B');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $store2->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);
        $service->submit($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->actingAs($this->manager, 'sanctum');
        $service->approve($request->id, $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->complete($request->id, $this->tenant->id);
        Auth::forgetGuards();
    }

    public function test_warehouse_to_store_transfer(): void
    {
        $warehouse = $this->createWarehouse('WH-1');
        $product = $this->createProduct();

        $this->actingAs($this->owner, 'sanctum');
        $whService = new \App\Services\WarehouseService();
        $whService->adjustStock($warehouse->id, $product->id, 50, $this->tenant->id, null, null, 'initial stock');
        Auth::forgetGuards();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();
        $request = $service->createRequest([
            'from_warehouse_id' => $warehouse->id, 'to_store_id' => $this->store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ], $this->tenant->id);
        $service->submit($request->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->actingAs($this->manager, 'sanctum');
        $service->approve($request->id, $this->tenant->id);
        $request = $service->startTransit($request->id, $this->tenant->id);
        $this->assertEquals('in_transit', $request->status);

        $request = $service->complete($request->id, $this->tenant->id);
        $this->assertEquals('completed', $request->status);
        Auth::forgetGuards();

        $inv = Inventory::where('tenant_id', $this->tenant->id)
            ->where('store_id', $this->store->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(10, $inv->quantity);
    }

    public function test_transfer_request_disabled_without_feature(): void
    {
        $this->disableFeature('inventory.transfer_request');

        $response = $this->withToken($this->tokenOwner)->getJson('/api/v1/transfer-requests');
        $response->assertStatus(403);
    }

    public function test_cross_tenant_from_to_rejected(): void
    {
        $product = $this->createProduct();

        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other-xtr']);
        $storeOther = new \App\Models\Store;
        $storeOther->tenant_id = $tenant2->id;
        $storeOther->name = 'Other Store';
        $storeOther->code = 'STR-OTH';
        $storeOther->is_active = true;
        $storeOther->save();

        $this->actingAs($this->owner, 'sanctum');
        $service = new TransferRequestService();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->createRequest([
            'from_store_id' => $this->store->id, 'to_store_id' => $storeOther->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->tenant->id);
        Auth::forgetGuards();
    }
}
