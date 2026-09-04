<?php

namespace App\Services;

use App\Models\IntegrationLog;
use App\Models\IntegrationProvider;
use App\Models\TenantIntegration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class IntegrationService
{
    public function listProviders(): \Illuminate\Database\Eloquent\Collection
    {
        return IntegrationProvider::where('is_active', true)->get();
    }

    public function getProvider(string $slug): ?IntegrationProvider
    {
        return IntegrationProvider::where('slug', $slug)->where('is_active', true)->first();
    }

    public function listIntegrations(int $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = TenantIntegration::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with('provider:id,slug,name')
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function getIntegration(int $tenantId, int $id): ?TenantIntegration
    {
        return TenantIntegration::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with('provider')
            ->find($id);
    }

    public function create(int $tenantId, string $providerSlug, string $name, array $config = [], array $credentials = []): TenantIntegration
    {
        $provider = $this->getProvider($providerSlug);
        if (!$provider) {
            throw new \DomainException("Provider '{$providerSlug}' not found or inactive");
        }

        $encrypted = null;
        if (!empty($credentials)) {
            $encrypted = Crypt::encryptString(json_encode($credentials));
        }

        return TenantIntegration::withoutTenantScope()->create([
            'tenant_id' => $tenantId,
            'integration_provider_id' => $provider->id,
            'name' => $name,
            'config' => $config,
            'encrypted_credentials' => $encrypted,
            'status' => 'inactive',
        ]);
    }

    public function updateConfig(TenantIntegration $integration, array $config): TenantIntegration
    {
        $integration->update(['config' => $config]);
        return $integration->fresh();
    }

    public function updateCredentials(TenantIntegration $integration, array $credentials): TenantIntegration
    {
        $encrypted = Crypt::encryptString(json_encode($credentials));
        $integration->update(['encrypted_credentials' => $encrypted]);
        return $integration->fresh();
    }

    public function getCredentials(TenantIntegration $integration): array
    {
        if (empty($integration->encrypted_credentials)) {
            return [];
        }
        $decrypted = Crypt::decryptString($integration->encrypted_credentials);
        return json_decode($decrypted, true) ?? [];
    }

    public function activate(TenantIntegration $integration): TenantIntegration
    {
        $integration->update(['status' => 'active']);
        return $integration->fresh();
    }

    public function deactivate(TenantIntegration $integration): TenantIntegration
    {
        $integration->update(['status' => 'inactive']);
        return $integration->fresh();
    }

    public function testConnection(TenantIntegration $integration): array
    {
        $credentials = $this->getCredentials($integration);
        $config = $integration->config ?? [];
        $baseUrl = $config['base_url'] ?? '';

        try {
            $startTime = microtime(true);
            $response = Http::timeout(10)->withHeaders($this->buildAuthHeaders($credentials))
                ->get(rtrim($baseUrl, '/') . '/health');

            $latency = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $integration->update([
                    'status' => 'active',
                    'last_connected_at' => now(),
                    'last_error' => null,
                ]);

                $this->logCall($integration->tenant_id, $integration->id, 'outbound', 'GET', $baseUrl . '/health', 200, $latency);

                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'latency_ms' => $latency,
                ];
            } else {
                $errorMsg = "HTTP {$response->status()}: {$response->body()}";
                $integration->update([
                    'status' => 'error',
                    'last_error' => $errorMsg,
                ]);

                $this->logCall($integration->tenant_id, $integration->id, 'outbound', 'GET', $baseUrl . '/health', $response->status(), $latency, $errorMsg);

                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'latency_ms' => $latency,
                ];
            }
        } catch (\Exception $e) {
            $integration->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            $this->logCall($integration->tenant_id, $integration->id, 'outbound', 'GET', $baseUrl . '/health', null, null, $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latency_ms' => 0,
            ];
        }
    }

    public function delete(TenantIntegration $integration): void
    {
        $integration->delete();
    }

    public function getLogs(int $tenantId, int $integrationId, array $filters = []): LengthAwarePaginator
    {
        $query = IntegrationLog::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('tenant_integration_id', $integrationId)
            ->orderByDesc('created_at');

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function logCall(
        int $tenantId,
        ?int $integrationId,
        string $direction,
        string $method,
        string $url,
        ?int $responseStatus = null,
        ?int $latencyMs = null,
        ?string $errorMessage = null,
        ?string $idempotencyKey = null,
    ): IntegrationLog {
        return IntegrationLog::withoutTenantScope()->create([
            'tenant_id' => $tenantId,
            'tenant_integration_id' => $integrationId,
            'direction' => $direction,
            'method' => $method,
            'url' => $url,
            'response_status' => $responseStatus,
            'latency_ms' => $latencyMs,
            'error_message' => $errorMessage,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function getHealth(int $tenantId): array
    {
        $integrations = TenantIntegration::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with('provider:id,slug,name')
            ->get();

        return $integrations->map(function ($integration) {
            $errorCount = IntegrationLog::withoutTenantScope()
                ->where('tenant_id', $integration->tenant_id)
                ->where('tenant_integration_id', $integration->id)
                ->where('direction', 'outbound')
                ->whereNotNull('error_message')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            return [
                'id' => $integration->id,
                'name' => $integration->name,
                'provider' => $integration->provider->slug ?? null,
                'status' => $integration->status,
                'last_connected_at' => $integration->last_connected_at,
                'error_count_24h' => $errorCount,
                'last_error' => $integration->last_error,
            ];
        })->toArray();
    }

    protected function buildAuthHeaders(array $credentials): array
    {
        $headers = [];
        if (!empty($credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $credentials['api_key'];
        }
        return $headers;
    }
}
