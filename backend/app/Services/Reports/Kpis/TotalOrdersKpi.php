<?php

namespace App\Services\Reports\Kpis;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\Kpi;
use Illuminate\Support\Facades\DB;

class TotalOrdersKpi implements Kpi
{
    public function compute(AuthorizedStoreScope $scope, array $filters): array
    {
        $storeIds = $scope->stores->pluck('id')->toArray();

        $query = DB::table('sales')
            ->where('sales.tenant_id', $scope->user->tenant_id)
            ->where('sales.status', 'completed')
            ->whereIn('sales.store_id', $storeIds);

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('sales.sale_date', [$filters['date_from'], $filters['date_to']]);
        }

        return [
            'value' => $query->count(),
            'format' => 'number',
        ];
    }
}
