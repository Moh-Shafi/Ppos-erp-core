<?php

namespace App\Services\Reports\Contracts;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;

interface ReportDefinition
{
    public function reportId(): string;

    public function requiredPermission(): string;

    public function requiredFeatureFlag(): string;

    public function allowedFilters(): array;

    public function allowedGroupBy(): array;

    public function allowedSortColumns(): array;

    public function allowedDrillDownKeys(): array;

    public function columns(): array;

    public function query(ReportContext $ctx, AuthorizedStoreScope $storeScope): Builder;
}
