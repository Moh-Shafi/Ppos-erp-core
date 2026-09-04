<?php

namespace App\Services\Reports;

use Illuminate\Pagination\LengthAwarePaginator;

class ReportResult
{
    public function __construct(
        public readonly string $report,
        public readonly array $filters,
        public readonly LengthAwarePaginator $data,
        public readonly array $columns = [],
        public readonly array $summary = [],
        public readonly array $meta = [],
        public readonly array $notes = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'report' => $this->report,
            'filters' => $this->filters,
            'columns' => $this->columns,
            'data' => $this->data->items(),
            'summary' => $this->summary,
            'meta' => array_merge($this->meta, [
                'current_page' => $this->data->currentPage(),
                'last_page' => $this->data->lastPage(),
                'per_page' => $this->data->perPage(),
                'total' => $this->data->total(),
            ]),
            'notes' => $this->notes,
        ];
    }
}
