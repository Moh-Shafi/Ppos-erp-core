<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CashFlowReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'cash-flow'; }
    public function requiredPermission(): string { return 'reports.financial'; }
    public function requiredFeatureFlag(): string { return 'reports.cash_flow'; }

    public function allowedFilters(): array { return ['date_from', 'date_to', 'fiscal_period_id']; }
    public function allowedGroupBy(): array { return []; }
    public function allowedSortColumns(): array { return ['classification', 'account_code', 'net_amount']; }
    public function allowedDrillDownKeys(): array { return ['account_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'classification', 'label' => 'Classification'],
            ['key' => 'account_code', 'label' => 'Code'],
            ['key' => 'account_name', 'label' => 'Account'],
            ['key' => 'inflow', 'label' => 'Inflow', 'format' => 'currency'],
            ['key' => 'outflow', 'label' => 'Outflow', 'format' => 'currency'],
            ['key' => 'net_amount', 'label' => 'Net', 'format' => 'currency'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;

        $query = DB::table('journal_entry_lines')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $ctx->user->tenant_id)
            ->where('accounts.type', 'asset')
            ->where('accounts.is_bank', true)
            ->selectRaw("accounts.id as account_id, 'Cash/Bank' as classification, accounts.code as account_code, accounts.name as account_name, SUM(journal_entry_lines.debit) as inflow, SUM(journal_entry_lines.credit) as outflow, SUM(journal_entry_lines.debit - journal_entry_lines.credit) as net_amount")
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code');

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('journal_entries.entry_date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['fiscal_period_id'])) {
            $query->where('journal_entries.fiscal_period_id', $filters['fiscal_period_id']);
        }

        return $query;
    }
}
