<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SalaryReportController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::post('/reports/salary', [SalaryReportController::class, 'create']);
Route::post('/reports/salary/export', [SalaryReportController::class, 'export']);
Route::post('/reports/dynamic', [SalaryReportController::class, 'dynamicReport']);
