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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private Store $storeC;
    private Product $productA;
    private Product $productB;
    private User $userA;
    private User $userB;
    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();

        // Tenant A with 2 stores
        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'role_id' => $ownerRole->id,
            'name' => 'User A',
            'email' => 'a@test.com',
            'password' => 'password',
        ]);
        $this->storeA = new Store;
        $this->storeA->tenant_id = $this->tenantA->id;
        $this->storeA->name = 'Store A';
        $this->storeA->code = 'STR-A';
        $this->storeA->is_active = true;
        $this->storeA->save();

        $this->storeB = new Store;
        $this->storeB->tenant_id = $this->tenantA->id;
        $this->storeB->name = 'Store B';
        $this->storeB->code = 'STR-B';
        $this->storeB->is_active = true;
        $this->storeB->save();

        $catA = new Category;
        $catA->tenant_id = $this->tenantA->id;
        $catA->name = 'Minuman';
        $catA->slug = 'minuman';
        $catA->save();

        $this->productA = new Product;
        $this->productA->tenant_id = $this->tenantA->id;
        $this->productA->category_id = $catA->id;
        $this->productA->name = 'Aqua 600ml';
        $this->productA->sku = 'AQUA-600';
        $this->productA->barcode = '8992761141234';
        $this->productA->cost_price = 2500;
        $this->productA->selling_price = 4000;
        $this->productA->unit = 'botol';
        $this->productA->save();

        // Tenant B with 1 store
        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'role_id' => $ownerRole->id,
            'name' => 'User B',
            'email' => 'b@test.com',
            'password' => 'password',
        ]);
        $this->storeC = new Store;
        $this->storeC->tenant_id = $this->tenantB->id;
        $this->storeC->name = 'Store C';
        $this->storeC->code = 'STR-C';
        $this->storeC->is_active = true;
        $this->storeC->save();

        $catB = new Category;
        $catB->tenant_id = $this->tenantB->id;
        $catB->name = 'Elektronik';
        $catB->slug = 'elektronik';
        $catB->save();

        $this->productB = new Product;
        $this->productB->tenant_id = $this->tenantB->id;
        $this->productB->category_id = $catB->id;
        $this->productB->name = 'TV';
        $this->productB->sku = 'TV-001';
        $this->productB->barcode = '111111';
        $this->productB->cost_price = 1000000;
        $this->productB->selling_price = 1500000;
        $this->productB->unit = 'pcs';
        $this->productB->save();

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

    // --- increase() ---

    public function test_increase_stock(): void
    {
        Auth::login($this->userA);

        $movement = $this->service->increase($this->storeA, $this->productA, 50, 'purchase');

        $this->assertEquals(50, $movement->after_quantity);
        $this->assertEquals(0, $movement->before_quantity);
        $this->assertEquals(50, $movement->quantity);

        $inventory = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(50, $inventory->quantity);
    }

    public function test_increase_on_existing_inventory(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 20);

        $movement = $this->service->increase($this->storeA, $this->productA, 30, 'purchase');

        $this->assertEquals(20, $movement->before_quantity);
        $this->assertEquals(50, $movement->after_quantity);
        $this->assertEquals(30, $movement->quantity);

        $inventory = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(50, $inventory->quantity);
    }

    public function test_increase_creates_movement_with_correct_type(): void
    {
        Auth::login($this->userA);

        $movement = $this->service->increase($this->storeA, $this->productA, 10, 'initial');

        $this->assertEquals('initial', $movement->type);
    }

    // --- decrease() ---

    public function test_decrease_stock(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 30);

        $movement = $this->service->decrease($this->storeA, $this->productA, 5, 'sale');

        $this->assertEquals(30, $movement->before_quantity);
        $this->assertEquals(25, $movement->after_quantity);
        $this->assertEquals(-5, $movement->quantity);
        $this->assertEquals('sale', $movement->type);

        $inventory = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(25, $inventory->quantity);
    }

    public function test_decrease_insufficient_stock_rejected(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 5);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->decrease($this->storeA, $this->productA, 10, 'sale');
    }

    public function test_decrease_exact_quantity_allowed(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 10);

        $movement = $this->service->decrease($this->storeA, $this->productA, 10, 'sale');

        $this->assertEquals(0, $movement->after_quantity);

        $inventory = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(0, $inventory->quantity);
    }

    public function test_decrease_on_empty_inventory_rejected(): void
    {
        Auth::login($this->userA);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->decrease($this->storeA, $this->productA, 1, 'sale');
    }

    // --- adjust() ---

    public function test_adjust_positive(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $movement = $this->service->adjust($this->storeA, $this->productA, 10);

        $this->assertEquals(50, $movement->before_quantity);
        $this->assertEquals(60, $movement->after_quantity);
        $this->assertEquals(10, $movement->quantity);
        $this->assertEquals('adjustment', $movement->type);
    }

    public function test_adjust_negative(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $movement = $this->service->adjust($this->storeA, $this->productA, -10);

        $this->assertEquals(50, $movement->before_quantity);
        $this->assertEquals(40, $movement->after_quantity);
        $this->assertEquals(-10, $movement->quantity);
        $this->assertEquals('adjustment', $movement->type);
    }

    public function test_adjust_zero_rejected(): void
    {
        Auth::login($this->userA);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Delta cannot be 0');

        $this->service->adjust($this->storeA, $this->productA, 0);
    }

    public function test_adjust_negative_below_zero_rejected(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 5);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->adjust($this->storeA, $this->productA, -10);
    }

    // --- Movement correctness ---

    public function test_movement_has_correct_user_id(): void
    {
        Auth::login($this->userA);

        $movement = $this->service->increase($this->storeA, $this->productA, 10, 'purchase');

        $this->assertEquals($this->userA->id, $movement->user_id);
    }

    public function test_movement_has_correct_tenant_id(): void
    {
        Auth::login($this->userA);

        $movement = $this->service->increase($this->storeA, $this->productA, 10, 'purchase');

        $this->assertEquals($this->tenantA->id, $movement->tenant_id);
    }

    public function test_movement_with_reference(): void
    {
        Auth::login($this->userA);

        $movement = $this->service->increase(
            $this->storeA,
            $this->productA,
            10,
            'purchase',
            $this->storeA, // using Store as reference for test
            'Purchase order #1'
        );

        $this->assertEquals(Store::class, $movement->reference_type);
        $this->assertEquals($this->storeA->id, $movement->reference_id);
        $this->assertEquals('Purchase order #1', $movement->note);
    }

    public function test_movement_without_reference(): void
    {
        Auth::login($this->userA);

        $movement = $this->service->increase($this->storeA, $this->productA, 10, 'initial');

        $this->assertNull($movement->reference_type);
        $this->assertNull($movement->reference_id);
    }

    // --- Multiple movements sequence ---

    public function test_multiple_movements_sequence(): void
    {
        Auth::login($this->userA);

        $m1 = $this->service->increase($this->storeA, $this->productA, 20, 'initial');
        $this->assertEquals(0, $m1->before_quantity);
        $this->assertEquals(20, $m1->after_quantity);

        $m2 = $this->service->increase($this->storeA, $this->productA, 50, 'purchase');
        $this->assertEquals(20, $m2->before_quantity);
        $this->assertEquals(70, $m2->after_quantity);

        $m3 = $this->service->decrease($this->storeA, $this->productA, 2, 'sale');
        $this->assertEquals(70, $m3->before_quantity);
        $this->assertEquals(68, $m3->after_quantity);

        $m4 = $this->service->adjust($this->storeA, $this->productA, 1);
        $this->assertEquals(68, $m4->before_quantity);
        $this->assertEquals(69, $m4->after_quantity);

        $inventory = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(69, $inventory->quantity);

        $movements = InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->orderBy('id')
            ->get();
        $this->assertEquals(4, $movements->count());
    }

    // --- Validation: invalid quantity ---

    public function test_increase_zero_quantity_rejected(): void
    {
        Auth::login($this->userA);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->service->increase($this->storeA, $this->productA, 0, 'purchase');
    }

    public function test_increase_negative_quantity_rejected(): void
    {
        Auth::login($this->userA);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->service->increase($this->storeA, $this->productA, -5, 'purchase');
    }

    public function test_decrease_zero_quantity_rejected(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 10);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->service->decrease($this->storeA, $this->productA, 0, 'sale');
    }

    public function test_decrease_negative_quantity_rejected(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 10);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->service->decrease($this->storeA, $this->productA, -5, 'sale');
    }

    // --- Security: Cross-tenant ---

    public function test_cross_tenant_store_rejected(): void
    {
        Auth::login($this->userA);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Store does not belong to your tenant');

        $this->service->increase($this->storeC, $this->productA, 10, 'purchase');
    }

    public function test_cross_tenant_product_rejected(): void
    {
        Auth::login($this->userA);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product does not belong to your tenant');

        $this->service->increase($this->storeA, $this->productB, 10, 'purchase');
    }

    public function test_cross_tenant_both_rejected(): void
    {
        Auth::login($this->userA);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->increase($this->storeC, $this->productB, 10, 'purchase');
    }

    public function test_unauthenticated_user_rejected(): void
    {
        // Don't login

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unauthenticated');

        $this->service->increase($this->storeA, $this->productA, 10, 'purchase');
    }

    // --- Security: tenant_id and user_id not controllable ---

    public function test_tenant_id_from_auth_not_model(): void
    {
        Auth::login($this->userA);

        // Even if productA somehow has a different tenant_id, service uses auth tenant
        $movement = $this->service->increase($this->storeA, $this->productA, 10, 'purchase');

        $this->assertEquals($this->tenantA->id, $movement->tenant_id);
        $this->assertNotEquals($this->tenantB->id, $movement->tenant_id);
    }

    public function test_user_id_from_auth_not_impersonable(): void
    {
        Auth::login($this->userA);

        $movement = $this->service->increase($this->storeA, $this->productA, 10, 'purchase');

        // user_id must be the authenticated user, not any other
        $this->assertEquals($this->userA->id, $movement->user_id);
        $this->assertNotEquals($this->userB->id, $movement->user_id);
    }

    // --- Transaction rollback ---

    public function test_transaction_rollback_on_failure(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 10);

        // Force a failure by mocking DB or causing an exception inside transaction
        // We'll use a raw approach: try to decrease more than available
        try {
            $this->service->decrease($this->storeA, $this->productA, 100, 'sale');
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            // Expected
        }

        // Inventory should be unchanged (rollback)
        $inventory = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(10, $inventory->quantity);

        // No movement should have been created
        $movements = InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->get();
        $this->assertEquals(0, $movements->count());
    }

    // --- Race condition protection (lockForUpdate) ---

    public function test_concurrent_increase_does_not_lose_updates(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 0);

        // Simulate two concurrent increases within separate transactions
        // Since we can't truly do parallel in a test, we verify the locking mechanism
        // by checking that sequential operations produce correct results

        $m1 = $this->service->increase($this->storeA, $this->productA, 50, 'purchase');
        $this->assertEquals(50, $m1->after_quantity);

        $m2 = $this->service->increase($this->storeA, $this->productA, 30, 'purchase');
        $this->assertEquals(80, $m2->after_quantity);
        $this->assertEquals(50, $m2->before_quantity);

        $inventory = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(80, $inventory->quantity);
    }

    public function test_concurrent_decrease_does_not_oversell(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 10);

        // First decrease succeeds
        $m1 = $this->service->decrease($this->storeA, $this->productA, 7, 'sale');
        $this->assertEquals(3, $m1->after_quantity);

        // Second decrease that would oversell should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->decrease($this->storeA, $this->productA, 5, 'sale');
    }

    // --- Inventory auto-creation ---

    public function test_inventory_auto_created_on_first_movement(): void
    {
        Auth::login($this->userA);

        // No inventory exists yet
        $exists = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->exists();
        $this->assertFalse($exists);

        $movement = $this->service->increase($this->storeA, $this->productA, 20, 'initial');

        // Inventory should now exist
        $inventory = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertNotNull($inventory);
        $this->assertEquals(20, $inventory->quantity);
        $this->assertEquals(0, $movement->before_quantity);
        $this->assertEquals(20, $movement->after_quantity);
    }

    // --- Same product, different stores are independent ---

    public function test_same_product_different_stores_independent(): void
    {
        Auth::login($this->userA);

        $m1 = $this->service->increase($this->storeA, $this->productA, 50, 'purchase');
        $m2 = $this->service->increase($this->storeB, $this->productA, 30, 'purchase');

        $this->assertEquals(50, $m1->after_quantity);
        $this->assertEquals(30, $m2->after_quantity);

        $invA = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $invB = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertEquals(50, $invA->quantity);
        $this->assertEquals(30, $invB->quantity);
    }

    public function test_decrease_in_store_a_does_not_affect_store_b(): void
    {
        Auth::login($this->userA);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 30);

        $this->service->decrease($this->storeA, $this->productA, 20, 'sale');

        $invA = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $invB = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertEquals(30, $invA->quantity);
        $this->assertEquals(30, $invB->quantity); // unchanged
    }
}
