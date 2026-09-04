<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\ProductVariantOptionValue;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1VariantTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Tenant $tenant;
    private Category $category;
    private CatalogService $catalogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $this->tenant = Tenant::create(['name' => 'Test Toko', 'slug' => 'test-toko']);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $ownerRole->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $this->category = Category::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
        ]);

        $this->catalogService = app(CatalogService::class);
    }

    public function test_create_product_with_variants_via_service(): void
    {
        $product = $this->catalogService->createProduct([
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
                    'barcode' => null,
                    'price_override' => 70000,
                    'is_active' => true,
                ],
                [
                    'option_value_ids' => ['M'],
                    'sku' => 'TSHIRT-M',
                    'barcode' => null,
                    'price_override' => null,
                    'is_active' => true,
                ],
            ],
        ], $this->tenant->id);

        $this->assertTrue($product->has_variants);
        $this->assertCount(2, $product->variants);
        $this->assertEquals('TSHIRT-S', $product->variants[0]->sku);
        $this->assertEquals('70000.00', $product->variants[0]->price_override);
        $this->assertNull($product->variants[1]->price_override);
    }

    public function test_generate_variant_combinations(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test',
            'cost_price' => 1000,
            'selling_price' => 2000,
            'unit' => 'pcs',
            'has_variants' => true,
        ]);

        $option = ProductVariantOption::create([
            'product_id' => $product->id,
            'name' => 'Size',
            'sort_order' => 0,
        ]);

        $v1 = ProductVariantOptionValue::create(['option_id' => $option->id, 'value' => 'S', 'sort_order' => 0]);
        $v2 = ProductVariantOptionValue::create(['option_id' => $option->id, 'value' => 'M', 'sort_order' => 1]);
        $v3 = ProductVariantOptionValue::create(['option_id' => $option->id, 'value' => 'L', 'sort_order' => 2]);

        $combinations = $this->catalogService->generateVariantCombinations($product->id, [[$v1->id, $v2->id, $v3->id]]);

        $this->assertCount(3, $combinations);
        $this->assertEquals([$v1->id], $combinations[0]['option_value_ids']);
        $this->assertEquals('S', $combinations[0]['label']);
    }

    public function test_generate_variant_combinations_multi_option(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test',
            'cost_price' => 1000,
            'selling_price' => 2000,
            'unit' => 'pcs',
            'has_variants' => true,
        ]);

        $opt1 = ProductVariantOption::create(['product_id' => $product->id, 'name' => 'Size', 'sort_order' => 0]);
        $opt2 = ProductVariantOption::create(['product_id' => $product->id, 'name' => 'Color', 'sort_order' => 1]);

        $s = ProductVariantOptionValue::create(['option_id' => $opt1->id, 'value' => 'S', 'sort_order' => 0]);
        $m = ProductVariantOptionValue::create(['option_id' => $opt1->id, 'value' => 'M', 'sort_order' => 1]);
        $red = ProductVariantOptionValue::create(['option_id' => $opt2->id, 'value' => 'Red', 'sort_order' => 0]);
        $blue = ProductVariantOptionValue::create(['option_id' => $opt2->id, 'value' => 'Blue', 'sort_order' => 1]);

        $combinations = $this->catalogService->generateVariantCombinations($product->id, [
            [$s->id, $m->id],
            [$red->id, $blue->id],
        ]);

        $this->assertCount(4, $combinations);
        $this->assertEquals('S / Red', $combinations[0]['label']);
        $this->assertEquals('M / Blue', $combinations[3]['label']);
    }

    public function test_sku_uniqueness_across_variants(): void
    {
        // Create first product with variant SKU
        $this->catalogService->createProduct([
            'category_id' => $this->category->id,
            'name' => 'Product A',
            'cost_price' => 1000,
            'selling_price' => 2000,
            'unit' => 'pcs',
            'has_variants' => true,
            'variant_options' => [
                ['name' => 'Size', 'values' => [['value' => 'S']]],
            ],
            'variants' => [
                ['option_value_ids' => ['S'], 'sku' => 'UNIQUE-SKU', 'is_active' => true],
            ],
        ], $this->tenant->id);

        // Try to create second product with same variant SKU
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU');

        $this->catalogService->createProduct([
            'category_id' => $this->category->id,
            'name' => 'Product B',
            'cost_price' => 1000,
            'selling_price' => 2000,
            'unit' => 'pcs',
            'has_variants' => true,
            'variant_options' => [
                ['name' => 'Size', 'values' => [['value' => 'S']]],
            ],
            'variants' => [
                ['option_value_ids' => ['S'], 'sku' => 'UNIQUE-SKU', 'is_active' => true],
            ],
        ], $this->tenant->id);
    }

    public function test_variant_option_values_created(): void
    {
        $product = $this->catalogService->createProduct([
            'category_id' => $this->category->id,
            'name' => 'Test',
            'cost_price' => 1000,
            'selling_price' => 2000,
            'unit' => 'pcs',
            'has_variants' => true,
            'variant_options' => [
                ['name' => 'Size', 'values' => [['value' => 'S'], ['value' => 'M']]],
                ['name' => 'Color', 'values' => [['value' => 'Red']]],
            ],
            'variants' => [
                ['option_value_ids' => ['S', 'Red'], 'sku' => 'TEST-S-RED', 'is_active' => true],
            ],
        ], $this->tenant->id);

        $this->assertCount(2, $product->variantOptions);
        $this->assertCount(2, $product->variantOptions[0]->values);
        $this->assertCount(1, $product->variantOptions[1]->values);
        $this->assertCount(1, $product->variants);
        $this->assertCount(2, $product->variants[0]->optionValues);
    }

    public function test_generate_variants_api_endpoint(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test',
            'cost_price' => 1000,
            'selling_price' => 2000,
            'unit' => 'pcs',
            'has_variants' => true,
        ]);

        $option = ProductVariantOption::create(['product_id' => $product->id, 'name' => 'Size', 'sort_order' => 0]);
        $v1 = ProductVariantOptionValue::create(['option_id' => $option->id, 'value' => 'S', 'sort_order' => 0]);
        $v2 = ProductVariantOptionValue::create(['option_id' => $option->id, 'value' => 'M', 'sort_order' => 1]);

        $response = $this->postJson("/api/v1/products/{$product->id}/variants/generate", [
            'option_value_ids' => [[$v1->id, $v2->id]],
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'combinations');
    }
}
