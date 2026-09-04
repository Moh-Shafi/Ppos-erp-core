<?php

namespace App\Services;

use App\Models\IntegrationApiKey;
use Illuminate\Support\Str;

class ApiKeyService
{
    public function listKeys(int $tenantId): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return IntegrationApiKey::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function generate(int $tenantId, string $name, array $scopes = ['read']): array
    {
        $key = 'itg_' . Str::random(40);
        $hash = hash('sha256', $key);
        $prefix = substr($key, 0, 12) . '...';

        $record = IntegrationApiKey::withoutTenantScope()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'key_hash' => $hash,
            'key_prefix' => $prefix,
            'scopes' => $scopes,
            'is_revoked' => false,
        ]);

        return [
            'id' => $record->id,
            'name' => $name,
            'key' => $key,
            'key_prefix' => $prefix,
            'scopes' => $scopes,
            'is_revoked' => false,
            'created_at' => $record->created_at,
        ];
    }

    public function validate(string $key): ?IntegrationApiKey
    {
        if (!str_starts_with($key, 'itg_')) {
            return null;
        }

        $hash = hash('sha256', $key);

        $apiKey = IntegrationApiKey::withoutTenantScope()
            ->where('key_hash', $hash)
            ->where('is_revoked', false)
            ->first();

        if (!$apiKey) {
            return null;
        }

        if ($apiKey->isExpired()) {
            return null;
        }

        $apiKey->update(['last_used_at' => now()]);

        return $apiKey;
    }

    public function revoke(IntegrationApiKey $apiKey): void
    {
        $apiKey->update(['is_revoked' => true]);
    }

    public function rotate(IntegrationApiKey $apiKey): array
    {
        $this->revoke($apiKey);

        return $this->generate($apiKey->tenant_id, $apiKey->name, $apiKey->scopes ?? ['read']);
    }

    public function hasScope(IntegrationApiKey $key, string $scope): bool
    {
        return $key->hasScope($scope);
    }
}
