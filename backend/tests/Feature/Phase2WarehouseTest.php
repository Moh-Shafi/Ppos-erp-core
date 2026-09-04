<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Category;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Product;
use App\Models\Role;
use App\Models\TenantModule;
use App\Models\TenantFeature;
use App\Services\WarehouseService;

class Phase2WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private User $cashier;
    private string $tokenOwner;
    private string $tokenCashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();

        $this->tenant = Tenant::create(['name' => 'Wh Test Toko', 'slug' => 'wh-test-toko']);
        $this->owner = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner', 'email' => 'owner@whtest.com', 'password' => 'password',
        ]);
        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier', 'email' => 'cashier@whtest.com', 'password' => 'password',
        ]);

        foreach (\App\Models\Module::all() as $module) {
            TenantModule::firstOrCreate([
                'tenant_id' => $this->tenant->id,
                'module_id' => $module->id,
            ], ['is_enabled' => true]);
        }
        foreach (\App\Models\Feature::all() as $feature) {
            TenantFeature::firstOrCreate([
                'tenant_id' => $this->tenant->id,
                'feature_id' => $feature->id,
            ], ['is_enabled' => true]);
        }

        $this->tokenOwner = $this->owner->createToken('test')->plainTextToken;
        $this->tokenCashier = $this->cashier->createToken('test')->plainTextToken;
    }

    private function createProduct(): Product
    {
        $cat = new Category;
        $cat->tenant_id = $this->tenant->id;
        $cat->name = 'Test Cat'; $cat->slug = 'test-cat'; $cat->save();

        $product = new Product;
        $product->tenant_id = $this->tenant->id;
        $product->category_id = $cat->id;
        $product->name = 'Test Product'; $product->sku = 'TST-' . uniqid();
        $product->barcode = (string) uniqid();
        $product->cost_price = 5000; $product->selling_price = 8000;
        $product->unit = 'pcs'; $product->save();

        return $product;
    }

    public function test_create_warehouse(): void
    {
        $service = new WarehouseService();
        $warehouse = $service->createWarehouse([
            'name' => 'Main Warehouse',
            'address' => '123 Storage St',
            'phone' => '555-0100',
        ], $this->tenant->id);

        $this->assertInstanceOf(Warehouse::class, $warehouse);
        $this->assertEquals('Main Warehouse', $warehouse->name);
        $this->assertEquals($this->tenant->id, $warehouse->tenant_id);
        $this->assertTrue($warehouse->is_active);
    }

    public function test_list_warehouses_tenant_scoped(): void
    {
        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other-wh']);

        $wh1 = new Warehouse; $wh1->tenant_id = $this->tenant->id; $wh1->name = 'Wh1'; $wh1->is_active = true; $wh1->save();
        $wh2 = new Warehouse; $wh2->tenant_id = $this->tenant->id; $wh2->name = 'Wh2'; $wh2->is_active = true; $wh2->save();
        $wh3 = new Warehouse; $wh3->tenant_id = $tenant2->id; $wh3->name = 'Other Wh'; $wh3->is_active = true; $wh3->save();

        $warehouses = Warehouse::where('tenant_id', $this->tenant->id)->get();
        $this->assertCount(2, $warehouses);
        $this->assertFalse($warehouses->contains('name', 'Other Wh'));
    }

    public function test_delete_warehouse_without_stock(): void
    {
        $service = new WarehouseService();
        $warehouse = $service->createWarehouse(['name' => 'To Delete'], $this->tenant->id);

        $service->deleteWarehouse($warehouse->id, $this->tenant->id);
        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    public function test_delete_warehouse_with_stock_blocked(): void
    {
        $service = new WarehouseService();
        $warehouse = $service->createWarehouse(['name' => 'Has Stock'], $this->tenant->id);
        $product = $this->createProduct();

        $ws = new WarehouseStock;
        $ws->tenant_id = $this->tenant->id;
        $ws->warehouse_id = $warehouse->id;
        $ws->product_id = $product->id;
        $ws->quantity = 10;
        $ws->save();

        $this->expectException(\InvalidArgumentException::class);
        $service->deleteWarehouse($warehouse->id, $this->tenant->id);
    }

    public function test_api_list_warehouses(): void
    {
        $wh = new Warehouse; $wh->tenant_id = $this->tenant->id; $wh->name = 'API Wh'; $wh->is_active = true; $wh->save();

        $response = $this->withToken($this->tokenOwner)->getJson('/api/v1/warehouses');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'total', 'per_page', 'current_page', 'last_page']);
    }

    public function test_api_create_warehouse(): void
    {
        $response = $this->withToken($this->tokenOwner)->postJson('/api/v1/warehouses', [
            'name' => 'New API Wh',
            'address' => '456 Test Ave',
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('warehouse.name', 'New API Wh');
    }

    public function test_api_update_warehouse(): void
    {
        $warehouse = new Warehouse; $warehouse->tenant_id = $this->tenant->id; $warehouse->name = 'Old Name'; $warehouse->is_active = true; $warehouse->save();

        $response = $this->withToken($this->tokenOwner)->putJson("/api/v1/warehouses/{$warehouse->id}", ['name' => 'New Name']);
        $response->assertStatus(200);
        $response->assertJsonPath('warehouse.name', 'New Name');
    }

    public function test_api_delete_warehouse(): void
    {
        $warehouse = new Warehouse; $warehouse->tenant_id = $this->tenant->id; $warehouse->name = 'Delete Me'; $warehouse->is_active = true; $warehouse->save();

        $response = $this->withToken($this->tokenOwner)->deleteJson("/api/v1/warehouses/{$warehouse->id}");
        $response->assertStatus(200);
    }

    public function test_api_warehouse_stock(): void
    {
        $warehouse = new Warehouse; $warehouse->tenant_id = $this->tenant->id; $warehouse->name = 'Stock Wh'; $warehouse->is_active = true; $warehouse->save();
        $product = $this->createProduct();
        $ws = new WarehouseStock;
        $ws->tenant_id = $this->tenant->id; $ws->warehouse_id = $warehouse->id;
        $ws->product_id = $product->id; $ws->quantity = 50; $ws->save();

        $response = $this->withToken($this->tokenOwner)->getJson("/api/v1/warehouses/{$warehouse->id}/stock");
        $response->assertStatus(200);
    }

    public function test_api_adjust_warehouse_stock(): void
    {
        $warehouse = new Warehouse; $warehouse->tenant_id = $this->tenant->id; $warehouse->name = 'Adjust Wh'; $warehouse->is_active = true; $warehouse->save();
        $product = $this->createProduct();

        $response = $this->withToken($this->tokenOwner)->postJson("/api/v1/warehouses/{$warehouse->id}/adjust", [
            'product_id' => $product->id,
            'delta' => 25,
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('stock.quantity', 25);
    }

    public function test_cashier_cannot_manage_warehouse(): void
    {
        $response = $this->withToken($this->tokenCashier)->postJson('/api/v1/warehouses', ['name' => 'Test']);
        $response->assertStatus(403);
    }

    public function test_tenant_isolation(): void
    {
        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other-iso']);
        $warehouse = new Warehouse; $warehouse->tenant_id = $tenant2->id; $warehouse->name = 'Other Wh'; $warehouse->is_active = true; $warehouse->save();

        $response = $this->withToken($this->tokenOwner)->getJson("/api/v1/warehouses/{$warehouse->id}");
        $response->assertStatus(404);
    }

    public function test_staff_cannot_view_warehouse(): void
    {
        $staffRole = \App\Models\Role::where('slug', 'staff')->first();
        $staff = \App\Models\User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $staffRole->id,
            'name' => 'Staff', 'email' => 'staff@wh-test.com', 'password' => 'password',
        ]);
        $token = $staff->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/warehouses');
        $response->assertStatus(403);
    }
}
