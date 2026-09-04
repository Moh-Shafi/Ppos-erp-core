<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function trialBalance(int $tenantId, string $startDate, string $endDate): array
    {
        $lines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->select(
                'accounts.id as account_id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            )
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get();

        $debit = $lines->sum('total_debit');
        $credit = $lines->sum('total_credit');

        return [
            'period' => ['from' => $startDate, 'to' => $endDate],
            'total_debit' => (float) $debit,
            'total_credit' => (float) $credit,
            'is_balanced' => abs((float) $debit - (float) $credit) < 0.001,
            'accounts' => $lines->toArray(),
        ];
    }

    public function profitAndLoss(int $tenantId, string $startDate, string $endDate): array
    {
        $lines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->whereIn('accounts.type', ['revenue', 'expense'])
            ->select(
                'accounts.id as account_id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            )
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get();

        $revenue = 0.0;
        $expense = 0.0;

        foreach ($lines as $line) {
            if ($line->type === 'revenue') {
                $revenue += (float) $line->total_credit - (float) $line->total_debit;
            }
            if ($line->type === 'expense') {
                $expense += (float) $line->total_debit - (float) $line->total_credit;
            }
        }

        return [
            'period' => ['from' => $startDate, 'to' => $endDate],
            'revenue' => $revenue,
            'expenses' => $expense,
            'net_income' => $revenue - $expense,
            'accounts' => $lines->toArray(),
        ];
    }

    public function balanceSheet(int $tenantId, string $asOfDate): array
    {
        $lines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->whereDate('journal_entries.entry_date', '<=', $asOfDate)
            ->whereIn('accounts.type', ['asset', 'liability', 'equity'])
            ->select(
                'accounts.id as account_id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            )
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get();

        $assets = 0.0;
        $liabilities = 0.0;
        $equity = 0.0;

        foreach ($lines as $line) {
            $balance = match ($line->type) {
                'asset' => (float) $line->total_debit - (float) $line->total_credit,
                'liability' => (float) $line->total_credit - (float) $line->total_debit,
                'equity' => (float) $line->total_credit - (float) $line->total_debit,
                default => 0.0,
            };

            match ($line->type) {
                'asset' => $assets += $balance,
                'liability' => $liabilities += $balance,
                'equity' => $equity += $balance,
                default => null,
            };
        }

        return [
            'as_of' => $asOfDate,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'is_balanced' => abs($assets - ($liabilities + $equity)) < 0.001,
            'accounts' => $lines->toArray(),
        ];
    }

    public function ledger(int $accountId, string $startDate, string $endDate): array
    {
        $lines = JournalEntryLine::with('journalEntry')
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->where('account_id', $accountId)
            ->orderBy('journalEntry.entry_date')
            ->get();

        $account = Account::findOrFail($accountId);

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'period' => ['from' => $startDate, 'to' => $endDate],
            'lines' => $lines->toArray(),
        ];
    }
}
