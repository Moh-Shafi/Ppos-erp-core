<?php

namespace App\Integrations\Adapters;

use App\Contracts\ProviderAdapterInterface;
use Illuminate\Support\Facades\Http;

class GenericHttpAdapter implements ProviderAdapterInterface
{
    public static function slug(): string
    {
        return 'generic_http';
    }

    public function healthCheck(array $credentials, array $config): array
    {
        $baseUrl = $config['base_url'] ?? '';
        $timeout = $config['timeout'] ?? 10;

        try {
            $response = Http::timeout($timeout)
                ->withHeaders($this->authHeaders($credentials))
                ->get(rtrim($baseUrl, '/') . '/health');

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'OK' : "HTTP {$response->status()}",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function execute(string $method, string $path, array $data, array $credentials, array $config, ?string $idempotencyKey = null): array
    {
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        $timeout = $config['timeout'] ?? 30;
        $url = $baseUrl . '/' . ltrim($path, '/');

        $headers = $this->authHeaders($credentials);

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $http = Http::timeout($timeout)->withHeaders($headers);

        $response = match (strtoupper($method)) {
            'GET' => $http->get($url, $data),
            'POST' => $http->post($url, $data),
            'PUT' => $http->put($url, $data),
            'DELETE' => $http->delete($url),
            default => throw new \DomainException("Unsupported method: {$method}"),
        };

        return [
            'status' => $response->status(),
            'body' => $response->body(),
            'json' => $response->json(),
        ];
    }

    protected function authHeaders(array $credentials): array
    {
        $headers = [];
        if (!empty($credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $credentials['api_key'];
        }
        if (!empty($credentials['api_secret'])) {
            $headers['X-Api-Secret'] = $credentials['api_secret'];
        }
        return $headers;
    }
}
