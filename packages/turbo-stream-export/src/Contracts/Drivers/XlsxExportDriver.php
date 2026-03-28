<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use TurboStreamExport\Contracts\ExportDriverInterface;
use App\Services\ReportFormatter;

class XlsxExportDriver implements ExportDriverInterface
{
    private Spreadsheet $spreadsheet;
    private int $currentRow = 1;
    private string $tempFile;
    private array $numericColumns = [];
    private string $reportName = '';
    private array $filters = [];
    private int $totalRecords = 0;
    private array $runningTotals = [];

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->getProperties()
            ->setCreator('TurboStream Export Engine')
            ->setTitle('Export Data')
            ->setSubject('Data Export');
    }

    public function getFormat(): string
    {
        return 'xlsx';
    }

    public function getContentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function getFileExtension(): string
    {
        return 'xlsx';
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

    public function writeHeader(array $columns, $sheet = null): void
    {
        $sheet = $sheet ?? $this->spreadsheet->getActiveSheet();
        
        $this->writeReportHeader($sheet);
        
        $this->currentRow = 3;
        
        $colIndex = 0;
        $lastColIndex = 0;
        foreach ($columns as $column) {
            $cell = chr(65 + $colIndex) . $this->currentRow;
            $sheet->setCellValue($cell, ReportFormatter::formatHeaderName($column));
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFont()->setSize(10);
            $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($cell)->getFill()->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $colIndex++;
            $lastColIndex = $colIndex;
        }
        
        if ($lastColIndex > 0) {
            $headerRange = 'A' . $this->currentRow . ':' . chr(64 + $lastColIndex) . $this->currentRow;
            $sheet->getStyle($headerRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);
        }
        
        $this->currentRow++;
    }
    
    private function writeReportHeader($sheet): void
    {
        $sheet->setCellValue("A1", $this->reportName);
        $sheet->getStyle("A1")->getFont()->setBold(true);
        $sheet->getStyle("A1")->getFont()->setSize(12);
        
        if (!empty($this->filters)) {
            $filterParts = [];
            
            if (isset($this->filters['start_date']) && isset($this->filters['end_date'])) {
                $startDate = ReportFormatter::formatDate($this->filters['start_date']);
                $endDate = ReportFormatter::formatDate($this->filters['end_date']);
                if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
                    $filterParts[] = "Date Range: {$startDate} To {$endDate}";
                }
            } elseif (isset($this->filters['date'])) {
                $filterParts[] = "Date: " . ReportFormatter::formatDate($this->filters['date']);
            }
            
            foreach ($this->filters as $key => $value) {
                if (in_array($key, ['start_date', 'end_date', 'date'])) continue;
                if (empty($value)) continue;
                
                $label = ReportFormatter::formatHeaderName($key);
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $filterParts[] = "{$label}: {$value}";
            }
            
            if (!empty($filterParts)) {
                $filterLine = 'Filters: ' . implode(' | ', $filterParts);
                $sheet->setCellValue("A2", $filterLine);
                $sheet->getStyle("A2")->getFont()->setSize(9);
                $sheet->getStyle("A2")->getAlignment()->setWrapText(true);
            }
        }
    }

    public function writeRow(array $data, $sheet = null): void
    {
        $sheet = $sheet ?? $this->spreadsheet->getActiveSheet();
        $colIndex = 'A';
        
        foreach ($data as $index => $value) {
            $cell = $colIndex . $this->currentRow;
            $columnName = $data[$index] ?? '';
            $isNumeric = ReportFormatter::isNumericColumn($columnName);
            $sheet->setCellValue($cell, ReportFormatter::formatValue($value, $isNumeric));
            
            if ($isNumeric) {
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            } else {
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }
            $sheet->getStyle($cell)->getFont()->setSize(9);
            $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
            $colIndex++;
        }
        
        $this->currentRow++;
    }

    public function writeBatch($records, array $columns, $sheet = null): void
    {
        $sheet = $sheet ?? $this->spreadsheet->getActiveSheet();
        
        $startRow = $this->currentRow;
        
        $firstRecord = $records->first();
        $usesProcessedRows = $firstRecord && is_object($firstRecord) && (get_class($firstRecord) === 'stdClass' || isset($firstRecord->{'Employee ID'}) || isset($firstRecord->{'Employee Name'}));
        
        foreach ($records as $record) {
            $colIndex = 'A';
            $colIndexNum = 0;
            foreach ($columns as $colIndexKey => $columnName) {
                if ($usesProcessedRows) {
                    $value = $record->{$columnName} ?? null;
                } else {
                    $value = data_get($record, $columnName);
                }
                $cell = $colIndex . $this->currentRow;
                $isNumeric = ReportFormatter::isNumericColumn($columnName);
                
                if ($isNumeric && is_numeric($value)) {
                    $sheet->setCellValue($cell, floatval($value));
                } else {
                    $sheet->setCellValue($cell, ReportFormatter::formatValue($value, false));
                }
                
                $colIndex++;
                $colIndexNum++;
            }
            $this->currentRow++;
            $this->totalRecords++;
            
            foreach ($this->numericColumns as $colIndexNum => $columnName) {
                if (!ReportFormatter::isNumericColumn($columnName)) {
                    continue;
                }
                $value = $usesProcessedRows 
                    ? ($record->{$columnName} ?? null)
                    : data_get($record, $columnName);
                if (is_numeric($value)) {
                    if (!isset($this->runningTotals[$columnName])) {
                        $this->runningTotals[$columnName] = 0;
                    }
                    $this->runningTotals[$columnName] += floatval($value);
                }
            }
        }
        
        $this->applyStylesInBatch($sheet, $columns, $startRow);
    }
    
    private function applyStylesInBatch($sheet, array $columns, int $startRow): void
    {
        $endRow = $this->currentRow - 1;
        
        foreach ($columns as $colIndex => $column) {
            $colLetter = chr(65 + $colIndex);
            $cellRange = $colLetter . $startRow . ':' . $colLetter . $endRow;
            
            $isNumeric = ReportFormatter::isNumericColumn($column);
            
            if ($isNumeric) {
                $sheet->getStyle($cellRange)->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
    }

    public function finalize(string $filePath, $handle = null): string
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        
        $this->addGrandTotal($sheet);
        
        $this->addFooter($sheet);
        
        $this->autosizeColumns($sheet);
        
        $this->tempFile = tempnam(sys_get_temp_dir(), 'turbo_export_');
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($this->tempFile);
        
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        copy($this->tempFile, $filePath);
        unlink($this->tempFile);
        
        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);
        
        return $filePath;
    }

    public function getSpreadsheet(): Spreadsheet
    {
        return $this->spreadsheet;
    }

    private function addGrandTotal($sheet): void
    {
        if (empty($this->numericColumns)) {
            return;
        }
        
        foreach ($this->numericColumns as $colIndex => $columnName) {
            if (!ReportFormatter::isNumericColumn($columnName)) {
                continue;
            }
            
            $cell = chr(65 + $colIndex) . $this->currentRow;
            $total = $this->runningTotals[$columnName] ?? 0;
            
            $sheet->setCellValue($cell, $total);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID);
            $sheet->getStyle($cell)->getFill()->getStartColor()->setRGB('E2EFDA');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function addFooter($sheet): void
    {
        $this->currentRow++;
        $footerText = ReportFormatter::getFooterText(1, $this->totalRecords);
        $sheet->setCellValue("A{$this->currentRow}", $footerText);
        $sheet->getStyle("A{$this->currentRow}")->getFont()->setSize(8);
        $sheet->getStyle("A{$this->currentRow}")->getFont()->setItalic(true);
    }

    private function autosizeColumns($sheet): void
    {
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();
        
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $maxLength = 10;
            $colLetter = $col;
            
            for ($row = 1; $row <= $highestRow; $row++) {
                $cellValue = $sheet->getCell($colLetter . $row)->getValue();
                if ($cellValue !== null && $cellValue !== '') {
                    $length = strlen((string) $cellValue);
                    $maxLength = max($maxLength, $length);
                }
            }
            
            $sheet->getColumnDimension($colLetter)->setWidth(min($maxLength + 2, 50));
        }
    }
}
