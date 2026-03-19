<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use TurboStreamExport\Contracts\ExportDriverInterface;

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
        }
    }

    public function finalize($handle = null, string $filePath): string
    {
        $this->filePath = $filePath;
        
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $handle = fopen($filePath, 'w');
        
        fwrite($handle, "-- TurboStream Export Engine\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Total Records: " . count($this->insertStatements) . "\n");
        fwrite($handle, "--\n\n");
        
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
        
        fwrite($handle, "-- Export completed successfully.\n");
        
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
            return "'" . $value->format('Y-m-d H:i:s') . "'";
        }
        
        return "'" . addcslashes((string) $value, "'\\") . "'";
    }

    private function sanitizeIdentifier(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $identifier);
    }
}
