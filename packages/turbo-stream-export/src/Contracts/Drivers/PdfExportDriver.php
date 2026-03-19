<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use TurboStreamExport\Contracts\ExportDriverInterface;
use TCPDF;

class PdfExportDriver implements ExportDriverInterface
{
    private TCPDF $pdf;
    private array $columns = [];
    private int $currentRow = 0;
    private int $maxRowsPerPage = 40;
    private string $title = 'Export Data';

    public function __construct()
    {
        $this->pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->initializePdf();
    }

    private function initializePdf(): void
    {
        $this->pdf->SetCreator('TurboStream Export Engine');
        $this->pdf->SetAuthor('TurboStream');
        $this->pdf->SetTitle($this->title);
        $this->pdf->SetSubject('Data Export');
        
        $this->pdf->setPrintHeader(true);
        $this->pdf->setPrintFooter(true);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);
        $this->pdf->SetMargins(10, 20, 10);
        $this->pdf->SetAutoPageBreak(true, 20);
        
        $this->pdf->AddPage();
        $this->pdf->SetFont('helvetica', '', 8);
    }

    public function getFormat(): string
    {
        return 'pdf';
    }

    public function getContentType(): string
    {
        return 'application/pdf';
    }

    public function getFileExtension(): string
    {
        return 'pdf';
    }

    public function writeHeader(array $columns, $handle = null): void
    {
        $this->columns = $columns;
        $this->currentRow = 0;
        
        $this->pdf->setHeaderFont(['helvetica', 'B', 9]);
        $this->pdf->SetHeaderData('', 0, $this->title, 'Generated: ' . date('Y-m-d H:i:s'));
        
        $colWidth = (190 / count($columns));
        $this->pdf->SetFillColor(68, 114, 196);
        $this->pdf->SetTextColor(255);
        $this->pdf->SetDrawColor(0);
        $this->pdf->SetLineWidth(0.1);
        
        foreach ($columns as $column) {
            $this->pdf->Cell($colWidth, 7, $this->formatHeaderName($column), 1, 0, 'C', true);
        }
        
        $this->pdf->Ln();
        $this->pdf->SetTextColor(0);
        $this->pdf->SetFont('helvetica', '', 8);
    }

    public function writeRow(array $data, $handle = null): void
    {
        $colWidth = (190 / count($this->columns));
        $rowHeight = 6;
        
        if ($this->currentRow >= $this->maxRowsPerPage) {
            $this->pdf->AddPage();
            $this->currentRow = 0;
            $this->writeHeader($this->columns);
        }
        
        $fill = ($this->currentRow % 2) == 0;
        $this->pdf->SetFillColor(245, 245, 245);
        
        $colIndex = 0;
        foreach ($data as $value) {
            $cellWidth = $colWidth;
            $this->pdf->Cell($cellWidth, $rowHeight, $this->truncateValue($value, 30), 1, 0, 'L', $fill);
            $colIndex++;
        }
        
        $this->pdf->Ln();
        $this->currentRow++;
    }

    public function writeBatch($records, array $columns, $handle = null): void
    {
        foreach ($records as $record) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = data_get($record, $column);
            }
            $this->writeRow($row);
        }
    }

    public function finalize(string $filePath, $handle = null): string
    {
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $this->pdf->Output($filePath, 'F');
        
        return $filePath;
    }

    private function formatHeaderName(string $column): string
    {
        return strtoupper(ucwords(str_replace(['_', '.'], ' ', $column)));
    }

    private function truncateValue(mixed $value, int $length = 50): string
    {
        if (is_null($value)) {
            return '-';
        }
        
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        
        $value = (string) $value;
        
        if (mb_strlen($value) > $length) {
            return mb_substr($value, 0, $length - 3) . '...';
        }
        
        return $value;
    }
}
