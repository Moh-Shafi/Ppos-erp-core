<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BranchComparisonReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'branch-comparison'; }
    public function requiredPermission(): string { return 'reports.comparison'; }
    public function requiredFeatureFlag(): string { return 'reports.sales'; }

    public function allowedFilters(): array
    {
        return ['date_from', 'date_to', 'stores', 'metric'];
    }

    public function allowedGroupBy(): array { return []; }
    public function allowedSortColumns(): array { return ['store_name', 'total']; }
    public function allowedDrillDownKeys(): array { return ['store_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'store_id', 'label' => 'Store ID'],
            ['key' => 'store_name', 'label' => 'Store'],
            ['key' => 'total', 'label' => 'Total', 'format' => 'currency'],
            ['key' => 'count', 'label' => 'Transactions'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;
        $metric = $filters['metric'] ?? 'sales';

        $query = DB::table('sales')
            ->join('stores', 'sales.store_id', '=', 'stores.id')
            ->where('sales.tenant_id', $ctx->user->tenant_id)
            ->selectRaw('stores.id as store_id, stores.name as store_name, SUM(sales.total) as total, COUNT(*) as count')
            ->groupBy('stores.id', 'stores.name')
            ->orderBy('store_name');

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('sales.created_at', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['stores']) && is_array($filters['stores'])) {
            $query->whereIn('sales.store_id', $filters['stores']);
        }

        return $query;
    }
}
