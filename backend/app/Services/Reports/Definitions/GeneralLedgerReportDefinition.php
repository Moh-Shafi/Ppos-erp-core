<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GeneralLedgerReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'general-ledger'; }
    public function requiredPermission(): string { return 'reports.financial'; }
    public function requiredFeatureFlag(): string { return 'reports.financial'; }

    public function allowedFilters(): array { return ['date_from', 'date_to', 'account_id', 'fiscal_period_id']; }
    public function allowedGroupBy(): array { return []; }
    public function allowedSortColumns(): array { return ['entry_date', 'debit', 'credit']; }
    public function allowedDrillDownKeys(): array { return ['journal_entry_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'entry_date', 'label' => 'Date'],
            ['key' => 'entry_number', 'label' => 'Entry #'],
            ['key' => 'account_code', 'label' => 'Account'],
            ['key' => 'debit', 'label' => 'Debit', 'format' => 'currency'],
            ['key' => 'credit', 'label' => 'Credit', 'format' => 'currency'],
            ['key' => 'description', 'label' => 'Description'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;

        $query = DB::table('journal_entry_lines')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $ctx->user->tenant_id)
            ->select([
                'journal_entries.entry_date',
                'journal_entries.entry_number',
                'accounts.code as account_code',
                'journal_entry_lines.debit',
                'journal_entry_lines.credit',
                'journal_entries.description',
            ])
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.entry_number');

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('journal_entries.entry_date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['account_id'])) {
            $query->where('journal_entry_lines.account_id', $filters['account_id']);
        }

        if (isset($filters['fiscal_period_id'])) {
            $query->where('journal_entries.fiscal_period_id', $filters['fiscal_period_id']);
        }

        return $query;
    }
}
