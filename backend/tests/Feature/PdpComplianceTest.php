<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdpComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
    }

    private function createUserWithTenant(): User
    {
        $tenant = Tenant::create(['name' => 'PDP Test Tenant', 'slug' => 'pdp-test-' . uniqid()]);
        $ownerRole = Role::where('slug', 'owner')->first();
        return User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $ownerRole->id]);
    }

    public function test_account_export_returns_user_data(): void
    {
        $user = $this->createUserWithTenant();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/account/export');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'created_at'],
            'sales',
            'payments',
            'audit_logs',
        ]);
    }

    public function test_account_export_includes_audit_log(): void
    {
        $user = $this->createUserWithTenant();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/account/export');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('audit_logs'));
    }

    public function test_account_delete_requires_password(): void
    {
        $user = $this->createUserWithTenant();
        $user->update(['password' => bcrypt('Test1234!Pass')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->deleteJson('/api/v1/account', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_account_delete_with_wrong_password_fails(): void
    {
        $user = $this->createUserWithTenant();
        $user->update(['password' => bcrypt('Test1234!Pass')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->deleteJson('/api/v1/account', [
                'password' => 'WrongPassword123!',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_account_delete_with_correct_password_succeeds(): void
    {
        $user = $this->createUserWithTenant();
        $user->update(['password' => bcrypt('Test1234!Pass')]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->deleteJson('/api/v1/account', [
                'password' => 'Test1234!Pass',
            ]);

        $response->assertStatus(202);
        $response->assertJsonStructure(['message', 'scheduled_purge_at']);

        $user->refresh();
        $this->assertTrue($user->trashed());
    }

    public function test_account_consent_returns_consent_info(): void
    {
        $user = $this->createUserWithTenant();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/account/consent');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'consented_at',
            'consent_type',
            'privacy_policy_version',
            'data_types',
        ]);
    }

    public function test_account_export_logs_audit_entry(): void
    {
        $user = $this->createUserWithTenant();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/account/export');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.exported',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }

    public function test_account_delete_logs_audit_entry(): void
    {
        $user = $this->createUserWithTenant();
        $user->update(['password' => bcrypt('Test1234!Pass')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/account', [
            'password' => 'Test1234!Pass',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.deletion_requested',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }

    public function test_deleted_user_email_is_anonymized(): void
    {
        $user = $this->createUserWithTenant();
        $user->update(['email' => 'real@example.com', 'password' => bcrypt('Test1234!Pass')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/account', [
            'password' => 'Test1234!Pass',
        ]);

        $user->refresh();
        $this->assertNotEquals('real@example.com', $user->email);
        $this->assertStringStartsWith('deleted_', $user->email);
    }
}
