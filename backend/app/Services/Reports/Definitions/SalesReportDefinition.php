<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SalesReportDefinition implements ReportDefinition
{
    public function reportId(): string
    {
        return 'sales';
    }

    public function requiredPermission(): string
    {
        return 'reports.view';
    }

    public function requiredFeatureFlag(): string
    {
        return 'reports.sales';
    }

    public function allowedFilters(): array
    {
        return ['date_from', 'date_to', 'store_id', 'group_by'];
    }

    public function allowedGroupBy(): array
    {
        return ['day', 'week', 'month'];
    }

    public function allowedSortColumns(): array
    {
        return ['date', 'total', 'quantity'];
    }

    public function allowedDrillDownKeys(): array
    {
        return ['date', 'product_id', 'category_id'];
    }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'total', 'label' => 'Total', 'format' => 'currency'],
            ['key' => 'quantity', 'label' => 'Quantity'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;
        $group = $filters['group_by'] ?? 'day';

        $query = DB::table('sales')
            ->where('sales.tenant_id', $ctx->user->tenant_id);

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('sales.sale_date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['store_id'])) {
            $query->where('sales.store_id', $filters['store_id']);
        }

        return match ($group) {
            'day' => $query
                ->selectRaw('DATE(sales.sale_date) as date, SUM(sales.total) as total, COUNT(*) as quantity')
                ->groupByRaw('DATE(sales.sale_date)')
                ->orderBy('date'),
            'week' => $query
                ->selectRaw("YEARWEEK(sales.sale_date) as date, SUM(sales.total) as total, COUNT(*) as quantity")
                ->groupByRaw('YEARWEEK(sales.sale_date)')
                ->orderBy('date'),
            'month' => $query
                ->selectRaw("DATE_FORMAT(sales.sale_date, '%Y-%m') as date, SUM(sales.total) as total, COUNT(*) as quantity")
                ->groupByRaw("DATE_FORMAT(sales.sale_date, '%Y-%m')")
                ->orderBy('date'),
            default => $query
                ->selectRaw('DATE(sales.sale_date) as date, SUM(sales.total) as total, COUNT(*) as quantity')
                ->groupByRaw('DATE(sales.sale_date)')
                ->orderBy('date'),
        };
    }
}
