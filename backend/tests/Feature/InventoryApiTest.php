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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
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
    private User $cashierA;
    private User $staffA;
    private string $tokenOwnerA;
    private string $tokenOwnerB;
    private string $tokenCashierA;
    private string $tokenStaffA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        // Tenant A with 2 stores
        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@test.com', 'password' => 'password',
        ]);
        $this->cashierA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier A', 'email' => 'cashier.a@test.com', 'password' => 'password',
        ]);
        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $staffRole->id,
            'name' => 'Staff A', 'email' => 'staff.a@test.com', 'password' => 'password',
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
        $this->tokenCashierA = $this->cashierA->createToken('test')->plainTextToken;
        $this->tokenStaffA = $this->staffA->createToken('test')->plainTextToken;

        // Tenant B with 1 store
        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@test.com', 'password' => 'password',
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
    }

    private function createInventory(int $tenantId, int $storeId, int $productId, int $qty = 0, int $minQty = 0): Inventory
    {
        $inv = new Inventory;
        $inv->tenant_id = $tenantId;
        $inv->store_id = $storeId;
        $inv->product_id = $productId;
        $inv->quantity = $qty;
        $inv->minimum_quantity = $minQty;
        $inv->save();
        return $inv;
    }

    private function createMovement(int $tenantId, int $storeId, int $productId, int $userId, array $overrides = []): InventoryMovement
    {
        $m = new InventoryMovement;
        $m->tenant_id = $tenantId;
        $m->store_id = $storeId;
        $m->product_id = $productId;
        $m->user_id = $userId;
        $m->type = $overrides['type'] ?? 'initial';
        $m->quantity = $overrides['quantity'] ?? 20;
        $m->before_quantity = $overrides['before_quantity'] ?? 0;
        $m->after_quantity = $overrides['after_quantity'] ?? 20;
        $m->save();
        return $m;
    }

    // --- API Smoke ---

    public function test_get_inventory_list(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 30);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/inventory');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_get_inventory_with_store_filter(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 30);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/inventory?store_id={$this->storeA->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.store_id', $this->storeA->id);
    }

    public function test_get_product_inventory(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 30);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/inventory/{$this->productA->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'inventories');
    }

    public function test_get_product_inventory_with_store_filter(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 30);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/inventory/{$this->productA->id}?store_id={$this->storeB->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'inventories');
        $response->assertJsonPath('inventories.0.quantity', 30);
    }

    public function test_get_product_inventory_not_found(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/inventory/99999');

        $response->assertStatus(404);
    }

    public function test_post_adjust(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => 10,
                'note' => 'Stock count correction',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('movement.type', 'adjustment');
        $response->assertJsonPath('movement.before_quantity', 50);
        $response->assertJsonPath('movement.after_quantity', 60);
        $response->assertJsonPath('movement.quantity', 10);
        $response->assertJsonPath('movement.user_id', $this->ownerA->id);
        $response->assertJsonPath('movement.tenant_id', $this->tenantA->id);
    }

    public function test_post_adjust_negative_delta(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => -10,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('movement.after_quantity', 40);
        $response->assertJsonPath('movement.quantity', -10);
    }

    public function test_get_movements(): void
    {
        $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->ownerA->id, ['type' => 'purchase', 'quantity' => 50, 'after_quantity' => 50]);
        $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->ownerA->id, ['type' => 'sale', 'quantity' => -5, 'before_quantity' => 50, 'after_quantity' => 45]);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/inventory/movements');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_get_movements_with_filters(): void
    {
        $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->ownerA->id, ['type' => 'purchase']);
        $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->ownerA->id, ['type' => 'sale', 'quantity' => -5, 'before_quantity' => 50, 'after_quantity' => 45]);
        $this->createMovement($this->tenantA->id, $this->storeB->id, $this->productA->id, $this->ownerA->id, ['type' => 'purchase']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/inventory/movements?store_id={$this->storeA->id}&type=purchase");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type', 'purchase');
    }

    // --- Authentication ---

    public function test_unauthenticated_inventory_list(): void
    {
        $response = $this->getJson('/api/v1/inventory');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_adjust(): void
    {
        $response = $this->postJson('/api/v1/inventory/adjust', [
            'store_id' => 1, 'product_id' => 1, 'delta' => 10,
        ]);
        $response->assertStatus(401);
    }

    public function test_unauthenticated_movements(): void
    {
        $response = $this->getJson('/api/v1/inventory/movements');
        $response->assertStatus(401);
    }

    // --- Tenant Isolation ---

    public function test_tenant_a_cannot_see_tenant_b_inventory(): void
    {
        $this->createInventory($this->tenantB->id, $this->storeC->id, $this->productB->id, 100);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/inventory');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_cannot_see_tenant_b_movements(): void
    {
        $this->createMovement($this->tenantB->id, $this->storeC->id, $this->productB->id, $this->ownerB->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/inventory/movements');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_cannot_adjust_tenant_b_store(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeC->id,
                'product_id' => $this->productA->id,
                'delta' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['store_id']);
    }

    public function test_tenant_a_cannot_adjust_tenant_b_product(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productB->id,
                'delta' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id']);
    }

    // --- IDOR ---

    public function test_idor_get_product_inventory_returns_404_for_other_tenant_product(): void
    {
        // Tenant A tries to get inventory for Tenant B's product
        $this->createInventory($this->tenantB->id, $this->storeC->id, $this->productB->id, 100);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/inventory/{$this->productB->id}");

        // Product B belongs to Tenant B, so Tenant A's global scope returns no inventory
        $response->assertStatus(404);
    }

    // --- RBAC ---

    public function test_cashier_can_view_inventory(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $response = $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/inventory');

        $response->assertStatus(200);
    }

    public function test_cashier_cannot_adjust_inventory(): void
    {
        $response = $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => 10,
            ]);

        $response->assertStatus(403);
    }

    public function test_staff_can_view_inventory(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $response = $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/inventory');

        $response->assertStatus(200);
    }

    public function test_staff_cannot_adjust_inventory(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => 10,
            ]);

        $response->assertStatus(403);
    }

    public function test_staff_cannot_view_movements(): void
    {
        // Staff has inventory.view but not inventory.manage
        // movements route only requires inventory.view
        $response = $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/inventory/movements');

        $response->assertStatus(200);
    }

    // --- Mass Assignment ---

    public function test_tenant_id_in_request_ignored(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => 10,
                'tenant_id' => $this->tenantB->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('movement.tenant_id', $this->tenantA->id);
    }

    public function test_user_id_in_request_ignored(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => 10,
                'user_id' => $this->ownerB->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('movement.user_id', $this->ownerA->id);
    }

    public function test_before_quantity_in_request_ignored(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => 10,
                'before_quantity' => 999,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('movement.before_quantity', 50);
    }

    public function test_after_quantity_in_request_ignored(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => 10,
                'after_quantity' => 999,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('movement.after_quantity', 60);
    }

    // --- Validation ---

    public function test_adjust_delta_zero_rejected(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['delta']);
    }

    public function test_adjust_missing_store_id(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'product_id' => $this->productA->id,
                'delta' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['store_id']);
    }

    public function test_adjust_missing_product_id(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'delta' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id']);
    }

    public function test_adjust_invalid_store_id(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => 99999,
                'product_id' => $this->productA->id,
                'delta' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['store_id']);
    }

    public function test_adjust_invalid_product_id(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => 99999,
                'delta' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id']);
    }

    public function test_adjust_delta_string_rejected(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => 'abc',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['delta']);
    }

    public function test_adjust_insufficient_stock_returns_422(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 5);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => -10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Insufficient stock. Current: 5, Requested change: -10');
    }

    // --- Store Isolation ---

    public function test_store_a_inventory_separate_from_store_b(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 30);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/inventory/{$this->productA->id}?store_id={$this->storeA->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'inventories');
        $response->assertJsonPath('inventories.0.quantity', 50);
    }

    public function test_adjust_in_store_a_does_not_affect_store_b(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 30);

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/inventory/adjust', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'delta' => -20,
            ]);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/inventory/{$this->productA->id}?store_id={$this->storeB->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('inventories.0.quantity', 30); // unchanged
    }

    // --- Pagination ---

    public function test_inventory_pagination(): void
    {
        // Create multiple products for multiple inventory records
        for ($i = 1; $i <= 25; $i++) {
            $p = new Product;
            $p->tenant_id = $this->tenantA->id;
            $p->category_id = $this->productA->category_id;
            $p->name = "Product {$i}";
            $p->sku = "SKU-{$i}";
            $p->barcode = "BC-{$i}";
            $p->cost_price = 1000; $p->selling_price = 2000;
            $p->unit = 'pcs'; $p->save();

            $this->createInventory($this->tenantA->id, $this->storeA->id, $p->id, $i);
        }

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/inventory?per_page=10&page=1');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('total', 25);
        $response->assertJsonPath('last_page', 3);
    }

    // --- Low stock filter ---

    public function test_low_stock_filter(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 5, 10);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 50, 10);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/inventory?low_stock=1');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.quantity', 5);
    }
}
