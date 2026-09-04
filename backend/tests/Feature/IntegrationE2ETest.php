<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\IntegrationService;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationE2ETest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $this->seed(\Database\Seeders\IntegrationProviderSeeder::class);
        $this->seed(\Database\Seeders\WebhookEventSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();

        $this->tenant = Tenant::create(['name' => 'E2E Integration Toko', 'slug' => 'e2e-int-toko']);
        $this->owner = User::create([
            'tenant_id' => $this->tenant->id, 'role_id' => $ownerRole->id,
            'name' => 'E2E Owner', 'email' => 'e2e.int@t.com', 'password' => 'password',
        ]);
        $this->token = $this->owner->createToken('test')->plainTextToken;

        $module = \App\Models\Module::where('slug', 'integrations')->first();
        \App\Models\TenantModule::create([
            'tenant_id' => $this->tenant->id, 'module_id' => $module->id, 'is_enabled' => true,
        ]);
    }

    public function test_e2e_full_integration_flow(): void
    {
        // Step 1: List available providers
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/integrations/providers');
        $response->assertStatus(200);
        $providers = $response->json('data');
        $this->assertNotEmpty($providers);

        // Step 2: Create an integration
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/integrations', [
                'provider_slug' => 'generic_http',
                'name' => 'E2E ERP Sync',
                'config' => ['base_url' => 'https://erp.example.com'],
                'credentials' => ['api_key' => 'e2e-secret-key'],
            ]);
        $response->assertStatus(201);
        $integrationId = $response->json('data.id');

        // Step 3: Verify credentials are encrypted
        $integration = \App\Models\TenantIntegration::withoutTenantScope()->find($integrationId);
        $this->assertNotEquals('e2e-secret-key', $integration->encrypted_credentials);

        // Step 4: Activate the integration
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/integrations/{$integrationId}/activate");
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'active');

        // Step 5: List webhook events
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/webhooks/events');
        $response->assertStatus(200);
        $events = $response->json('data');
        $this->assertNotEmpty($events);

        // Step 6: Create a webhook endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/webhooks/endpoints', [
                'name' => 'E2E Webhook',
                'url' => 'https://hook.example.com/receive',
                'events' => ['sale.created', 'payment.received'],
                'description' => 'E2E test endpoint',
            ]);
        $response->assertStatus(201);
        $endpointId = $response->json('endpoint.id');
        $secret = $response->json('secret');
        $this->assertStringStartsWith('whsec_', $secret);

        // Step 7: Add a subscription
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/webhooks/endpoints/{$endpointId}/subscriptions", [
                'event_type' => 'inventory.low_stock',
            ]);
        $response->assertStatus(201);

        // Step 8: Generate an API key
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/api-keys', [
                'name' => 'E2E External Key',
                'scopes' => ['read', 'write'],
            ]);
        $response->assertStatus(201);
        $apiKeyId = $response->json('id');
        $apiKey = $response->json('key');
        $this->assertStringStartsWith('itg_', $apiKey);

        // Step 9: Use the API key to access the external integration API
        $response = $this->withHeader('X-Integration-Key', $apiKey)
            ->getJson('/api/v1/v1/integration/sales');
        $response->assertStatus(200);

        // Step 10: Rotate the API key
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/api-keys/{$apiKeyId}/rotate");
        $response->assertStatus(201);
        $newApiKey = $response->json('key');
        $newApiKeyId = $response->json('id');
        $this->assertNotEquals($apiKey, $newApiKey);

        // Step 11: Old key should no longer work
        $response = $this->withHeader('X-Integration-Key', $apiKey)
            ->getJson('/api/v1/v1/integration/sales');
        $response->assertStatus(401);

        // Step 12: New key should work
        $response = $this->withHeader('X-Integration-Key', $newApiKey)
            ->getJson('/api/v1/v1/integration/sales');
        $response->assertStatus(200);

        // Step 13: Check webhook stats
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/webhooks/stats?period=24h');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'period', 'total', 'delivered', 'failed', 'pending', 'dead_lettered', 'success_rate', 'avg_latency_ms',
        ]);

        // Step 14: Check integration health
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/integrations/health');
        $response->assertStatus(200);

        // Step 15: Deactivate integration
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/integrations/{$integrationId}/deactivate");
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'inactive');

        // Step 16: Revoke rotated API key
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/api-keys/{$newApiKeyId}");
        $response->assertStatus(204);

        // Step 17: Revoked key should not work
        $response = $this->withHeader('X-Integration-Key', $newApiKey)
            ->getJson('/api/v1/v1/integration/sales');
        $response->assertStatus(401);

        // Step 18: Delete webhook endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/webhooks/endpoints/{$endpointId}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpointId]);

        // Step 19: Delete integration
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/integrations/{$integrationId}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('tenant_integrations', ['id' => $integrationId]);
    }

    public function test_e2e_webhook_delivery_retry_flow(): void
    {
        // Create webhook endpoint
        $service = app(WebhookService::class);
        $result = $service->createEndpoint($this->tenant->id, 'Retry Test', 'https://example.com/wh', ['sale.created']);
        $endpoint = $result['endpoint'];

        // Create a failed delivery
        $delivery = \App\Models\WebhookDelivery::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'sale.created',
            'event_id' => \Illuminate\Support\Str::uuid(),
            'payload' => ['event' => 'sale.created', 'data' => ['id' => 1]],
            'signature' => 'test-sig',
            'status' => 'failed',
            'attempt_count' => 5,
            'error_message' => 'Connection timeout',
        ]);

        // Replay the failed delivery
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/webhooks/deliveries/{$delivery->id}/replay");
        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.original_delivery_id', $delivery->id);

        // Verify original delivery is marked as replayed
        $delivery->refresh();
        $this->assertEquals('replayed', $delivery->status);
    }

    public function test_e2e_ssrf_protection(): void
    {
        $ssrfUrls = [
            'http://127.0.0.1:8080/internal',
            'http://169.254.169.254/latest/meta-data',
            'http://10.0.0.1/admin',
            'http://192.168.1.1/config',
            'http://0.0.0.0/',
        ];

        foreach ($ssrfUrls as $url) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/v1/webhooks/endpoints', [
                    'name' => 'SSRF Test',
                    'url' => $url,
                    'events' => ['sale.created'],
                ]);
            $response->assertStatus(422);
        }
    }

    public function test_e2e_credential_isolation(): void
    {
        // Create integration as tenant A
        $service = app(IntegrationService::class);
        $integration = $service->create(
            $this->tenant->id,
            'generic_http',
            'Isolation Test',
            [],
            ['api_key' => 'tenant-a-secret'],
        );

        // Create a second tenant
        $ownerRole = Role::where('slug', 'owner')->first();
        $tenantB = Tenant::create(['name' => 'Isolation B', 'slug' => 'iso-b']);
        $ownerB = User::create([
            'tenant_id' => $tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'iso.b@t.com', 'password' => 'password',
        ]);
        $tokenB = $ownerB->createToken('test')->plainTextToken;

        $module = \App\Models\Module::where('slug', 'integrations')->first();
        \App\Models\TenantModule::create([
            'tenant_id' => $tenantB->id, 'module_id' => $module->id, 'is_enabled' => true,
        ]);

        // Tenant B cannot see tenant A's integration
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson("/api/v1/integrations/{$integration->id}");
        $response->assertStatus(404);

        // Tenant B cannot delete tenant A's integration
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->deleteJson("/api/v1/integrations/{$integration->id}");
        $response->assertStatus(404);

        // Verify integration still exists
        $this->assertDatabaseHas('tenant_integrations', ['id' => $integration->id]);
    }
}
