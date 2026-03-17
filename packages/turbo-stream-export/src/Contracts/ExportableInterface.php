<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface ExportableInterface
{
    public function getExportQuery(): Builder;
    
    public function getExportColumns(): array;
    
    public function getExportFilename(): string;
}
