<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use League\Csv\Writer;
use TurboStreamExport\Contracts\ExportDriverInterface;
use App\Services\ReportFormatter;

class CsvExportDriver implements ExportDriverInterface
{
    private ?string $buffer = null;
    private bool $isMemoryMode = false;
    private array $numericColumns = [];
    private string $reportName = '';
    private array $filters = [];
    private int $totalRecords = 0;

    public function getFormat(): string
    {
        return 'csv';
    }

    public function getContentType(): string
    {
        return 'text/csv';
    }

    public function getFileExtension(): string
    {
        return 'csv';
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
        if ($handle === null) {
            $this->isMemoryMode = true;
            $this->buffer = '';
            return;
        }

        $headerLines = ReportFormatter::buildReportHeader($this->reportName, $this->filters, true);
        foreach ($headerLines as $line) {
            fwrite($handle, $this->escapeCsvValue($line) . "\n");
        }
        fwrite($handle, "\n");

        $formattedColumns = array_map([ReportFormatter::class, 'formatHeaderName'], $columns);
        fputcsv($handle, $formattedColumns);
    }

    public function writeRow(array $data, $handle = null): void
    {
        if ($this->isMemoryMode) {
            $this->buffer .= implode(',', array_map([$this, 'escapeCsvValue'], $data)) . "\n";
            return;
        }
        fputcsv($handle, $data);
    }

    public function writeBatch(\Illuminate\Support\Collection $records, array $columns, $handle = null): void
    {
        $recordCount = $records->count();
        $this->totalRecords += $recordCount;
        
        $firstRecord = $records->first();
        $usesProcessedRows = $firstRecord && is_object($firstRecord) && (get_class($firstRecord) === 'stdClass' || isset($firstRecord->{'Employee ID'}) || isset($firstRecord->{'Employee Name'}));
        
        if ($this->isMemoryMode) {
            foreach ($records as $record) {
                if ($usesProcessedRows) {
                    $formattedRow = $this->formatProcessedRow($record, $columns);
                } else {
                    $row = array_map(fn($col) => data_get($record, $col), $columns);
                    $formattedRow = $this->formatRow($row, $columns);
                }
                $this->buffer .= implode(',', array_map([$this, 'escapeCsvValue'], $formattedRow)) . "\n";
            }
            return;
        }

        if ($usesProcessedRows) {
            foreach ($records as $record) {
                $formattedRow = $this->formatProcessedRow($record, $columns);
                fputcsv($handle, $formattedRow);
            }
        } else {
            $data = $records->map(function ($record) use ($columns) {
                return array_map(fn($col) => data_get($record, $col), $columns);
            })->toArray();

            foreach ($data as $row) {
                $formattedRow = $this->formatRow($row, $columns);
                fputcsv($handle, $formattedRow);
            }
        }
    }
    
    private function formatProcessedRow($record, array $columns): array
    {
        $formatted = [];
        foreach ($columns as $columnName) {
            $value = $record->{$columnName} ?? null;
            $isNumeric = ReportFormatter::isNumericColumn($columnName);
            $formatted[] = ReportFormatter::formatValue($value, $isNumeric);
        }
        return $formatted;
    }

    public function finalize(string $filePath, $handle = null): string
    {
        if ($this->isMemoryMode && $this->buffer !== null) {
            $content = '';
            $headerLines = ReportFormatter::buildReportHeader($this->reportName, $this->filters, true);
            foreach ($headerLines as $line) {
                $content .= $this->escapeCsvValue($line) . "\n";
            }
            $content .= "\n";
            $content .= $this->buffer;

            $content .= "\n";
            $content .= ReportFormatter::getFooterText(1, $this->totalRecords);

            $directory = dirname($filePath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($filePath, $content);
            $this->buffer = null;
            $this->isMemoryMode = false;
            return $filePath;
        }

        if ($handle !== null) {
            fwrite($handle, "\n");
            fwrite($handle, ReportFormatter::getFooterText(1, $this->totalRecords) . "\n");
            fclose($handle);
        }

        return $filePath;
    }

    private function escapeCsvValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_string($value) && (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false)) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return (string) $value;
    }

    private function formatRow(array $row, array $columns): array
    {
        $formatted = [];
        foreach ($row as $index => $value) {
            $columnName = $columns[$index] ?? '';
            $isNumeric = ReportFormatter::isNumericColumn($columnName);
            $formatted[] = ReportFormatter::formatValue($value, $isNumeric);
        }
        return $formatted;
    }
}
