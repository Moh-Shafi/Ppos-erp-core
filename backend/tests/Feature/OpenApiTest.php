<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_spec_returns_valid_json(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200);
        $response->assertJsonPath('openapi', '3.1.0');
    }

    public function test_openapi_spec_has_info_section(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'openapi',
            'info' => ['title', 'version', 'description'],
        ]);
    }

    public function test_openapi_spec_has_auth_endpoints(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200);
        $paths = $response->json('paths');
        $this->assertArrayHasKey('/auth/register', $paths);
        $this->assertArrayHasKey('/auth/login', $paths);
    }

    public function test_openapi_spec_has_2fa_endpoints(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200);
        $paths = $response->json('paths');
        $this->assertArrayHasKey('/auth/2fa/enable', $paths);
        $this->assertArrayHasKey('/auth/2fa/verify', $paths);
        $this->assertArrayHasKey('/auth/2fa/disable', $paths);
        $this->assertArrayHasKey('/auth/2fa/status', $paths);
    }

    public function test_openapi_spec_has_health_endpoint(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200);
        $paths = $response->json('paths');
        $this->assertArrayHasKey('/health', $paths);
    }

    public function test_openapi_spec_has_pdp_endpoints(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200);
        $paths = $response->json('paths');
        $this->assertArrayHasKey('/account/export', $paths);
        $this->assertArrayHasKey('/account', $paths);
        $this->assertArrayHasKey('/account/consent', $paths);
    }

    public function test_openapi_spec_has_audit_endpoints(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200);
        $paths = $response->json('paths');
        $this->assertArrayHasKey('/audit-logs', $paths);
        $this->assertArrayHasKey('/audit-logs/export', $paths);
    }

    public function test_openapi_spec_has_security_schemes(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'components' => [
                'securitySchemes' => ['bearerAuth'],
            ],
        ]);
    }

    public function test_openapi_spec_has_servers(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');

        $response->assertStatus(200);
        $response->assertJsonStructure(['servers']);
    }
}
