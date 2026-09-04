<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentGatewayAccount;
use App\Models\PaymentWebhook;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookProcessor
{
    public function __construct(
        private SubAccountService $subAccountService,
    ) {}

    /**
     * Process a verified webhook.
     */
    public function process(PaymentWebhook $webhook): void
    {
        $payload = $webhook->payload;
        $event = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        try {
            match (true) {
                str_starts_with($event, 'payment.') => $this->processPaymentEvent($event, $data),
                str_starts_with($event, 'account_holder.') => $this->processAccountHolderEvent($event, $data),
                str_starts_with($event, 'account.') => $this->processAccountEvent($event, $data),
                default => $this->logUnhandled($webhook, $event),
            };

            $webhook->processed = true;
            $webhook->processed_at = now();
            $webhook->save();
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', [
                'webhook_id' => $webhook->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            $webhook->error_message = $e->getMessage();
            $webhook->save();
        }
    }

    /**
     * Process payment lifecycle events.
     */
    private function processPaymentEvent(string $event, array $data): void
    {
        $gatewayTransactionId = $data['payment_request_id'] ?? $data['payment_id'] ?? null;

        if (!$gatewayTransactionId) {
            throw new \DomainException('Missing gateway transaction ID in payment webhook');
        }

        $payment = Payment::withoutTenantScope()
            ->where('gateway_transaction_id', $gatewayTransactionId)
            ->first();

        if (!$payment) {
            throw new \DomainException('Payment not found for gateway transaction: ' . $gatewayTransactionId);
        }

        DB::transaction(function () use ($payment, $data, $event) {
            $payment = Payment::lockForUpdate()->find($payment->id);

            $gatewayStatus = match ($event) {
                'payment.capture' => $data['status'] ?? 'SUCCEEDED',
                'payment.failure' => 'FAILED',
                'payment.authorization' => 'AUTHORIZED',
                'payment.pending' => 'PENDING',
                default => $data['status'] ?? 'PENDING',
            };

            $payment->gateway_status = $gatewayStatus;
            $payment->gateway_response = $data;

            $status = match ($gatewayStatus) {
                'SUCCEEDED' => 'success',
                'FAILED' => 'failed',
                'EXPIRED' => 'failed',
                'CANCELED' => 'failed',
                default => 'pending',
            };

            if ($status !== 'pending') {
                $payment->status = $status;
            }

            if ($gatewayStatus === 'SUCCEEDED') {
                $payment->payment_date = $data['created'] ?? now();
            }

            $payment->save();

            if ($gatewayStatus === 'SUCCEEDED') {
                $this->updateSalePaymentStatus($payment);
            }
        });
    }

    /**
     * Update sale payment status after successful payment.
     */
    private function updateSalePaymentStatus(Payment $payment): void
    {
        $sale = Sale::withoutTenantScope()->where('id', $payment->sale_id)->first();

        if (!$sale) {
            return;
        }

        $sale = Sale::lockForUpdate()->find($sale->id);

        $paid = (float) Payment::withoutTenantScope()
            ->where('sale_id', $sale->id)
            ->where('status', 'success')
            ->sum('amount');

        $sale->paid_amount = $paid;

        if ($paid >= (float) $sale->total) {
            $sale->payment_status = 'paid';
            $sale->change_amount = $paid - (float) $sale->total;
        } elseif ($paid > 0) {
            $sale->payment_status = 'partial';
        } else {
            $sale->payment_status = 'unpaid';
        }

        $sale->save();
    }

    /**
     * Process account holder KYC/capability events.
     */
    private function processAccountHolderEvent(string $event, array $data): void
    {
        $gatewayAccountId = $data['business_id'] ?? $data['user_id'] ?? null;

        if (!$gatewayAccountId) {
            throw new \DomainException('Missing business ID in account holder webhook');
        }

        $updates = [];

        if (str_contains($event, 'kyc.status:passed')) {
            $updates['kyc_status'] = 'passed';
        }

        if (str_contains($event, 'kyc.status:resubmission_required')) {
            $updates['kyc_status'] = 'resubmission_required';
        }

        if (str_contains($event, 'kyc.status:failed')) {
            $updates['kyc_status'] = 'failed';
        }

        if (str_contains($event, 'capabilities.status:live')) {
            $updates['status'] = 'active';
            $updates['capabilities'] = $data['capabilities'] ?? [];
        }

        if (str_contains($event, 'capabilities.status:declined')) {
            $updates['status'] = 'rejected';
        }

        $this->subAccountService->updateStatus($gatewayAccountId, $updates);
    }

    /**
     * Process xenPlatform account events.
     */
    private function processAccountEvent(string $event, array $data): void
    {
        $gatewayAccountId = $data['user_id'] ?? $data['business_id'] ?? null;

        if (!$gatewayAccountId) {
            return;
        }

        if ($event === 'account.activated') {
            $this->subAccountService->updateStatus($gatewayAccountId, [
                'status' => 'active',
            ]);
        }
    }

    /**
     * Log unhandled webhook events.
     */
    private function logUnhandled(PaymentWebhook $webhook, string $event): void
    {
        Log::info('Unhandled webhook event', [
            'webhook_id' => $webhook->id,
            'event' => $event,
        ]);
    }
}
