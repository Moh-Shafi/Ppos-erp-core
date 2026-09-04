<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class Phase0RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    public function test_registration_with_business_type(): void
    {
        $businessType = BusinessType::where('slug', 'restaurant')->first();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Owner',
            'email' => 'test@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Test Restaurant',
            'business_type_id' => $businessType->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'token',
            'user' => ['id', 'name', 'email', 'tenant', 'role'],
            'modules',
            'features',
            'permissions',
            'stores',
            'business_profile',
        ]);

        $this->assertNotEmpty($response->json('modules'));
        $this->assertContains('core', $response->json('modules'));
        $this->assertContains('pos', $response->json('modules'));
        $this->assertNotEmpty($response->json('permissions'));
        $this->assertNotEmpty($response->json('stores'));
        $this->assertNotNull($response->json('business_profile'));
    }

    public function test_registration_without_business_type_defaults_to_general(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Owner 2',
            'email' => 'test2@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Test General Store',
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('business_profile'));
        $this->assertEquals('general', $response->json('business_profile.business_type.slug'));
    }

    public function test_registration_creates_business_profile(): void
    {
        $businessType = BusinessType::where('slug', 'cafe')->first();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Cafe Owner',
            'email' => 'cafe@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Test Cafe',
            'business_type_id' => $businessType->id,
        ]);

        $response->assertStatus(201);

        $tenantId = $response->json('user.tenant_id');
        $this->assertDatabaseHas('business_profiles', [
            'tenant_id' => $tenantId,
            'business_type_id' => $businessType->id,
            'business_name' => 'Test Cafe',
        ]);
    }

    public function test_registration_creates_store_with_headquarters_flag(): void
    {
        $businessType = BusinessType::where('slug', 'retail')->first();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Retail Owner',
            'email' => 'retail@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Test Retail',
            'business_type_id' => $businessType->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('stores', [
            'tenant_id' => $response->json('user.tenant_id'),
            'is_headquarters' => true,
        ]);
    }

    public function test_registration_enables_modules_per_business_type(): void
    {
        $businessType = BusinessType::where('slug', 'restaurant')->first();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Resto Owner',
            'email' => 'resto@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Test Resto',
            'business_type_id' => $businessType->id,
        ]);

        $response->assertStatus(201);
        $modules = $response->json('modules');
        $this->assertContains('core', $modules);
        $this->assertContains('pos', $modules);
        $this->assertContains('inventory', $modules);
        $this->assertContains('tables', $modules);
        $this->assertContains('kitchen', $modules);
    }

    public function test_login_returns_module_config(): void
    {
        $businessType = BusinessType::where('slug', 'restaurant')->first();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Login Test',
            'email' => 'login@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Login Test Store',
            'business_type_id' => $businessType->id,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Str0ng!Pass#2024',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'token',
            'user',
            'modules',
            'features',
            'permissions',
            'stores',
            'business_profile',
        ]);
    }

    public function test_me_returns_module_config(): void
    {
        $businessType = BusinessType::where('slug', 'restaurant')->first();

        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Me Test',
            'email' => 'me@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Me Test Store',
            'business_type_id' => $businessType->id,
        ]);

        $token = $regResponse->json('token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user',
            'modules',
            'features',
            'permissions',
            'stores',
            'business_profile',
        ]);
    }

    public function test_registration_creates_audit_log(): void
    {
        $businessType = BusinessType::where('slug', 'restaurant')->first();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Audit Test',
            'email' => 'audit@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Audit Test Store',
            'business_type_id' => $businessType->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'register',
            'entity_type' => 'Tenant',
        ]);
    }

    public function test_business_types_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/v1/business-types');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_registration_with_invalid_business_type_fails(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Invalid BT',
            'email' => 'invalid@example.com',
            'password' => 'Str0ng!Pass#2024',
            'password_confirmation' => 'Str0ng!Pass#2024',
            'store_name' => 'Invalid Store',
            'business_type_id' => 99999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['business_type_id']);
    }
}
