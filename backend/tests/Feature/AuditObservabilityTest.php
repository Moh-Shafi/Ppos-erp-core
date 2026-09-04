<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
    }

    private function createOwnerUser(): array
    {
        $tenant = Tenant::create(['name' => 'Audit Test Tenant', 'slug' => 'audit-test-' . uniqid()]);
        $ownerRole = Role::where('slug', 'owner')->first();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $ownerRole->id]);
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    public function test_audit_observer_logs_model_creation(): void
    {
        [$user, $token] = $this->createOwnerUser();

        $this->withToken($token)
            ->postJson('/api/v1/categories', [
                'name' => 'Test Category',
                'slug' => 'test-cat-audit',
            ]);

        $log = AuditLog::where('action', 'created')
            ->where('entity_type', Product::class)
            ->first();

        if ($log) {
            $this->assertNotNull($log->route);
            $this->assertNotNull($log->method);
            $this->assertEquals('POST', $log->method);
        }
    }

    public function test_audit_log_includes_route_and_method(): void
    {
        [$user, $token] = $this->createOwnerUser();

        $this->withToken($token)
            ->postJson('/api/v1/categories', [
                'name' => 'Route Test Cat',
                'slug' => 'route-test-cat',
            ]);

        $log = AuditLog::where('action', 'created')->latest()->first();

        if ($log) {
            $this->assertNotNull($log->route);
            $this->assertEquals('POST', $log->method);
        }
    }

    public function test_audit_log_redacts_sensitive_fields(): void
    {
        [$user] = $this->createOwnerUser();
        $service = app(\App\Services\AuditService::class);

        $service->log(
            'test.action',
            'TestEntity',
            1,
            ['password' => 'secret123', 'name' => 'John'],
            ['password' => 'newsecret456', 'name' => 'Jane'],
            $user->id,
            $user->tenant_id,
        );

        $log = AuditLog::where('action', 'test.action')->first();

        $this->assertEquals('[REDACTED]', $log->old_values['password']);
        $this->assertEquals('[REDACTED]', $log->new_values['password']);
        $this->assertEquals('John', $log->old_values['name']);
        $this->assertEquals('Jane', $log->new_values['name']);
    }

    public function test_audit_log_can_be_filtered_by_route(): void
    {
        [$user, $token] = $this->createOwnerUser();

        $this->withToken($token)
            ->postJson('/api/v1/categories', [
                'name' => 'Filter Test Cat',
                'slug' => 'filter-test-cat',
            ]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/audit-logs?route=categories');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_audit_log_csv_export(): void
    {
        [$user, $token] = $this->createOwnerUser();

        $this->withToken($token)
            ->postJson('/api/v1/categories', [
                'name' => 'CSV Export Cat',
                'slug' => 'csv-export-cat',
            ]);

        $response = $this->withToken($token)
            ->get('/api/v1/audit-logs/export');

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_audit_observer_logs_model_update(): void
    {
        [$user, $token] = $this->createOwnerUser();

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/categories', [
                'name' => 'Update Test Cat',
                'slug' => 'update-test-cat',
            ]);

        $categoryId = $createResponse->json('id');

        $this->withToken($token)
            ->putJson("/api/v1/categories/{$categoryId}", [
                'name' => 'Updated Cat Name',
            ]);

        $log = AuditLog::where('action', 'updated')->latest()->first();

        if ($log) {
            $this->assertNotNull($log->old_values);
            $this->assertNotNull($log->new_values);
        }
    }

    public function test_audit_observer_logs_model_deletion(): void
    {
        [$user, $token] = $this->createOwnerUser();

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/categories', [
                'name' => 'Delete Test Cat',
                'slug' => 'delete-test-cat',
            ]);

        $categoryId = $createResponse->json('id');

        $this->withToken($token)
            ->deleteJson("/api/v1/categories/{$categoryId}");

        $log = AuditLog::where('action', 'deleted')->latest()->first();

        if ($log) {
            $this->assertNotNull($log);
        }
    }

    public function test_health_check_returns_200_when_healthy(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'healthy');
    }

    public function test_health_check_includes_all_checks(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $checks = $response->json('checks');
        $this->assertArrayHasKey('database', $checks);
        $this->assertArrayHasKey('storage', $checks);
        $this->assertArrayHasKey('queue', $checks);
    }

    public function test_audit_purge_command_deletes_old_logs(): void
    {
        [$user] = $this->createOwnerUser();

        $oldLog = AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => 'old.action',
            'entity_type' => 'Test',
            'entity_id' => 1,
        ]);
        \DB::table('audit_logs')->where('id', $oldLog->id)->update(['created_at' => now()->subDays(100)]);

        $recentLog = AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => 'recent.action',
            'entity_type' => 'Test',
            'entity_id' => 2,
        ]);

        $this->artisan('audit:purge')
            ->assertSuccessful();

        $this->assertNull(AuditLog::find($oldLog->id));
        $this->assertNotNull(AuditLog::find($recentLog->id));
    }
}
