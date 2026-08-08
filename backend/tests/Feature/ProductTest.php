<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private string $tokenA;
    private string $tokenB;
    private Category $catA;
    private Category $catB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();

        // Tenant A
        $tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->userA = User::create([
            'tenant_id' => $tenantA->id,
            'role_id' => $ownerRole->id,
            'name' => 'User A',
            'email' => 'a@test.com',
            'password' => 'password',
        ]);
        $storeA = new Store;
        $storeA->tenant_id = $tenantA->id;
        $storeA->name = 'Store A';
        $storeA->code = 'STR-001';
        $storeA->is_active = true;
        $storeA->save();
        $this->tokenA = $this->userA->createToken('test')->plainTextToken;

        $this->catA = new Category;
        $this->catA->tenant_id = $tenantA->id;
        $this->catA->name = 'Minuman';
        $this->catA->slug = 'minuman';
        $this->catA->save();

        // Tenant B
        $tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->userB = User::create([
            'tenant_id' => $tenantB->id,
            'role_id' => $ownerRole->id,
            'name' => 'User B',
            'email' => 'b@test.com',
            'password' => 'password',
        ]);
        $storeB = new Store;
        $storeB->tenant_id = $tenantB->id;
        $storeB->name = 'Store B';
        $storeB->code = 'STR-001';
        $storeB->is_active = true;
        $storeB->save();
        $this->tokenB = $this->userB->createToken('test')->plainTextToken;

        $this->catB = new Category;
        $this->catB->tenant_id = $tenantB->id;
        $this->catB->name = 'Elektronik';
        $this->catB->slug = 'elektronik';
        $this->catB->save();
    }

    private function createProductForTenant(int $tenantId, int $categoryId, array $overrides = []): Product
    {
        $product = new Product;
        $product->tenant_id = $tenantId;
        $product->category_id = $categoryId;
        $product->name = $overrides['name'] ?? 'Aqua 600ml';
        $product->sku = $overrides['sku'] ?? 'AQUA-600';
        $product->barcode = $overrides['barcode'] ?? '8992761141234';
        $product->cost_price = $overrides['cost_price'] ?? 2500;
        $product->selling_price = $overrides['selling_price'] ?? 4000;
        $product->unit = $overrides['unit'] ?? 'botol';
        $product->is_active = $overrides['is_active'] ?? true;
        $product->save();
        return $product;
    }

    // --- CRUD ---

    public function test_create_product(): void
    {
        $response = $this->withToken($this->tokenA)
            ->postJson('/api/v1/products', [
                'category_id' => $this->catA->id,
                'name' => 'Aqua 600ml',
                'sku' => 'AQUA-600',
                'barcode' => '8992761141234',
                'cost_price' => 2500,
                'selling_price' => 4000,
                'unit' => 'botol',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('product.tenant_id', $this->userA->tenant_id);
        $response->assertJsonPath('product.name', 'Aqua 600ml');
        $response->assertJsonPath('product.category.name', 'Minuman');
    }

    public function test_list_products(): void
    {
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Aqua']);
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Indomie', 'sku' => 'IND-01', 'barcode' => '8991']);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/products');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_show_product(): void
    {
        $product = $this->createProductForTenant($this->userA->tenant_id, $this->catA->id);

        $response = $this->withToken($this->tokenA)
            ->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('product.name', 'Aqua 600ml');
    }

    public function test_update_product(): void
    {
        $product = $this->createProductForTenant($this->userA->tenant_id, $this->catA->id);

        $response = $this->withToken($this->tokenA)
            ->putJson("/api/v1/products/{$product->id}", [
                'name' => 'Aqua 1000ml',
                'selling_price' => 6000,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('product.name', 'Aqua 1000ml');
        $response->assertJsonPath('product.selling_price', '6000.00');
    }

    public function test_delete_product(): void
    {
        $product = $this->createProductForTenant($this->userA->tenant_id, $this->catA->id);

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    // --- Tenant Isolation ---

    public function test_tenant_a_cannot_see_tenant_b_products(): void
    {
        $this->createProductForTenant($this->userB->tenant_id, $this->catB->id, ['name' => 'TV']);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/products');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_cannot_access_tenant_b_product(): void
    {
        $productB = $this->createProductForTenant($this->userB->tenant_id, $this->catB->id, ['name' => 'TV']);

        $response = $this->withToken($this->tokenA)
            ->getJson("/api/v1/products/{$productB->id}");

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_update_tenant_b_product(): void
    {
        $productB = $this->createProductForTenant($this->userB->tenant_id, $this->catB->id, ['name' => 'TV']);

        $response = $this->withToken($this->tokenA)
            ->putJson("/api/v1/products/{$productB->id}", ['name' => 'Hacked']);

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_delete_tenant_b_product(): void
    {
        $productB = $this->createProductForTenant($this->userB->tenant_id, $this->catB->id, ['name' => 'TV']);

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/v1/products/{$productB->id}");

        $response->assertStatus(404);
    }

    // --- Category must belong to same tenant ---

    public function test_cannot_create_product_with_category_from_another_tenant(): void
    {
        $response = $this->withToken($this->tokenA)
            ->postJson('/api/v1/products', [
                'category_id' => $this->catB->id,
                'name' => 'Test',
                'cost_price' => 1000,
                'selling_price' => 2000,
                'unit' => 'pcs',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category_id']);
    }

    // --- SKU uniqueness within tenant ---

    public function test_duplicate_sku_rejected_within_tenant(): void
    {
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['sku' => 'DUP-001']);

        $response = $this->withToken($this->tokenA)
            ->postJson('/api/v1/products', [
                'category_id' => $this->catA->id,
                'name' => 'Another',
                'sku' => 'DUP-001',
                'cost_price' => 1000,
                'selling_price' => 2000,
                'unit' => 'pcs',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sku']);
    }

    public function test_same_sku_allowed_in_another_tenant(): void
    {
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['sku' => 'SHARED-001']);

        $response = $this->withToken($this->tokenB)
            ->postJson('/api/v1/products', [
                'category_id' => $this->catB->id,
                'name' => 'B Product',
                'sku' => 'SHARED-001',
                'cost_price' => 1000,
                'selling_price' => 2000,
                'unit' => 'pcs',
            ]);

        $response->assertStatus(201);
    }

    // --- Barcode uniqueness within tenant ---

    public function test_duplicate_barcode_rejected_within_tenant(): void
    {
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['barcode' => '1234567890']);

        $response = $this->withToken($this->tokenA)
            ->postJson('/api/v1/products', [
                'category_id' => $this->catA->id,
                'name' => 'Another',
                'barcode' => '1234567890',
                'cost_price' => 1000,
                'selling_price' => 2000,
                'unit' => 'pcs',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['barcode']);
    }

    public function test_same_barcode_allowed_in_another_tenant(): void
    {
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['barcode' => '999999']);

        $response = $this->withToken($this->tokenB)
            ->postJson('/api/v1/products', [
                'category_id' => $this->catB->id,
                'name' => 'B Product',
                'barcode' => '999999',
                'cost_price' => 1000,
                'selling_price' => 2000,
                'unit' => 'pcs',
            ]);

        $response->assertStatus(201);
    }

    // --- Search + Pagination + Filter ---

    public function test_search_by_name(): void
    {
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Aqua 600ml']);
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Indomie', 'sku' => 'IND-01', 'barcode' => '8991']);
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Teh Botol', 'sku' => 'TEH-01', 'barcode' => '8992']);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/products?search=aqua');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Aqua 600ml');
    }

    public function test_search_by_sku(): void
    {
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Aqua', 'sku' => 'AQUA-01']);
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Indomie', 'sku' => 'IND-01', 'barcode' => '8991']);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/products?search=IND');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Indomie');
    }

    public function test_filter_by_category(): void
    {
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Aqua']);
        $catA2 = new Category;
        $catA2->tenant_id = $this->userA->tenant_id;
        $catA2->name = 'Makanan';
        $catA2->slug = 'makanan';
        $catA2->save();
        $this->createProductForTenant($this->userA->tenant_id, $catA2->id, ['name' => 'Indomie', 'sku' => 'IND-01', 'barcode' => '8991']);

        $response = $this->withToken($this->tokenA)
            ->getJson("/api/v1/products?category_id={$this->catA->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Aqua');
    }

    public function test_filter_by_is_active(): void
    {
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Active', 'is_active' => true]);
        $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, ['name' => 'Inactive', 'sku' => 'INACT', 'barcode' => '0000', 'is_active' => false]);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/products?is_active=1');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Active');
    }

    public function test_pagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createProductForTenant($this->userA->tenant_id, $this->catA->id, [
                'name' => "Product {$i}",
                'sku' => "SKU-{$i}",
                'barcode' => "BC-{$i}",
            ]);
        }

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/products?per_page=10&page=1');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('total', 25);
        $response->assertJsonPath('last_page', 3);
    }

    // --- tenant_id cannot be supplied by request ---

    public function test_tenant_id_ignored_from_request(): void
    {
        $response = $this->withToken($this->tokenA)
            ->postJson('/api/v1/products', [
                'tenant_id' => $this->userB->tenant_id,
                'category_id' => $this->catA->id,
                'name' => 'Hacked',
                'cost_price' => 1000,
                'selling_price' => 2000,
                'unit' => 'pcs',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('product.tenant_id', $this->userA->tenant_id);
    }

    // --- Auth ---

    public function test_unauthenticated_access_denied(): void
    {
        $response = $this->getJson('/api/v1/products');
        $response->assertStatus(401);
    }
}
