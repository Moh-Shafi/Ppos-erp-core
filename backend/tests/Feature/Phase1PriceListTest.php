<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PriceListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1PriceListTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Tenant $tenant;
    private Category $category;
    private PriceListService $priceListService;

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

        $this->priceListService = app(PriceListService::class);
    }

    public function test_create_price_list(): void
    {
        $pl = $this->priceListService->createPriceList([
            'name' => 'Wholesale',
            'description' => 'Bulk pricing',
            'is_default' => false,
        ], $this->tenant->id);

        $this->assertEquals('Wholesale', $pl->name);
        $this->assertFalse($pl->is_default);
    }

    public function test_set_default_price_list_unsets_previous(): void
    {
        $pl1 = $this->priceListService->createPriceList([
            'name' => 'Retail',
            'is_default' => true,
        ], $this->tenant->id);

        $pl2 = $this->priceListService->createPriceList([
            'name' => 'Wholesale',
            'is_default' => true,
        ], $this->tenant->id);

        $this->assertFalse($pl1->fresh()->is_default);
        $this->assertTrue($pl2->fresh()->is_default);
    }

    public function test_add_price_list_item(): void
    {
        $pl = $this->priceListService->createPriceList(['name' => 'Test PL'], $this->tenant->id);

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $item = $this->priceListService->addItem($pl->id, [
            'product_id' => $product->id,
            'price' => 8000,
        ], $this->tenant->id);

        $this->assertEquals('8000.00', $item->price);
    }

    public function test_add_duplicate_item_rejected(): void
    {
        $pl = $this->priceListService->createPriceList(['name' => 'Test PL'], $this->tenant->id);

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $this->priceListService->addItem($pl->id, [
            'product_id' => $product->id,
            'price' => 8000,
        ], $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->priceListService->addItem($pl->id, [
            'product_id' => $product->id,
            'price' => 9000,
        ], $this->tenant->id);
    }

    public function test_resolve_price_from_list(): void
    {
        $pl = $this->priceListService->createPriceList(['name' => 'Test PL'], $this->tenant->id);

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $this->priceListService->addItem($pl->id, [
            'product_id' => $product->id,
            'price' => 8000,
        ], $this->tenant->id);

        $price = $this->priceListService->resolvePrice($product->id, null, $pl->id);

        $this->assertEquals('8000.00', $price);
    }

    public function test_resolve_price_fallback_to_product(): void
    {
        $pl = $this->priceListService->createPriceList(['name' => 'Test PL'], $this->tenant->id);

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        // Product not in price list → fallback to product.selling_price
        $price = $this->priceListService->resolvePrice($product->id, null, $pl->id);

        $this->assertEquals('10000.00', $price);
    }

    public function test_resolve_price_no_price_list(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $price = $this->priceListService->resolvePrice($product->id, null, null);

        $this->assertEquals('10000.00', $price);
    }

    public function test_cannot_deactivate_default_price_list(): void
    {
        $pl = $this->priceListService->createPriceList([
            'name' => 'Default PL',
            'is_default' => true,
        ], $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->priceListService->updatePriceList($pl->id, ['is_active' => false], $this->tenant->id);
    }

    public function test_delete_price_list_cascades_items(): void
    {
        $pl = $this->priceListService->createPriceList(['name' => 'Test PL'], $this->tenant->id);

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $this->priceListService->addItem($pl->id, [
            'product_id' => $product->id,
            'price' => 8000,
        ], $this->tenant->id);

        $this->priceListService->deletePriceList($pl->id, $this->tenant->id);

        $this->assertDatabaseMissing('price_lists', ['id' => $pl->id]);
        $this->assertDatabaseMissing('price_list_items', ['price_list_id' => $pl->id]);
    }

    public function test_api_create_price_list(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/v1/price-lists', [
            'name' => 'Wholesale',
            'description' => 'Bulk pricing',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('price_list.name', 'Wholesale');
    }

    public function test_api_list_price_lists(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $this->priceListService->createPriceList(['name' => 'Retail'], $this->tenant->id);

        $response = $this->getJson('/api/v1/price-lists');

        $response->assertStatus(200);
    }

    public function test_price_list_tenant_isolation(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $pl = $this->priceListService->createPriceList(['name' => 'Other PL'], $otherTenant->id);

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->getJson("/api/v1/price-lists/{$pl->id}");

        $response->assertStatus(404);
    }
}
