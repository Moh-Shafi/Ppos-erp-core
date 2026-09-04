<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Inventory;
use App\Models\Module;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Phase4TestHelper extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $tenantB;
    protected User $owner;
    protected User $manager;
    protected User $cashier;
    protected User $ownerB;
    protected Store $store;
    protected Store $storeB;
    protected Category $cat;
    protected Product $product;
    protected Product $productWithVariants;
    protected ProductVariant $variant1;
    protected ProductVariant $variant2;
    protected Customer $customer;
    protected Customer $customerWithCredit;
    protected Customer $customerWithPriceList;
    protected PriceList $priceList;
    protected string $tokenOwner;
    protected string $tokenManager;
    protected string $tokenCashier;

    protected function setupPhase4(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();

        $this->tenant = Tenant::create(['name' => 'P4 Test Toko', 'slug' => 'p4-test-toko']);
        $this->tenantB = Tenant::create(['name' => 'P4 Toko B', 'slug' => 'p4-toko-b']);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner', 'email' => 'owner@p4test.com', 'password' => 'password',
        ]);
        $this->manager = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $managerRole->id,
            'name' => 'Manager', 'email' => 'manager@p4test.com', 'password' => 'password',
        ]);
        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier', 'email' => 'cashier@p4test.com', 'password' => 'password',
        ]);

        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@p4test.com', 'password' => 'password',
        ]);

        foreach (Module::all() as $module) {
            TenantModule::firstOrCreate([
                'tenant_id' => $this->tenant->id,
                'module_id' => $module->id,
            ], ['is_enabled' => true]);
        }
        foreach (Feature::all() as $feature) {
            TenantFeature::firstOrCreate([
                'tenant_id' => $this->tenant->id,
                'feature_id' => $feature->id,
            ], ['is_enabled' => true]);
        }

        foreach (Module::all() as $module) {
            TenantModule::firstOrCreate([
                'tenant_id' => $this->tenantB->id,
                'module_id' => $module->id,
            ], ['is_enabled' => true]);
        }
        foreach (Feature::all() as $feature) {
            TenantFeature::firstOrCreate([
                'tenant_id' => $this->tenantB->id,
                'feature_id' => $feature->id,
            ], ['is_enabled' => true]);
        }

        $this->store = new Store;
        $this->store->tenant_id = $this->tenant->id;
        $this->store->name = 'Main Store';
        $this->store->code = 'MS01';
        $this->store->is_active = true;
        $this->store->save();

        $this->storeB = new Store;
        $this->storeB->tenant_id = $this->tenantB->id;
        $this->storeB->name = 'Store B';
        $this->storeB->code = 'SB01';
        $this->storeB->is_active = true;
        $this->storeB->save();

        $this->cat = new Category;
        $this->cat->tenant_id = $this->tenant->id;
        $this->cat->name = 'Test Cat';
        $this->cat->slug = 'test-cat-' . uniqid();
        $this->cat->save();

        $this->product = new Product;
        $this->product->tenant_id = $this->tenant->id;
        $this->product->category_id = $this->cat->id;
        $this->product->name = 'Test Product ' . uniqid();
        $this->product->sku = 'TST-' . uniqid();
        $this->product->barcode = (string) uniqid();
        $this->product->cost_price = 5000;
        $this->product->selling_price = 10000;
        $this->product->unit = 'pcs';
        $this->product->has_variants = false;
        $this->product->save();

        $this->productWithVariants = new Product;
        $this->productWithVariants->tenant_id = $this->tenant->id;
        $this->productWithVariants->category_id = $this->cat->id;
        $this->productWithVariants->name = 'Variant Product ' . uniqid();
        $this->productWithVariants->sku = 'VAR-' . uniqid();
        $this->productWithVariants->barcode = (string) uniqid();
        $this->productWithVariants->cost_price = 8000;
        $this->productWithVariants->selling_price = 15000;
        $this->productWithVariants->unit = 'pcs';
        $this->productWithVariants->has_variants = true;
        $this->productWithVariants->save();

        $this->variant1 = new ProductVariant;
        $this->variant1->product_id = $this->productWithVariants->id;
        $this->variant1->sku = 'VAR1-' . uniqid();
        $this->variant1->barcode = (string) uniqid();
        $this->variant1->price_override = 18000;
        $this->variant1->is_active = true;
        $this->variant1->save();

        $this->variant2 = new ProductVariant;
        $this->variant2->product_id = $this->productWithVariants->id;
        $this->variant2->sku = 'VAR2-' . uniqid();
        $this->variant2->barcode = (string) uniqid();
        $this->variant2->price_override = null;
        $this->variant2->is_active = true;
        $this->variant2->save();

        $this->customer = new Customer;
        $this->customer->tenant_id = $this->tenant->id;
        $this->customer->name = 'Test Customer ' . uniqid();
        $this->customer->is_active = true;
        $this->customer->save();

        $this->customerWithCredit = new Customer;
        $this->customerWithCredit->tenant_id = $this->tenant->id;
        $this->customerWithCredit->name = 'Credit Customer ' . uniqid();
        $this->customerWithCredit->is_active = true;
        $this->customerWithCredit->credit_limit = 50000;
        $this->customerWithCredit->outstanding_balance = 0;
        $this->customerWithCredit->save();

        $this->priceList = new PriceList;
        $this->priceList->tenant_id = $this->tenant->id;
        $this->priceList->name = 'VIP Price List ' . uniqid();
        $this->priceList->slug = 'vip-pl-' . uniqid();
        $this->priceList->is_active = true;
        $this->priceList->save();

        $pli = new PriceListItem;
        $pli->price_list_id = $this->priceList->id;
        $pli->product_id = $this->product->id;
        $pli->variant_id = null;
        $pli->price = 8500;
        $pli->save();

        $pli2 = new PriceListItem;
        $pli2->price_list_id = $this->priceList->id;
        $pli2->product_id = $this->productWithVariants->id;
        $pli2->variant_id = $this->variant1->id;
        $pli2->price = 16000;
        $pli2->save();

        $this->customerWithPriceList = new Customer;
        $this->customerWithPriceList->tenant_id = $this->tenant->id;
        $this->customerWithPriceList->name = 'VIP Customer ' . uniqid();
        $this->customerWithPriceList->is_active = true;
        $this->customerWithPriceList->price_list_id = $this->priceList->id;
        $this->customerWithPriceList->save();

        $this->tokenOwner = $this->owner->createToken('test')->plainTextToken;
        $this->tokenManager = $this->manager->createToken('test')->plainTextToken;
        $this->tokenCashier = $this->cashier->createToken('test')->plainTextToken;
    }

    protected function setInventory(Store $store, Product $product, int $qty): void
    {
        $inv = Inventory::withoutTenantScope()
            ->where('tenant_id', $store->tenant_id)
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->first();

        if (!$inv) {
            $inv = new Inventory;
            $inv->tenant_id = $store->tenant_id;
            $inv->store_id = $store->id;
            $inv->product_id = $product->id;
            $inv->minimum_quantity = 0;
        }
        $inv->quantity = $qty;
        $inv->save();
    }

    protected function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    protected function checkoutData(array $items, array $payments = [], array $extra = []): array
    {
        return array_merge([
            'store_id' => $this->store->id,
            'items' => $items,
            'payments' => $payments ?: [['payment_method' => 'cash', 'amount' => 999999]],
        ], $extra);
    }
}
