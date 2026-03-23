<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use EvoSys21\PdfLib\Tcpdf\Pdf as TcpdfPdf;
use EvoSys21\PdfLib\Table;
use TurboStreamExport\Contracts\ExportDriverInterface;
use App\Services\ReportFormatter;

class PdfExportDriver extends TcpdfPdf implements ExportDriverInterface
{
    private array $reportColumns = [];
    private array $reportColumnWidths = [];
    private int $currentRow = 0;
    private string $reportName = '';
    private array $filters = [];
    private array $numericColumns = [];
    private array $columnTotals = [];
    private int $totalRecords = 0;
    private string $outputFilePath = '';
    private int $rowsSinceLastGc = 0;
    private int $gcInterval = 1000;
    private ?Table $table = null;
    private array $tableConfig = [];
    private array $columnStyles = [];
    private mixed $currentGroupValue = null;
    private array $groupSubtotals = [];
    private string $groupByColumn = '';
    private bool $useAdvancedTable = false;
    private bool $headerAdded = false;
    private bool $headerWritten = false;
    private int $rowBufferSize = 100;

    public function __construct()
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false, false);
        
        if (!isset($this->w)) {
            $this->w = $this->getPageWidth();
        }
        
        $this->initializePdf();
    }

    private function initializePdf(): void
    {
        $this->SetCreator('TurboStream Export Engine');
        $this->SetAuthor('TurboStream');
        $this->SetTitle($this->reportName);
        $this->SetSubject('Data Export');
        
        $this->setPrintHeader(false);
        $this->setPrintFooter(true);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(15);
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 18);
        
        $this->AddPage();
        $this->SetFont('dejavusans', '', 8);
        
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('dejavusans', 'I', 7);
        $this->SetTextColor(100, 100, 100);
        
        $printDate = ReportFormatter::formatDateTime(now());
        $totalFormatted = ReportFormatter::bangladeshNumber($this->totalRecords);
        $pageInfoText = "Page {$this->getAliasNumPage()} of {$totalFormatted}";
        
        $pageWidth = $this->getPageWidth();
        
        $this->SetX(10);
        $this->Cell(0, 5, "Print On: {$printDate}", 0, 0, 'L');
        
        $pageInfoWidth = $this->GetStringWidth($pageInfoText);
        $this->SetX($pageWidth - 10 - $pageInfoWidth);
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
        $this->SetTitle($name);
        return $this;
    }

    public function setNumericColumns(array $columns): self
    {
        $this->numericColumns = $columns;
        return $this;
    }

    public function setGroupBy(string $column): self
    {
        $this->groupByColumn = $column;
        $this->useAdvancedTable = true;
        return $this;
    }

    public function setTableConfig(array $config): self
    {
        $this->tableConfig = $config;
        return $this;
    }

    public function setColumnStyles(array $styles): self
    {
        $this->columnStyles = $styles;
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
        
        if ($this->useAdvancedTable && !empty($this->groupByColumn)) {
            $this->initializeAdvancedTable();
        } else {
            $this->writeColumnHeader();
        }
    }

    private function writeReportInfo(): void
    {
        $this->SetFont('dejavusans', 'B', 12);
        $this->Cell(0, 8, $this->reportName, 0, 1, 'L');
        
        if (!empty($this->filters)) {
            $filterParts = [];
            
            if (isset($this->filters['start_date']) && isset($this->filters['end_date'])) {
                $startDate = ReportFormatter::formatDate($this->filters['start_date']);
                $endDate = ReportFormatter::formatDate($this->filters['end_date']);
                if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
                    $filterParts[] = 'Date Range: ' . $startDate . ' To ' . $endDate;
                }
            } elseif (isset($this->filters['date'])) {
                $filterParts[] = 'Date: ' . ReportFormatter::formatDate($this->filters['date']);
            }
            
            foreach ($this->filters as $key => $value) {
                if (in_array($key, ['start_date', 'end_date', 'date'])) continue;
                if (empty($value)) continue;
                
                $label = ReportFormatter::formatHeaderName($key);
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $filterParts[] = $label . ': ' . $value;
            }
            
            if (!empty($filterParts)) {
                $this->SetFont('dejavusans', '', 7);
                $filterLine = 'Filters: ' . implode(' | ', $filterParts);
                $this->Cell(0, 4, $filterLine, 0, 0, 'L');
                $this->Ln(4.5);
            }
        }
        
        $this->Ln(2);
    }

    private function initializeAdvancedTable(): void
    {
        $this->table = new Table($this);
        
        $this->table->setStyle('default', 7, '', [0, 0, 0], 'helvetica');
        $this->table->setStyle('p', 7, '', [0, 0, 0], 'helvetica');
        $this->table->setStyle('b', 7, 'B', [0, 0, 0], 'helvetica');
        $this->table->setStyle('header', 8, 'B', [255, 255, 255], 'helvetica');
        $this->table->setStyle('subtotal', 7, 'B', [68, 114, 196], 'helvetica');
        $this->table->setStyle('grandtotal', 8, 'B', [226, 239, 218], 'helvetica');
        $this->table->setStyle('numeric', 7, '', [0, 0, 0], 'helvetica');
        
        $config = array_merge([
            'TABLE' => [
                'BORDER_COLOR' => [0, 0, 0],
                'BORDER_SIZE' => 0.2,
                'BORDER_TYPE' => 1,
            ],
            'HEADER' => [
                'BACKGROUND_COLOR' => [68, 114, 196],
                'TEXT_COLOR' => [255, 255, 255],
                'TEXT_ALIGN' => 'C',
                'TEXT_FONT' => 'helvetica',
                'TEXT_SIZE' => 8,
                'TEXT_TYPE' => 'B',
            ],
            'ROW' => [
                'BACKGROUND_COLOR' => [255, 255, 255],
                'TEXT_ALIGN' => 'L',
                'TEXT_COLOR' => [0, 0, 0],
                'TEXT_FONT' => 'helvetica',
                'TEXT_SIZE' => 7,
            ],
        ], $this->tableConfig);
        
        $this->table->initialize($this->reportColumnWidths, $config);
        $this->table->setSplitMode(true);
        
        $this->addTableHeader();
    }

    private function addTableHeader(): void
    {
        $header = [];
        foreach ($this->reportColumns as $index => $column) {
            $headerName = ReportFormatter::formatHeaderName($column);
            $header[$index] = [
                'TEXT' => $headerName,
                'STYLE' => 'header',
                'TEXT_ALIGN' => 'C',
            ];
            
            $colName = $column;
            if (isset($this->columnStyles[$colName])) {
                $header[$index] = array_merge($header[$index], $this->columnStyles[$colName]);
            }
            
            if (ReportFormatter::isNumericColumn($colName)) {
                $header[$index]['TEXT_ALIGN'] = 'R';
            }
        }
        
        $this->table->addHeader($header);
        $this->headerAdded = true;
    }

    private function writeColumnHeader(): void
    {
        $this->SetFillColor(68, 114, 196);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.1);
        $this->SetFont('dejavusans', 'B', 8);
        
        $pageWidth = $this->getPageWidth() - 20;
        $colWidth = $pageWidth / count($this->reportColumns);
        
        foreach ($this->reportColumns as $index => $column) {
            $headerName = ReportFormatter::formatHeaderName($column);
            $cellWidth = $this->reportColumnWidths[$index] ?? $colWidth;
            $this->Cell($cellWidth, 7, $headerName, 1, 0, 'C', true);
        }
        
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('dejavusans', '', 7);
        $this->headerWritten = true;
    }

    public function writeRow(array $data, $handle = null): void
    {
        if ($this->useAdvancedTable && $this->table !== null) {
            $this->processGroupChange($data);
            $this->writeAdvancedRow($data);
        } else {
            $this->writeSimpleRow($data);
        }
        
        $this->currentRow++;
        $this->totalRecords++;
        $this->rowsSinceLastGc++;
        
        if ($this->rowsSinceLastGc >= $this->gcInterval) {
            $this->rowsSinceLastGc = 0;
            gc_collect_cycles();
        }
    }

    private function processGroupChange(array $data): void
    {
        if (empty($this->groupByColumn)) {
            return;
        }
        
        $groupColumnIndex = array_search($this->groupByColumn, $this->reportColumns);
        if ($groupColumnIndex === false) {
            return;
        }
        
        $currentValue = $data[$groupColumnIndex] ?? null;
        
        if ($this->currentGroupValue !== null && $currentValue !== $this->currentGroupValue) {
            $this->writeSubtotal($this->currentGroupValue);
        }
        
        if ($this->currentGroupValue !== $currentValue) {
            $this->currentGroupValue = $currentValue;
            $this->resetGroupSubtotals();
        }
    }

    private function resetGroupSubtotals(): void
    {
        $this->groupSubtotals = [];
        foreach ($this->reportColumns as $index => $column) {
            $this->groupSubtotals[$index] = 0;
        }
    }

    private function writeSubtotal(mixed $groupValue): void
    {
        $row = [];
        foreach ($this->reportColumns as $index => $column) {
            $isNumeric = ReportFormatter::isNumericColumn($column);
            
            if ($index === 0) {
                $row[$index] = [
                    'TEXT' => 'Subtotal - ' . $groupValue,
                    'STYLE' => 'subtotal',
                    'COLSPAN' => 2,
                ];
                $row[$index + 1] = [
                    'TEXT' => '',
                    'STYLE' => 'subtotal',
                ];
            } elseif ($isNumeric && isset($this->groupSubtotals[$index]) && $this->groupSubtotals[$index] != 0) {
                $formattedValue = ReportFormatter::formatValue($this->groupSubtotals[$index], true);
                $row[$index] = [
                    'TEXT' => $formattedValue,
                    'STYLE' => 'subtotal',
                    'TEXT_ALIGN' => 'R',
                ];
            } else {
                $row[$index] = [
                    'TEXT' => '',
                    'STYLE' => 'subtotal',
                ];
            }
        }
        
        $this->table->addRow($row);
    }

    private function writeAdvancedRow(array $data): void
    {
        $row = [];
        $fill = ($this->currentRow % 2) == 0;
        $bgColor = $fill ? [245, 245, 245] : [255, 255, 255];
        
        foreach ($data as $index => $value) {
            $columnName = $this->reportColumns[$index] ?? '';
            $isNumeric = ReportFormatter::isNumericColumn($columnName);
            $formattedValue = ReportFormatter::formatValue($value, $isNumeric);
            
            $cellConfig = [
                'TEXT' => $formattedValue,
                'STYLE' => 'body',
                'BACKGROUND_COLOR' => $bgColor,
            ];
            
            if ($isNumeric) {
                $cellConfig['TEXT_ALIGN'] = 'R';
                
                if (is_numeric($value)) {
                    $this->groupSubtotals[$index] = ($this->groupSubtotals[$index] ?? 0) + floatval($value);
                }
            }
            
            if (isset($this->columnStyles[$columnName])) {
                $cellConfig = array_merge($cellConfig, $this->columnStyles[$columnName]);
            }
            
            $row[$index] = $cellConfig;
        }
        
        $this->table->addRow($row);
    }

    private function writeSimpleRow(array $data): void
    {
        $pageWidth = $this->getPageWidth() - 20;
        $colWidth = $pageWidth / count($this->reportColumns);
        
        $fill = ($this->currentRow % 2) == 0;
        $this->SetFillColor(245, 245, 245);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('dejavusans', '', 7);
        
        $rowHeight = 5.5;
        
        foreach ($data as $index => $value) {
            $columnName = $this->reportColumns[$index] ?? '';
            $isNumeric = ReportFormatter::isNumericColumn($columnName);
            $formattedValue = ReportFormatter::formatValue($value, $isNumeric);
            
            $cellWidth = $this->reportColumnWidths[$index] ?? $colWidth;
            $align = $isNumeric ? 'R' : 'L';
            
            $stringWidth = $this->GetStringWidth($formattedValue);
            if ($stringWidth > $cellWidth - 2) {
                $lines = ceil($stringWidth / ($cellWidth - 2));
                $cellHeight = max($rowHeight, $lines * 4);
            } else {
                $cellHeight = $rowHeight;
            }
            
            $this->MultiCell($cellWidth, $cellHeight, $formattedValue, 1, $align, $fill, 0);
            
            if ($isNumeric && is_numeric($value)) {
                $this->columnTotals[$index] += floatval($value);
            }
        }
        
        $this->Ln($rowHeight);
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
        if ($this->useAdvancedTable && $this->table !== null) {
            if (!empty($this->groupByColumn) && $this->currentGroupValue !== null) {
                $this->writeSubtotal($this->currentGroupValue);
            }
            $this->writeGrandTotal();
            $this->table->close();
        } else {
            $this->writeGrandTotal();
        }
        
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $this->Output($filePath, 'F');
        
        $this->cleanup();
        
        return $filePath;
    }

    private function writeGrandTotal(): void
    {
        if ($this->totalRecords === 0) {
            return;
        }
        
        if ($this->useAdvancedTable && $this->table !== null) {
            $this->writeAdvancedGrandTotal();
        } else {
            $this->writeSimpleGrandTotal();
        }
    }

    private function writeAdvancedGrandTotal(): void
    {
        $row = [];
        foreach ($this->reportColumns as $index => $column) {
            $isNumeric = ReportFormatter::isNumericColumn($column);
            
            if ($index === 0) {
                $row[$index] = [
                    'TEXT' => 'Grand Total',
                    'STYLE' => 'grandtotal',
                    'FONT_WEIGHT' => 'B',
                ];
            } elseif ($isNumeric && isset($this->groupSubtotals[$index]) && $this->groupSubtotals[$index] != 0) {
                $formattedValue = ReportFormatter::formatValue($this->groupSubtotals[$index], true);
                $row[$index] = [
                    'TEXT' => $formattedValue,
                    'STYLE' => 'grandtotal',
                    'TEXT_ALIGN' => 'R',
                    'FONT_WEIGHT' => 'B',
                ];
            } else {
                $row[$index] = [
                    'TEXT' => '',
                    'STYLE' => 'grandtotal',
                ];
            }
        }
        
        $this->table->addRow($row);
    }

    private function writeSimpleGrandTotal(): void
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
        $this->SetFont('dejavusans', 'B', 8);
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

    public function addCustomRow(array $cellData, string $style = 'body'): void
    {
        if ($this->table !== null) {
            $row = [];
            foreach ($cellData as $index => $data) {
                if (is_array($data)) {
                    $row[$index] = array_merge(['STYLE' => $style], $data);
                } else {
                    $row[$index] = [
                        'TEXT' => $data,
                        'STYLE' => $style,
                    ];
                }
            }
            $this->table->addRow($row);
        }
    }

    public function addColspanRow(array $data, int $colspan, string $text, string $style = 'subtotal'): void
    {
        if ($this->table !== null) {
            $row = [];
            $currentIndex = 0;
            
            foreach ($data as $index => $value) {
                if ($currentIndex == $index) {
                    $row[$currentIndex] = [
                        'TEXT' => $text,
                        'STYLE' => $style,
                        'COLSPAN' => $colspan,
                        'FONT_WEIGHT' => 'B',
                    ];
                    $currentIndex += $colspan;
                } else {
                    $row[$index] = [
                        'TEXT' => is_array($value) ? ($value['TEXT'] ?? '') : $value,
                        'STYLE' => $style,
                    ];
                    $currentIndex++;
                }
            }
            
            $this->table->addRow($row);
        }
    }

    public function addEmptyRow(): void
    {
        if ($this->table !== null) {
            $row = [];
            foreach ($this->reportColumns as $index => $column) {
                $row[$index] = [
                    'TEXT' => '',
                    'STYLE' => 'body',
                    'LINE_SIZE' => 2,
                ];
            }
            $this->table->addRow($row);
        }
    }

    public function addSectionHeader(string $text, int $colspan = 0, array $options = []): void
    {
        if ($this->table === null) {
            return;
        }
        
        if ($colspan === 0) {
            $colspan = count($this->reportColumns);
        }
        
        $row = [];
        $row[0] = array_merge([
            'TEXT' => $text,
            'STYLE' => 'subtotal',
            'COLSPAN' => $colspan,
            'FONT_WEIGHT' => 'B',
            'TEXT_ALIGN' => 'C',
        ], $options);
        
        for ($i = 1; $i < $colspan; $i++) {
            $row[$i] = ['TEXT' => '', 'STYLE' => 'subtotal'];
        }
        
        $this->table->addRow($row);
    }

    private function cleanup(): void
    {
        $this->reportColumns = [];
        $this->reportColumnWidths = [];
        $this->columnTotals = [];
        $this->groupSubtotals = [];
        $this->tableConfig = [];
        $this->columnStyles = [];
        $this->table = null;
        gc_collect_cycles();
    }
}
