<?php

declare(strict_types=1);

namespace TurboStreamExport\Providers;

use Illuminate\Support\ServiceProvider;
use TurboStreamExport\Services\ExportService;

class TurboStreamExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExportService::class, function ($app) {
            return new ExportService(
                disk: config('turbo-export.disk', 'local')
            );
        });

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

        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
    }
}
