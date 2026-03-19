<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TurboStreamExport\Http\Controllers\ExportController;

Route::post('/', [ExportController::class, 'create'])
    ->name('exports.create')
    ->middleware('auth:sanctum');

Route::get('/{exportId}/progress', [ExportController::class, 'progress'])
    ->name('exports.progress');

Route::get('/{exportId}/download', [ExportController::class, 'download'])
    ->name('exports.download')
    ->middleware('auth:sanctum');
