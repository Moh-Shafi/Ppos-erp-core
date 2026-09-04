<?php

namespace App\Services\Reports;

use Illuminate\Http\Response;

class SimplePdf
{
    public static function download(ReportResult $result): Response
    {
        $pdf = new self($result);

        return new Response($pdf->toString(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $result->report . '.pdf"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    private ReportResult $result;
    private array $objects = [];

    public function __construct(ReportResult $result)
    {
        $this->result = $result;
        $this->build();
    }

    private function build(): void
    {
        $fontObj = $this->addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');

        $stream = $this->buildContent();
        $contentObj = $this->addObject('<< /Length ' . strlen($stream) . ' >>' . "\n" . 'stream' . "\n" . $stream . "\n" . 'endstream');

        $pageObj = $this->addObject('<< /Type /Page /Parent 4 0 R /MediaBox [0 0 612 792] /Contents ' . $contentObj . ' 0 R /Resources << /Font << /F1 ' . $fontObj . ' 0 R >> >> >>');

        $this->addObject('<< /Type /Pages /Kids [' . $pageObj . ' 0 R] /Count 1 >>');
        $this->addObject('<< /Type /Catalog /Pages 4 0 R >>');
    }

    private function addObject(string $content): int
    {
        $this->objects[] = $content;
        return count($this->objects);
    }

    private function buildContent(): string
    {
        $lines = [];

        $lines[] = 'BT /F1 14 Tf 50 760 Td (' . $this->escape('Report: ' . $this->result->report) . ') Tj ET';

        $columns = array_column($this->result->columns, 'key');
        $headers = array_column($this->result->columns, 'label');
        $rows = iterator_to_array($this->result->data);

        $y = 730;
        $rowHeight = 14;
        $colWidth = 80;

        $headerLine = 'BT /F1 10 Tf ';
        $x = 50;
        foreach ($headers as $header) {
            $headerLine .= $x . ' ' . $y . ' Td (' . $this->escape((string) $header) . ') Tj ';
            $x += $colWidth;
        }
        $headerLine .= 'ET';
        $lines[] = $headerLine;

        $y -= $rowHeight;
        foreach ($rows as $row) {
            $row = (array) $row;
            $line = 'BT /F1 9 Tf ';
            $x = 50;
            foreach ($columns as $column) {
                $value = $row[$column] ?? '';
                $line .= $x . ' ' . $y . ' Td (' . $this->escape((string) $value) . ') Tj ';
                $x += $colWidth;
            }
            $line .= 'ET';
            $lines[] = $line;
            $y -= $rowHeight;

            if ($y < 50) {
                break;
            }
        }

        return implode("\n", $lines);
    }

    private function escape(string $text): string
    {
        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', '\\r', '\\n'],
            $text
        );
    }

    public function toString(): string
    {
        $output = "%PDF-1.4\n";
        $offsets = [];

        foreach ($this->objects as $i => $content) {
            $n = $i + 1;
            $offsets[$n] = strlen($output);
            $output .= $n . " 0 obj\n" . $content . "\nendobj\n";
        }

        $startxref = strlen($output);
        $count = count($this->objects) + 1;
        $output .= "xref\n0 " . $count . "\n";
        $output .= "0000000000 65535 f \n";

        foreach ($this->objects as $i => $_) {
            $n = $i + 1;
            $output .= str_pad((string) $offsets[$n], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $output .= "trailer\n<< /Root 5 0 R /Size " . $count . " >>\n";
        $output .= "startxref\n" . $startxref . "\n%%EOF";

        return $output;
    }
}
