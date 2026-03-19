<?php

declare(strict_types=1);

namespace TurboStreamExport\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use TurboStreamExport\Contracts\Drivers\CsvExportDriver;
use TurboStreamExport\Contracts\Drivers\DocxExportDriver;
use TurboStreamExport\Contracts\Drivers\PdfExportDriver;
use TurboStreamExport\Contracts\Drivers\SqlExportDriver;
use TurboStreamExport\Contracts\Drivers\XlsxExportDriver;
use TurboStreamExport\Contracts\ExportDriverInterface;
use TurboStreamExport\Facades\ExportFacade;
use TurboStreamExport\Services\ExportService;

class TurboStreamExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerDrivers();
        $this->registerExportService();
        $this->registerFacade();
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/turbo-export.php',
            'turbo-export'
        );
    }

    private function registerDrivers(): void
    {
        $this->app->singleton(CsvExportDriver::class, function () {
            return new CsvExportDriver();
        });

        $this->app->singleton(XlsxExportDriver::class, function () {
            return new XlsxExportDriver();
        });

        $this->app->singleton(PdfExportDriver::class, function () {
            return new PdfExportDriver();
        });

        $this->app->singleton(DocxExportDriver::class, function () {
            return new DocxExportDriver();
        });

        $this->app->singleton(SqlExportDriver::class, function () {
            return new SqlExportDriver();
        });

        $this->app->bind(ExportDriverInterface::class . '.csv', CsvExportDriver::class);
        $this->app->bind(ExportDriverInterface::class . '.xlsx', XlsxExportDriver::class);
        $this->app->bind(ExportDriverInterface::class . '.pdf', PdfExportDriver::class);
        $this->app->bind(ExportDriverInterface::class . '.docx', DocxExportDriver::class);
        $this->app->bind(ExportDriverInterface::class . '.sql', SqlExportDriver::class);
    }

    private function registerExportService(): void
    {
        $this->app->singleton(ExportService::class, function ($app) {
            $formats = config('turbo-export.formats', ['csv']);
            $drivers = [];

            foreach ($formats as $format) {
                $driverClass = config("turbo-export.drivers.{$format}");
                
                if ($driverClass && class_exists($driverClass)) {
                    $drivers[] = $app->make($driverClass);
                }
            }

            return new ExportService(
                disk: config('turbo-export.disk', 'local'),
                drivers: $drivers
            );
        });

        $this->app->alias(ExportService::class, 'turbo-export');
        $this->app->alias(ExportService::class, ExportFacade::class);
    }

    private function registerFacade(): void
    {
        $this->app->singleton('turbo.export', function ($app) {
            return $app->make(ExportService::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/turbo-export.php' => config_path('turbo-export.php'),
        ], 'turbo-export-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations/' => database_path('migrations'),
        ], 'turbo-export-migrations');

        Route::prefix('api/exports')
            ->group(__DIR__ . '/../../routes/api.php');
    }

    public function provides(): array
    {
        return [
            ExportService::class,
            CsvExportDriver::class,
            XlsxExportDriver::class,
            PdfExportDriver::class,
            DocxExportDriver::class,
            SqlExportDriver::class,
            'turbo-export',
        ];
    }
}
