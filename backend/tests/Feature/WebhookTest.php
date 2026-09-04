<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
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
        $this->seed(\Database\Seeders\WebhookEventSeeder::class);

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

    public function test_list_webhook_events(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/webhooks/events');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'slug', 'name']]]);
    }

    public function test_create_webhook_endpoint(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/webhooks/endpoints', [
                'name' => 'My Webhook',
                'url' => 'https://example.com/webhook',
                'events' => ['sale.created', 'payment.received'],
                'description' => 'Test endpoint',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['endpoint', 'secret']);
        $this->assertStringStartsWith('whsec_', $response->json('secret'));
        $this->assertDatabaseHas('webhook_endpoints', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'My Webhook',
        ]);
    }

    public function test_create_webhook_invalid_event(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/webhooks/endpoints', [
                'name' => 'Test',
                'url' => 'https://example.com/webhook',
                'events' => ['invalid.event'],
            ]);

        $response->assertStatus(422);
    }

    public function test_create_webhook_localhost_blocked(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/webhooks/endpoints', [
                'name' => 'Test',
                'url' => 'http://localhost:8000/webhook',
                'events' => ['sale.created'],
            ]);

        $response->assertStatus(422);
    }

    public function test_create_webhook_metadata_endpoint_blocked(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/webhooks/endpoints', [
                'name' => 'Test',
                'url' => 'http://169.254.169.254/latest/meta-data',
                'events' => ['sale.created'],
            ]);

        $response->assertStatus(422);
    }

    public function test_list_endpoints(): void
    {
        $service = app(WebhookService::class);
        $service->createEndpoint($this->tenantA->id, 'Test', 'https://example.com/wh', ['sale.created']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/webhooks/endpoints');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_show_endpoint(): void
    {
        $service = app(WebhookService::class);
        $result = $service->createEndpoint($this->tenantA->id, 'Show Test', 'https://example.com/wh', ['sale.created']);
        $endpointId = $result['endpoint']->id;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/webhooks/endpoints/' . $endpointId);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Show Test');
    }

    public function test_update_endpoint(): void
    {
        $service = app(WebhookService::class);
        $result = $service->createEndpoint($this->tenantA->id, 'Update Test', 'https://example.com/wh', ['sale.created']);
        $endpointId = $result['endpoint']->id;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->putJson('/api/v1/webhooks/endpoints/' . $endpointId, [
                'name' => 'Updated Name',
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_delete_endpoint(): void
    {
        $service = app(WebhookService::class);
        $result = $service->createEndpoint($this->tenantA->id, 'Delete Test', 'https://example.com/wh', ['sale.created']);
        $endpointId = $result['endpoint']->id;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->deleteJson('/api/v1/webhooks/endpoints/' . $endpointId);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpointId]);
    }

    public function test_subscribe_unsubscribe(): void
    {
        $service = app(WebhookService::class);
        $result = $service->createEndpoint($this->tenantA->id, 'Sub Test', 'https://example.com/wh', ['sale.created']);
        $endpointId = $result['endpoint']->id;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/webhooks/endpoints/' . $endpointId . '/subscriptions', [
                'event_type' => 'payment.received',
            ]);

        $response->assertStatus(201);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->deleteJson('/api/v1/webhooks/endpoints/' . $endpointId . '/subscriptions/payment.received');

        $response->assertStatus(204);
    }

    public function test_list_deliveries(): void
    {
        $service = app(WebhookService::class);
        $result = $service->createEndpoint($this->tenantA->id, 'Deliveries Test', 'https://example.com/wh', ['sale.created']);
        $endpoint = $result['endpoint'];

        WebhookDelivery::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'sale.created',
            'event_id' => \Illuminate\Support\Str::uuid(),
            'payload' => ['test' => true],
            'signature' => 'test-sig',
            'status' => 'delivered',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/webhooks/deliveries');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_replay_delivery(): void
    {
        $service = app(WebhookService::class);
        $result = $service->createEndpoint($this->tenantA->id, 'Replay Test', 'https://example.com/wh', ['sale.created']);
        $endpoint = $result['endpoint'];

        $delivery = WebhookDelivery::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'sale.created',
            'event_id' => \Illuminate\Support\Str::uuid(),
            'payload' => ['test' => true],
            'signature' => 'test-sig',
            'status' => 'failed',
            'attempt_count' => 5,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/webhooks/deliveries/' . $delivery->id . '/replay');

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.original_delivery_id', $delivery->id);
    }

    public function test_replay_non_failed_rejected(): void
    {
        $service = app(WebhookService::class);
        $result = $service->createEndpoint($this->tenantA->id, 'Replay Reject', 'https://example.com/wh', ['sale.created']);
        $endpoint = $result['endpoint'];

        $delivery = WebhookDelivery::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'sale.created',
            'event_id' => \Illuminate\Support\Str::uuid(),
            'payload' => ['test' => true],
            'signature' => 'test-sig',
            'status' => 'delivered',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->postJson('/api/v1/webhooks/deliveries/' . $delivery->id . '/replay');

        $response->assertStatus(422);
    }

    public function test_webhook_stats(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/webhooks/stats?period=24h');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'period', 'total', 'delivered', 'failed', 'pending', 'dead_lettered', 'success_rate', 'avg_latency_ms',
        ]);
    }

    public function test_cross_tenant_endpoint_isolation(): void
    {
        $service = app(WebhookService::class);
        $result = $service->createEndpoint($this->tenantA->id, 'Isolation Test', 'https://example.com/wh', ['sale.created']);
        $endpointId = $result['endpoint']->id;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerB)
            ->getJson('/api/v1/webhooks/endpoints/' . $endpointId);

        $response->assertStatus(404);
    }

    public function test_secret_not_exposed_in_list(): void
    {
        $service = app(WebhookService::class);
        $service->createEndpoint($this->tenantA->id, 'Secret Test', 'https://example.com/wh', ['sale.created']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenOwnerA)
            ->getJson('/api/v1/webhooks/endpoints');

        $response->assertStatus(200);
        $json = $response->json('data.0');
        $this->assertArrayNotHasKey('secret', $json);
    }
}
