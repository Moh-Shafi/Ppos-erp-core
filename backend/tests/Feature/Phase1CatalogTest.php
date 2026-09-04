<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1CatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private Tenant $tenant;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $this->tenant = Tenant::create(['name' => 'Test Toko', 'slug' => 'test-toko']);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $ownerRole->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password',
        ]);

        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $cashierRole->id,
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password' => 'password',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $this->category = Category::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
        ]);
    }

    // === Category Service Tests ===

    public function test_create_root_category(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Hot Drinks',
            'description' => 'Hot beverages',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('category.name', 'Hot Drinks');
        $response->assertJsonPath('category.parent_id', null);
        $this->assertDatabaseHas('categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Hot Drinks',
            'parent_id' => null,
        ]);
    }

    public function test_create_child_category(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Coffee',
            'parent_id' => $this->category->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('category.parent_id', $this->category->id);
    }

    public function test_create_category_nonexistent_parent_rejected(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Orphan Child',
            'parent_id' => 99999,
        ]);

        $response->assertStatus(404);
    }

    public function test_update_category_creates_cycle(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $parent = Category::create([
            'name' => 'Parent',
            'slug' => 'parent',
            'is_active' => true,
        ]);

        $child = Category::create([
            'name' => 'Child',
            'slug' => 'child',
            'is_active' => true,
            'parent_id' => $parent->id,
        ]);

        $grandchild = Category::create([
            'name' => 'Grandchild',
            'slug' => 'grandchild',
            'is_active' => true,
            'parent_id' => $child->id,
        ]);

        // Try to move parent under grandchild → cycle
        $response = $this->putJson("/api/v1/categories/{$parent->id}", [
            'parent_id' => $grandchild->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_delete_category_with_children_blocked(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $child = Category::create([
            'name' => 'Child',
            'slug' => 'child',
            'is_active' => true,
            'parent_id' => $this->category->id,
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$this->category->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot delete category with sub-categories');
    }

    public function test_delete_category_with_products_blocked(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$this->category->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot delete category with existing products');
    }

    public function test_delete_leaf_category_success(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $leaf = Category::create([
            'name' => 'Leaf',
            'slug' => 'leaf',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$leaf->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $leaf->id]);
    }

    public function test_category_tree_endpoint(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $child = Category::create([
            'name' => 'Coffee',
            'slug' => 'coffee',
            'is_active' => true,
            'parent_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/v1/categories/tree');

        $response->assertStatus(200);
        $response->assertJsonStructure(['tree']);
    }

    // === Product API Tests ===

    public function test_api_create_simple_product(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/v1/products', [
            'category_id' => $this->category->id,
            'name' => 'Espresso',
            'sku' => 'ESP-01',
            'barcode' => '899001',
            'cost_price' => 5000,
            'selling_price' => 15000,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('product.name', 'Espresso');
        $response->assertJsonPath('product.has_variants', false);
        $response->assertJsonPath('product.is_trackable', true);
    }

    public function test_api_create_product_with_new_fields(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/v1/products', [
            'category_id' => $this->category->id,
            'name' => 'Service Product',
            'cost_price' => 0,
            'selling_price' => 50000,
            'unit' => 'pcs',
            'is_active' => true,
            'is_trackable' => false,
            'min_stock' => 5,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('product.is_trackable', false);
        $response->assertJsonPath('product.min_stock', 5);
    }

    public function test_api_create_product_with_variants(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/v1/products', [
            'category_id' => $this->category->id,
            'name' => 'T-Shirt',
            'cost_price' => 30000,
            'selling_price' => 75000,
            'unit' => 'pcs',
            'has_variants' => true,
            'variant_options' => [
                [
                    'name' => 'Size',
                    'sort_order' => 0,
                    'values' => [
                        ['value' => 'S', 'sort_order' => 0],
                        ['value' => 'M', 'sort_order' => 1],
                    ],
                ],
            ],
            'variants' => [
                [
                    'option_value_ids' => ['S'],
                    'sku' => 'TSHIRT-S',
                    'barcode' => 'TSHIRT-S-BC',
                    'price_override' => 70000,
                    'is_active' => true,
                ],
                [
                    'option_value_ids' => ['M'],
                    'sku' => 'TSHIRT-M',
                    'barcode' => 'TSHIRT-M-BC',
                    'price_override' => null,
                    'is_active' => true,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('product.has_variants', true);
        $response->assertJsonPath('product.variants.0.sku', 'TSHIRT-S');
        $response->assertJsonPath('product.variants.1.sku', 'TSHIRT-M');
    }

    public function test_api_show_product_includes_variants(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
            'has_variants' => false,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('product.name', 'Test Product');
        $response->assertJsonPath('product.has_variants', false);
    }

    public function test_api_list_products_with_variant_filter(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Simple',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
            'has_variants' => false,
        ]);

        Product::create([
            'category_id' => $this->category->id,
            'name' => 'With Variants',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
            'has_variants' => true,
        ]);

        $response = $this->getJson('/api/v1/products?has_variants=1');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.name', 'With Variants');
        $response->assertJsonPath('total', 1);
    }

    // === Permission Tests ===

    public function test_cashier_cannot_create_product(): void
    {
        $this->actingAs($this->cashier, 'sanctum');

        $response = $this->postJson('/api/v1/products', [
            'category_id' => $this->category->id,
            'name' => 'Test',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $response->assertStatus(403);
    }

    public function test_cashier_cannot_create_category(): void
    {
        $this->actingAs($this->cashier, 'sanctum');

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Test Cat',
        ]);

        $response->assertStatus(403);
    }

    public function test_cashier_can_view_products(): void
    {
        $this->actingAs($this->cashier, 'sanctum');

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
    }

    public function test_cashier_can_view_categories(): void
    {
        $this->actingAs($this->cashier, 'sanctum');

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200);
    }

    // === Tenant Isolation Tests ===

    public function test_product_tenant_isolation(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $otherCategory = new Category;
        $otherCategory->forceFill([
            'name' => 'Other Cat',
            'slug' => 'other-cat',
            'is_active' => true,
            'tenant_id' => $otherTenant->id,
        ])->save();

        $otherProduct = new Product;
        $otherProduct->forceFill([
            'category_id' => $otherCategory->id,
            'name' => 'Other Product',
            'cost_price' => 1000,
            'selling_price' => 2000,
            'unit' => 'pcs',
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ])->save();

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->getJson("/api/v1/products/{$otherProduct->id}");

        $response->assertStatus(404);
    }

    public function test_category_tenant_isolation(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $otherCategory = new Category;
        $otherCategory->forceFill([
            'name' => 'Other Cat',
            'slug' => 'other-cat',
            'is_active' => true,
            'tenant_id' => $otherTenant->id,
        ])->save();

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->deleteJson("/api/v1/categories/{$otherCategory->id}");

        $response->assertStatus(404);
    }

    // === Barcode Lookup Test ===

    public function test_barcode_lookup(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Barcode Product',
            'sku' => 'BP-01',
            'barcode' => '123456789',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $response = $this->getJson('/api/v1/products/lookup?barcode=123456789');

        $response->assertStatus(200);
        $response->assertJsonPath('product.name', 'Barcode Product');
    }

    public function test_barcode_lookup_not_found(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->getJson('/api/v1/products/lookup?barcode=NOTFOUND');

        $response->assertStatus(404);
    }

    // === CSV Export Test ===

    public function test_export_products_csv(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Export Test',
            'sku' => 'EXP-01',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $response = $this->getJson('/api/v1/products/export');

        $response->assertStatus(200);
        $response->assertHeaderContains('Content-Type', 'text/csv');
    }
}
