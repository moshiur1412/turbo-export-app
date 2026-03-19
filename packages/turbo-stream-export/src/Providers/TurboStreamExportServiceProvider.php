<?php

declare(strict_types=1);

namespace TurboStreamExport\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use TurboStreamExport\Contracts\Drivers\CsvExportDriver;
use TurboStreamExport\Contracts\ExportDriverInterface;
use TurboStreamExport\Services\ExportService;

class TurboStreamExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExportDriverInterface::class . '.csv', function () {
            return new CsvExportDriver();
        });

        $this->app->singleton(ExportService::class, function ($app) {
            $drivers = [
                $app->make(ExportDriverInterface::class . '.csv'),
            ];

            return new ExportService(
                disk: config('turbo-export.disk', 'local'),
                drivers: $drivers
            );
        });

        $this->app->alias(ExportService::class, 'turbo-export');

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/turbo-export.php',
            'turbo-export'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/turbo-export.php' => config_path('turbo-export.php'),
        ], 'turbo-export-config');

        Route::prefix('api/exports')
            ->group(__DIR__ . '/../../routes/api.php');
    }
}
