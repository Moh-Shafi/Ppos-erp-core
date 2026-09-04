<?php

namespace App\Services;

use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    public function post(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            $tenantId = $data['tenant_id'];
            $entryDate = $data['entry_date'];
            $fiscalPeriod = $this->resolveFiscalPeriod($tenantId, $entryDate);

            if ($fiscalPeriod?->isClosed()) {
                throw new \DomainException('Cannot post to a closed fiscal period');
            }

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($data['lines'] as $line) {
                $totalDebit += (float) ($line['debit'] ?? 0);
                $totalCredit += (float) ($line['credit'] ?? 0);
            }

            if (abs($totalDebit - $totalCredit) > 0.001) {
                throw new \DomainException('Journal entry is not balanced: debit ' . $totalDebit . ' != credit ' . $totalCredit);
            }

            if ($totalDebit === 0.0 && $totalCredit === 0.0) {
                throw new \DomainException('Journal entry must have non-zero debit or credit');
            }

            $entry = new JournalEntry();
            $entry->tenant_id = $tenantId;
            $entry->entry_number = $this->nextEntryNumber($tenantId);
            $entry->entry_date = $entryDate;
            $entry->fiscal_period_id = $fiscalPeriod?->id;
            $entry->reference_type = $data['reference_type'] ?? null;
            $entry->reference_id = $data['reference_id'] ?? null;
            $entry->source = $data['source'] ?? 'manual';
            $entry->description = $data['description'] ?? null;
            $entry->total_debit = $totalDebit;
            $entry->total_credit = $totalCredit;
            $entry->posted_by = $data['posted_by'] ?? Auth::id();
            $entry->posted_at = now();
            $entry->save();

            foreach ($data['lines'] as $index => $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if ($debit === 0.0 && $credit === 0.0) {
                    continue;
                }

                JournalEntryLine::create([
                    'tenant_id' => $tenantId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'line_number' => $index + 1,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $line['description'] ?? null,
                    'reference_type' => $line['reference_type'] ?? null,
                    'reference_id' => $line['reference_id'] ?? null,
                    'currency' => $line['currency'] ?? 'IDR',
                    'exchange_rate' => $line['exchange_rate'] ?? 1,
                ]);
            }

            $this->updateAccountBalances($entry);

            return $entry->fresh('lines.account');
        });
    }

    private function resolveFiscalPeriod(int $tenantId, string $entryDate): ?FiscalPeriod
    {
        return FiscalPeriod::where('tenant_id', $tenantId)
            ->whereDate('start_date', '<=', $entryDate)
            ->whereDate('end_date', '>=', $entryDate)
            ->first();
    }

    private function nextEntryNumber(int $tenantId): string
    {
        $count = JournalEntry::where('tenant_id', $tenantId)->count() + 1;
        return 'JE-' . now()->format('Y') . '-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }

    private function updateAccountBalances(JournalEntry $entry): void
    {
        if (!$entry->fiscal_period_id) {
            return;
        }

        foreach ($entry->lines as $line) {
            $balance = \App\Models\AccountBalance::firstOrNew([
                'tenant_id' => $entry->tenant_id,
                'account_id' => $line->account_id,
                'fiscal_period_id' => $entry->fiscal_period_id,
            ]);

            $balance->period_debits = (float) $balance->period_debits + (float) $line->debit;
            $balance->period_credits = (float) $balance->period_credits + (float) $line->credit;
            $balance->closing_balance = $this->calculateClosingBalance($line->account, $balance);
            $balance->save();
        }
    }

    private function calculateClosingBalance(\App\Models\Account $account, \App\Models\AccountBalance $balance): float
    {
        $opening = (float) $balance->opening_balance;
        $debits = (float) $balance->period_debits;
        $credits = (float) $balance->period_credits;

        return match ($account->type) {
            'asset', 'expense' => $opening + $debits - $credits,
            'liability', 'equity', 'revenue' => $opening - $debits + $credits,
            default => $opening + $debits - $credits,
        };
    }
}
