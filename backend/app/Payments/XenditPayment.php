<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditPayment implements PaymentGatewayInterface
{
    public function __construct(
        private string $apiKey,
        private string $baseUrl,
        private string $apiVersion,
    ) {}

    /**
     * Create a payment request via Xendit Payment Request API.
     */
    public function createCharge(array $paymentData): array
    {
        $forUserId = $paymentData['for_user_id'] ?? null;
        $feeRule = $paymentData['fee_rule'] ?? null;

        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
            'Content-Type' => 'application/json',
            'api-version' => $this->apiVersion,
        ];

        if ($forUserId) {
            $headers['for-user-id'] = $forUserId;
        }

        if ($feeRule) {
            $headers['with-fee-rule'] = $feeRule;
        }

        if (!empty($paymentData['idempotency_key'])) {
            $headers['Idempotency-Key'] = $paymentData['idempotency_key'];
        }

        $body = $this->buildPaymentRequestBody($paymentData);

        $response = Http::withHeaders($headers)
            ->post($this->baseUrl . '/payment_requests', $body);

        if (!$response->successful()) {
            Log::error('Xendit createCharge failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \DomainException('Gateway charge creation failed: ' . $response->body());
        }

        $data = $response->json();

        return $this->normalizeCreateChargeResponse($data, $paymentData);
    }

    /**
     * Verify Xendit webhook payload.
     */
    public function verifyWebhook(string $payload, array $headers): array
    {
        $callbackToken = $headers['x-callback-token'] ?? $headers['X-Callback-Token'] ?? '';
        $expected = config('payments.gateways.xendit.webhook_token', '');

        $verified = !empty($callbackToken) && hash_equals($expected, $callbackToken);

        if (!$verified) {
            return [
                'verified' => false,
                'event_type' => 'none',
                'gateway_transaction_id' => null,
                'amount' => 0,
                'paid_at' => null,
            ];
        }

        $decoded = json_decode($payload, true) ?? [];
        $event = $decoded['event'] ?? 'unknown';

        $data = $decoded['data'] ?? [];
        $status = $data['status'] ?? null;

        return [
            'verified' => true,
            'event_type' => $event,
            'gateway_transaction_id' => $data['payment_request_id'] ?? $data['payment_id'] ?? null,
            'amount' => (float) ($data['request_amount'] ?? $data['amount'] ?? 0),
            'paid_at' => $data['created'] ?? null,
            'status' => $status,
            'payload' => $decoded,
        ];
    }

    /**
     * Refund a payment via Xendit Refund API.
     */
    public function refund(string $gatewayTransactionId, float $amount, string $reason): array
    {
        $forUserId = $reason['for_user_id'] ?? null;

        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
            'Content-Type' => 'application/json',
            'api-version' => $this->apiVersion,
        ];

        if ($forUserId) {
            $headers['for-user-id'] = $forUserId;
        }

        $body = [
            'amount' => $amount,
            'reason' => is_string($reason) ? $reason : 'Customer request',
        ];

        $response = Http::withHeaders($headers)
            ->post($this->baseUrl . '/refunds?payment_request_id=' . urlencode($gatewayTransactionId), $body);

        if (!$response->successful()) {
            Log::error('Xendit refund failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \DomainException('Gateway refund failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'refund_id' => $data['id'] ?? $data['refund_id'] ?? null,
            'status' => 'success',
            'amount' => $amount,
            'gateway_response' => $data,
        ];
    }

    /**
     * Get status of a payment request.
     */
    public function getStatus(string $gatewayTransactionId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
            'api-version' => $this->apiVersion,
        ])->get($this->baseUrl . '/payment_requests/' . urlencode($gatewayTransactionId));

        if (!$response->successful()) {
            Log::error('Xendit getStatus failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \DomainException('Gateway status check failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'status' => $data['status'] ?? 'UNKNOWN',
            'amount' => (float) ($data['request_amount'] ?? $data['amount'] ?? 0),
            'paid_at' => $data['paid_at'] ?? null,
            'settlement_amount' => $data['settlement_amount'] ?? null,
            'platform_fee' => $data['platform_fee'] ?? null,
            'net_amount' => $data['net_amount'] ?? null,
            'gateway_response' => $data,
        ];
    }

    /**
     * Provision a Xendit sub-account (xenPlatform).
     */
    public function provisionSubAccount(array $tenantInfo): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
            'Content-Type' => 'application/json',
            'api-version' => $this->apiVersion,
        ])->post($this->baseUrl . '/v2/accounts', [
            'type' => $tenantInfo['type'] ?? 'OWNED',
            'account_email' => $tenantInfo['business_email'] ?? 'noreply@' . ($tenantInfo['tenant_id'] ?? 'tenant') . '.local',
            'public_profile' => [
                'business_name' => $tenantInfo['business_name'] ?? 'Tenant ' . ($tenantInfo['tenant_id'] ?? ''),
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Xendit provisionSubAccount failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \DomainException('Gateway sub-account provisioning failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'gateway_account_id' => $data['id'] ?? $data['user_id'] ?? null,
            'status' => 'pending',
            'gateway_response' => $data,
        ];
    }

    /**
     * Build Payment Request body.
     */
    private function buildPaymentRequestBody(array $paymentData): array
    {
        $method = $paymentData['payment_method'] ?? 'qris';
        $amount = (float) ($paymentData['amount'] ?? 0);
        $reference = $paymentData['reference_id'] ?? ('REF-' . uniqid());

        $body = [
            'reference_id' => $reference,
            'type' => 'PAY',
            'country' => $paymentData['country'] ?? 'ID',
            'currency' => $paymentData['currency'] ?? 'IDR',
            'request_amount' => $amount,
            'capture_method' => 'AUTOMATIC',
            'description' => $paymentData['description'] ?? 'Payment',
            'metadata' => $paymentData['metadata'] ?? [],
        ];

        switch ($method) {
            case 'qris':
                $body['payment_method'] = [
                    'type' => 'QR_CODE',
                    'qr_code' => [
                        'channel_code' => 'QRIS',
                    ],
                    'reusability' => 'ONE_TIME_USE',
                ];
                break;

            case 'card':
                $body['channel_code'] = 'CARDS';
                $body['channel_properties'] = $paymentData['channel_properties'] ?? [];
                break;

            case 'bank_transfer':
            case 'virtual_account':
                $body['payment_method'] = [
                    'type' => 'VIRTUAL_ACCOUNT',
                    'virtual_account' => [
                        'channel_code' => $paymentData['bank_code'] ?? 'MANDIRI',
                        'channel_properties' => [
                            'expires_at' => $paymentData['expires_at'] ?? now()->addHours(24)->toIso8601String(),
                        ],
                    ],
                    'reusability' => 'ONE_TIME_USE',
                ];
                break;

            default:
                throw new \InvalidArgumentException('Unsupported payment method: ' . $method);
        }

        return $body;
    }

    /**
     * Normalize createCharge response.
     */
    private function normalizeCreateChargeResponse(array $data, array $paymentData): array
    {
        $qrString = null;
        $paymentUrl = null;

        if (!empty($data['actions'])) {
            foreach ($data['actions'] as $action) {
                if (($action['type'] ?? '') === 'QR_DISPLAY') {
                    $qrString = $action['value'] ?? null;
                }
                if (($action['type'] ?? '') === 'REDIRECT_CUSTOMER') {
                    $paymentUrl = $action['value'] ?? null;
                }
            }
        }

        return [
            'gateway_transaction_id' => $data['id'] ?? $data['payment_request_id'] ?? null,
            'status' => 'pending',
            'gateway_status' => $data['status'] ?? 'REQUIRES_ACTION',
            'gateway_response' => $data,
            'expires_at' => $data['expires_at'] ?? null,
            'qr_string' => $qrString,
            'payment_url' => $paymentUrl,
            'metadata' => [
                'qr_string' => $qrString,
                'payment_url' => $paymentUrl,
                'actions' => $data['actions'] ?? [],
            ],
        ];
    }
}
