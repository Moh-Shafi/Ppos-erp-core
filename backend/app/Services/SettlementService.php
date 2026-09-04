<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Models\PaymentGatewayAccount;
use App\Models\PaymentSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SettlementService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Sync settlements for a tenant based on Xendit transaction data.
     */
    public function sync(PaymentGatewayAccount $account, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $tenant = $account->tenant;
        $dateFrom = $dateFrom ?? now()->subDays(7)->toDateString();
        $dateTo = $dateTo ?? now()->toDateString();

        $headers = [
            'Authorization' => 'Basic ' . base64_encode(config('payments.gateways.xendit.api_key') . ':'),
            'api-version' => config('payments.gateways.xendit.api_version', '2024-11-11'),
            'for-user-id' => $account->gateway_account_id,
        ];

        $baseUrl = config('payments.gateways.xendit.base_url', 'https://api.xendit.co');

        $response = Http::withHeaders($headers)
            ->get($baseUrl . '/transactions', [
                'from' => $dateFrom,
                'to' => $dateTo,
            ]);

        if (!$response->successful()) {
            Log::error('Xendit transaction fetch failed', [
                'tenant_id' => $tenant->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \DomainException('Settlement sync failed: ' . $response->body());
        }

        $transactions = $response->json()['data'] ?? [];

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($transactions, $tenant, $account, &$created, &$updated) {
            foreach ($transactions as $transaction) {
                $gatewayTransactionId = $transaction['payment_request_id'] ?? $transaction['payment_id'] ?? null;

                if (!$gatewayTransactionId) {
                    continue;
                }

                $payment = Payment::withoutTenantScope()
                    ->where('tenant_id', $tenant->id)
                    ->where('gateway_transaction_id', $gatewayTransactionId)
                    ->first();

                $settlement = PaymentSettlement::withoutTenantScope()
                    ->where('tenant_id', $tenant->id)
                    ->where('settlement_id', $transaction['id'] ?? $transaction['settlement_id'] ?? null)
                    ->first();

                if (!$settlement) {
                    $settlement = new PaymentSettlement();
                    $settlement->tenant_id = $tenant->id;
                    $settlement->gateway = 'xendit';
                    $settlement->settlement_id = $transaction['id'] ?? $transaction['settlement_id'] ?? null;
                    $created++;
                } else {
                    $updated++;
                }

                $settlement->payment_id = $payment?->id;
                $settlement->gross_amount = $transaction['gross_amount'] ?? $transaction['amount'] ?? 0;
                $settlement->platform_fee = $transaction['platform_fee'] ?? 0;
                $settlement->net_amount = $transaction['net_amount'] ?? ($settlement->gross_amount - $settlement->platform_fee);
                $settlement->settled_at = $transaction['settled_at'] ?? $transaction['created'] ?? null;
                $settlement->status = $transaction['status'] ?? 'settled';
                $settlement->metadata = $transaction;
                $settlement->save();

                if ($payment) {
                    $payment->settlement_amount = $settlement->net_amount;
                    $payment->platform_fee = $settlement->platform_fee;
                    $payment->net_amount = $settlement->net_amount;
                    $payment->settled_at = $settlement->settled_at;
                    $payment->save();
                }
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Reconcile internal payments against Xendit settlement data.
     */
    public function reconcile(int $tenantId, string $dateFrom, string $dateTo): array
    {
        $internal = Payment::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('status', 'success')
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo)
            ->get()
            ->keyBy('gateway_transaction_id');

        $settlements = PaymentSettlement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereDate('settled_at', '>=', $dateFrom)
            ->whereDate('settled_at', '<=', $dateTo)
            ->get()
            ->keyBy(function ($s) {
                return $s->metadata['payment_request_id'] ?? $s->metadata['payment_id'] ?? $s->settlement_id;
            });

        $matched = [];
        $mismatched = [];
        $missingSettlements = [];
        $missingPayments = [];

        foreach ($internal as $txId => $payment) {
            $settlement = $settlements->get($txId);

            if (!$settlement) {
                $missingSettlements[] = [
                    'gateway_transaction_id' => $txId,
                    'internal_amount' => $payment->amount,
                ];
                continue;
            }

            if ((float) $payment->amount !== (float) $settlement->gross_amount) {
                $mismatched[] = [
                    'gateway_transaction_id' => $txId,
                    'internal_amount' => $payment->amount,
                    'xendit_amount' => $settlement->gross_amount,
                ];
                continue;
            }

            $matched[] = [
                'gateway_transaction_id' => $txId,
                'payment_id' => $payment->id,
                'settlement_id' => $settlement->id,
                'amount' => $payment->amount,
            ];
        }

        foreach ($settlements as $txId => $settlement) {
            if (!$internal->has($txId)) {
                $missingPayments[] = [
                    'gateway_transaction_id' => $txId,
                    'settlement_id' => $settlement->id,
                    'xendit_amount' => $settlement->gross_amount,
                ];
            }
        }

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'internal_total' => $internal->sum('amount'),
            'xendit_total' => $settlements->sum('gross_amount'),
            'matched_count' => count($matched),
            'mismatched_count' => count($mismatched),
            'missing_settlement_count' => count($missingSettlements),
            'missing_payment_count' => count($missingPayments),
            'matched' => $matched,
            'mismatched' => $mismatched,
            'missing_settlements' => $missingSettlements,
            'missing_payments' => $missingPayments,
        ];
    }
}
