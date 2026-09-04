<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\ReportDefinition;
use App\Services\Reports\Definitions\ApAgingReportDefinition;
use App\Services\Reports\Definitions\ArAgingReportDefinition;
use App\Services\Reports\Definitions\BalanceSheetReportDefinition;
use App\Services\Reports\Definitions\BranchComparisonReportDefinition;
use App\Services\Reports\Definitions\CashFlowReportDefinition;
use App\Services\Reports\Definitions\CustomersReportDefinition;
use App\Services\Reports\Definitions\GeneralLedgerReportDefinition;
use App\Services\Reports\Definitions\InventoryReportDefinition;
use App\Services\Reports\Definitions\PaymentsReportDefinition;
use App\Services\Reports\Definitions\ProductPerformanceReportDefinition;
use App\Services\Reports\Definitions\ProfitLossReportDefinition;
use App\Services\Reports\Definitions\PurchasingReportDefinition;
use App\Services\Reports\Definitions\SalesReportDefinition;
use App\Services\Reports\Definitions\TrialBalanceReportDefinition;
use App\Services\Reports\Exceptions\UnregisteredReportException;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ReportEngine
{
    public function __construct(
        protected ReportBuilder $builder,
    ) {
        $this->registerDefinitions();
    }

    protected function registerDefinitions(): void
    {
        if (ReportRegistry::has('sales')) {
            return;
        }

        ReportRegistry::register(new SalesReportDefinition());
        ReportRegistry::register(new InventoryReportDefinition());
        ReportRegistry::register(new PurchasingReportDefinition());
        ReportRegistry::register(new CustomersReportDefinition());
        ReportRegistry::register(new PaymentsReportDefinition());
        ReportRegistry::register(new ProductPerformanceReportDefinition());
        ReportRegistry::register(new BranchComparisonReportDefinition());
        ReportRegistry::register(new TrialBalanceReportDefinition());
        ReportRegistry::register(new ProfitLossReportDefinition());
        ReportRegistry::register(new BalanceSheetReportDefinition());
        ReportRegistry::register(new CashFlowReportDefinition());
        ReportRegistry::register(new GeneralLedgerReportDefinition());
        ReportRegistry::register(new ArAgingReportDefinition());
        ReportRegistry::register(new ApAgingReportDefinition());
    }

    public function run(string $reportId, ReportContext $ctx, AuthorizedStoreScope $storeScope): ReportResult
    {
        $definition = $this->resolve($reportId);

        $filters = $this->validateFilters($definition, $ctx->filters);
        $this->validateStoreFilters($filters, $storeScope);

        $query = $definition->query($ctx, $storeScope);

        $data = $this->builder->build($query, $filters);

        return new ReportResult(
            report: $reportId,
            filters: $filters,
            data: $data,
            columns: $definition->columns(),
        );
    }

    public function resolve(string $reportId): ReportDefinition
    {
        $definition = ReportRegistry::get($reportId);

        if (!$definition) {
            throw new UnregisteredReportException($reportId);
        }

        return $definition;
    }

    protected function validateFilters(ReportDefinition $definition, array $filters): array
    {
        $allowed = array_merge(
            $definition->allowedFilters(),
            ['page', 'per_page', 'sort', 'group_by']
        );

        foreach (array_keys($filters) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("Invalid filter: {$key}");
            }
        }

        if (isset($filters['group_by'])) {
            if (!in_array($filters['group_by'], $definition->allowedGroupBy(), true)) {
                throw new InvalidArgumentException('Invalid group_by value');
            }
        }

        if (isset($filters['sort'])) {
            $parts = explode(':', $filters['sort']);
            $column = $parts[0];
            $direction = strtolower($parts[1] ?? 'asc');

            if (!in_array($column, $definition->allowedSortColumns(), true)) {
                throw new InvalidArgumentException('Invalid sort column');
            }

            if (!in_array($direction, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException('Invalid sort direction');
            }
        }

        return $filters;
    }

    protected function validateStoreFilters(array $filters, AuthorizedStoreScope $scope): void
    {
        if (isset($filters['store_id'])) {
            if (!$scope->contains((int) $filters['store_id'])) {
                throw new InvalidArgumentException('Store not authorized');
            }
        }

        if (isset($filters['stores']) && is_array($filters['stores'])) {
            $unauthorized = array_diff($filters['stores'], $scope->only($filters['stores']));
            if (!empty($unauthorized)) {
                throw new InvalidArgumentException('One or more stores not authorized');
            }
        }
    }
}
