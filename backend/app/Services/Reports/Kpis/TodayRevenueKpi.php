<?php

namespace App\Services\Reports\Kpis;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\Kpi;
use Illuminate\Support\Facades\DB;

class TodayRevenueKpi implements Kpi
{
    public function compute(AuthorizedStoreScope $scope, array $filters): array
    {
        $storeIds = $scope->stores->pluck('id')->toArray();

        $total = DB::table('sales')
            ->where('sales.tenant_id', $scope->user->tenant_id)
            ->where('sales.status', 'completed')
            ->whereIn('sales.store_id', $storeIds)
            ->whereDate('sales.sale_date', today()->toDateString())
            ->sum('sales.total');

        return [
            'value' => (float) ($total ?? 0),
            'format' => 'currency',
        ];
    }
}
