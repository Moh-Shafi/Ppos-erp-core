<?php

namespace App\Services\Reports;

use App\Models\DashboardWidget;
use App\Models\User;

class DashboardService
{
    public function __construct(
        protected KpiService $kpiService,
        protected ReportEngine $engine,
    ) {
    }

    public function load(User $user, array $filters): array
    {
        $scope = AuthorizedStoreScope::forUser($user);

        $widgets = DashboardWidget::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->get();

        $result = [];

        foreach ($widgets as $widget) {
            if ($widget->type === 'kpi') {
                $result[] = [
                    'id' => $widget->id,
                    'type' => 'kpi',
                    'kpi_id' => $widget->kpi_id,
                    'position' => $widget->position,
                    'value' => $this->kpiService->compute($widget->kpi_id, $scope, $widget->filters ?? []),
                ];
            } else {
                $ctx = new ReportContext(user: $user, filters: $widget->filters ?? []);
                $report = $this->engine->run($widget->report_id, $ctx, $scope);
                $result[] = [
                    'id' => $widget->id,
                    'type' => 'report',
                    'report_id' => $widget->report_id,
                    'position' => $widget->position,
                    'data' => $report->data,
                ];
            }
        }

        return $result;
    }
}
