<?php

namespace App\Services;

use App\Jobs\WebhookDeliveryJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventDispatcher
{
    public function handleDomainEvent(string $eventType, array $data, int $tenantId, ?string $module = null): void
    {
        $eventId = (string) Str::uuid();
        $timestamp = now()->toIso8601String();

        $payload = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'timestamp' => $timestamp,
            'tenant_id' => $tenantId,
            'data' => $data,
        ];

        $payloadJson = json_encode($payload);

        $subscriptions = WebhookSubscription::where('event_type', $eventType)
            ->whereHas('endpoint', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->where('is_active', true);
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            $endpoint = $subscription->endpoint;

            $existing = WebhookDelivery::withoutTenantScope()
                ->where('webhook_endpoint_id', $endpoint->id)
                ->where('event_id', $eventId)
                ->exists();

            if ($existing) {
                continue;
            }

            $ts = time();
            $signature = hash_hmac('sha256', $payloadJson . $ts, $endpoint->secret);

            $delivery = WebhookDelivery::withoutTenantScope()->create([
                'tenant_id' => $tenantId,
                'webhook_endpoint_id' => $endpoint->id,
                'event_type' => $eventType,
                'event_id' => $eventId,
                'payload' => $payload,
                'signature' => $signature,
                'status' => 'pending',
                'attempt_count' => 0,
            ]);

            WebhookDeliveryJob::dispatch($delivery->id);
        }

        Log::info("EventDispatcher: dispatched {$eventType} to {$subscriptions->count()} endpoints", [
            'event_id' => $eventId,
            'tenant_id' => $tenantId,
        ]);
    }
}
