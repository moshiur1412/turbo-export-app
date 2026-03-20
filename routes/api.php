<?php

use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\SalaryReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/departments', [DepartmentController::class, 'index']);

Route::prefix('reports')->group(function () {
    Route::get('/', [ReportController::class, 'index']);
    Route::post('/', [ReportController::class, 'store']);
    Route::get('/types', [ReportController::class, 'types']);
    Route::get('/formats', [ReportController::class, 'formats']);
    Route::get('/{id}', [ReportController::class, 'show']);
    Route::get('/{id}/progress', [ReportController::class, 'progress']);
    Route::get('/{id}/download', [ReportController::class, 'download'])->name('api.reports.download');
    Route::post('/{id}/cancel', [ReportController::class, 'cancel']);
    Route::post('/{id}/retry', [ReportController::class, 'retry']);
    Route::delete('/{id}', [ReportController::class, 'destroy']);
});

Route::post('/reports/salary', [SalaryReportController::class, 'create']);
Route::post('/reports/salary/export', [SalaryReportController::class, 'export']);
Route::post('/reports/dynamic', [SalaryReportController::class, 'dynamicReport']);
