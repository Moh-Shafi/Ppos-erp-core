<?php

namespace App\Services\Reports\Kpis;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\Kpi;
use Illuminate\Support\Facades\DB;

class TotalCustomersKpi implements Kpi
{
    public function compute(AuthorizedStoreScope $scope, array $filters): array
    {
        $value = DB::table('customers')
            ->where('tenant_id', $scope->user->tenant_id)
            ->count();

        return [
            'value' => $value,
            'format' => 'number',
        ];
    }
}
