<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use TurboStreamExport\Contracts\ExportDriverInterface;
use App\Services\ReportFormatter;

class SqlExportDriver implements ExportDriverInterface
{
    private string $tableName = 'export_data';
    private array $columns = [];
    private array $insertStatements = [];
    private int $currentBatch = 0;
    private int $batchSize = 1000;
    private string $createStatement = '';
    private $handle;
    private string $filePath = '';
    private string $reportName = '';
    private array $filters = [];
    private array $numericColumns = [];
    private int $totalRecords = 0;

    public function __construct()
    {
    }

    public function getFormat(): string
    {
        return 'sql';
    }

    public function getContentType(): string
    {
        return 'application/sql';
    }

    public function getFileExtension(): string
    {
        return 'sql';
    }

    public function setTableName(string $tableName): self
    {
        $this->tableName = preg_replace('/[^a-zA-Z0-9_]/', '_', $tableName);
        return $this;
    }

    public function setBatchSize(int $size): self
    {
        $this->batchSize = $size;
        return $this;
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
        
        $sanitizedColumns = array_map(function ($col) {
            return '`' . $this->sanitizeIdentifier($col) . '`';
        }, $columns);
        
        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = '  `' . $this->sanitizeIdentifier($column) . '` VARCHAR(255)';
        }
        
        $this->createStatement = sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n%s\n);\n\n",
            $this->tableName,
            implode(",\n", $columnDefinitions)
        );
    }

    public function writeRow(array $data, $handle = null): void
    {
        $values = array_map([$this, 'escapeValue'], $data);
        
        $this->insertStatements[] = '(' . implode(', ', $values) . ')';
        
        if (count($this->insertStatements) >= $this->batchSize) {
            $this->flushStatements();
        }
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
        $this->filePath = $filePath;
        
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $handle = fopen($filePath, 'w');
        
        fwrite($handle, "-- ================================================\n");
        fwrite($handle, "-- TurboStream Export Engine\n");
        fwrite($handle, "-- Report: " . $this->reportName . "\n");
        fwrite($handle, "-- Generated: " . ReportFormatter::formatDateTime(now()) . "\n");
        fwrite($handle, "-- Total Records: " . count($this->insertStatements) . "\n");
        
        if (!empty($this->filters)) {
            $filterParts = [];
            foreach ($this->filters as $key => $value) {
                if (empty($value)) continue;
                
                $label = ReportFormatter::formatHeaderName($key);
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                if (in_array($key, ['start_date', 'end_date', 'date'])) {
                    $value = ReportFormatter::formatDate($value);
                }
                $filterParts[] = "{$label}: {$value}";
            }
            
            if (!empty($filterParts)) {
                fwrite($handle, "-- Filters: " . implode(' | ', $filterParts) . "\n");
            }
        }
        
        fwrite($handle, "-- ================================================\n\n");
        
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($handle, "SET UNIQUE_CHECKS = 0;\n");
        fwrite($handle, "SET autocommit = 0;\n\n");
        
        if (!empty($this->createStatement)) {
            fwrite($handle, $this->createStatement);
        }
        
        if (!empty($this->insertStatements)) {
            $this->flushStatements($handle);
        }
        
        fwrite($handle, "\nCOMMIT;\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fwrite($handle, "SET UNIQUE_CHECKS = 1;\n");
        fwrite($handle, "SET autocommit = 1;\n\n");
        
        fwrite($handle, "-- ================================================\n");
        fwrite($handle, "-- Export completed successfully.\n");
        fwrite($handle, "-- " . ReportFormatter::getFooterText(1, $this->totalRecords) . "\n");
        fwrite($handle, "-- ================================================\n");
        
        fclose($handle);
        
        $this->insertStatements = [];
        $this->createStatement = '';
        
        return $filePath;
    }

    private function flushStatements($handle = null): void
    {
        if (empty($this->insertStatements)) {
            return;
        }
        
        $columns = array_map(fn($col) => '`' . $this->sanitizeIdentifier($col) . '`', $this->columns);
        
        $insertSql = sprintf(
            "INSERT INTO `%s` (%s) VALUES\n%s;\n\n",
            $this->tableName,
            implode(', ', $columns),
            implode(",\n", $this->insertStatements)
        );
        
        if ($handle) {
            fwrite($handle, $insertSql);
        }
        
        $this->insertStatements = [];
        $this->currentBatch++;
    }

    private function escapeValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        
        if ($value instanceof \DateTimeInterface) {
            return "'" . ReportFormatter::formatDate($value) . "'";
        }
        
        return "'" . addcslashes((string) $value, "'\\") . "'";
    }

    private function sanitizeIdentifier(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $identifier);
    }
}
