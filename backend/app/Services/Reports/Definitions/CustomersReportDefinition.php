<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CustomersReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'customers'; }
    public function requiredPermission(): string { return 'reports.view'; }
    public function requiredFeatureFlag(): string { return 'reports.customers'; }

    public function allowedFilters(): array
    {
        return ['date_from', 'date_to', 'store_id', 'top_n'];
    }

    public function allowedGroupBy(): array { return []; }
    public function allowedSortColumns(): array { return ['customer_name', 'total', 'orders']; }
    public function allowedDrillDownKeys(): array { return ['customer_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'customer_id', 'label' => 'Customer ID'],
            ['key' => 'customer_name', 'label' => 'Customer'],
            ['key' => 'total', 'label' => 'Total Sales', 'format' => 'currency'],
            ['key' => 'orders', 'label' => 'Orders'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;
        $topN = (int) ($filters['top_n'] ?? 20);

        $query = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.tenant_id', $ctx->user->tenant_id)
            ->selectRaw('customers.id as customer_id, customers.name as customer_name, SUM(sales.total) as total, COUNT(*) as orders')
            ->groupBy('customers.id', 'customers.name')
            ->orderBy('total', 'desc')
            ->limit($topN);

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('sales.sale_date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['store_id'])) {
            $query->where('sales.store_id', $filters['store_id']);
        }

        return $query;
    }
}
