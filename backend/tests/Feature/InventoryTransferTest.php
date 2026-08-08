<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class InventoryTransferTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private Store $storeC;
    private Product $productA;
    private Product $productB;
    private User $ownerA;
    private User $ownerB;
    private User $managerA;
    private User $cashierA;
    private User $staffA;
    private string $tokenOwnerA;
    private string $tokenOwnerB;
    private string $tokenManagerA;
    private string $tokenCashierA;
    private string $tokenStaffA;
    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        // Tenant A with 2 stores
        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@t.com', 'password' => 'password',
        ]);
        $this->managerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $managerRole->id,
            'name' => 'Manager A', 'email' => 'manager.a@t.com', 'password' => 'password',
        ]);
        $this->cashierA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier A', 'email' => 'cashier.a@t.com', 'password' => 'password',
        ]);
        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $staffRole->id,
            'name' => 'Staff A', 'email' => 'staff.a@t.com', 'password' => 'password',
        ]);

        $this->storeA = new Store;
        $this->storeA->tenant_id = $this->tenantA->id;
        $this->storeA->name = 'Store A'; $this->storeA->code = 'STR-A';
        $this->storeA->is_active = true; $this->storeA->save();

        $this->storeB = new Store;
        $this->storeB->tenant_id = $this->tenantA->id;
        $this->storeB->name = 'Store B'; $this->storeB->code = 'STR-B';
        $this->storeB->is_active = true; $this->storeB->save();

        $catA = new Category;
        $catA->tenant_id = $this->tenantA->id;
        $catA->name = 'Minuman'; $catA->slug = 'minuman'; $catA->save();

        $this->productA = new Product;
        $this->productA->tenant_id = $this->tenantA->id;
        $this->productA->category_id = $catA->id;
        $this->productA->name = 'Aqua 600ml'; $this->productA->sku = 'AQUA-600';
        $this->productA->barcode = '8992761141234';
        $this->productA->cost_price = 2500; $this->productA->selling_price = 4000;
        $this->productA->unit = 'botol'; $this->productA->save();

        $this->tokenOwnerA = $this->ownerA->createToken('test')->plainTextToken;
        $this->tokenManagerA = $this->managerA->createToken('test')->plainTextToken;
        $this->tokenCashierA = $this->cashierA->createToken('test')->plainTextToken;
        $this->tokenStaffA = $this->staffA->createToken('test')->plainTextToken;

        // Tenant B with 1 store
        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@t.com', 'password' => 'password',
        ]);
        $this->storeC = new Store;
        $this->storeC->tenant_id = $this->tenantB->id;
        $this->storeC->name = 'Store C'; $this->storeC->code = 'STR-C';
        $this->storeC->is_active = true; $this->storeC->save();

        $catB = new Category;
        $catB->tenant_id = $this->tenantB->id;
        $catB->name = 'Elektronik'; $catB->slug = 'elektronik'; $catB->save();

        $this->productB = new Product;
        $this->productB->tenant_id = $this->tenantB->id;
        $this->productB->category_id = $catB->id;
        $this->productB->name = 'TV'; $this->productB->sku = 'TV-001';
        $this->productB->barcode = '111111';
        $this->productB->cost_price = 1000000; $this->productB->selling_price = 1500000;
        $this->productB->unit = 'pcs'; $this->productB->save();

        $this->tokenOwnerB = $this->ownerB->createToken('test')->plainTextToken;

        $this->service = app(InventoryService::class);
    }

    private function createInventory(int $tenantId, int $storeId, int $productId, int $qty = 0): Inventory
    {
        $inv = new Inventory;
        $inv->tenant_id = $tenantId;
        $inv->store_id = $storeId;
        $inv->product_id = $productId;
        $inv->quantity = $qty;
        $inv->minimum_quantity = 0;
        $inv->save();
        return $inv;
    }

    // --- Service: Basic Transfer ---

    public function test_transfer_basic(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 20);

        $result = $this->service->transfer($this->storeA, $this->storeB, $this->productA, 20, 'Restock branch');

        $this->assertEquals('transfer_out', $result['out']->type);
        $this->assertEquals('transfer_in', $result['in']->type);
        $this->assertEquals(-20, $result['out']->quantity);
        $this->assertEquals(20, $result['in']->quantity);
        $this->assertEquals(100, $result['out']->before_quantity);
        $this->assertEquals(80, $result['out']->after_quantity);
        $this->assertEquals(20, $result['in']->before_quantity);
        $this->assertEquals(40, $result['in']->after_quantity);
        $this->assertEquals('Restock branch', $result['out']->note);
        $this->assertEquals('Restock branch', $result['in']->note);
    }

    public function test_transfer_updates_inventory(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 20);

        $this->service->transfer($this->storeA, $this->storeB, $this->productA, 20);

        $invA = Inventory::withoutTenantScope()->where('store_id', $this->storeA->id)->where('product_id', $this->productA->id)->first();
        $invB = Inventory::withoutTenantScope()->where('store_id', $this->storeB->id)->where('product_id', $this->productA->id)->first();

        $this->assertEquals(80, $invA->quantity);
        $this->assertEquals(40, $invB->quantity);
    }

    public function test_transfer_creates_two_movements(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $this->service->transfer($this->storeA, $this->storeB, $this->productA, 20);

        $movements = InventoryMovement::withoutTenantScope()
            ->where('product_id', $this->productA->id)
            ->orderBy('id')
            ->get();

        $this->assertEquals(2, $movements->count());
        $this->assertEquals('transfer_out', $movements[0]->type);
        $this->assertEquals('transfer_in', $movements[1]->type);
    }

    public function test_transfer_auto_creates_destination_inventory(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        // Store B has no inventory yet
        $exists = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productA->id)
            ->exists();
        $this->assertFalse($exists);

        $result = $this->service->transfer($this->storeA, $this->storeB, $this->productA, 20);

        $this->assertEquals(0, $result['in']->before_quantity);
        $this->assertEquals(20, $result['in']->after_quantity);

        $invB = Inventory::withoutTenantScope()->where('store_id', $this->storeB->id)->where('product_id', $this->productA->id)->first();
        $this->assertNotNull($invB);
        $this->assertEquals(20, $invB->quantity);
    }

    // --- Service: Validation ---

    public function test_transfer_same_store_rejected(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source and destination stores must be different');

        $this->service->transfer($this->storeA, $this->storeA, $this->productA, 20);
    }

    public function test_transfer_zero_quantity_rejected(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->service->transfer($this->storeA, $this->storeB, $this->productA, 0);
    }

    public function test_transfer_negative_quantity_rejected(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->service->transfer($this->storeA, $this->storeB, $this->productA, -5);
    }

    public function test_transfer_insufficient_stock_rejected(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 10);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->transfer($this->storeA, $this->storeB, $this->productA, 20);
    }

    // --- Service: Security ---

    public function test_transfer_cross_tenant_source_store_rejected(): void
    {
        Auth::login($this->ownerA);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('One or both stores do not belong to your tenant');

        $this->service->transfer($this->storeC, $this->storeB, $this->productA, 20);
    }

    public function test_transfer_cross_tenant_dest_store_rejected(): void
    {
        Auth::login($this->ownerA);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('One or both stores do not belong to your tenant');

        $this->service->transfer($this->storeA, $this->storeC, $this->productA, 20);
    }

    public function test_transfer_cross_tenant_both_stores_rejected(): void
    {
        Auth::login($this->ownerA);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->transfer($this->storeC, $this->storeC, $this->productA, 20);
    }

    public function test_transfer_cross_tenant_product_rejected(): void
    {
        Auth::login($this->ownerA);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product does not belong to your tenant');

        $this->service->transfer($this->storeA, $this->storeB, $this->productB, 20);
    }

    public function test_transfer_unauthenticated_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unauthenticated');

        $this->service->transfer($this->storeA, $this->storeB, $this->productA, 20);
    }

    public function test_transfer_user_id_from_auth(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $result = $this->service->transfer($this->storeA, $this->storeB, $this->productA, 20);

        $this->assertEquals($this->ownerA->id, $result['out']->user_id);
        $this->assertEquals($this->ownerA->id, $result['in']->user_id);
        $this->assertNotEquals($this->ownerB->id, $result['out']->user_id);
    }

    public function test_transfer_tenant_id_from_auth(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $result = $this->service->transfer($this->storeA, $this->storeB, $this->productA, 20);

        $this->assertEquals($this->tenantA->id, $result['out']->tenant_id);
        $this->assertEquals($this->tenantA->id, $result['in']->tenant_id);
    }

    // --- Service: Transaction Rollback ---

    public function test_transfer_rollback_on_insufficient_stock(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 10);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 5);

        try {
            $this->service->transfer($this->storeA, $this->storeB, $this->productA, 20);
            $this->fail('Should have thrown');
        } catch (\InvalidArgumentException $e) {
            // Expected
        }

        // Both inventories should be unchanged
        $invA = Inventory::withoutTenantScope()->where('store_id', $this->storeA->id)->where('product_id', $this->productA->id)->first();
        $invB = Inventory::withoutTenantScope()->where('store_id', $this->storeB->id)->where('product_id', $this->productA->id)->first();

        $this->assertEquals(10, $invA->quantity);
        $this->assertEquals(5, $invB->quantity);

        // No movements should have been created
        $count = InventoryMovement::withoutTenantScope()
            ->where('product_id', $this->productA->id)
            ->count();
        $this->assertEquals(0, $count);
    }

    // --- Service: Race Condition ---

    public function test_transfer_concurrent_does_not_oversell(): void
    {
        Auth::login($this->ownerA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 10);

        // First transfer succeeds
        $this->service->transfer($this->storeA, $this->storeB, $this->productA, 8);
        $invA = Inventory::withoutTenantScope()->where('store_id', $this->storeA->id)->where('product_id', $this->productA->id)->first();
        $this->assertEquals(2, $invA->quantity);

        // Second transfer should fail (only 2 left, need 8)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->transfer($this->storeA, $this->storeB, $this->productA, 8);
    }

    // --- API: Smoke ---

    public function test_api_transfer_success(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 20);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 20,
                'note' => 'Restock branch',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('out_movement.type', 'transfer_out');
        $response->assertJsonPath('in_movement.type', 'transfer_in');
        $response->assertJsonPath('out_movement.before_quantity', 100);
        $response->assertJsonPath('out_movement.after_quantity', 80);
        $response->assertJsonPath('in_movement.before_quantity', 20);
        $response->assertJsonPath('in_movement.after_quantity', 40);
    }

    public function test_api_transfer_verify_inventory_changed(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 20);

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 20,
            ]);

        // Verify via API
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/inventory/{$this->productA->id}");

        $response->assertStatus(200);
        $quantities = collect($response->json('inventories'))->pluck('quantity', 'store_id');
        $this->assertEquals(80, $quantities[$this->storeA->id]);
        $this->assertEquals(40, $quantities[$this->storeB->id]);
    }

    public function test_api_transfer_verify_movements_created(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 20,
            ]);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/inventory/movements');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    // --- API: Authentication ---

    public function test_api_transfer_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/inventory/transfer', [
            'from_store_id' => 1, 'to_store_id' => 2, 'product_id' => 1, 'quantity' => 10,
        ]);
        $response->assertStatus(401);
    }

    // --- API: RBAC ---

    public function test_api_transfer_owner_allowed(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(201);
    }

    public function test_api_transfer_manager_allowed(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $response = $this->withToken($this->tokenManagerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(201);
    }

    public function test_api_transfer_cashier_forbidden(): void
    {
        $response = $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(403);
    }

    public function test_api_transfer_staff_forbidden(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(403);
    }

    // --- API: Tenant Isolation ---

    public function test_api_transfer_cross_tenant_source_store(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeC->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from_store_id']);
    }

    public function test_api_transfer_cross_tenant_dest_store(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeC->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to_store_id']);
    }

    public function test_api_transfer_cross_tenant_product(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productB->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id']);
    }

    // --- API: Validation ---

    public function test_api_transfer_same_store_rejected(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to_store_id']);
    }

    public function test_api_transfer_quantity_zero_rejected(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);
    }

    public function test_api_transfer_quantity_negative_rejected(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => -5,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);
    }

    public function test_api_transfer_missing_fields(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from_store_id', 'to_store_id', 'product_id', 'quantity']);
    }

    public function test_api_transfer_insufficient_stock(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 5);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 20,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Insufficient stock. Current: 5, Requested transfer: 20');
    }

    // --- API: Mass Assignment ---

    public function test_api_transfer_tenant_id_ignored(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
                'tenant_id' => $this->tenantB->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('out_movement.tenant_id', $this->tenantA->id);
    }

    public function test_api_transfer_user_id_ignored(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 100);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 10,
                'user_id' => $this->ownerB->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('out_movement.user_id', $this->ownerA->id);
    }

    // --- API: Rollback verification ---

    public function test_api_transfer_rollback_on_insufficient_stock(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 10);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 5);

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/transfer', [
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'quantity' => 20,
            ]);

        // Verify nothing changed
        $invA = Inventory::withoutTenantScope()->where('store_id', $this->storeA->id)->where('product_id', $this->productA->id)->first();
        $invB = Inventory::withoutTenantScope()->where('store_id', $this->storeB->id)->where('product_id', $this->productA->id)->first();

        $this->assertEquals(10, $invA->quantity);
        $this->assertEquals(5, $invB->quantity);

        $movementCount = InventoryMovement::withoutTenantScope()->where('product_id', $this->productA->id)->count();
        $this->assertEquals(0, $movementCount);
    }
}
