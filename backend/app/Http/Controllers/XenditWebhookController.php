<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewayAccount;
use App\Models\PaymentWebhook;
use App\Services\SubAccountService;
use App\Services\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function __construct(
        private WebhookProcessor $webhookProcessor,
        private SubAccountService $subAccountService,
    ) {}

    /**
     * Receive and process Xendit webhooks.
     */
    public function handle(Request $request)
    {
        $token = $request->header('x-callback-token');
        $expected = config('payments.gateways.xendit.webhook_token', '');

        if (empty($expected)) {
            Log::warning('Xendit webhook token not configured');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (empty($token) || !hash_equals($expected, $token)) {
            Log::warning('Invalid Xendit webhook token', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        if (empty($payload['event'])) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        $event = $payload['event'];
        $businessId = $payload['business_id'] ?? $payload['data']['business_id'] ?? null;
        $eventId = $businessId . ':' . ($payload['data']['payment_id'] ?? $payload['data']['payment_request_id'] ?? $payload['data']['user_id'] ?? uniqid()) . ':' . $event;

        $existing = PaymentWebhook::where('gateway', 'xendit')
            ->where('event_id', $eventId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'OK']);
        }

        // Resolve tenant for audit
        $tenant = null;
        if ($businessId) {
            $account = PaymentGatewayAccount::where('gateway_account_id', $businessId)
                ->where('gateway', 'xendit')
                ->first();
            $tenant = $account?->tenant;
        }

        $webhook = new PaymentWebhook();
        $webhook->tenant_id = $tenant?->id;
        $webhook->gateway = 'xendit';
        $webhook->event_id = $eventId;
        $webhook->event_type = $event;
        $webhook->payload = $payload;
        $webhook->headers = $request->headers->all();
        $webhook->verified = true;
        $webhook->processed = false;
        $webhook->save();

        $this->webhookProcessor->process($webhook);

        return response()->json(['message' => 'OK']);
    }
}
