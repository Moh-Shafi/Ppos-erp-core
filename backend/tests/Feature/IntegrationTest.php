<?php

namespace Tests\Feature;

use App\Models\IntegrationApiKey;
use App\Models\IntegrationProvider;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationTest extends TestCase
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
        $this->seed(\Database\Seeders\IntegrationProviderSeeder::class);

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

    public function test_list_providers(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/integrations/providers');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'slug', 'name']]]);
    }

    public function test_create_integration(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/integrations', [
                'provider_slug' => 'generic_http',
                'name' => 'My ERP Sync',
                'config' => ['base_url' => 'https://api.example.com'],
                'credentials' => ['api_key' => 'secret123'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'My ERP Sync');
        $response->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseHas('tenant_integrations', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'My ERP Sync',
        ]);
    }

    public function test_create_integration_invalid_provider(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/integrations', [
                'provider_slug' => 'nonexistent',
                'name' => 'Test',
            ]);

        $response->assertStatus(422);
    }

    public function test_list_integrations(): void
    {
        $service = app(IntegrationService::class);
        $service->create($this->tenantA->id, 'generic_http', 'Test Integration');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/integrations');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_show_integration(): void
    {
        $service = app(IntegrationService::class);
        $integration = $service->create($this->tenantA->id, 'generic_http', 'Show Test');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/integrations/' . $integration->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Show Test');
    }

    public function test_update_integration_config(): void
    {
        $service = app(IntegrationService::class);
        $integration = $service->create($this->tenantA->id, 'generic_http', 'Update Test');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->putJson('/api/v1/integrations/' . $integration->id, [
                'name' => 'Updated Name',
                'config' => ['base_url' => 'https://new.example.com'],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_activate_deactivate_integration(): void
    {
        $service = app(IntegrationService::class);
        $integration = $service->create($this->tenantA->id, 'generic_http', 'Activate Test');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/integrations/' . $integration->id . '/activate');
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'active');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/integrations/' . $integration->id . '/deactivate');
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'inactive');
    }

    public function test_delete_integration(): void
    {
        $service = app(IntegrationService::class);
        $integration = $service->create($this->tenantA->id, 'generic_http', 'Delete Test');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->deleteJson('/api/v1/integrations/' . $integration->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('tenant_integrations', ['id' => $integration->id]);
    }

    public function test_cross_tenant_isolation(): void
    {
        $service = app(IntegrationService::class);
        $integration = $service->create($this->tenantA->id, 'generic_http', 'Tenant A Integration');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerB)
            ->getJson('/api/v1/integrations/' . $integration->id);

        $response->assertStatus(404);
    }

    public function test_credentials_encrypted(): void
    {
        $service = app(IntegrationService::class);
        $integration = $service->create(
            $this->tenantA->id,
            'generic_http',
            'Cred Test',
            [],
            ['api_key' => 'super-secret-key'],
        );

        $this->assertNotEmpty($integration->encrypted_credentials);
        $this->assertNotEquals('super-secret-key', $integration->encrypted_credentials);

        $credentials = $service->getCredentials($integration);
        $this->assertEquals('super-secret-key', $credentials['api_key']);
    }

    public function test_credentials_not_exposed_in_api(): void
    {
        $service = app(IntegrationService::class);
        $integration = $service->create(
            $this->tenantA->id,
            'generic_http',
            'Cred API Test',
            [],
            ['api_key' => 'super-secret-key'],
        );

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/integrations/' . $integration->id);

        $response->assertStatus(200);
        $json = $response->json('data');
        $this->assertArrayNotHasKey('encrypted_credentials', $json);
    }

    public function test_health_endpoint(): void
    {
        $service = app(IntegrationService::class);
        $service->create($this->tenantA->id, 'generic_http', 'Health Test');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/integrations/health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_unauthenticated_access_blocked(): void
    {
        $response = $this->getJson('/api/v1/integrations');
        $response->assertStatus(401);
    }
}
