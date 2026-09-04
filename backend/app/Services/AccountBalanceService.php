<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\FiscalPeriod;
use Illuminate\Support\Facades\DB;

class AccountBalanceService
{
    public function recalculate(int $tenantId, int $fiscalPeriodId): void
    {
        $period = FiscalPeriod::where('tenant_id', $tenantId)->findOrFail($fiscalPeriodId);

        DB::transaction(function () use ($tenantId, $period) {
            AccountBalance::where('tenant_id', $tenantId)
                ->where('fiscal_period_id', $period->id)
                ->delete();

            $accounts = Account::where('tenant_id', $tenantId)->pluck('id');

            foreach ($accounts as $accountId) {
                $aggregates = DB::table('journal_entry_lines')
                    ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                    ->where('journal_entries.tenant_id', $tenantId)
                    ->where('journal_entry_lines.account_id', $accountId)
                    ->whereBetween('journal_entries.entry_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                    ->select(
                        DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                        DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
                    )
                    ->first();

                // Previous period closing as opening
                $previousPeriod = FiscalPeriod::where('tenant_id', $tenantId)
                    ->where('end_date', '<', $period->start_date)
                    ->orderByDesc('end_date')
                    ->first();

                $opening = 0.0;
                if ($previousPeriod) {
                    $prevBalance = AccountBalance::where('tenant_id', $tenantId)
                        ->where('account_id', $accountId)
                        ->where('fiscal_period_id', $previousPeriod->id)
                        ->first();

                    $opening = $prevBalance ? (float) $prevBalance->closing_balance : 0.0;
                }

                $debits = (float) $aggregates->total_debit;
                $credits = (float) $aggregates->total_credit;

                $closing = $this->closingFor(Account::find($accountId), $opening, $debits, $credits);

                AccountBalance::create([
                    'tenant_id' => $tenantId,
                    'account_id' => $accountId,
                    'fiscal_period_id' => $period->id,
                    'opening_balance' => $opening,
                    'period_debits' => $debits,
                    'period_credits' => $credits,
                    'closing_balance' => $closing,
                ]);
            }
        });
    }

    private function closingFor(Account $account, float $opening, float $debits, float $credits): float
    {
        return match ($account->type) {
            'asset', 'expense' => $opening + $debits - $credits,
            'liability', 'equity', 'revenue' => $opening - $debits + $credits,
            default => $opening + $debits - $credits,
        };
    }
}
