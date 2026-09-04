<?php

namespace Tests\Feature;

use App\Models\IntegrationApiKey;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $ownerB;
    private string $tokenOwnerA;
    private string $tokenOwnerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();

        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@t.com', 'password' => 'password',
        ]);
        $this->tokenOwnerA = $this->ownerA->createToken('test')->plainTextToken;

        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@t.com', 'password' => 'password',
        ]);
        $this->tokenOwnerB = $this->ownerB->createToken('test')->plainTextToken;

        $module = \App\Models\Module::where('slug', 'integrations')->first();
        \App\Models\TenantModule::create([
            'tenant_id' => $this->tenantA->id, 'module_id' => $module->id, 'is_enabled' => true,
        ]);
        \App\Models\TenantModule::create([
            'tenant_id' => $this->tenantB->id, 'module_id' => $module->id, 'is_enabled' => true,
        ]);
    }

    public function test_generate_api_key(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/api-keys', [
                'name' => 'My API Key',
                'scopes' => ['read', 'write'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'name', 'key', 'key_prefix', 'scopes']);
        $this->assertStringStartsWith('itg_', $response->json('key'));
        $this->assertNotEmpty($response->json('key'));
    }

    public function test_list_api_keys(): void
    {
        $service = app(ApiKeyService::class);
        $service->generate($this->tenantA->id, 'Test Key', ['read']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/api-keys');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_api_key_not_exposed_in_list(): void
    {
        $service = app(ApiKeyService::class);
        $service->generate($this->tenantA->id, 'Test Key', ['read']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/api-keys');

        $response->assertStatus(200);
        $json = $response->json('data.0');
        $this->assertArrayNotHasKey('key_hash', $json);
    }

    public function test_revoke_api_key(): void
    {
        $service = app(ApiKeyService::class);
        $keyData = $service->generate($this->tenantA->id, 'Revoke Test', ['read']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->deleteJson('/api/v1/api-keys/' . $keyData['id']);

        $response->assertStatus(204);
        $this->assertDatabaseHas('integration_api_keys', [
            'id' => $keyData['id'],
            'is_revoked' => true,
        ]);
    }

    public function test_rotate_api_key(): void
    {
        $service = app(ApiKeyService::class);
        $keyData = $service->generate($this->tenantA->id, 'Rotate Test', ['read']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/api-keys/' . $keyData['id'] . '/rotate');

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'key']);
        $this->assertNotEquals($keyData['key'], $response->json('key'));
    }

    public function test_integration_api_access_with_valid_key(): void
    {
        $service = app(ApiKeyService::class);
        $keyData = $service->generate($this->tenantA->id, 'API Access Test', ['read']);

        $response = $this->withHeader('X-Integration-Key', $keyData['key'])
            ->getJson('/api/v1/v1/integration/sales');

        $response->assertStatus(200);
    }

    public function test_integration_api_access_without_key(): void
    {
        $response = $this->getJson('/api/v1/v1/integration/sales');
        $response->assertStatus(401);
    }

    public function test_integration_api_access_with_invalid_key(): void
    {
        $response = $this->withHeader('X-Integration-Key', 'itg_invalid_key_123456789012345678901234567890')
            ->getJson('/api/v1/v1/integration/sales');

        $response->assertStatus(401);
    }

    public function test_integration_api_access_with_revoked_key(): void
    {
        $service = app(ApiKeyService::class);
        $keyData = $service->generate($this->tenantA->id, 'Revoked Access Test', ['read']);
        $key = IntegrationApiKey::withoutTenantScope()->find($keyData['id']);
        $service->revoke($key);

        $response = $this->withHeader('X-Integration-Key', $keyData['key'])
            ->getJson('/api/v1/v1/integration/sales');

        $response->assertStatus(401);
    }

    public function test_cross_tenant_api_key_isolation(): void
    {
        $service = app(ApiKeyService::class);
        $keyDataA = $service->generate($this->tenantA->id, 'Tenant A Key', ['read']);

        $response = $this->withHeader('X-Integration-Key', $keyDataA['key'])
            ->getJson('/api/v1/v1/integration/sales');

        $response->assertStatus(200);
        $json = $response->json('data');
        foreach ($json as $sale) {
            $this->assertEquals($this->tenantA->id, $sale['tenant_id']);
        }
    }

    public function test_unauthenticated_access_blocked(): void
    {
        $response = $this->getJson('/api/v1/api-keys');
        $response->assertStatus(401);
    }
}
