<?php

namespace App\Services\Reports\Exceptions;

use Exception;

class UnregisteredReportException extends Exception
{
    public function __construct(string $reportId)
    {
        parent::__construct("Unregistered report_id: {$reportId}");
    }
}
