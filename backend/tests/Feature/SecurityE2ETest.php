<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    public function test_full_security_lifecycle(): void
    {
        // 1. Register with strong password
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Security Test User',
            'email' => 'security@test.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Security Test Store',
        ]);

        $registerResponse->assertStatus(201);
        $token = $registerResponse->json('token');
        $userId = $registerResponse->json('user.id');

        // 2. Check health
        $healthResponse = $this->getJson('/api/v1/health');
        $healthResponse->assertStatus(200);
        $healthResponse->assertJsonPath('status', 'healthy');

        // 3. Check 2FA status (should be disabled)
        $statusResponse = $this->withToken($token)->getJson('/api/v1/auth/2fa/status');
        $statusResponse->assertStatus(200);
        $statusResponse->assertJson(['enabled' => false]);

        // 4. Enable 2FA
        $enableResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');
        $enableResponse->assertStatus(200);
        $enableResponse->assertJsonStructure(['qr_code', 'secret', 'backup_codes']);
        $this->assertCount(10, $enableResponse->json('backup_codes'));

        // 5. Verify 2FA with invalid code (should fail)
        $invalidVerifyResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/verify', [
            'code' => '000000',
        ]);
        $invalidVerifyResponse->assertStatus(422);

        // 6. Verify 2FA with valid code
        $user = User::find($userId);
        $service = app(TwoFactorService::class);
        $tfa = $user->twoFactorAuth;
        $secret = decrypt($tfa->secret);
        $validCode = $this->generateTotp($secret);

        $verifyResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/verify', [
            'code' => $validCode,
        ]);
        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJson(['verified' => true]);

        // 7. Check 2FA status (should be enabled)
        $statusResponse2 = $this->withToken($token)->getJson('/api/v1/auth/2fa/status');
        $statusResponse2->assertStatus(200);
        $statusResponse2->assertJson(['enabled' => true]);

        // 8. Export account data
        $exportResponse = $this->withToken($token)->getJson('/api/v1/account/export');
        $exportResponse->assertStatus(200);
        $exportResponse->assertJsonStructure(['user', 'audit_logs']);

        // 9. Get consent info
        $consentResponse = $this->withToken($token)->getJson('/api/v1/account/consent');
        $consentResponse->assertStatus(200);
        $consentResponse->assertJsonStructure(['consented_at', 'data_types']);

        // 10. Delete account with wrong password (should fail)
        $wrongDeleteResponse = $this->withToken($token)->deleteJson('/api/v1/account', [
            'password' => 'WrongPassword123!',
        ]);
        $wrongDeleteResponse->assertStatus(422);

        // 11. Delete account with correct password
        $deleteResponse = $this->withToken($token)->deleteJson('/api/v1/account', [
            'password' => 'Str0ng!Pass#2024',
        ]);
        $deleteResponse->assertStatus(202);

        // 12. Verify user is soft-deleted
        $this->assertTrue(User::withTrashed()->find($userId)->trashed());
    }

    public function test_lockout_and_unlock_lifecycle(): void
    {
        $user = User::factory()->create([
            'email' => 'lockout@test.com',
            'password' => bcrypt('Test1234!Pass'),
        ]);

        $service = app(\App\Services\SecurityService::class);

        // Simulate 5 failed attempts to trigger lockout
        for ($i = 0; $i < 5; $i++) {
            $service->recordFailedAttempt('lockout@test.com');
        }

        // Verify lockout exists and is locked
        $lockout = \App\Models\AccountLockout::where('username', 'lockout@test.com')->first();
        $this->assertNotNull($lockout);
        $this->assertTrue($lockout->isLocked());

        // Admin unlocks the account
        $ownerRole = \App\Models\Role::where('slug', 'owner')->first();
        $admin = User::factory()->create(['role_id' => $ownerRole->id]);
        $adminToken = $admin->createToken('admin')->plainTextToken;

        $unlockResponse = $this->withToken($adminToken)
            ->postJson("/api/v1/admin/users/{$user->id}/unlock");

        $unlockResponse->assertStatus(200);
        $unlockResponse->assertJson(['unlocked' => true]);

        // Verify lockout is cleared
        $this->assertNull(\App\Models\AccountLockout::where('username', 'lockout@test.com')->first());

        // Login with correct password should work
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'lockout@test.com',
            'password' => 'Test1234!Pass',
        ]);

        $loginResponse->assertStatus(200);
    }

    public function test_xss_sanitization_lifecycle(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create category with XSS payload
        $response = $this->withToken($token)->postJson('/api/v1/categories', [
            'name' => '<script>alert("xss")</script>Safe Name',
            'slug' => 'xss-test',
        ]);

        $response->assertStatus(201);
        $this->assertStringNotContainsString('<script>', $response->json('name'));
        $this->assertStringContainsString('Safe Name', $response->json('name'));
    }

    public function test_audit_log_lifecycle(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create a category (triggers audit observer)
        $createResponse = $this->withToken($token)->postJson('/api/v1/categories', [
            'name' => 'Audit Lifecycle Cat',
            'slug' => 'audit-lifecycle',
        ]);
        $createResponse->assertStatus(201);

        // List audit logs
        $listResponse = $this->withToken($token)->getJson('/api/v1/audit-logs');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonStructure(['data', 'meta']);

        // Export audit logs as CSV
        $exportResponse = $this->withToken($token)->get('/api/v1/audit-logs/export');
        $exportResponse->assertStatus(200);
        $exportResponse->assertHeader('Content-Type', 'text/csv');
    }

    public function test_openapi_spec_lifecycle(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');
        $response->assertStatus(200);
        $response->assertJsonPath('openapi', '3.1.0');

        $paths = $response->json('paths');
        $this->assertArrayHasKey('/auth/register', $paths);
        $this->assertArrayHasKey('/auth/login', $paths);
        $this->assertArrayHasKey('/health', $paths);
        $this->assertArrayHasKey('/auth/2fa/enable', $paths);
        $this->assertArrayHasKey('/account/export', $paths);
        $this->assertArrayHasKey('/audit-logs', $paths);
    }

    private function generateTotp(string $secret): string
    {
        $timeStep = 30;
        $currentTime = floor(time() / $timeStep);

        $binarySecret = $this->base32Decode($secret);
        $time = pack('N', 0) . pack('N', $currentTime);
        $hash = hash_hmac('sha1', $time, $binarySecret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            (ord($hash[$offset + 1]) & 0xFF) << 16 |
            (ord($hash[$offset + 2]) & 0xFF) << 8 |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $value = strpos($chars, strtoupper($secret[$i]));
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
