<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use League\Csv\Writer;
use TurboStreamExport\Contracts\ExportDriverInterface;

class CsvExportDriver implements ExportDriverInterface
{
    private ?string $buffer = null;
    private bool $isMemoryMode = false;

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

    public function writeHeader(array $columns, $handle = null): void
    {
        if ($handle === null) {
            $this->isMemoryMode = true;
            $this->buffer = '';
            $this->buffer .= fputcsv(null, $columns, ',', '"', '') !== false ? implode(',', $columns) . "\n" : '';
            return;
        }
        fputcsv($handle, $columns);
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
        if ($this->isMemoryMode) {
            foreach ($records as $record) {
                $row = array_map(fn($col) => data_get($record, $col), $columns);
                $this->buffer .= implode(',', array_map([$this, 'escapeCsvValue'], $row)) . "\n";
            }
            return;
        }

        $data = $records->map(function ($record) use ($columns) {
            return array_map(fn($col) => data_get($record, $col), $columns);
        })->toArray();

        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
    }

    public function finalize(string $filePath, $handle = null): string
    {
        if ($this->isMemoryMode && $this->buffer !== null) {
            $directory = dirname($filePath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($filePath, $this->buffer);
            $this->buffer = null;
            $this->isMemoryMode = false;
            return $filePath;
        }

        if ($handle !== null) {
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
}