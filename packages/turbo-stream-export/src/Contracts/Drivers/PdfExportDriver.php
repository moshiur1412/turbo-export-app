<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use TurboStreamExport\Contracts\ExportDriverInterface;
use TCPDF;
use App\Services\ReportFormatter;

class PdfExportDriver extends TCPDF implements ExportDriverInterface
{
    private array $reportColumns = [];
    private array $reportColumnWidths = [];
    private int $currentRow = 0;
    private int $maxRowsPerPage = 35;
    private string $reportTitle = 'Export Data';
    private string $reportName = '';
    private array $filters = [];
    private array $numericColumns = [];
    private array $columnTotals = [];
    private int $totalRecords = 0;
    private bool $headerWritten = false;

    public function __construct()
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->initializePdf();
    }

    private function initializePdf(): void
    {
        $this->SetCreator('TurboStream Export Engine');
        $this->SetAuthor('TurboStream');
        $this->SetTitle($this->reportTitle);
        $this->SetSubject('Data Export');
        
        $this->setPrintHeader(false);
        $this->setPrintFooter(true);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(15);
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 18);
        
        $this->AddPage();
        $this->SetFont('helvetica', '', 8);
        
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 7);
        $this->SetTextColor(100, 100, 100);
        
        $printDate = ReportFormatter::formatDateTime(now());
        $printOnText = "Print On: {$printDate}";
        
        $totalFormatted = ReportFormatter::bangladeshNumber($this->totalRecords);
        $pageInfoText = "Page {$this->getAliasNumPage()} of {$totalFormatted}";
        
        $pageWidth = $this->getPageWidth();
        $leftMargin = 10;
        $rightMargin = 10;
        
        $this->SetX($leftMargin);
        $this->Cell(0, 5, $printOnText, 0, 0, 'L');
        
        $pageInfoWidth = $this->GetStringWidth($pageInfoText);
        $this->SetX($pageWidth - $rightMargin - $pageInfoWidth);
        $this->Cell($pageInfoWidth, 5, $pageInfoText, 0, 0, 'R');
        
        $this->SetTextColor(0, 0, 0);
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

    public function setReportInfo(string $name, array $filters = []): self
    {
        $this->reportName = $name;
        $this->filters = $filters;
        return $this;
    }

    public function setNumericColumns(array $columns): self
    {
        $this->numericColumns = $columns;
        return $this;
    }

    public function writeHeader(array $columns, $handle = null): void
    {
        $this->reportColumns = $columns;
        $this->currentRow = 0;
        $this->columnTotals = array_fill(0, count($columns), 0);
        $this->headerWritten = false;
        
        $pageWidth = $this->getPageWidth() - 20;
        $colWidth = $pageWidth / count($columns);
        
        foreach ($columns as $index => $column) {
            $headerName = ReportFormatter::formatHeaderName($column);
            $headerWidth = $this->GetStringWidth($headerName) + 6;
            $this->reportColumnWidths[$index] = max($headerWidth, $colWidth);
        }
        
        $totalWidth = array_sum($this->reportColumnWidths);
        if ($totalWidth > $pageWidth) {
            $scale = $pageWidth / $totalWidth;
            foreach ($this->reportColumnWidths as $index => $width) {
                $this->reportColumnWidths[$index] = $width * $scale;
            }
        }
        
        $this->writeReportInfo();
        $this->writeColumnHeader();
    }

    private function writeReportInfo(): void
    {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, $this->reportName, 0, 1, 'L');
        
        if (!empty($this->filters)) {
            $filterParts = [];
            $pageWidth = $this->getPageWidth() - 20;
            
            if (isset($this->filters['start_date']) && isset($this->filters['end_date'])) {
                $startDate = ReportFormatter::formatDate($this->filters['start_date']);
                $endDate = ReportFormatter::formatDate($this->filters['end_date']);
                if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
                    $filterParts[] = ['bold' => 'Date Range:', 'normal' => " {$startDate} To {$endDate}"];
                }
            } elseif (isset($this->filters['date'])) {
                $filterParts[] = ['bold' => 'Date:', 'normal' => ' ' . ReportFormatter::formatDate($this->filters['date'])];
            }
            
            foreach ($this->filters as $key => $value) {
                if (in_array($key, ['start_date', 'end_date', 'date'])) continue;
                if (empty($value)) continue;
                
                $label = ReportFormatter::formatHeaderName($key);
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $filterParts[] = ['bold' => "{$label}:", 'normal' => " {$value}"];
            }
            
            if (!empty($filterParts)) {
                $this->SetFont('helvetica', '', 7);
                
                $filterLine = 'Filters:';
                foreach ($filterParts as $part) {
                    $filterLine .= ' | ' . $part['bold'] . $part['normal'];
                }
                
                $this->SetX(10);
                $this->MultiCell($pageWidth, 4, $filterLine, 0, 'L', false, 1);
            }
        }
        
        $this->Ln(2);
    }

    private function writeColumnHeader(): void
    {
        $this->SetFillColor(68, 114, 196);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.1);
        $this->SetFont('helvetica', 'B', 8);
        
        $pageWidth = $this->getPageWidth() - 20;
        $colWidth = $pageWidth / count($this->reportColumns);
        
        foreach ($this->reportColumns as $index => $column) {
            $headerName = ReportFormatter::formatHeaderName($column);
            $cellWidth = $this->reportColumnWidths[$index] ?? $colWidth;
            $this->Cell($cellWidth, 7, $headerName, 1, 0, 'C', true);
        }
        
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', '', 7);
        $this->headerWritten = true;
    }

    public function writeRow(array $data, $handle = null): void
    {
        $pageWidth = $this->getPageWidth() - 20;
        $colWidth = $pageWidth / count($this->reportColumns);
        
        $fill = ($this->currentRow % 2) == 0;
        $this->SetFillColor(245, 245, 245);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', '', 7);
        
        foreach ($data as $index => $value) {
            $columnName = $this->reportColumns[$index] ?? '';
            $isNumeric = ReportFormatter::isNumericColumn($columnName);
            $formattedValue = ReportFormatter::formatValue($value, $isNumeric);
            
            $cellWidth = $this->reportColumnWidths[$index] ?? $colWidth;
            $align = $isNumeric ? 'R' : 'L';
            
            $this->Cell($cellWidth, 5.5, $formattedValue, 1, 0, $align, $fill);
            
            if ($isNumeric && is_numeric($value)) {
                $this->columnTotals[$index] += floatval($value);
            }
        }
        
        $this->Ln();
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
            $this->totalRecords++;
        }
    }

    public function finalize(string $filePath, $handle = null): string
    {
        $this->writeGrandTotal();
        
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $this->Output($filePath, 'F');
        
        return $filePath;
    }

    private function writeGrandTotal(): void
    {
        $hasData = false;
        foreach ($this->columnTotals as $total) {
            if ($total != 0) {
                $hasData = true;
                break;
            }
        }
        
        if (!$hasData) {
            return;
        }
        
        $this->Ln(2);
        
        $this->SetFillColor(226, 239, 218);
        $this->SetFont('helvetica', 'B', 8);
        $this->SetTextColor(0, 0, 0);
        
        $pageWidth = $this->getPageWidth() - 20;
        $colWidth = $pageWidth / count($this->reportColumns);
        
        foreach ($this->columnTotals as $index => $total) {
            $columnName = $this->reportColumns[$index] ?? '';
            $isNumeric = ReportFormatter::isNumericColumn($columnName);
            $cellWidth = $this->reportColumnWidths[$index] ?? $colWidth;
            
            if ($index === 0) {
                $this->Cell($cellWidth, 6, 'Grand Total', 1, 0, 'L', true);
            } elseif ($isNumeric && $total != 0) {
                $formattedTotal = ReportFormatter::formatValue($total, true);
                $this->Cell($cellWidth, 6, $formattedTotal, 1, 0, 'R', true);
            } else {
                $this->Cell($cellWidth, 6, '-', 1, 0, 'C', true);
            }
        }
    }

    private function formatHeaderName(string $column): string
    {
        return ReportFormatter::formatHeaderName($column);
    }

    private function formatValue(mixed $value): string
    {
        return ReportFormatter::formatValue($value);
    }
}
