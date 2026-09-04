<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\Kpi;
use InvalidArgumentException;

class KpiRegistry
{
    /** @var array<string, Kpi> */
    protected static array $kpis = [];

    public static function register(string $kpiId, Kpi $kpi): void
    {
        self::$kpis[$kpiId] = $kpi;
    }

    public static function get(string $kpiId): Kpi
    {
        if (!isset(self::$kpis[$kpiId])) {
            throw new InvalidArgumentException("Unregistered kpi_id: {$kpiId}");
        }

        return self::$kpis[$kpiId];
    }

    public static function all(): array
    {
        return self::$kpis;
    }
}
