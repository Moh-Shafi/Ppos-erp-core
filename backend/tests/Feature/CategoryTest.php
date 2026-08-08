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

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private string $tokenA;
    private string $tokenB;

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
    }

    public function test_tenant_a_cannot_see_tenant_b_categories(): void
    {
        $cat = new Category;
        $cat->tenant_id = $this->userB->tenant_id;
        $cat->name = 'Elektronik';
        $cat->slug = 'elektronik';
        $cat->save();

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/categories');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_cannot_access_tenant_b_category(): void
    {
        $cat = new Category;
        $cat->tenant_id = $this->userB->tenant_id;
        $cat->name = 'Elektronik';
        $cat->slug = 'elektronik';
        $cat->save();

        $response = $this->withToken($this->tokenA)
            ->getJson("/api/v1/categories/{$cat->id}");

        $response->assertStatus(404);
    }

    public function test_create_category_auto_sets_tenant_id(): void
    {
        $response = $this->withToken($this->tokenA)
            ->postJson('/api/v1/categories', [
                'name' => 'Minuman',
                'description' => 'Minuman segar',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('category.tenant_id', $this->userA->tenant_id);
        $response->assertJsonPath('category.slug', 'minuman');
    }

    public function test_tenant_a_cannot_update_tenant_b_category(): void
    {
        $cat = new Category;
        $cat->tenant_id = $this->userB->tenant_id;
        $cat->name = 'Elektronik';
        $cat->slug = 'elektronik';
        $cat->save();

        $response = $this->withToken($this->tokenA)
            ->putJson("/api/v1/categories/{$cat->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_delete_tenant_b_category(): void
    {
        $cat = new Category;
        $cat->tenant_id = $this->userB->tenant_id;
        $cat->name = 'Elektronik';
        $cat->slug = 'elektronik';
        $cat->save();

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/v1/categories/{$cat->id}");

        $response->assertStatus(404);
    }

    public function test_search_and_pagination(): void
    {
        $names = ['Minuman', 'Makanan', 'Snack'];
        foreach ($names as $name) {
            $cat = new Category;
            $cat->tenant_id = $this->userA->tenant_id;
            $cat->name = $name;
            $cat->slug = str()->slug($name);
            $cat->save();
        }

        // Search "ma" matches Minuman and Makanan
        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/categories?search=ma');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $cat = new Category;
        $cat->tenant_id = $this->userA->tenant_id;
        $cat->name = 'Minuman';
        $cat->slug = 'minuman';
        $cat->save();

        $product = new Product;
        $product->tenant_id = $this->userA->tenant_id;
        $product->category_id = $cat->id;
        $product->name = 'Aqua';
        $product->sku = 'AQUA-01';
        $product->barcode = '8991';
        $product->cost_price = 2500;
        $product->selling_price = 4000;
        $product->unit = 'botol';
        $product->save();

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/v1/categories/{$cat->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot delete category with existing products');
    }

    public function test_unauthenticated_access_denied(): void
    {
        $response = $this->getJson('/api/v1/categories');
        $response->assertStatus(401);
    }
}
