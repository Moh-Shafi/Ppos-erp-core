<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PurchasingReportDefinition implements ReportDefinition
{
    public function reportId(): string { return 'purchasing'; }
    public function requiredPermission(): string { return 'reports.view'; }
    public function requiredFeatureFlag(): string { return 'reports.purchasing'; }

    public function allowedFilters(): array
    {
        return ['date_from', 'date_to', 'store_id', 'supplier_id', 'group_by'];
    }

    public function allowedGroupBy(): array { return ['day', 'supplier']; }
    public function allowedSortColumns(): array { return ['date', 'supplier', 'total']; }
    public function allowedDrillDownKeys(): array { return ['date', 'supplier_id', 'store_id']; }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'total', 'label' => 'Total', 'format' => 'currency'],
            ['key' => 'count', 'label' => 'POs'],
        ];
    }

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder
    {
        $filters = $ctx->filters;
        $group = $filters['group_by'] ?? 'day';

        $query = DB::table('purchases')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('purchases.tenant_id', $ctx->user->tenant_id);

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('purchases.purchase_date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['store_id'])) {
            $query->where('purchases.store_id', $filters['store_id']);
        }

        if (isset($filters['supplier_id'])) {
            $query->where('purchases.supplier_id', $filters['supplier_id']);
        }

        return match ($group) {
            'supplier' => $query
                ->selectRaw('MIN(DATE(purchases.purchase_date)) as date, suppliers.name as supplier, SUM(purchases.total) as total, COUNT(*) as count')
                ->groupBy('suppliers.name')
                ->orderBy('supplier'),
            default => $query
                ->selectRaw('DATE(purchases.purchase_date) as date, suppliers.name as supplier, SUM(purchases.total) as total, COUNT(*) as count')
                ->groupByRaw('DATE(purchases.purchase_date), suppliers.name')
                ->orderBy('date'),
        };
    }
}
