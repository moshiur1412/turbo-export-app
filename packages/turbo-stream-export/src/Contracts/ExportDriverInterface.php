<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts;

use Illuminate\Support\Collection;

interface ExportDriverInterface
{
    public function getFormat(): string;

    public function getContentType(): string;

    public function getFileExtension(): string;

    public function writeHeader(array $columns, $handle): void;

    public function writeRow(array $data, $handle): void;

    public function writeBatch(Collection $records, array $columns, $handle): void;

    public function finalize($handle, string $filePath): string;
}
