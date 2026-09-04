<?php

namespace App\Providers;

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
use App\Services\Reports\KpiRegistry;
use App\Services\Reports\Kpis\LowStockCountKpi;
use App\Services\Reports\Kpis\TodayRevenueKpi;
use App\Services\Reports\Kpis\TotalCustomersKpi;
use App\Services\Reports\Kpis\TotalOrdersKpi;
use App\Services\Reports\Kpis\TotalSalesKpi;
use App\Services\Reports\ReportRegistry;
use Illuminate\Support\ServiceProvider;

class ReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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

        KpiRegistry::register('total-sales', new TotalSalesKpi());
        KpiRegistry::register('total-orders', new TotalOrdersKpi());
        KpiRegistry::register('today-revenue', new TodayRevenueKpi());
        KpiRegistry::register('low-stock-count', new LowStockCountKpi());
        KpiRegistry::register('total-customers', new TotalCustomersKpi());
    }

    public function register(): void
    {
        $this->app->singleton(\App\Services\Reports\ReportEngine::class);
        $this->app->singleton(\App\Services\Reports\KpiService::class);
        $this->app->singleton(\App\Services\Reports\DashboardService::class);
    }
}
