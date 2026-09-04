<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\ReportDefinition;
use InvalidArgumentException;

class ReportRegistry
{
    /** @var array<string, ReportDefinition> */
    protected static array $definitions = [];

    public static function register(ReportDefinition $definition): void
    {
        self::$definitions[$definition->reportId()] = $definition;
    }

    public static function get(string $reportId): ?ReportDefinition
    {
        return self::$definitions[$reportId] ?? null;
    }

    public static function has(string $reportId): bool
    {
        return isset(self::$definitions[$reportId]);
    }

    public static function all(): array
    {
        return self::$definitions;
    }
}
