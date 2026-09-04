<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleRefund;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    public function __construct(
        private JournalEntryService $journalEntryService,
    ) {}

    public function postFor(string $referenceType, int $referenceId): ?JournalEntry
    {
        return DB::transaction(function () use ($referenceType, $referenceId) {
            $model = $this->resolveModel($referenceType, $referenceId);

            if (!$model) {
                return null;
            }

            // Avoid duplicate auto-posting for the same source
            $existing = JournalEntry::withoutTenantScope()
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('source', 'auto')
                ->first();

            if ($existing) {
                return $existing;
            }

            $data = match ($referenceType) {
                'Sale' => $this->buildForSale($model),
                'Purchase' => $this->buildForPurchase($model),
                'Payment' => $this->buildForPayment($model),
                'SaleRefund' => $this->buildForRefund($model),
                default => null,
            };

            if (!$data) {
                return null;
            }

            return $this->journalEntryService->post($data);
        });
    }

    private function resolveModel(string $referenceType, int $referenceId): ?\Illuminate\Database\Eloquent\Model
    {
        return match ($referenceType) {
            'Sale' => Sale::withoutTenantScope()->find($referenceId),
            'Purchase' => Purchase::withoutTenantScope()->find($referenceId),
            'Payment' => Payment::withoutTenantScope()->find($referenceId),
            'SaleRefund' => SaleRefund::withoutTenantScope()->find($referenceId),
            default => null,
        };
    }

    private function buildForSale(Sale $sale): array
    {
        $accounts = $this->getAccountsForTenant($sale->tenant_id);
        $total = (float) $sale->total;
        $date = $sale->sale_date?->toDateString() ?? $sale->created_at->toDateString();
        $isCredit = in_array($sale->payment_status, ['unpaid', 'partial'], true);

        $debitAccount = $isCredit ? $accounts['1-1200'] : $accounts['1-1000'];
        $creditAccount = $accounts['4-1000'];

        return [
            'tenant_id' => $sale->tenant_id,
            'entry_date' => $date,
            'reference_type' => 'Sale',
            'reference_id' => $sale->id,
            'source' => 'auto',
            'description' => 'Auto journal for sale ' . $sale->sale_number,
            'lines' => [
                ['account_id' => $debitAccount, 'debit' => $total, 'credit' => 0, 'description' => 'Sale total'],
                ['account_id' => $creditAccount, 'debit' => 0, 'credit' => $total, 'description' => 'Revenue'],
            ],
        ];
    }

    private function buildForPurchase(Purchase $purchase): array
    {
        $accounts = $this->getAccountsForTenant($purchase->tenant_id);
        $total = (float) $purchase->total;
        $date = $purchase->purchase_date?->toDateString() ?? $purchase->created_at->toDateString();

        return [
            'tenant_id' => $purchase->tenant_id,
            'entry_date' => $date,
            'reference_type' => 'Purchase',
            'reference_id' => $purchase->id,
            'source' => 'auto',
            'description' => 'Auto journal for purchase ' . $purchase->purchase_number,
            'lines' => [
                ['account_id' => $accounts['1-1300'], 'debit' => $total, 'credit' => 0, 'description' => 'Inventory / COGS'],
                ['account_id' => $accounts['2-1000'], 'debit' => 0, 'credit' => $total, 'description' => 'Accounts Payable'],
            ],
        ];
    }

    private function buildForPayment(Payment $payment): array
    {
        $accounts = $this->getAccountsForTenant($payment->tenant_id);
        $amount = (float) $payment->amount;
        $date = $payment->payment_date?->toDateString() ?? $payment->created_at->toDateString();

        if (!$payment->sale_id) {
            return [];
        }

        $sale = Sale::withoutTenantScope()->find($payment->sale_id);

        if (!$sale) {
            return [];
        }

        // Cash sale payment: Dr Cash, Cr AR (to clear the sale AR if any)
        $isCash = $payment->payment_method === 'cash';
        $debitAccount = $isCash ? $accounts['1-1000'] : $accounts['1-1100'];

        return [
            'tenant_id' => $payment->tenant_id,
            'entry_date' => $date,
            'reference_type' => 'Payment',
            'reference_id' => $payment->id,
            'source' => 'auto',
            'description' => 'Payment for sale ' . $sale->sale_number,
            'lines' => [
                ['account_id' => $debitAccount, 'debit' => $amount, 'credit' => 0, 'description' => 'Cash/Bank received'],
                ['account_id' => $accounts['1-1200'], 'debit' => 0, 'credit' => $amount, 'description' => 'Clear AR'],
            ],
        ];
    }

    private function buildForRefund(SaleRefund $refund): array
    {
        $accounts = $this->getAccountsForTenant($refund->tenant_id);
        $amount = (float) $refund->refund_amount;
        $date = $refund->refunded_at?->toDateString() ?? $refund->created_at->toDateString();

        return [
            'tenant_id' => $refund->tenant_id,
            'entry_date' => $date,
            'reference_type' => 'SaleRefund',
            'reference_id' => $refund->id,
            'source' => 'auto',
            'description' => 'Refund for sale ' . ($refund->sale?->sale_number ?? $refund->sale_id),
            'lines' => [
                ['account_id' => $accounts['4-2000'], 'debit' => $amount, 'credit' => 0, 'description' => 'Refund allowance'],
                ['account_id' => $accounts['1-1000'], 'debit' => 0, 'credit' => $amount, 'description' => 'Cash out'],
            ],
        ];
    }

    private function getAccountsForTenant(int $tenantId): array
    {
        return Account::where('tenant_id', $tenantId)
            ->whereIn('code', [
                '1-1000', '1-1100', '1-1200', '1-1300',
                '2-1000', '3-1000', '4-1000', '4-2000',
                '5-1000', '5-1100', '5-1200', '5-1300', '5-1400',
            ])
            ->get()
            ->mapWithKeys(fn ($a) => [$a->code => $a->id])
            ->toArray();
    }
}
