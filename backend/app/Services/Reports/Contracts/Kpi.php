<?php

namespace App\Services\Reports\Contracts;

use App\Services\Reports\AuthorizedStoreScope;

interface Kpi
{
    public function compute(AuthorizedStoreScope $scope, array $filters): array;
}
