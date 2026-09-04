<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ApAgingReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'ap-aging'; }
    public function requiredPermission(): string { return 'reports.financial'; }
    public function requiredFeatureFlag(): string { return 'reports.ap_aging'; }

    public function allowedFilters(): array { return ['as_of']; }
    public function allowedGroupBy(): array { return []; }
    public function allowedSortColumns(): array { return ['supplier_name', 'balance', 'bucket']; }
    public function allowedDrillDownKeys(): array { return ['supplier_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'supplier_id', 'label' => 'Supplier ID'],
            ['key' => 'supplier_name', 'label' => 'Supplier'],
            ['key' => 'balance', 'label' => 'Balance', 'format' => 'currency'],
            ['key' => 'bucket', 'label' => 'Aging'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;
        $asOf = $filters['as_of'] ?? now()->toDateString();

        return DB::table('purchases')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('purchases.tenant_id', $ctx->user->tenant_id)
            ->whereDate('purchases.purchase_date', '<=', $asOf)
            ->whereIn('purchases.status', ['received'])
            ->select([
                'suppliers.id as supplier_id',
                'suppliers.name as supplier_name',
                DB::raw('SUM(purchases.total) as balance'),
                DB::raw("CASE
                    WHEN MAX(DATEDIFF(?, purchases.purchase_date)) <= 30 THEN 'Current'
                    WHEN MAX(DATEDIFF(?, purchases.purchase_date)) <= 60 THEN '1-30'
                    WHEN MAX(DATEDIFF(?, purchases.purchase_date)) <= 90 THEN '31-60'
                    WHEN MAX(DATEDIFF(?, purchases.purchase_date)) <= 120 THEN '61-90'
                    ELSE '90+'
                END as bucket", [$asOf, $asOf, $asOf, $asOf]),
            ])
            ->groupBy('suppliers.id', 'suppliers.name')
            ->havingRaw('balance > 0')
            ->orderBy('supplier_name');
    }
}
