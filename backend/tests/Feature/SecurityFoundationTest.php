<?php

namespace Tests\Feature;

use App\Models\AccountLockout;
use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
    }

    public function test_xss_sanitizer_strips_script_tags(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/categories', [
                'name' => '<script>alert("xss")</script>Test Category',
                'slug' => 'test-cat',
            ]);

        $response->assertStatus(201);
        $this->assertStringNotContainsString('<script>', $response->json('name'));
    }

    public function test_xss_sanitizer_strips_onclick_handlers(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/categories', [
                'name' => 'Test onclick="alert(1)" Category',
                'slug' => 'test-onclick',
            ]);

        $response->assertStatus(201);
        $this->assertStringNotContainsString('onclick', $response->json('name'));
    }

    public function test_xss_sanitizer_preserves_safe_html_in_skip_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/categories', [
                'name' => 'Safe Category',
                'slug' => 'safe-cat',
                'description' => '<p>This is <b>safe</b> HTML</p>',
            ]);

        $response->assertStatus(201);
    }

    public function test_password_policy_rejects_weak_password_on_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'store_name' => 'Test Store',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_policy_rejects_short_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'Ab1!',
            'password_confirmation' => 'Ab1!',
            'store_name' => 'Test Store',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_policy_rejects_missing_symbols(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test3@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'store_name' => 'Test Store',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_policy_accepts_strong_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test4@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Test Store',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['token', 'user']);
    }

    public function test_account_lockout_records_failed_attempts(): void
    {
        $service = app(SecurityService::class);

        $lockout = $service->recordFailedAttempt('test@lockout.com');

        $this->assertEquals(1, $lockout->failed_attempts);
        $this->assertNull($lockout->locked_until);
    }

    public function test_account_lockout_locks_after_threshold(): void
    {
        $service = app(SecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $lockout = $service->recordFailedAttempt('lock@test.com');
        }

        $this->assertNotNull($lockout->locked_until);
        $this->assertTrue($lockout->isLocked());
    }

    public function test_account_lockout_resets_on_success(): void
    {
        $service = app(SecurityService::class);

        $service->recordFailedAttempt('reset@test.com');
        $service->recordFailedAttempt('reset@test.com');

        $service->resetFailedAttempts('reset@test.com');

        $this->assertNull(AccountLockout::where('username', 'reset@test.com')->first());
    }

    public function test_account_lockout_unlock_by_admin(): void
    {
        $user = User::factory()->create();
        $lockout = AccountLockout::create([
            'user_id' => $user->id,
            'username' => $user->email,
            'failed_attempts' => 10,
            'locked_until' => now()->addHour(),
            'last_attempt_at' => now(),
        ]);

        $service = app(SecurityService::class);
        $result = $service->unlockUser($user->id);

        $this->assertTrue($result);
        $this->assertNull(AccountLockout::find($lockout->id));
    }

    public function test_account_lockout_progressive_durations(): void
    {
        $service = app(SecurityService::class);

        for ($i = 0; $i < 5; $i++) {
            $lockout = $service->recordFailedAttempt('prog@test.com');
        }
        $firstLockDuration = now()->diffInSeconds($lockout->locked_until);
        $this->assertGreaterThan(800, $firstLockDuration);

        $service->resetFailedAttempts('prog@test.com');

        for ($i = 0; $i < 10; $i++) {
            $lockout = $service->recordFailedAttempt('prog@test.com');
        }
        $secondLockDuration = now()->diffInSeconds($lockout->locked_until);
        $this->assertGreaterThan(3500, $secondLockDuration);
    }

    public function test_cors_configuration_loads(): void
    {
        $config = config('cors');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('allowed_origins', $config);
        $this->assertArrayHasKey('allowed_methods', $config);
        $this->assertArrayHasKey('allowed_headers', $config);
    }

    public function test_security_configuration_loads(): void
    {
        $this->assertEquals(12, config('security.password.min_length'));
        $this->assertTrue(config('security.password.require_mixed_case'));
        $this->assertTrue(config('security.password.require_numbers'));
        $this->assertTrue(config('security.password.require_symbols'));
        $this->assertEquals([5, 10, 15], config('security.lockout.thresholds'));
        $this->assertEquals([900, 3600, 86400], config('security.lockout.durations'));
    }

    public function test_audit_configuration_loads(): void
    {
        $this->assertIsArray(config('audit.observed_models'));
        $this->assertNotEmpty(config('audit.observed_models'));
        $this->assertContains('password', config('audit.redacted_fields'));
    }

    public function test_health_check_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'timestamp', 'checks']);
    }

    public function test_health_check_includes_database_check(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJsonPath('checks.database', 'ok');
    }
}
