<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use League\Csv\Writer;
use TurboStreamExport\Contracts\ExportDriverInterface;

class CsvExportDriver implements ExportDriverInterface
{
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

    public function writeHeader(array $columns, $handle): void
    {
        fputcsv($handle, $columns);
    }

    public function writeRow(array $data, $handle): void
    {
        fputcsv($handle, $data);
    }

    public function writeBatch(\Illuminate\Support\Collection $records, array $columns, $handle): void
    {
        $data = $records->map(function ($record) use ($columns) {
            return array_map(fn($col) => data_get($record, $col), $columns);
        })->toArray();

        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
    }

    public function finalize($handle, string $filePath): string
    {
        fclose($handle);

        return $filePath;
    }
}
