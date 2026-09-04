<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;

class ManualPayment implements PaymentGatewayInterface
{
    public function createCharge(array $paymentData): array
    {
        return [
            'gateway_transaction_id' => 'MANUAL-' . uniqid(),
            'status' => 'success',
            'gateway_response' => ['method' => 'manual'],
            'expires_at' => null,
            'payment_url' => null,
            'qr_string' => null,
        ];
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        return [
            'verified' => false,
            'event_type' => 'none',
            'gateway_transaction_id' => null,
            'amount' => 0,
            'paid_at' => null,
        ];
    }

    public function refund(string $gatewayTransactionId, float $amount, string $reason): array
    {
        return [
            'refund_id' => 'MANUAL-REF-' . uniqid(),
            'status' => 'success',
            'amount' => $amount,
        ];
    }

    public function getStatus(string $gatewayTransactionId): array
    {
        return [
            'status' => 'success',
            'amount' => 0,
            'paid_at' => now()->toIso8601String(),
            'settlement_amount' => null,
            'platform_fee' => null,
            'net_amount' => null,
        ];
    }

    public function provisionSubAccount(array $tenantInfo): array
    {
        return [
            'gateway_account_id' => 'MANUAL-' . $tenantInfo['tenant_id'],
            'status' => 'active',
        ];
    }
}
