<?php

namespace App\Services\Reports\Kpis;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\Kpi;
use Illuminate\Support\Facades\DB;

class LowStockCountKpi implements Kpi
{
    public function compute(AuthorizedStoreScope $scope, array $filters): array
    {
        $storeIds = $scope->stores->pluck('id')->toArray();

        $count = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->where('inventories.tenant_id', $scope->user->tenant_id)
            ->whereIn('inventories.store_id', $storeIds)
            ->whereRaw('inventories.quantity <= COALESCE(products.reorder_point, 0)')
            ->distinct('products.id')
            ->count('products.id');

        return [
            'value' => $count,
            'format' => 'number',
        ];
    }
}
