<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ArAgingReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'ar-aging'; }
    public function requiredPermission(): string { return 'reports.financial'; }
    public function requiredFeatureFlag(): string { return 'reports.ar_aging'; }

    public function allowedFilters(): array { return ['as_of']; }
    public function allowedGroupBy(): array { return []; }
    public function allowedSortColumns(): array { return ['customer_name', 'balance', 'bucket']; }
    public function allowedDrillDownKeys(): array { return ['customer_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'customer_id', 'label' => 'Customer ID'],
            ['key' => 'customer_name', 'label' => 'Customer'],
            ['key' => 'balance', 'label' => 'Balance', 'format' => 'currency'],
            ['key' => 'bucket', 'label' => 'Aging'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;
        $asOf = $filters['as_of'] ?? now()->toDateString();

        return DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.tenant_id', $ctx->user->tenant_id)
            ->whereDate('sales.sale_date', '<=', $asOf)
            ->whereIn('sales.payment_status', ['unpaid', 'partial'])
            ->whereNotNull('sales.customer_id')
            ->select([
                'customers.id as customer_id',
                'customers.name as customer_name',
                DB::raw('SUM(sales.total - sales.paid_amount - sales.refunded_amount) as balance'),
                DB::raw("CASE
                    WHEN MAX(DATEDIFF(?, sales.sale_date)) <= 30 THEN 'Current'
                    WHEN MAX(DATEDIFF(?, sales.sale_date)) <= 60 THEN '1-30'
                    WHEN MAX(DATEDIFF(?, sales.sale_date)) <= 90 THEN '31-60'
                    WHEN MAX(DATEDIFF(?, sales.sale_date)) <= 120 THEN '61-90'
                    ELSE '90+'
                END as bucket", [$asOf, $asOf, $asOf, $asOf]),
            ])
            ->groupBy('customers.id', 'customers.name')
            ->havingRaw('balance > 0')
            ->orderBy('customer_name');
    }
}
