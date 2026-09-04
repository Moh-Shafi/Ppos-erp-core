<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Inventory;
use App\Models\StockAdjustmentReason;
use App\Services\StocktakeService;
use App\Services\AdjustmentReasonService;
use Illuminate\Support\Facades\Auth;

class Phase2StocktakeTest extends TestCase
{
    use RefreshDatabase;
    use Phase2TestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase2();
    }

    private function createInventory(int $qty = 50): Inventory
    {
        $product = $this->createProduct();
        $inv = new Inventory;
        $inv->tenant_id = $this->tenant->id;
        $inv->store_id = $this->store->id;
        $inv->product_id = $product->id;
        $inv->quantity = $qty;
        $inv->minimum_quantity = 5;
        $inv->save();
        return $inv;
    }

    public function test_create_session_snapshots_inventory(): void
    {
        $inv = $this->createInventory(50);

        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('draft', $session->status);
        $this->assertCount(1, $session->items);
        $this->assertEquals(50, $session->items->first()->system_quantity);
    }

    public function test_start_counting(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        $session = $service->startCounting($session->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('counting', $session->status);
        $this->assertNotNull($session->started_at);
    }

    public function test_update_counted_quantity(): void
    {
        $inv = $this->createInventory(30);

        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        $service->startCounting($session->id, $this->tenant->id);

        $item = $session->items->first();
        $updatedItem = $service->updateItem($session->id, $item->id, 25, 'Some missing', $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals(25, $updatedItem->counted_quantity);
        $this->assertEquals(-5, $updatedItem->variance);
    }

    public function test_reconcile(): void
    {
        $inv = $this->createInventory(30);

        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        $service->startCounting($session->id, $this->tenant->id);

        $item = $session->items->first();
        $service->updateItem($session->id, $item->id, 30, null, $this->tenant->id);

        $session = $service->reconcile($session->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('reconciling', $session->status);
    }

    public function test_post_creates_adjustments(): void
    {
        $inv = $this->createInventory(30);

        $reasonService = new AdjustmentReasonService();
        $reasonService->seedSystemReasons($this->tenant->id);
        $reason = StockAdjustmentReason::where('tenant_id', $this->tenant->id)->first();

        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        $service->startCounting($session->id, $this->tenant->id);

        $item = $session->items->first();
        $service->updateItem($session->id, $item->id, 25, null, $this->tenant->id);

        $service->reconcile($session->id, $this->tenant->id);
        $session = $service->post($session->id, $reason->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('posted', $session->status);
        $this->assertNotNull($session->completed_at);

        $inventory = Inventory::where('tenant_id', $this->tenant->id)
            ->where('store_id', $this->store->id)
            ->where('product_id', $inv->product_id)
            ->first();
        $this->assertEquals(25, $inventory->quantity);
    }

    public function test_post_zero_variance_no_adjustments(): void
    {
        $inv = $this->createInventory(30);

        $reasonService = new AdjustmentReasonService();
        $reasonService->seedSystemReasons($this->tenant->id);
        $reason = StockAdjustmentReason::where('tenant_id', $this->tenant->id)->first();

        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        $service->startCounting($session->id, $this->tenant->id);

        $item = $session->items->first();
        $service->updateItem($session->id, $item->id, 30, null, $this->tenant->id);

        $service->reconcile($session->id, $this->tenant->id);
        $session = $service->post($session->id, $reason->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('posted', $session->status);

        $movements = \App\Models\InventoryMovement::where('tenant_id', $this->tenant->id)
            ->where('type', 'adjustment')
            ->count();
        $this->assertEquals(0, $movements);
    }

    public function test_cancel_from_draft(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        $session = $service->cancel($session->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('cancelled', $session->status);
    }

    public function test_cannot_cancel_reconciling(): void
    {
        $inv = $this->createInventory(30);

        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        $service->startCounting($session->id, $this->tenant->id);
        $item = $session->items->first();
        $service->updateItem($session->id, $item->id, 30, null, $this->tenant->id);
        $service->reconcile($session->id, $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->cancel($session->id, $this->tenant->id);
        Auth::forgetGuards();
    }

    public function test_cannot_modify_posted(): void
    {
        $inv = $this->createInventory(30);

        $reasonService = new AdjustmentReasonService();
        $reasonService->seedSystemReasons($this->tenant->id);
        $reason = StockAdjustmentReason::where('tenant_id', $this->tenant->id)->first();

        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        $service->startCounting($session->id, $this->tenant->id);
        $item = $session->items->first();
        $service->updateItem($session->id, $item->id, 30, null, $this->tenant->id);
        $service->reconcile($session->id, $this->tenant->id);
        $session = $service->post($session->id, $reason->id, $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->cancel($session->id, $this->tenant->id);
        Auth::forgetGuards();
    }

    public function test_tenant_isolation(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        Auth::forgetGuards();

        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other-st']);
        $this->enableFeature($tenant2, 'inventory.stocktake');
        $ownerRole = \App\Models\Role::where('slug', 'owner')->first();
        $user2 = User::create([
            'tenant_id' => $tenant2->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner2', 'email' => 'owner2@sttest.com', 'password' => 'password',
        ]);
        $token2 = $user2->createToken('test')->plainTextToken;

        $response = $this->withToken($token2)->getJson("/api/v1/stocktake/{$session->id}");
        $response->assertStatus(404);
    }

    public function test_cancel_from_counting(): void
    {
        $inv = $this->createInventory(30);

        $this->actingAs($this->owner, 'sanctum');
        $service = new StocktakeService();
        $session = $service->createSession($this->store->id, $this->tenant->id);
        $service->startCounting($session->id, $this->tenant->id);
        $session = $service->cancel($session->id, $this->tenant->id);
        Auth::forgetGuards();

        $this->assertEquals('cancelled', $session->status);
    }

    public function test_cashier_cannot_stocktake(): void
    {
        $response = $this->withToken($this->tokenCashier)->postJson('/api/v1/stocktake', [
            'store_id' => $this->store->id,
        ]);
        $response->assertStatus(403);
    }

    public function test_stocktake_disabled_without_feature(): void
    {
        $this->disableFeature('inventory.stocktake');

        $response = $this->withToken($this->tokenOwner)->getJson('/api/v1/stocktake');
        $response->assertStatus(403);
    }

    public function test_api_full_workflow(): void
    {
        $inv = $this->createInventory(40);

        $reasonService = new AdjustmentReasonService();
        $reasonService->seedSystemReasons($this->tenant->id);
        $reason = StockAdjustmentReason::where('tenant_id', $this->tenant->id)->first();

        $response = $this->withToken($this->tokenOwner)->postJson('/api/v1/stocktake', [
            'store_id' => $this->store->id,
        ]);
        $response->assertStatus(201);
        $sessionId = $response->json('stocktake.id');

        $response = $this->withToken($this->tokenOwner)->postJson("/api/v1/stocktake/{$sessionId}/start");
        $response->assertStatus(200);

        $items = $this->withToken($this->tokenOwner)->getJson("/api/v1/stocktake/{$sessionId}")
            ->json('stocktake.items');
        $itemId = $items[0]['id'];

        $response = $this->withToken($this->tokenOwner)->putJson("/api/v1/stocktake/{$sessionId}/items/{$itemId}", [
            'counted_quantity' => 35,
        ]);
        $response->assertStatus(200);

        $response = $this->withToken($this->tokenOwner)->postJson("/api/v1/stocktake/{$sessionId}/reconcile");
        $response->assertStatus(200);

        $response = $this->withToken($this->tokenOwner)->postJson("/api/v1/stocktake/{$sessionId}/post", [
            'reason_id' => $reason->id,
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('stocktake.status', 'posted');
    }
}
