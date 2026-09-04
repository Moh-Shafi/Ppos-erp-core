<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function createCharge(array $paymentData): array;

    public function verifyWebhook(string $payload, array $headers): array;

    public function refund(string $gatewayTransactionId, float $amount, string $reason): array;

    public function getStatus(string $gatewayTransactionId): array;

    public function provisionSubAccount(array $tenantInfo): array;
}
