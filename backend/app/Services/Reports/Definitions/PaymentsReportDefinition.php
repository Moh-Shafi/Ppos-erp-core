<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PaymentsReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'payments'; }
    public function requiredPermission(): string { return 'reports.view'; }
    public function requiredFeatureFlag(): string { return 'reports.payments'; }

    public function allowedFilters(): array
    {
        return ['date_from', 'date_to', 'store_id', 'method', 'group_by'];
    }

    public function allowedGroupBy(): array { return ['day', 'method']; }
    public function allowedSortColumns(): array { return ['date', 'method', 'total']; }
    public function allowedDrillDownKeys(): array { return ['date', 'method', 'store_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'method', 'label' => 'Method'],
            ['key' => 'total', 'label' => 'Total', 'format' => 'currency'],
            ['key' => 'count', 'label' => 'Transactions'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;
        $group = $filters['group_by'] ?? 'day';

        $query = DB::table('payments')
            ->where('payments.tenant_id', $ctx->user->tenant_id);

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('payments.payment_date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['store_id'])) {
            $query->where('payments.store_id', $filters['store_id']);
        }

        if (isset($filters['method'])) {
            $query->where('payments.method', $filters['method']);
        }

        return match ($group) {
            'method' => $query
                ->selectRaw('MIN(DATE(payments.payment_date)) as date, payments.method as method, SUM(payments.amount) as total, COUNT(*) as count')
                ->groupBy('payments.method')
                ->orderBy('method'),
            default => $query
                ->selectRaw('DATE(payments.payment_date) as date, payments.method as method, SUM(payments.amount) as total, COUNT(*) as count')
                ->groupByRaw('DATE(payments.payment_date), payments.method')
                ->orderBy('date'),
        };
    }
}
