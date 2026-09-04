<?php

namespace App\Services\Reports;

use App\Models\FiscalPeriod;
use App\Models\User;

class ReportContext
{
    public function __construct(
        public readonly User $user,
        public readonly array $filters = [],
        public readonly ?FiscalPeriod $fiscalPeriod = null,
    ) {
    }
}
