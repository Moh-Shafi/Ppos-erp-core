<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TwoFactorAuth;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
    }

    private function createUserWith2faFeature(): array
    {
        $tenant = Tenant::create(['name' => '2FA Test Tenant', 'slug' => '2fa-test']);
        $ownerRole = Role::where('slug', 'owner')->first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $ownerRole->id,
        ]);

        $feature = Feature::where('slug', '2fa')->first();
        if ($feature) {
            TenantFeature::create([
                'tenant_id' => $tenant->id,
                'feature_id' => $feature->id,
                'is_enabled' => true,
            ]);
        }

        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    public function test_enable_2fa_returns_qr_code_and_backup_codes(): void
    {
        [$user, $token] = $this->createUserWith2faFeature();

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/enable');

        $response->assertStatus(200);
        $response->assertJsonStructure(['qr_code', 'secret', 'backup_codes']);
        $this->assertNotEmpty($response->json('secret'));
        $this->assertCount(10, $response->json('backup_codes'));
    }

    public function test_verify_2fa_with_valid_code(): void
    {
        [$user, $token] = $this->createUserWith2faFeature();

        $enableResponse = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/enable');

        $secret = $enableResponse->json('secret');

        $service = app(TwoFactorService::class);
        $timeCounter = floor(time() / 30);
        $validCode = $service->verifyTotp($secret, '000000');
        $code = $this->generateValidTotp($secret);

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/verify', [
                'code' => $code,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['verified' => true]);

        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
    }

    public function test_verify_2fa_with_invalid_code(): void
    {
        [$user, $token] = $this->createUserWith2faFeature();

        $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/verify', [
                'code' => '000000',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_2fa_status_endpoint(): void
    {
        [$user, $token] = $this->createUserWith2faFeature();

        $response = $this->withToken($token)
            ->getJson('/api/v1/auth/2fa/status');

        $response->assertStatus(200);
        $response->assertJson(['enabled' => false]);
    }

    public function test_2fa_status_shows_enabled_after_verification(): void
    {
        [$user, $token] = $this->createUserWith2faFeature();

        $enableResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');
        $secret = $enableResponse->json('secret');
        $code = $this->generateValidTotp($secret);

        $this->withToken($token)->postJson('/api/v1/auth/2fa/verify', ['code' => $code]);

        $response = $this->withToken($token)->getJson('/api/v1/auth/2fa/status');

        $response->assertStatus(200);
        $response->assertJson(['enabled' => true]);
    }

    public function test_disable_2fa_with_valid_code(): void
    {
        [$user, $token] = $this->createUserWith2faFeature();

        $enableResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');
        $secret = $enableResponse->json('secret');
        $code = $this->generateValidTotp($secret);

        $this->withToken($token)->postJson('/api/v1/auth/2fa/verify', ['code' => $code]);

        $newCode = $this->generateValidTotp($secret);
        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/disable', ['code' => $newCode]);

        $response->assertStatus(200);
        $response->assertJson(['disabled' => true]);

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
    }

    public function test_disable_2fa_with_invalid_code(): void
    {
        [$user, $token] = $this->createUserWith2faFeature();

        $enableResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');
        $secret = $enableResponse->json('secret');
        $code = $this->generateValidTotp($secret);

        $this->withToken($token)->postJson('/api/v1/auth/2fa/verify', ['code' => $code]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/disable', ['code' => '000000']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_backup_codes_can_verify(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);

        $result = $service->enable($user);
        $backupCode = $result['backup_codes'][0];

        $verified = $service->verifyBackupCode($user, $backupCode);

        $this->assertTrue($verified);
    }

    public function test_backup_code_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);

        $result = $service->enable($user);
        $backupCode = $result['backup_codes'][0];

        $service->verifyBackupCode($user, $backupCode);
        $secondAttempt = $service->verifyBackupCode($user, $backupCode);

        $this->assertFalse($secondAttempt);
    }

    public function test_regenerate_backup_codes(): void
    {
        [$user, $token] = $this->createUserWith2faFeature();

        $enableResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');
        $secret = $enableResponse->json('secret');
        $code = $this->generateValidTotp($secret);

        $this->withToken($token)->postJson('/api/v1/auth/2fa/verify', ['code' => $code]);

        $newCode = $this->generateValidTotp($secret);
        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/backup-codes', ['code' => $newCode]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['backup_codes']);
        $this->assertCount(10, $response->json('backup_codes'));
    }

    public function test_2fa_login_flow_returns_2fa_required(): void
    {
        $tenant = Tenant::create(['name' => '2FA Login Tenant', 'slug' => '2fa-login']);
        $ownerRole = Role::where('slug', 'owner')->first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $ownerRole->id,
            'password' => bcrypt('Test1234!Pass'),
        ]);

        $feature = Feature::where('slug', '2fa')->first();
        if ($feature) {
            TenantFeature::create(['tenant_id' => $tenant->id, 'feature_id' => $feature->id, 'is_enabled' => true]);
        }

        $token = $user->createToken('test')->plainTextToken;
        $enableResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');
        $secret = $enableResponse->json('secret');
        $code = $this->generateValidTotp($secret);
        $this->withToken($token)->postJson('/api/v1/auth/2fa/verify', ['code' => $code]);
        $user->tokens()->delete();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Test1234!Pass',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['2fa_required' => true]);
        $response->assertJsonStructure(['2fa_token', 'expires_in']);
    }

    public function test_2fa_login_with_valid_code(): void
    {
        $tenant = Tenant::create(['name' => '2FA Login Valid Tenant', 'slug' => '2fa-login-valid']);
        $ownerRole = Role::where('slug', 'owner')->first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $ownerRole->id,
            'password' => bcrypt('Test1234!Pass'),
        ]);

        $feature = Feature::where('slug', '2fa')->first();
        if ($feature) {
            TenantFeature::create(['tenant_id' => $tenant->id, 'feature_id' => $feature->id, 'is_enabled' => true]);
        }

        $token = $user->createToken('test')->plainTextToken;
        $enableResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');
        $secret = $enableResponse->json('secret');
        $code = $this->generateValidTotp($secret);
        $this->withToken($token)->postJson('/api/v1/auth/2fa/verify', ['code' => $code]);
        $user->tokens()->delete();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Test1234!Pass',
        ]);

        $twoFaToken = $loginResponse->json('2fa_token');
        $totpCode = $this->generateValidTotp($secret);

        $response = $this->postJson('/api/v1/auth/login-2fa', [
            '2fa_token' => $twoFaToken,
            'code' => $totpCode,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
    }

    public function test_2fa_login_with_backup_code(): void
    {
        $tenant = Tenant::create(['name' => '2FA Backup Tenant', 'slug' => '2fa-backup']);
        $ownerRole = Role::where('slug', 'owner')->first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $ownerRole->id,
            'password' => bcrypt('Test1234!Pass'),
        ]);

        $feature = Feature::where('slug', '2fa')->first();
        if ($feature) {
            TenantFeature::create(['tenant_id' => $tenant->id, 'feature_id' => $feature->id, 'is_enabled' => true]);
        }

        $token = $user->createToken('test')->plainTextToken;
        $enableResponse = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');
        $secret = $enableResponse->json('secret');
        $backupCodes = $enableResponse->json('backup_codes');
        $code = $this->generateValidTotp($secret);
        $this->withToken($token)->postJson('/api/v1/auth/2fa/verify', ['code' => $code]);
        $user->tokens()->delete();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Test1234!Pass',
        ]);

        $twoFaToken = $loginResponse->json('2fa_token');

        $response = $this->postJson('/api/v1/auth/login-2fa', [
            '2fa_token' => $twoFaToken,
            'code' => $backupCodes[0],
            'is_backup' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
    }

    public function test_2fa_login_with_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login-2fa', [
            '2fa_token' => 'invalid-token',
            'code' => '123456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['2fa_token']);
    }

    public function test_admin_can_reset_2fa(): void
    {
        $ownerRole = Role::where('slug', 'owner')->first();
        $admin = User::factory()->create(['role_id' => $ownerRole->id]);
        $adminToken = $admin->createToken('admin')->plainTextToken;

        $targetUser = User::factory()->create();
        $service = app(TwoFactorService::class);
        $service->enable($targetUser);
        $targetUser->two_factor_enabled = true;
        $targetUser->save();

        $response = $this->withToken($adminToken)
            ->postJson("/api/v1/admin/users/{$targetUser->id}/reset-2fa");

        $response->assertStatus(200);
        $response->assertJson(['reset' => true]);

        $targetUser->refresh();
        $this->assertFalse($targetUser->two_factor_enabled);
    }

    public function test_totp_generation_and_verification(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();

        $this->assertEquals(16, strlen($secret));

        $code = $this->generateValidTotp($secret);
        $this->assertTrue($service->verifyTotp($secret, $code));
    }

    public function test_totp_rejects_wrong_code(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();

        $this->assertFalse($service->verifyTotp($secret, '000000'));
    }

    public function test_2fa_secret_is_encrypted_in_database(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);

        $result = $service->enable($user);

        $tfa = TwoFactorAuth::where('user_id', $user->id)->first();
        $this->assertNotEquals($result['secret'], $tfa->secret);
    }

    private function generateValidTotp(string $secret): string
    {
        $service = app(TwoFactorService::class);
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
