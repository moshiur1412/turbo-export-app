<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\Style\Cell;
use PhpOffice\PhpWord\Style\Font;
use TurboStreamExport\Contracts\ExportDriverInterface;
use App\Services\ReportFormatter;

class DocxExportDriver implements ExportDriverInterface
{
    private PhpWord $phpWord;
    private \PhpOffice\PhpWord\Element\Table $table;
    private array $columns = [];
    private int $currentRowIndex = 0;
    private string $title = 'Export Data';
    private string $reportName = '';
    private array $filters = [];
    private array $numericColumns = [];
    private array $allRecords = [];
    private array $columnTotals = [];
    private int $totalRecords = 0;
    private $currentSection;

    public function __construct()
    {
        $this->phpWord = new PhpWord();
        $this->phpWord->getDocumentProperties()
            ->setCreator('TurboStream Export Engine')
            ->setTitle($this->title)
            ->setSubject('Data Export');
        
        $this->currentSection = $this->phpWord->addSection();
        
        $this->table = $this->currentSection->addTable([
            'borderSize' => 6,
            'borderColor' => '4472C4',
            'cellMargin' => 50,
        ]);
    }

    public function getFormat(): string
    {
        return 'docx';
    }

    public function getContentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function getFileExtension(): string
    {
        return 'docx';
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
        $this->columns = $columns;
        $this->currentRowIndex = 0;
        $this->columnTotals = array_fill(0, count($columns), 0);
        
        $this->currentSection->addTitle($this->reportName, 1);
        
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
                $this->currentSection->addText($filterLine, ['size' => 9]);
            }
        }
        
        $this->currentSection->addTextBreak(1);
        
        $headerRow = $this->table->addRow();
        
        foreach ($columns as $column) {
            $cell = $headerRow->addCell();
            $cell->setWidth(2500);
            $cell->addText(
                ReportFormatter::formatHeaderName($column),
                [
                    'bold' => true,
                    'color' => 'FFFFFF',
                    'size' => 9,
                ],
                [
                    'backgroundColor' => '4472C4',
                    'alignment' => 'center',
                ]
            );
        }
    }

    public function writeRow(array $data, $handle = null): void
    {
        $row = $this->table->addRow();
        $isAlternate = ($this->currentRowIndex % 2) == 0;
        
        foreach ($data as $index => $value) {
            $columnName = $this->columns[$index] ?? '';
            $isNumeric = ReportFormatter::isNumericColumn($columnName);
            
            $cell = $row->addCell();
            $cell->setWidth(2500);
            $cell->addText(
                ReportFormatter::formatValue($value, $isNumeric),
                [
                    'size' => 9,
                    'bold' => false,
                ],
                [
                    'backgroundColor' => $isAlternate ? 'F2F2F2' : 'FFFFFF',
                    'alignment' => $isNumeric ? 'right' : 'left',
                    'valign' => 'middle',
                ]
            );
            
            if ($isNumeric && is_numeric($value)) {
                $this->columnTotals[$index] += floatval($value);
            }
        }
        
        $this->currentRowIndex++;
    }

    public function writeBatch($records, array $columns, $handle = null): void
    {
        foreach ($records as $record) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = data_get($record, $column);
            }
            $this->writeRow($row);
            $this->allRecords[] = $record;
            $this->totalRecords++;
        }
    }

    public function finalize(string $filePath, $handle = null): string
    {
        $this->writeGrandTotal();
        $this->writeFooter();
        
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $writer = IOFactory::createWriter($this->phpWord, 'Word2007');
        $writer->save($filePath);
        
        unset($this->phpWord);
        
        return $filePath;
    }

    private function writeGrandTotal(): void
    {
        if (empty($this->numericColumns)) {
            return;
        }
        
        $hasNumeric = false;
        foreach ($this->columnTotals as $total) {
            if ($total != 0) {
                $hasNumeric = true;
                break;
            }
        }
        
        if (!$hasNumeric) {
            return;
        }
        
        $totalRow = $this->table->addRow();
        
        foreach ($this->columnTotals as $index => $total) {
            $columnName = $this->columns[$index] ?? '';
            $isNumeric = ReportFormatter::isNumericColumn($columnName);
            
            $cell = $totalRow->addCell();
            $cell->setWidth(2500);
            
            if ($index === 0) {
                $cell->addText(
                    'Grand Total',
                    [
                        'bold' => true,
                        'size' => 9,
                    ],
                    [
                        'backgroundColor' => 'E2EFDA',
                        'alignment' => 'left',
                    ]
                );
            } elseif ($isNumeric && $total != 0) {
                $cell->addText(
                    ReportFormatter::formatValue($total, true),
                    [
                        'bold' => true,
                        'size' => 9,
                    ],
                    [
                        'backgroundColor' => 'E2EFDA',
                        'alignment' => 'right',
                    ]
                );
            } else {
                $cell->addText(
                    '',
                    ['size' => 9],
                    [
                        'backgroundColor' => 'E2EFDA',
                        'alignment' => 'right',
                    ]
                );
            }
        }
    }

    private function writeFooter(): void
    {
        $sections = $this->phpWord->getSections();
        if (!empty($sections)) {
            $section = end($sections);
            $section->addTextBreak(1);
            $footerText = ReportFormatter::getFooterText(1, $this->totalRecords);
            $section->addText(
                $footerText,
                [
                    'size' => 8,
                    'italic' => true,
                    'color' => '666666',
                ]
            );
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
