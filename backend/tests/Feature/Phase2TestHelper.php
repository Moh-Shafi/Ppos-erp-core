<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\TenantModule;
use App\Models\TenantFeature;

trait Phase2TestHelper
{
    protected Tenant $tenant;
    protected User $owner;
    protected User $manager;
    protected User $cashier;
    protected Store $store;
    protected string $tokenOwner;
    protected string $tokenManager;
    protected string $tokenCashier;

    protected function setupPhase2(): void
    {
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();

        $slug = uniqid();
        $this->tenant = Tenant::create(['name' => 'Test Toko ' . $slug, 'slug' => 'test-toko-' . $slug]);
        $this->owner = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner', 'email' => 'owner@' . $slug . '.com', 'password' => 'password',
        ]);
        $this->manager = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $managerRole->id,
            'name' => 'Manager', 'email' => 'manager@' . $slug . '.com', 'password' => 'password',
        ]);
        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier', 'email' => 'cashier@' . $slug . '.com', 'password' => 'password',
        ]);

        $this->store = new Store;
        $this->store->tenant_id = $this->tenant->id;
        $this->store->name = 'Store A'; $this->store->code = 'STR-' . $slug;
        $this->store->is_active = true; $this->store->save();

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
        $this->tokenManager = $this->manager->createToken('test')->plainTextToken;
        $this->tokenCashier = $this->cashier->createToken('test')->plainTextToken;
    }

    protected function createProduct(?Tenant $tenant = null): Product
    {
        $tenant = $tenant ?? $this->tenant;
        $cat = new Category;
        $cat->tenant_id = $tenant->id;
        $cat->name = 'Cat ' . uniqid(); $cat->slug = 'cat-' . uniqid(); $cat->save();

        $product = new Product;
        $product->tenant_id = $tenant->id;
        $product->category_id = $cat->id;
        $product->name = 'Product ' . uniqid(); $product->sku = 'SKU-' . uniqid();
        $product->barcode = (string) uniqid();
        $product->cost_price = 5000; $product->selling_price = 8000;
        $product->unit = 'pcs'; $product->save();

        return $product;
    }

    protected function createStore(string $name, string $code): Store
    {
        $store = new Store;
        $store->tenant_id = $this->tenant->id;
        $store->name = $name; $store->code = $code;
        $store->is_active = true; $store->save();
        return $store;
    }

    protected function createWarehouse(string $name): \App\Models\Warehouse
    {
        $wh = new \App\Models\Warehouse;
        $wh->tenant_id = $this->tenant->id;
        $wh->name = $name;
        $wh->is_active = true;
        $wh->save();
        return $wh;
    }

    protected function enableFeature(Tenant $tenant, string $featureSlug): void
    {
        $feature = \App\Models\Feature::where('slug', $featureSlug)->first();
        if ($feature) {
            TenantFeature::firstOrCreate(
                ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
                ['is_enabled' => true]
            );
        }
    }

    protected function disableFeature(string $featureSlug): void
    {
        $feature = \App\Models\Feature::where('slug', $featureSlug)->first();
        if ($feature) {
            TenantFeature::where('tenant_id', $this->tenant->id)
                ->where('feature_id', $feature->id)
                ->update(['is_enabled' => false]);
        }
    }
}
