<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BalanceSheetReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'balance-sheet'; }
    public function requiredPermission(): string { return 'reports.financial'; }
    public function requiredFeatureFlag(): string { return 'reports.financial'; }

    public function allowedFilters(): array { return ['as_of', 'fiscal_period_id']; }
    public function allowedGroupBy(): array { return []; }
    public function allowedSortColumns(): array { return ['account_type', 'account_code', 'balance']; }
    public function allowedDrillDownKeys(): array { return ['account_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'account_type', 'label' => 'Type'],
            ['key' => 'account_code', 'label' => 'Code'],
            ['key' => 'account_name', 'label' => 'Account'],
            ['key' => 'balance', 'label' => 'Balance', 'format' => 'currency'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;
        $asOf = $filters['as_of'] ?? now()->toDateString();

        $query = DB::table('journal_entry_lines')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $ctx->user->tenant_id)
            ->whereIn('accounts.type', ['asset', 'liability', 'equity'])
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->selectRaw('accounts.id as account_id, accounts.type as account_type, accounts.code as account_code, accounts.name as account_name, SUM(journal_entry_lines.debit - journal_entry_lines.credit) as balance')
            ->groupBy('accounts.id', 'accounts.type', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.type')
            ->orderBy('accounts.code');

        if (isset($filters['fiscal_period_id'])) {
            $query->where('journal_entries.fiscal_period_id', $filters['fiscal_period_id']);
        }

        return $query;
    }
}
