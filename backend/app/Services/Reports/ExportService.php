<?php

namespace App\Services\Reports;

use Illuminate\Http\Response;
use InvalidArgumentException;

class ExportService
{
    public function download(ReportResult $result, string $format): Response
    {
        return match ($format) {
            'csv' => $this->csv($result),
            'xlsx' => $this->xlsx($result),
            'pdf' => $this->pdf($result),
            default => throw new InvalidArgumentException('Unsupported export format'),
        };
    }

    protected function csv(ReportResult $result): Response
    {
        $httpHeaders = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $result->report . '.csv"',
        ];

        $columns = array_column($result->columns, 'key');
        $csvHeaders = array_column($result->columns, 'label');
        $csv = fopen('php://temp', 'r+');

        fputcsv($csv, $csvHeaders);

        foreach ($result->data as $row) {
            $row = (array) $row;
            $line = [];
            foreach ($columns as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($csv, $line);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, $httpHeaders);
    }

    protected function xlsx(ReportResult $result): Response
    {
        return SimpleXlsx::download($result);
    }

    protected function pdf(ReportResult $result): Response
    {
        return SimplePdf::download($result);
    }
}
