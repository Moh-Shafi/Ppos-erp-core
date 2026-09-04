<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Role;
use App\Models\TenantModule;
use App\Models\TenantFeature;

class Phase3TestHelper extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $owner;
    protected User $manager;
    protected User $cashier;
    protected Store $store;
    protected string $tokenOwner;
    protected string $tokenManager;
    protected string $tokenCashier;

    protected function setupPhase3(): void
    {
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();

        $this->tenant = Tenant::create(['name' => 'P3 Test Toko', 'slug' => 'p3-test-toko']);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner', 'email' => 'owner@p3test.com', 'password' => 'password',
        ]);
        $this->manager = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $managerRole->id,
            'name' => 'Manager', 'email' => 'manager@p3test.com', 'password' => 'password',
        ]);
        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier', 'email' => 'cashier@p3test.com', 'password' => 'password',
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

        $this->store = new Store;
        $this->store->tenant_id = $this->tenant->id;
        $this->store->name = 'Main Store';
        $this->store->code = 'MS01';
        $this->store->is_active = true;
        $this->store->save();

        $this->tokenOwner = $this->owner->createToken('test')->plainTextToken;
        $this->tokenManager = $this->manager->createToken('test')->plainTextToken;
        $this->tokenCashier = $this->cashier->createToken('test')->plainTextToken;
    }

    protected function createProduct(): Product
    {
        $cat = new Category;
        $cat->tenant_id = $this->tenant->id;
        $cat->name = 'Test Cat';
        $cat->slug = 'test-cat-' . uniqid();
        $cat->save();

        $product = new Product;
        $product->tenant_id = $this->tenant->id;
        $product->category_id = $cat->id;
        $product->name = 'Test Product ' . uniqid();
        $product->sku = 'TST-' . uniqid();
        $product->barcode = (string) uniqid();
        $product->cost_price = 5000;
        $product->selling_price = 8000;
        $product->unit = 'pcs';
        $product->save();

        return $product;
    }

    protected function createSupplier(): Supplier
    {
        $supplier = new Supplier;
        $supplier->tenant_id = $this->tenant->id;
        $supplier->name = 'Test Supplier ' . uniqid();
        $supplier->is_active = true;
        $supplier->save();

        return $supplier;
    }

    protected function createCustomer(): Customer
    {
        $customer = new Customer;
        $customer->tenant_id = $this->tenant->id;
        $customer->name = 'Test Customer ' . uniqid();
        $customer->is_active = true;
        $customer->save();

        return $customer;
    }

    protected function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }
}
