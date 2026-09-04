<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ReportBuilder
{
    public function build(Builder $query, array $filters): LengthAwarePaginator
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = min($perPage, 1000);

        $this->applySort($query, $filters);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    protected function applySort(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? null;

        if (!$sort) {
            return;
        }

        $parts = explode(':', $sort);
        $column = $parts[0];
        $direction = strtolower($parts[1] ?? 'asc');

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Invalid sort direction');
        }

        $query->orderBy($column, $direction);
    }
}
