<?php

namespace App\Services\Reports;

use InvalidArgumentException;

class KpiService
{
    public function compute(string $kpiId, AuthorizedStoreScope $scope, array $filters): array
    {
        $kpi = KpiRegistry::get($kpiId);

        if (!$kpi) {
            throw new InvalidArgumentException("Unregistered kpi_id: {$kpiId}");
        }

        return $kpi->compute($scope, $filters);
    }
}
