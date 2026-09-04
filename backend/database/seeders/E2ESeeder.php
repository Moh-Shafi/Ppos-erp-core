<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiscountPreset;
use App\Models\Feature;
use App\Models\Inventory;
use App\Models\Plan;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\Unit;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class E2ESeeder extends Seeder
{
    public function run(): void
    {
        // Create tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'e2e-tenant'],
            [
                'name' => 'E2E Test Toko',
                'plan_id' => Plan::where('slug', 'free')->first()?->id,
            ]
        );

        // Create store
        $store = Store::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'E2E-STORE-1'],
            [
                'name' => 'E2E Store Utama',
                'address' => 'Jl. Test No. 1',
                'phone' => '081234567890',
                'is_active' => true,
                'is_headquarters' => true,
            ]
        );

        // Create second store for switcher tests
        $store2 = Store::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'E2E-STORE-2'],
            [
                'name' => 'E2E Store Cabang',
                'address' => 'Jl. Cabang No. 2',
                'phone' => '081234567891',
                'is_active' => true,
            ]
        );

        // Create users with different roles
        $ownerRole = Role::where('slug', 'owner')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();
        $accountantRole = Role::where('slug', 'accountant')->first();

        $owner = User::firstOrCreate(
            ['email' => 'e2e.owner@test.com'],
            [
                'tenant_id' => $tenant->id,
                'role_id' => $ownerRole->id,
                'name' => 'E2E Owner',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

        $cashier = User::firstOrCreate(
            ['email' => 'e2e.cashier@test.com'],
            [
                'tenant_id' => $tenant->id,
                'role_id' => $cashierRole->id,
                'name' => 'E2E Cashier',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

        $staff = User::firstOrCreate(
            ['email' => 'e2e.staff@test.com'],
            [
                'tenant_id' => $tenant->id,
                'role_id' => $staffRole->id,
                'name' => 'E2E Staff',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

        $accountant = User::firstOrCreate(
            ['email' => 'e2e.accountant@test.com'],
            [
                'tenant_id' => $tenant->id,
                'role_id' => $accountantRole->id,
                'name' => 'E2E Accountant',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Create business profile
        $businessType = BusinessType::where('slug', 'restaurant')->first();
        BusinessProfile::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'business_type_id' => $businessType->id,
                'business_name' => 'E2E Test Toko',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'locale' => 'id',
                'is_active' => true,
            ]
        );

        // Enable modules for test tenant (restaurant defaults + warehouse for Phase 2)
        $moduleService = app(ModuleService::class);
        $moduleService->applyBusinessTypeDefaults($tenant->id, $businessType->id);

        // Enable warehouse module for Phase 2 E2E tests
        $warehouseModule = \App\Models\Module::where('slug', 'warehouse')->first();
        if ($warehouseModule) {
            \App\Models\TenantModule::firstOrCreate(
                ['tenant_id' => $tenant->id, 'module_id' => $warehouseModule->id],
                ['is_enabled' => true]
            );
        }

        // Enable inventory.stocktake feature for Phase 2 E2E tests
        $stocktakeFeature = \App\Models\Feature::where('slug', 'inventory.stocktake')->first();
        if ($stocktakeFeature) {
            \App\Models\TenantFeature::firstOrCreate(
                ['tenant_id' => $tenant->id, 'feature_id' => $stocktakeFeature->id],
                ['is_enabled' => true]
            );
        }

        // Create category
        $category = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'e2e-minuman'],
            [
                'name' => 'E2E Minuman',
                'description' => 'Minuman untuk E2E test',
                'is_active' => true,
            ]
        );

        // Create products
        $products = [
            [
                'name' => 'E2E Kopi Susu',
                'sku' => 'E2E-KS-001',
                'barcode' => '8990001',
                'cost_price' => 5000,
                'selling_price' => 8000,
                'unit' => 'cup',
            ],
            [
                'name' => 'E2E Es Teh',
                'sku' => 'E2E-ET-002',
                'barcode' => '8990002',
                'cost_price' => 2000,
                'selling_price' => 5000,
                'unit' => 'cup',
            ],
            [
                'name' => 'E2E Air Mineral',
                'sku' => 'E2E-AM-003',
                'barcode' => '8990003',
                'cost_price' => 1500,
                'selling_price' => 3000,
                'unit' => 'botol',
            ],
        ];

        foreach ($products as $pData) {
            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $pData['sku']],
                array_merge($pData, [
                    'category_id' => $category->id,
                    'is_active' => true,
                ])
            );

            // Create inventory with stock
            Inventory::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('store_id', $store->id)
                ->where('product_id', $product->id)
                ->delete();

            $inv = new Inventory();
            $inv->tenant_id = $tenant->id;
            $inv->store_id = $store->id;
            $inv->product_id = $product->id;
            $inv->quantity = 100;
            $inv->minimum_quantity = 5;
            $inv->save();
        }

        // Create customer
        Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '08123456789'],
            [
                'name' => 'E2E Pelanggan',
                'email' => 'e2e.customer@test.com',
                'is_active' => true,
            ]
        );

        // Create units
        $unitPcs = Unit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'symbol' => 'pcs'],
            ['name' => 'Pieces', 'is_base_unit' => true]
        );
        $unitBox = Unit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'symbol' => 'box'],
            ['name' => 'Box', 'is_base_unit' => false]
        );

        // Create unit conversion (1 box = 12 pcs)
        DB::table('unit_conversions')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'from_unit_id' => $unitBox->id, 'to_unit_id' => $unitPcs->id],
            ['factor' => 12]
        );

        // Create price list
        PriceList::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'E2E Retail Price'],
            [
                'slug' => 'e2e-retail-price',
                'description' => 'Standard retail price list for E2E',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        // Seed system adjustment reasons for Phase 2
        $adjustmentReasonService = app(\App\Services\AdjustmentReasonService::class);
        $adjustmentReasonService->seedSystemReasons($tenant->id);

        // ===== Phase 4 E2E Data =====

        // Enable Phase 4 + Phase 5 + Phase 6 feature flags
        $phase4Features = ['pos.hold_sale', 'pos.refund', 'pos.discount_presets', 'sales.customer_credit', 'customers.loyalty_points', 'payment.gateway_qris', 'payment.cash_drawer', 'finance.chart_of_accounts', 'finance.journal_entries', 'finance.financial_reports', 'finance.fiscal_periods'];
        foreach ($phase4Features as $featureSlug) {
            $feature = Feature::where('slug', $featureSlug)->first();
            if ($feature) {
                TenantFeature::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
                    ['is_enabled' => true]
                );
            }
        }

        // Create product with variants (E2E Kopi Variant)
        $variantProduct = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'E2E-KV-001'],
            [
                'category_id' => $category->id,
                'name' => 'E2E Kopi Variant',
                'barcode' => '8990010',
                'cost_price' => 5000,
                'selling_price' => 8000,
                'unit' => 'cup',
                'is_active' => true,
                'has_variants' => true,
            ]
        );

        // Create variants for the variant product
        $variantRegular = ProductVariant::firstOrCreate(
            ['product_id' => $variantProduct->id, 'sku' => 'E2E-KV-001-R'],
            [
                'price_override' => '8000.00',
                'is_active' => true,
            ]
        );
        $variantLarge = ProductVariant::firstOrCreate(
            ['product_id' => $variantProduct->id, 'sku' => 'E2E-KV-001-L'],
            [
                'price_override' => '12000.00',
                'is_active' => true,
            ]
        );

        // Create variant option and option values
        $optionId = DB::table('product_variant_options')->insertGetId(
            ['product_id' => $variantProduct->id, 'name' => 'Size', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
        $regularValueId = DB::table('product_variant_option_values')->insertGetId(
            ['option_id' => $optionId, 'value' => 'Regular', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
        $largeValueId = DB::table('product_variant_option_values')->insertGetId(
            ['option_id' => $optionId, 'value' => 'Large', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()]
        );
        // Link variants to option values via pivot
        DB::table('product_variant_values')->insertOrIgnore(
            ['variant_id' => $variantRegular->id, 'option_value_id' => $regularValueId, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('product_variant_values')->insertOrIgnore(
            ['variant_id' => $variantLarge->id, 'option_value_id' => $largeValueId, 'created_at' => now(), 'updated_at' => now()]
        );

        // Create inventory for variant product
        Inventory::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('store_id', $store->id)
            ->where('product_id', $variantProduct->id)
            ->delete();
        $inv = new Inventory();
        $inv->tenant_id = $tenant->id;
        $inv->store_id = $store->id;
        $inv->product_id = $variantProduct->id;
        $inv->quantity = 100;
        $inv->minimum_quantity = 5;
        $inv->save();

        // Create customer with credit limit and price list
        $priceList = PriceList::where('tenant_id', $tenant->id)->where('name', 'E2E Retail Price')->first();
        $creditCustomer = Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '08123456790'],
            [
                'name' => 'E2E Pelanggan Kredit',
                'email' => 'e2e.credit@test.com',
                'is_active' => true,
                'credit_limit' => 50000,
                'outstanding_balance' => '10000.00',
                'price_list_id' => $priceList?->id,
            ]
        );

        // Create price list items (discounted prices for E2E products)
        $e2eProducts = Product::where('tenant_id', $tenant->id)
            ->whereIn('sku', ['E2E-KS-001', 'E2E-ET-002', 'E2E-AM-003'])
            ->get();
        foreach ($e2eProducts as $plProduct) {
            PriceListItem::firstOrCreate(
                ['price_list_id' => $priceList->id, 'product_id' => $plProduct->id],
                ['price' => (string) ((int) $plProduct->selling_price - 1000)]
            );
        }

        // Create discount presets
        DiscountPreset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'E2E Diskon 10%'],
            [
                'type' => 'percentage',
                'value' => '10.00',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
        DiscountPreset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'E2E Diskon Rp 2000'],
            [
                'type' => 'fixed',
                'value' => '2000.00',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Set receipt settings for store
        DB::table('stores')->where('id', $store->id)->update([
            'receipt_settings' => json_encode([
                'header_text' => 'E2E Test Toko',
                'footer_text' => 'Terima kasih!',
                'show_cashier' => true,
                'show_customer' => false,
                'show_qr_code' => false,
                'paper_width' => '80mm',
            ]),
        ]);

        // ===== Phase 8 E2E Data — enable business-specific modules =====
        $phase8Modules = ['tables', 'reservations', 'kitchen', 'kds', 'promotions', 'loyalty', 'appointments', 'services'];
        foreach ($phase8Modules as $moduleSlug) {
            $module = \App\Models\Module::where('slug', $moduleSlug)->first();
            if ($module) {
                \App\Models\TenantModule::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'module_id' => $module->id],
                    ['is_enabled' => true]
                );
            }
        }

        // Enable Phase 8A restaurant features
        $phase8AFeatures = ['tables.qr_ordering', 'recipes.auto_deduct', 'billsplit'];
        foreach ($phase8AFeatures as $featureSlug) {
            $feature = Feature::where('slug', $featureSlug)->first();
            if ($feature) {
                TenantFeature::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
                    ['is_enabled' => true]
                );
            }
        }

        // ===== Phase 9 E2E Data — enable integrations module =====
        $phase9Module = \App\Models\Module::where('slug', 'integrations')->first();
        if ($phase9Module) {
            \App\Models\TenantModule::updateOrCreate(
                ['tenant_id' => $tenant->id, 'module_id' => $phase9Module->id],
                ['is_enabled' => true]
            );
        }

        // Seed Phase 6 default accounts
        $defaultAccountsSeeder = new DefaultAccountsSeeder();
        $defaultAccountsSeeder->seedForTenant($tenant->id);
    }
}
