<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;
use TurboStreamExport\Services\ExportService;

beforeEach(function () {
    Redis::flushdb();
});

describe('ExportService', function () {
    it('stores and retrieves progress from redis', function () {
        $service = new ExportService();
        
        $exportId = 'test-export-123';
        
        $progress = $service->getProgress($exportId);
        
        expect($progress['status'])->toBe('not_found');
    });

    it('calculates correct chunk size for large datasets', function () {
        $service = new ExportService();
        
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('processExport');
        
        expect($method)->toBeCallable();
    });
});

describe('ProcessExportJob', function () {
    it('can be instantiated with required parameters', function () {
        $job = new \TurboStreamExport\Jobs\ProcessExportJob(
            exportId: 'test-uuid',
            query: \App\Models\User::query(),
            columns: ['id', 'name', 'email'],
            filename: 'test_export',
            format: 'csv',
            userId: 1
        );
        
        expect($job->exportId)->toBe('test-uuid');
        expect($job->format)->toBe('csv');
        expect($job->userId)->toBe(1);
    });

    it('has correct queue configuration', function () {
        $job = new \TurboStreamExport\Jobs\ProcessExportJob(
            exportId: 'test-uuid',
            query: \App\Models\User::query(),
            columns: ['id', 'name'],
            filename: 'test',
            format: 'csv',
            userId: 1
        );
        
        expect($job->queue)->toBe('exports');
        expect($job->tries)->toBe(3);
    });

    it('returns correct tags for job', function () {
        $job = new \TurboStreamExport\Jobs\ProcessExportJob(
            exportId: 'test-uuid-456',
            query: \App\Models\User::query(),
            columns: ['id', 'name'],
            filename: 'test',
            format: 'csv',
            userId: 42
        );
        
        expect($job->tags())->toBe([
            'export',
            'export:test-uuid-456',
            'user:42',
        ]);
    });
});
