<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\Feature;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase1MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_units_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('units'));
        $this->assertTrue(Schema::hasColumn('units', 'id'));
        $this->assertTrue(Schema::hasColumn('units', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('units', 'name'));
        $this->assertTrue(Schema::hasColumn('units', 'symbol'));
        $this->assertTrue(Schema::hasColumn('units', 'is_base_unit'));
    }

    public function test_unit_conversions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('unit_conversions'));
        $this->assertTrue(Schema::hasColumn('unit_conversions', 'from_unit_id'));
        $this->assertTrue(Schema::hasColumn('unit_conversions', 'to_unit_id'));
        $this->assertTrue(Schema::hasColumn('unit_conversions', 'factor'));
    }

    public function test_products_has_new_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'has_variants'));
        $this->assertTrue(Schema::hasColumn('products', 'is_trackable'));
        $this->assertTrue(Schema::hasColumn('products', 'min_stock'));
        $this->assertTrue(Schema::hasColumn('products', 'base_unit_id'));
        $this->assertTrue(Schema::hasColumn('products', 'purchase_unit_id'));
    }

    public function test_categories_has_new_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('categories', 'parent_id'));
        $this->assertTrue(Schema::hasColumn('categories', 'sort_order'));
    }

    public function test_product_variant_options_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('product_variant_options'));
        $this->assertTrue(Schema::hasColumn('product_variant_options', 'product_id'));
        $this->assertTrue(Schema::hasColumn('product_variant_options', 'name'));
        $this->assertTrue(Schema::hasColumn('product_variant_options', 'sort_order'));
    }

    public function test_product_variant_option_values_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('product_variant_option_values'));
        $this->assertTrue(Schema::hasColumn('product_variant_option_values', 'option_id'));
        $this->assertTrue(Schema::hasColumn('product_variant_option_values', 'value'));
    }

    public function test_product_variants_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('product_variants'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'product_id'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'sku'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'barcode'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'price_override'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'cost_price_override'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'is_active'));
    }

    public function test_product_variant_values_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('product_variant_values'));
        $this->assertTrue(Schema::hasColumn('product_variant_values', 'variant_id'));
        $this->assertTrue(Schema::hasColumn('product_variant_values', 'option_value_id'));
    }

    public function test_product_barcodes_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('product_barcodes'));
        $this->assertTrue(Schema::hasColumn('product_barcodes', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('product_barcodes', 'product_id'));
        $this->assertTrue(Schema::hasColumn('product_barcodes', 'variant_id'));
        $this->assertTrue(Schema::hasColumn('product_barcodes', 'barcode'));
    }

    public function test_product_images_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('product_images'));
        $this->assertTrue(Schema::hasColumn('product_images', 'product_id'));
        $this->assertTrue(Schema::hasColumn('product_images', 'url'));
        $this->assertTrue(Schema::hasColumn('product_images', 'sort_order'));
    }

    public function test_price_lists_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('price_lists'));
        $this->assertTrue(Schema::hasColumn('price_lists', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('price_lists', 'name'));
        $this->assertTrue(Schema::hasColumn('price_lists', 'slug'));
        $this->assertTrue(Schema::hasColumn('price_lists', 'is_default'));
        $this->assertTrue(Schema::hasColumn('price_lists', 'is_active'));
    }

    public function test_price_list_items_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('price_list_items'));
        $this->assertTrue(Schema::hasColumn('price_list_items', 'price_list_id'));
        $this->assertTrue(Schema::hasColumn('price_list_items', 'product_id'));
        $this->assertTrue(Schema::hasColumn('price_list_items', 'variant_id'));
        $this->assertTrue(Schema::hasColumn('price_list_items', 'price'));
    }

    public function test_existing_products_preserved(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'role_id' => $ownerRole->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password',
        ]);
        $this->actingAs($user, 'sanctum');

        $category = Category::create([
            'name' => 'Test Cat',
            'slug' => 'test-cat',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Existing Product',
            'sku' => 'EXIST-01',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $this->assertEquals('Existing Product', $product->name);
        $this->assertEquals('EXIST-01', $product->sku);
        $this->assertFalse($product->has_variants);
        $this->assertTrue($product->is_trackable);
        $this->assertNull($product->min_stock);
    }

    public function test_existing_categories_preserved(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'role_id' => $ownerRole->id,
            'name' => 'Owner',
            'email' => 'owner2@test.com',
            'password' => 'password',
        ]);
        $this->actingAs($user, 'sanctum');

        $category = Category::create([
            'name' => 'Existing Cat',
            'slug' => 'existing-cat',
            'is_active' => true,
        ]);

        $this->assertEquals('Existing Cat', $category->name);
        $this->assertNull($category->parent_id);
        $this->assertEquals(0, $category->sort_order);
    }

    public function test_core_features_seeded(): void
    {
        $this->seed(\Database\Seeders\ModuleSeeder::class);

        $this->assertTrue(Feature::where('slug', 'core.variants')->exists());
        $this->assertTrue(Feature::where('slug', 'core.price_lists')->exists());
        $this->assertTrue(Feature::where('slug', 'core.units')->exists());
        $this->assertTrue(Feature::where('slug', 'core.import_export')->exists());
    }

    public function test_phase1_permissions_seeded(): void
    {
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->assertTrue(\App\Models\Permission::where('slug', 'products.view')->exists());
        $this->assertTrue(\App\Models\Permission::where('slug', 'products.manage')->exists());
        $this->assertTrue(\App\Models\Permission::where('slug', 'categories.view')->exists());
        $this->assertTrue(\App\Models\Permission::where('slug', 'categories.manage')->exists());
    }
}
