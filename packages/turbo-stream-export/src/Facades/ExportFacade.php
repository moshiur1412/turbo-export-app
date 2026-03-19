<?php

declare(strict_types=1);

namespace TurboStreamExport\Facades;

use Illuminate\Support\Facades\Facade;
use TurboStreamExport\Services\ExportService;

class ExportFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExportService::class;
    }
}
