<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookService
{
    public function listEndpoints(int $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = WebhookEndpoint::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->with('subscriptions')->paginate($filters['per_page'] ?? 20);
    }

    public function getEndpoint(int $tenantId, int $id): ?WebhookEndpoint
    {
        return WebhookEndpoint::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with('subscriptions')
            ->find($id);
    }

    public function createEndpoint(int $tenantId, string $name, string $url, array $events, ?string $description = null): array
    {
        $this->validateUrl($url);

        $this->validateEvents($events);

        $secret = 'whsec_' . Str::random(32);

        $endpoint = WebhookEndpoint::withoutTenantScope()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'url' => $url,
            'secret' => $secret,
            'is_active' => true,
            'description' => $description,
        ]);

        foreach ($events as $eventType) {
            WebhookSubscription::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event_type' => $eventType,
            ]);
        }

        return [
            'endpoint' => $endpoint->fresh('subscriptions'),
            'secret' => $secret,
        ];
    }

    public function updateEndpoint(WebhookEndpoint $endpoint, array $data): WebhookEndpoint
    {
        if (!empty($data['url'])) {
            $this->validateUrl($data['url']);
        }

        $endpoint->update(array_filter($data, fn($k) => in_array($k, ['name', 'url', 'is_active', 'description']), ARRAY_FILTER_USE_KEY));

        return $endpoint->fresh('subscriptions');
    }

    public function deleteEndpoint(WebhookEndpoint $endpoint): void
    {
        $endpoint->subscriptions()->delete();
        $endpoint->deliveries()->delete();
        $endpoint->delete();
    }

    public function subscribe(WebhookEndpoint $endpoint, string $eventType): WebhookSubscription
    {
        $this->validateEvents([$eventType]);

        return WebhookSubscription::firstOrCreate([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => $eventType,
        ]);
    }

    public function unsubscribe(WebhookEndpoint $endpoint, string $eventType): void
    {
        WebhookSubscription::where('webhook_endpoint_id', $endpoint->id)
            ->where('event_type', $eventType)
            ->delete();
    }

    public function sendTestPayload(WebhookEndpoint $endpoint): array
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'event_type' => 'test.ping',
            'timestamp' => now()->toIso8601String(),
            'tenant_id' => $endpoint->tenant_id,
            'data' => ['message' => 'Test webhook from ERP'],
        ];

        $payloadJson = json_encode($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', $payloadJson . $timestamp, $endpoint->secret);

        try {
            $startTime = microtime(true);
            $response = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Timestamp' => $timestamp,
                'X-Webhook-Event' => 'test.ping',
                'X-Webhook-Delivery' => $payload['event_id'],
            ])->post($endpoint->url, $payload);

            $latency = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'success' => $response->successful(),
                'response_status' => $response->status(),
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'response_status' => null,
                'latency_ms' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function listDeliveries(int $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = WebhookDelivery::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with('endpoint:id,name,url')
            ->orderByDesc('created_at');

        if (!empty($filters['endpoint_id'])) {
            $query->where('webhook_endpoint_id', $filters['endpoint_id']);
        }

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function getDelivery(int $tenantId, int $id): ?WebhookDelivery
    {
        return WebhookDelivery::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with('endpoint')
            ->find($id);
    }

    public function replayDelivery(WebhookDelivery $delivery): WebhookDelivery
    {
        if (!in_array($delivery->status, ['failed', 'dead_lettered'])) {
            throw new \DomainException('Only failed or dead-lettered deliveries can be replayed');
        }

        $newDelivery = WebhookDelivery::withoutTenantScope()->create([
            'tenant_id' => $delivery->tenant_id,
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            'event_type' => $delivery->event_type,
            'event_id' => $delivery->event_id,
            'payload' => $delivery->payload,
            'signature' => $delivery->signature,
            'status' => 'pending',
            'attempt_count' => 0,
            'original_delivery_id' => $delivery->id,
        ]);

        $delivery->update(['status' => 'replayed']);

        return $newDelivery;
    }

    public function getDeliveryStats(int $tenantId, string $period = '24h'): array
    {
        $from = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        $baseQuery = WebhookDelivery::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $from);

        $total = (clone $baseQuery)->count();
        $delivered = (clone $baseQuery)->where('status', 'delivered')->count();
        $failed = (clone $baseQuery)->where('status', 'failed')->count();
        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $deadLettered = (clone $baseQuery)->where('status', 'dead_lettered')->count();

        $avgLatency = (clone $baseQuery)->whereNotNull('latency_ms')->avg('latency_ms');

        return [
            'period' => $period,
            'total' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $pending,
            'dead_lettered' => $deadLettered,
            'success_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 100,
            'avg_latency_ms' => $avgLatency ? (int) $avgLatency : 0,
        ];
    }

    public function listEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return WebhookEvent::where('is_active', true)->orderBy('module')->orderBy('name')->get();
    }

    protected function validateUrl(string $url): void
    {
        $parsed = parse_url($url);

        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            throw new \DomainException('URL must use HTTP or HTTPS scheme');
        }

        $host = $parsed['host'] ?? '';

        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'])) {
            throw new \DomainException('URL cannot point to localhost');
        }

        if (in_array($host, ['169.254.169.254', 'metadata.google.internal'])) {
            throw new \DomainException('URL cannot point to cloud metadata endpoint');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \DomainException('URL cannot point to a private or reserved IP address');
            }
        } else {
            $ip = @gethostbyname($host);
            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \DomainException('URL cannot point to a private or reserved IP address');
            }
        }
    }

    protected function validateEvents(array $events): void
    {
        $validEvents = WebhookEvent::where('is_active', true)->pluck('slug')->toArray();

        foreach ($events as $event) {
            if (!in_array($event, $validEvents)) {
                throw new \DomainException("Invalid event type: {$event}");
            }
        }
    }
}
