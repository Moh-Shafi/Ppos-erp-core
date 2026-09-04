<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [30, 120, 600, 3600, 21600];

    public function __construct(
        public int $deliveryId
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::withoutTenantScope()->find($this->deliveryId);

        if (!$delivery) {
            Log::warning("WebhookDeliveryJob: delivery {$this->deliveryId} not found");
            return;
        }

        if ($delivery->status === 'delivered') {
            return;
        }

        $endpoint = WebhookEndpoint::withoutTenantScope()->find($delivery->webhook_endpoint_id);

        if (!$endpoint || !$endpoint->is_active) {
            $delivery->update([
                'status' => 'dead_lettered',
                'error_message' => 'Endpoint not found or inactive',
            ]);
            return;
        }

        $payloadJson = json_encode($delivery->payload);
        $timestamp = time();

        $headers = [
            'Content-Type' => 'application/json',
            'X-Webhook-Signature' => $delivery->signature,
            'X-Webhook-Timestamp' => $timestamp,
            'X-Webhook-Event' => $delivery->event_type,
            'X-Webhook-Delivery' => $delivery->event_id,
        ];

        $startTime = microtime(true);

        try {
            $response = Http::timeout(10)->withHeaders($headers)->post($endpoint->url, $delivery->payload);

            $latency = (int) ((microtime(true) - $startTime) * 1000);

            $delivery->update([
                'attempt_count' => $delivery->attempt_count + 1,
                'last_attempt_at' => now(),
                'request_headers' => $headers,
                'response_status' => $response->status(),
                'response_body' => $response->body(),
                'latency_ms' => $latency,
            ]);

            if ($response->successful()) {
                $delivery->update(['status' => 'delivered', 'error_message' => null]);
                Log::info("WebhookDeliveryJob: delivered event {$delivery->event_type} to {$endpoint->url}");
            } else {
                $delivery->update(['status' => 'failed', 'error_message' => "HTTP {$response->status()}"]);

                if ($delivery->attempt_count >= $this->tries) {
                    $delivery->update(['status' => 'dead_lettered']);
                } else {
                    $this->release($this->backoff[$delivery->attempt_count - 1] ?? 30);
                }
            }
        } catch (\Exception $e) {
            $latency = (int) ((microtime(true) - $startTime) * 1000);

            $delivery->update([
                'attempt_count' => $delivery->attempt_count + 1,
                'last_attempt_at' => now(),
                'request_headers' => $headers,
                'latency_ms' => $latency,
                'error_message' => $e->getMessage(),
                'status' => 'failed',
            ]);

            if ($delivery->attempt_count >= $this->tries) {
                $delivery->update(['status' => 'dead_lettered']);
            } else {
                $this->release($this->backoff[$delivery->attempt_count - 1] ?? 30);
            }
        }
    }
}
