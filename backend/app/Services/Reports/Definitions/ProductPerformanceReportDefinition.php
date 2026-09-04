<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProductPerformanceReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'product-performance'; }
    public function requiredPermission(): string { return 'reports.view'; }
    public function requiredFeatureFlag(): string { return 'reports.sales'; }

    public function allowedFilters(): array
    {
        return ['date_from', 'date_to', 'store_id', 'category_id'];
    }

    public function allowedGroupBy(): array { return []; }
    public function allowedSortColumns(): array { return ['product_name', 'quantity', 'revenue']; }
    public function allowedDrillDownKeys(): array { return ['product_id', 'category_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'product_id', 'label' => 'Product ID'],
            ['key' => 'product_name', 'label' => 'Product'],
            ['key' => 'quantity', 'label' => 'Units Sold'],
            ['key' => 'revenue', 'label' => 'Revenue', 'format' => 'currency'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;

        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $ctx->user->tenant_id)
            ->selectRaw('products.id as product_id, products.name as product_name, SUM(sale_items.quantity) as quantity, SUM(sale_items.total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderBy('revenue', 'desc');

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('sales.sale_date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['store_id'])) {
            $query->where('sales.store_id', $filters['store_id']);
        }

        if (isset($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        return $query;
    }
}
