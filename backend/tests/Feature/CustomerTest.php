<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $ownerB;
    private User $cashierA;
    private User $staffA;
    private string $tokenOwnerA;
    private string $tokenOwnerB;
    private string $tokenCashierA;
    private string $tokenStaffA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@t.com', 'password' => 'password',
        ]);
        $this->cashierA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier A', 'email' => 'cashier.a@t.com', 'password' => 'password',
        ]);
        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $staffRole->id,
            'name' => 'Staff A', 'email' => 'staff.a@t.com', 'password' => 'password',
        ]);

        $this->tokenOwnerA = $this->ownerA->createToken('test')->plainTextToken;
        $this->tokenCashierA = $this->cashierA->createToken('test')->plainTextToken;
        $this->tokenStaffA = $this->staffA->createToken('test')->plainTextToken;

        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@t.com', 'password' => 'password',
        ]);
        $this->tokenOwnerB = $this->ownerB->createToken('test')->plainTextToken;
    }

    private function createCustomer(int $tenantId, array $overrides = []): Customer
    {
        $c = new Customer;
        $c->tenant_id = $tenantId;
        $c->name = $overrides['name'] ?? 'John Doe';
        $c->phone = $overrides['phone'] ?? '08123456789';
        $c->email = $overrides['email'] ?? 'john@example.com';
        $c->address = $overrides['address'] ?? 'Jl. Sudirman 1';
        $c->notes = $overrides['notes'] ?? null;
        $c->is_active = $overrides['is_active'] ?? true;
        $c->save();
        return $c;
    }

    // --- API Smoke ---

    public function test_list_customers(): void
    {
        $this->createCustomer($this->tenantA->id, ['name' => 'Alice']);
        $this->createCustomer($this->tenantA->id, ['name' => 'Bob']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/customers');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_show_customer(): void
    {
        $customer = $this->createCustomer($this->tenantA->id, ['name' => 'Alice']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Alice');
    }

    public function test_create_customer(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/customers', [
                'name' => 'Charlie',
                'phone' => '08987654321',
                'email' => 'charlie@example.com',
                'address' => 'Jl. Gatot Subroto',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('name', 'Charlie');
        $response->assertJsonPath('phone', '08987654321');
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
    }

    public function test_update_customer(): void
    {
        $customer = $this->createCustomer($this->tenantA->id, ['name' => 'Alice']);

        $response = $this->withToken($this->tokenOwnerA)
            ->putJson("/api/v1/customers/{$customer->id}", [
                'name' => 'Alice Updated',
                'phone' => '08111111111',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Alice Updated');
        $response->assertJsonPath('phone', '08111111111');
    }

    public function test_delete_customer(): void
    {
        $customer = $this->createCustomer($this->tenantA->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    // --- Search ---

    public function test_search_by_name(): void
    {
        $this->createCustomer($this->tenantA->id, ['name' => 'Alice']);
        $this->createCustomer($this->tenantA->id, ['name' => 'Bob']);
        $this->createCustomer($this->tenantA->id, ['name' => 'Alice Cooper']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/customers?search=Alice');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_search_by_phone(): void
    {
        $this->createCustomer($this->tenantA->id, ['name' => 'Alice', 'phone' => '08111111111']);
        $this->createCustomer($this->tenantA->id, ['name' => 'Bob', 'phone' => '08222222222']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/customers?search=08111');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alice');
    }

    // --- Filter ---

    public function test_filter_by_is_active(): void
    {
        $this->createCustomer($this->tenantA->id, ['name' => 'Active', 'is_active' => true]);
        $this->createCustomer($this->tenantA->id, ['name' => 'Inactive', 'is_active' => false]);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/customers?is_active=true');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Active');
    }

    // --- Pagination ---

    public function test_pagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createCustomer($this->tenantA->id, ['name' => "Customer {$i}"]);
        }

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/customers?per_page=10&page=1');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('total', 25);
        $response->assertJsonPath('last_page', 3);
    }

    // --- Authentication ---

    public function test_unauthenticated_list(): void
    {
        $response = $this->getJson('/api/v1/customers');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_create(): void
    {
        $response = $this->postJson('/api/v1/customers', ['name' => 'Test']);
        $response->assertStatus(401);
    }

    // --- Tenant Isolation ---

    public function test_tenant_a_cannot_see_tenant_b_customers(): void
    {
        $this->createCustomer($this->tenantB->id, ['name' => 'Secret B']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/customers');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_cannot_access_tenant_b_customer(): void
    {
        $customerB = $this->createCustomer($this->tenantB->id, ['name' => 'Secret B']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/customers/{$customerB->id}");

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_update_tenant_b_customer(): void
    {
        $customerB = $this->createCustomer($this->tenantB->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->putJson("/api/v1/customers/{$customerB->id}", ['name' => 'Hacked']);

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_delete_tenant_b_customer(): void
    {
        $customerB = $this->createCustomer($this->tenantB->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/customers/{$customerB->id}");

        $response->assertStatus(404);
    }

    // --- IDOR ---

    public function test_idor_get_nonexistent_customer_returns_404(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/customers/99999');

        $response->assertStatus(404);
    }

    public function test_idor_delete_nonexistent_customer_returns_404(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson('/api/v1/customers/99999');

        $response->assertStatus(404);
    }

    // --- RBAC ---

    public function test_cashier_can_view_customers(): void
    {
        $this->createCustomer($this->tenantA->id);

        $response = $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/customers');

        $response->assertStatus(200);
    }

    public function test_cashier_can_create_customer(): void
    {
        $response = $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/customers', [
                'name' => 'Cashier Customer',
                'phone' => '08123456789',
            ]);

        $response->assertStatus(201);
    }

    public function test_staff_can_view_customers(): void
    {
        $this->createCustomer($this->tenantA->id);

        $response = $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/customers');

        $response->assertStatus(200);
    }

    public function test_staff_cannot_create_customer(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/customers', [
                'name' => 'Staff Customer',
            ]);

        $response->assertStatus(403);
    }

    public function test_staff_cannot_update_customer(): void
    {
        $customer = $this->createCustomer($this->tenantA->id);

        $response = $this->withToken($this->tokenStaffA)
            ->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_staff_cannot_delete_customer(): void
    {
        $customer = $this->createCustomer($this->tenantA->id);

        $response = $this->withToken($this->tokenStaffA)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(403);
    }

    // --- Mass Assignment ---

    public function test_tenant_id_ignored_from_request(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/customers', [
                'name' => 'Test',
                'tenant_id' => $this->tenantB->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
    }

    // --- Validation ---

    public function test_create_missing_name(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/customers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_create_invalid_email(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/customers', [
                'name' => 'Test',
                'email' => 'not-an-email',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_create_name_too_long(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/customers', [
                'name' => str_repeat('x', 256),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    // --- Model ---

    public function test_customer_belongs_to_tenant(): void
    {
        $c = $this->createCustomer($this->tenantA->id);
        $this->assertInstanceOf(Tenant::class, $c->tenant);
        $this->assertEquals($this->tenantA->id, $c->tenant->id);
    }

    public function test_customer_tenant_id_not_mass_assignable(): void
    {
        $c = new Customer;
        $c->fill([
            'name' => 'Test',
            'tenant_id' => $this->tenantB->id,
        ]);
        $this->assertNull($c->tenant_id);
    }

    public function test_customer_is_active_cast_to_boolean(): void
    {
        $c = $this->createCustomer($this->tenantA->id);
        $this->assertIsBool($c->is_active);
    }
}
