<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $ownerB;
    private User $managerA;
    private User $cashierA;
    private User $staffA;
    private string $tokenOwnerA;
    private string $tokenOwnerB;
    private string $tokenManagerA;
    private string $tokenCashierA;
    private string $tokenStaffA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@t.com', 'password' => 'password',
        ]);
        $this->managerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $managerRole->id,
            'name' => 'Manager A', 'email' => 'manager.a@t.com', 'password' => 'password',
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
        $this->tokenManagerA = $this->managerA->createToken('test')->plainTextToken;
        $this->tokenCashierA = $this->cashierA->createToken('test')->plainTextToken;
        $this->tokenStaffA = $this->staffA->createToken('test')->plainTextToken;

        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@t.com', 'password' => 'password',
        ]);
        $this->tokenOwnerB = $this->ownerB->createToken('test')->plainTextToken;
    }

    private function createSupplier(int $tenantId, array $overrides = []): Supplier
    {
        $s = new Supplier;
        $s->tenant_id = $tenantId;
        $s->name = $overrides['name'] ?? 'PT Supplier Jaya';
        $s->contact_person = $overrides['contact_person'] ?? 'Budi';
        $s->phone = $overrides['phone'] ?? '0211234567';
        $s->email = $overrides['email'] ?? 'budi@jaya.com';
        $s->address = $overrides['address'] ?? 'Jl. Industri 1';
        $s->tax_number = $overrides['tax_number'] ?? '01.234.567.8-901';
        $s->notes = $overrides['notes'] ?? null;
        $s->is_active = $overrides['is_active'] ?? true;
        $s->save();
        return $s;
    }

    // --- API Smoke ---

    public function test_list_suppliers(): void
    {
        $this->createSupplier($this->tenantA->id, ['name' => 'Supplier A']);
        $this->createSupplier($this->tenantA->id, ['name' => 'Supplier B']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_show_supplier(): void
    {
        $supplier = $this->createSupplier($this->tenantA->id, ['name' => 'PT Jaya']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'PT Jaya');
    }

    public function test_create_supplier(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/suppliers', [
                'name' => 'PT Maju Jaya',
                'contact_person' => 'Andi',
                'phone' => '0219876543',
                'email' => 'andi@majujaya.com',
                'address' => 'Jl. Sudirman 10',
                'tax_number' => '02.345.678.9-012',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('name', 'PT Maju Jaya');
        $response->assertJsonPath('contact_person', 'Andi');
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
    }

    public function test_update_supplier(): void
    {
        $supplier = $this->createSupplier($this->tenantA->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->putJson("/api/v1/suppliers/{$supplier->id}", [
                'name' => 'PT Updated',
                'contact_person' => 'New Person',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'PT Updated');
        $response->assertJsonPath('contact_person', 'New Person');
    }

    public function test_delete_supplier(): void
    {
        $supplier = $this->createSupplier($this->tenantA->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    // --- Search ---

    public function test_search_by_name(): void
    {
        $this->createSupplier($this->tenantA->id, ['name' => 'PT Jaya', 'email' => 'jaya@x.com', 'contact_person' => 'Andi']);
        $this->createSupplier($this->tenantA->id, ['name' => 'PT Maju', 'email' => 'maju@x.com', 'contact_person' => 'Candra']);
        $this->createSupplier($this->tenantA->id, ['name' => 'CV Sumber', 'email' => 'sumber@x.com', 'contact_person' => 'Dedi']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers?search=Jaya');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'PT Jaya');
    }

    public function test_search_by_phone(): void
    {
        $this->createSupplier($this->tenantA->id, ['phone' => '0211111111']);
        $this->createSupplier($this->tenantA->id, ['phone' => '0222222222']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers?search=02111');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_search_by_email(): void
    {
        $this->createSupplier($this->tenantA->id, ['email' => 'a@x.com']);
        $this->createSupplier($this->tenantA->id, ['email' => 'b@y.com']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers?search=a@x');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_search_by_contact_person(): void
    {
        $this->createSupplier($this->tenantA->id, ['contact_person' => 'Budi', 'email' => 'b1@x.com', 'name' => 'Sup 1']);
        $this->createSupplier($this->tenantA->id, ['contact_person' => 'Andi', 'email' => 'a1@x.com', 'name' => 'Sup 2']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers?search=Budi');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    // --- Filter ---

    public function test_filter_by_is_active(): void
    {
        $this->createSupplier($this->tenantA->id, ['is_active' => true]);
        $this->createSupplier($this->tenantA->id, ['is_active' => false]);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers?is_active=true');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.is_active', true);
    }

    // --- Pagination ---

    public function test_pagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createSupplier($this->tenantA->id, ['name' => "Supplier {$i}"]);
        }

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers?per_page=10&page=1');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('total', 25);
        $response->assertJsonPath('last_page', 3);
    }

    // --- Authentication ---

    public function test_unauthenticated_list(): void
    {
        $response = $this->getJson('/api/v1/suppliers');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_create(): void
    {
        $response = $this->postJson('/api/v1/suppliers', ['name' => 'Test']);
        $response->assertStatus(401);
    }

    // --- Tenant Isolation ---

    public function test_tenant_a_cannot_see_tenant_b_suppliers(): void
    {
        $this->createSupplier($this->tenantB->id, ['name' => 'Secret B']);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_cannot_access_tenant_b_supplier(): void
    {
        $supplierB = $this->createSupplier($this->tenantB->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/suppliers/{$supplierB->id}");

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_update_tenant_b_supplier(): void
    {
        $supplierB = $this->createSupplier($this->tenantB->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->putJson("/api/v1/suppliers/{$supplierB->id}", ['name' => 'Hacked']);

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_delete_tenant_b_supplier(): void
    {
        $supplierB = $this->createSupplier($this->tenantB->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/suppliers/{$supplierB->id}");

        $response->assertStatus(404);
    }

    // --- IDOR ---

    public function test_idor_get_nonexistent_returns_404(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers/99999');

        $response->assertStatus(404);
    }

    public function test_idor_delete_nonexistent_returns_404(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson('/api/v1/suppliers/99999');

        $response->assertStatus(404);
    }

    // --- RBAC ---

    public function test_owner_can_view_suppliers(): void
    {
        $this->createSupplier($this->tenantA->id);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(200);
    }

    public function test_owner_can_create_supplier(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/suppliers', ['name' => 'New Supplier']);

        $response->assertStatus(201);
    }

    public function test_manager_can_view_suppliers(): void
    {
        $this->createSupplier($this->tenantA->id);

        $response = $this->withToken($this->tokenManagerA)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(200);
    }

    public function test_manager_can_create_supplier(): void
    {
        $response = $this->withToken($this->tokenManagerA)
            ->postJson('/api/v1/suppliers', ['name' => 'Manager Supplier']);

        $response->assertStatus(201);
    }

    public function test_cashier_can_view_suppliers(): void
    {
        $this->createSupplier($this->tenantA->id);

        $response = $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(200);
    }

    public function test_cashier_cannot_create_supplier(): void
    {
        $response = $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/suppliers', ['name' => 'Cashier Supplier']);

        $response->assertStatus(403);
    }

    public function test_cashier_cannot_update_supplier(): void
    {
        $supplier = $this->createSupplier($this->tenantA->id);

        $response = $this->withToken($this->tokenCashierA)
            ->putJson("/api/v1/suppliers/{$supplier->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_cashier_cannot_delete_supplier(): void
    {
        $supplier = $this->createSupplier($this->tenantA->id);

        $response = $this->withToken($this->tokenCashierA)
            ->deleteJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(403);
    }

    public function test_staff_cannot_view_suppliers(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(403);
    }

    public function test_staff_cannot_create_supplier(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/suppliers', ['name' => 'Staff Supplier']);

        $response->assertStatus(403);
    }

    // --- Mass Assignment ---

    public function test_tenant_id_ignored_from_request(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/suppliers', [
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
            ->postJson('/api/v1/suppliers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_create_invalid_email(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/suppliers', [
                'name' => 'Test',
                'email' => 'not-an-email',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_create_name_too_long(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/suppliers', [
                'name' => str_repeat('x', 256),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_create_tax_number_too_long(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/suppliers', [
                'name' => 'Test',
                'tax_number' => str_repeat('x', 101),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_number']);
    }

    // --- Model ---

    public function test_supplier_belongs_to_tenant(): void
    {
        $s = $this->createSupplier($this->tenantA->id);
        $this->assertInstanceOf(Tenant::class, $s->tenant);
        $this->assertEquals($this->tenantA->id, $s->tenant->id);
    }

    public function test_supplier_tenant_id_not_mass_assignable(): void
    {
        $s = new Supplier;
        $s->fill([
            'name' => 'Test',
            'tenant_id' => $this->tenantB->id,
        ]);
        $this->assertNull($s->tenant_id);
    }

    public function test_supplier_is_active_cast_to_boolean(): void
    {
        $s = $this->createSupplier($this->tenantA->id);
        $this->assertIsBool($s->is_active);
    }
}
