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

class InventoryModelTest extends TestCase
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
    private string $tokenA;
    private string $tokenB;

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

        $this->tokenA = $this->userA->createToken('test')->plainTextToken;

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

        $this->tokenB = $this->userB->createToken('test')->plainTextToken;

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
        $m->note = $overrides['note'] ?? null;
        $m->save();
        return $m;
    }

    // --- Model Relationships ---

    public function test_inventory_belongs_to_tenant(): void
    {
        $inv = $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id);
        $this->assertInstanceOf(Tenant::class, $inv->tenant);
        $this->assertEquals($this->tenantA->id, $inv->tenant->id);
    }

    public function test_inventory_belongs_to_store(): void
    {
        $inv = $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id);
        $this->assertInstanceOf(Store::class, $inv->store);
        $this->assertEquals($this->storeA->id, $inv->store->id);
    }

    public function test_inventory_belongs_to_product(): void
    {
        $inv = $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id);
        $this->assertInstanceOf(Product::class, $inv->product);
        $this->assertEquals($this->productA->id, $inv->product->id);
    }

    public function test_movement_belongs_to_tenant(): void
    {
        $m = $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->userA->id);
        $this->assertInstanceOf(Tenant::class, $m->tenant);
        $this->assertEquals($this->tenantA->id, $m->tenant->id);
    }

    public function test_movement_belongs_to_store(): void
    {
        $m = $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->userA->id);
        $this->assertInstanceOf(Store::class, $m->store);
        $this->assertEquals($this->storeA->id, $m->store->id);
    }

    public function test_movement_belongs_to_product(): void
    {
        $m = $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->userA->id);
        $this->assertInstanceOf(Product::class, $m->product);
        $this->assertEquals($this->productA->id, $m->product->id);
    }

    public function test_movement_belongs_to_user(): void
    {
        $m = $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->userA->id);
        $this->assertInstanceOf(User::class, $m->user);
        $this->assertEquals($this->userA->id, $m->user->id);
    }

    public function test_movement_user_can_be_null(): void
    {
        $m = new InventoryMovement;
        $m->tenant_id = $this->tenantA->id;
        $m->store_id = $this->storeA->id;
        $m->product_id = $this->productA->id;
        $m->user_id = null;
        $m->type = 'initial';
        $m->quantity = 20;
        $m->before_quantity = 0;
        $m->after_quantity = 20;
        $m->save();

        $m2 = InventoryMovement::find($m->id);
        $this->assertNull($m2->user);
    }

    public function test_movement_reference_morph_to(): void
    {
        // Create a simple polymorphic reference using Store as example
        $m = new InventoryMovement;
        $m->tenant_id = $this->tenantA->id;
        $m->store_id = $this->storeA->id;
        $m->product_id = $this->productA->id;
        $m->user_id = $this->userA->id;
        $m->type = 'adjustment';
        $m->quantity = 10;
        $m->before_quantity = 0;
        $m->after_quantity = 10;
        $m->reference_type = Store::class;
        $m->reference_id = $this->storeA->id;
        $m->note = 'Test morph';
        $m->save();

        $this->assertInstanceOf(Store::class, $m->reference);
        $this->assertEquals($this->storeA->id, $m->reference->id);
    }

    // --- Reverse Relationships ---

    public function test_store_has_many_inventories(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->assertEquals(1, $this->storeA->inventories()->count());
    }

    public function test_product_has_many_inventories(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 30);
        $this->assertEquals(2, $this->productA->inventories()->count());
    }

    // --- Tenant Global Scope ---

    public function test_tenant_scope_filters_inventory(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantB->id, $this->storeC->id, $this->productB->id, 100);

        $this->actingAs($this->userA);
        $this->assertEquals(1, Inventory::count());
        $this->actingAs($this->userB);
        $this->assertEquals(1, Inventory::count());
    }

    public function test_tenant_scope_filters_movements(): void
    {
        $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->userA->id);
        $this->createMovement($this->tenantB->id, $this->storeC->id, $this->productB->id, $this->userB->id);

        $this->actingAs($this->userA);
        $this->assertEquals(1, InventoryMovement::count());
        $this->actingAs($this->userB);
        $this->assertEquals(1, InventoryMovement::count());
    }

    public function test_without_tenant_scope_sees_all(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->createInventory($this->tenantB->id, $this->storeC->id, $this->productB->id, 100);

        $this->assertEquals(2, Inventory::withoutTenantScope()->count());
    }

    // --- Security: Cross-Tenant Access ---

    public function test_tenant_a_cannot_access_tenant_b_inventory(): void
    {
        $invB = $this->createInventory($this->tenantB->id, $this->storeC->id, $this->productB->id, 100);

        $this->actingAs($this->userA);
        $this->assertNull(Inventory::find($invB->id));
    }

    public function test_tenant_a_cannot_access_tenant_b_movement(): void
    {
        $mB = $this->createMovement($this->tenantB->id, $this->storeC->id, $this->productB->id, $this->userB->id);

        $this->actingAs($this->userA);
        $this->assertNull(InventoryMovement::find($mB->id));
    }

    // --- Security: tenant_id not in fillable ---

    public function test_inventory_tenant_id_not_mass_assignable(): void
    {
        $inv = new Inventory;
        $inv->fill([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeA->id,
            'product_id' => $this->productA->id,
            'quantity' => 100,
        ]);
        // tenant_id should not be set via fill
        $this->assertNull($inv->tenant_id);
    }

    public function test_movement_tenant_id_not_mass_assignable(): void
    {
        $m = new InventoryMovement;
        $m->fill([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeA->id,
            'product_id' => $this->productA->id,
            'type' => 'adjustment',
            'quantity' => 10,
            'before_quantity' => 0,
            'after_quantity' => 10,
        ]);
        $this->assertNull($m->tenant_id);
    }

    // --- Security: before/after quantity not user-supplied (model level) ---
    // These are fillable for service use, but the controller will never accept them from request.
    // The test verifies the service pattern: model can set them programmatically.

    public function test_inventory_quantity_cast_to_integer(): void
    {
        $inv = $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $this->assertIsInt($inv->quantity);
        $this->assertIsInt($inv->minimum_quantity);
    }

    public function test_movement_quantities_cast_to_integer(): void
    {
        $m = $this->createMovement($this->tenantA->id, $this->storeA->id, $this->productA->id, $this->userA->id, [
            'quantity' => 50,
            'before_quantity' => 0,
            'after_quantity' => 50,
        ]);
        $this->assertIsInt($m->quantity);
        $this->assertIsInt($m->before_quantity);
        $this->assertIsInt($m->after_quantity);
    }

    // --- Unique Constraint ---

    public function test_duplicate_inventory_unique_constraint(): void
    {
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 30);
    }

    public function test_same_product_different_store_allowed(): void
    {
        $inv1 = $this->createInventory($this->tenantA->id, $this->storeA->id, $this->productA->id, 50);
        $inv2 = $this->createInventory($this->tenantA->id, $this->storeB->id, $this->productA->id, 30);

        $this->assertNotEquals($inv1->id, $inv2->id);
        $this->assertEquals(2, Inventory::withoutTenantScope()->where('product_id', $this->productA->id)->count());
    }

    // --- Movement Type Enum ---

    public function test_movement_type_enum_values(): void
    {
        $types = ['purchase', 'sale', 'sale_return', 'purchase_return', 'adjustment', 'transfer_in', 'transfer_out', 'initial'];

        foreach ($types as $type) {
            $m = new InventoryMovement;
            $m->tenant_id = $this->tenantA->id;
            $m->store_id = $this->storeA->id;
            $m->product_id = $this->productA->id;
            $m->user_id = $this->userA->id;
            $m->type = $type;
            $m->quantity = 10;
            $m->before_quantity = 0;
            $m->after_quantity = 10;
            $m->save();
            $this->assertEquals($type, InventoryMovement::find($m->id)->type);
        }
    }

    public function test_invalid_movement_type_rejected(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $m = new InventoryMovement;
        $m->tenant_id = $this->tenantA->id;
        $m->store_id = $this->storeA->id;
        $m->product_id = $this->productA->id;
        $m->user_id = $this->userA->id;
        $m->type = 'invalid_type';
        $m->quantity = 10;
        $m->before_quantity = 0;
        $m->after_quantity = 10;
        $m->save();
    }
}
