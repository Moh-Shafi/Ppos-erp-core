<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'inventory'; }
    public function requiredPermission(): string { return 'reports.view'; }
    public function requiredFeatureFlag(): string { return 'reports.inventory'; }

    public function allowedFilters(): array
    {
        return ['store_id', 'category_id', 'low_stock'];
    }

    public function allowedGroupBy(): array { return []; }
    public function allowedSortColumns(): array { return ['product_name', 'quantity', 'reorder_point']; }
    public function allowedDrillDownKeys(): array { return ['product_id', 'store_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'product_id', 'label' => 'Product ID'],
            ['key' => 'product_name', 'label' => 'Product'],
            ['key' => 'quantity', 'label' => 'Qty'],
            ['key' => 'reorder_point', 'label' => 'Reorder Point'],
            ['key' => 'store_name', 'label' => 'Store'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;

        $query = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('stores', 'inventories.store_id', '=', 'stores.id')
            ->where('inventories.tenant_id', $ctx->user->tenant_id)
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                DB::raw('SUM(inventories.quantity) as quantity'),
                'products.reorder_point',
                'stores.name as store_name',
            ]);

        if (isset($filters['store_id'])) {
            $query->where('inventories.store_id', $filters['store_id']);
        }

        if (isset($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        if (!empty($filters['low_stock'])) {
            $query->havingRaw('quantity <= products.reorder_point OR products.reorder_point IS NULL');
        }

        return $query
            ->groupBy('products.id', 'products.name', 'products.reorder_point', 'stores.name')
            ->orderBy('product_name');
    }
}
