<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Models\PaymentGatewayAccount;
use App\Models\Sale;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public const VALID_METHODS = ['cash', 'qris', 'card', 'bank_transfer'];

    public function __construct(
        private PaymentGatewayInterface $gateway,
        private SubAccountService $subAccountService,
    ) {}

    /**
     * Create payments for a checkout transaction.
     * Called within SaleService::checkout() inside a DB::transaction.
     *
     * @param  Sale  $sale  The freshly created sale
     * @param  array  $payments  Array of payment data from checkout request
     * @param  int  $tenantId  Tenant ID from Auth
     * @return array Array of created Payment models
     * @throws \DomainException  For duplicate references or invalid data
     */
    public function createForCheckout(Sale $sale, array $payments, int $tenantId): array
    {
        $created = [];
        $seenKeys = [];
        $seenReferences = [];

        foreach ($payments as $pay) {
            $this->validatePaymentData($pay);

            $reference = $pay['payment_reference'] ?? null;
            $idempotencyKey = $pay['idempotency_key'] ?? null;

            // Check duplicate reference within same request
            if ($reference) {
                if (in_array($reference, $seenReferences)) {
                    throw new \DomainException("Duplicate payment reference within request: {$reference}");
                }
                $seenReferences[] = $reference;

                // Check against existing payments in DB
                $existing = Payment::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('payment_reference', $reference)
                    ->where('status', '!=', 'failed')
                    ->exists();
                if ($existing) {
                    throw new \DomainException("Payment reference already exists: {$reference}");
                }
            }

            // Check duplicate idempotency key within same request
            if ($idempotencyKey) {
                if (in_array($idempotencyKey, $seenKeys)) {
                    throw new \DomainException("Duplicate idempotency key within request: {$idempotencyKey}");
                }
                $seenKeys[] = $idempotencyKey;

                $existing = Payment::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('status', '!=', 'failed')
                    ->exists();
                if ($existing) {
                    throw new \DomainException("Idempotency key already used: {$idempotencyKey}");
                }
            }

            $isCash = $pay['payment_method'] === 'cash';
            $gateway = config('payments.default_gateway', 'manual');

            $payment = new Payment;
            $payment->tenant_id = $tenantId;
            $payment->sale_id = $sale->id;
            $payment->payment_method = $pay['payment_method'];
            $payment->amount = $pay['amount'];
            $payment->change_amount = 0;
            $payment->payment_reference = $reference;
            $payment->idempotency_key = $idempotencyKey;
            $payment->status = 'success';
            $payment->metadata = $pay['metadata'] ?? null;
            $payment->payment_date = now();

            if (!$isCash && $gateway === 'xendit') {
                $account = $this->subAccountService->getActive($sale->tenant);

                if (!$account || $account->status !== 'active') {
                    throw new \DomainException('Gateway not active for tenant');
                }

                $result = $this->gateway->createCharge([
                    'payment_method' => $pay['payment_method'],
                    'amount' => (float) $pay['amount'],
                    'reference_id' => $sale->sale_number . '-' . ($idempotencyKey ?? uniqid()),
                    'idempotency_key' => $idempotencyKey ?? uniqid(),
                    'for_user_id' => $sale->tenant->xendit_user_id,
                    'fee_rule' => $sale->tenant->xendit_fee_rule_id,
                    'description' => 'Payment for ' . $sale->sale_number,
                    'metadata' => [
                        'sale_id' => $sale->id,
                        'tenant_id' => $tenantId,
                    ],
                ]);

                $payment->status = 'pending';
                $payment->gateway_transaction_id = $result['gateway_transaction_id'];
                $payment->gateway_status = $result['gateway_status'];
                $payment->gateway_response = $result['gateway_response'];
                $payment->expires_at = $result['expires_at'];
                $payment->gateway_account_id = $account->gateway_account_id;
                $payment->metadata = $result['metadata'];
                $payment->payment_date = null;
            }

            try {
                $payment->save();
            } catch (QueryException $e) {
                // Race condition: another request inserted same key/reference between check and insert
                if ($e->errorInfo[1] === 1062) {
                    if ($idempotencyKey) {
                        throw new \DomainException("Idempotency key already used: {$idempotencyKey}");
                    }
                    if ($reference) {
                        throw new \DomainException("Payment reference already exists: {$reference}");
                    }
                }
                throw $e;
            }

            $created[] = $payment;
        }

        return $created;
    }

    /**
     * Add a payment to an existing sale (e.g., partial → paid).
     * Atomic: locks sale row, recalculates paid_amount and payment_status.
     *
     * @throws \DomainException  For invalid sale state, duplicate reference, overpayment
     * @throws \InvalidArgumentException  For invalid amount
     */
    public function addPayment(Sale $sale, array $data): Payment
    {
        return DB::transaction(function () use ($sale, $data) {
            // Lock sale row
            $sale = Sale::withoutTenantScope()
                ->where('id', $sale->id)
                ->lockForUpdate()
                ->first();

            if (!$sale) {
                throw new \DomainException('Sale not found');
            }

            if ($sale->status !== 'completed') {
                throw new \DomainException('Can only add payment to a completed sale');
            }

            if ($sale->payment_status === 'paid') {
                throw new \DomainException('Sale is already fully paid');
            }

            $this->validatePaymentData($data);

            $tenantId = $sale->tenant_id;
            $reference = $data['payment_reference'] ?? null;
            $idempotencyKey = $data['idempotency_key'] ?? null;

            // Idempotency check: payment_reference
            if ($reference) {
                $existing = Payment::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('payment_reference', $reference)
                    ->where('status', '!=', 'failed')
                    ->exists();
                if ($existing) {
                    throw new \DomainException("Payment reference already exists: {$reference}");
                }
            }

            // Idempotency check: idempotency_key
            if ($idempotencyKey) {
                $existing = Payment::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('status', '!=', 'failed')
                    ->exists();
                if ($existing) {
                    throw new \DomainException("Idempotency key already used: {$idempotencyKey}");
                }
            }

            $amount = (float) $data['amount'];
            $currentPaid = (float) $sale->paid_amount;
            $total = (float) $sale->total;
            $newPaid = $currentPaid + $amount;

            // Prevent overpayment
            if ($newPaid > $total) {
                $outstanding = $total - $currentPaid;
                throw new \DomainException(
                    "Payment amount exceeds outstanding balance. Outstanding: {$outstanding}, Payment: {$amount}"
                );
            }

            // Create payment
            $isCash = $data['payment_method'] === 'cash';
            $gateway = config('payments.default_gateway', 'manual');

            $payment = new Payment;
            $payment->tenant_id = $tenantId;
            $payment->sale_id = $sale->id;
            $payment->payment_method = $data['payment_method'];
            $payment->amount = $amount;
            $payment->change_amount = 0;
            $payment->payment_reference = $reference;
            $payment->idempotency_key = $idempotencyKey;
            $payment->status = 'success';
            $payment->metadata = $data['metadata'] ?? null;
            $payment->payment_date = now();

            if (!$isCash && $gateway === 'xendit') {
                $account = $this->subAccountService->getActive($sale->tenant);

                if (!$account || $account->status !== 'active') {
                    throw new \DomainException('Gateway not active for tenant');
                }

                $result = $this->gateway->createCharge([
                    'payment_method' => $data['payment_method'],
                    'amount' => $amount,
                    'reference_id' => $sale->sale_number . '-' . ($idempotencyKey ?? uniqid()),
                    'idempotency_key' => $idempotencyKey ?? uniqid(),
                    'for_user_id' => $sale->tenant->xendit_user_id,
                    'fee_rule' => $sale->tenant->xendit_fee_rule_id,
                    'description' => 'Payment for ' . $sale->sale_number,
                    'metadata' => [
                        'sale_id' => $sale->id,
                        'tenant_id' => $tenantId,
                    ],
                ]);

                $payment->status = 'pending';
                $payment->gateway_transaction_id = $result['gateway_transaction_id'];
                $payment->gateway_status = $result['gateway_status'];
                $payment->gateway_response = $result['gateway_response'];
                $payment->expires_at = $result['expires_at'];
                $payment->gateway_account_id = $account->gateway_account_id;
                $payment->metadata = $result['metadata'];
                $payment->payment_date = null;
            }

            try {
                $payment->save();
            } catch (QueryException $e) {
                if ($e->errorInfo[1] === 1062) {
                    if ($idempotencyKey) {
                        throw new \DomainException("Idempotency key already used: {$idempotencyKey}");
                    }
                    if ($reference) {
                        throw new \DomainException("Payment reference already exists: {$reference}");
                    }
                }
                throw $e;
            }

            // Update sale (only for synchronous/cash payments; async gateway updated by webhook)
            if ($payment->status === 'success') {
                $sale->paid_amount = $newPaid;
                if ($newPaid >= $total) {
                    $sale->payment_status = 'paid';
                    $sale->change_amount = $newPaid - $total;
                } else {
                    $sale->payment_status = 'partial';
                }
                $sale->save();
            }

            return $payment;
        });
    }

    /**
     * Refund all successful payments for a sale.
     * Called within SaleService::cancel() inside a DB::transaction.
     */
    public function refundPayments(Sale $sale, int $tenantId): void
    {
        $payments = Payment::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $sale->id)
            ->where('status', 'success')
            ->get();

        foreach ($payments as $payment) {
            $payment->status = 'refunded';
            $payment->save();
        }
    }

    /**
     * Validate a single payment's data.
     *
     * @throws \DomainException  For invalid method or amount
     */
    private function validatePaymentData(array $pay): void
    {
        if (!in_array($pay['payment_method'], self::VALID_METHODS)) {
            throw new \DomainException("Invalid payment method: {$pay['payment_method']}");
        }

        $amount = (float) $pay['amount'];
        if ($amount <= 0) {
            throw new \DomainException('Payment amount must be greater than 0');
        }
    }
}
