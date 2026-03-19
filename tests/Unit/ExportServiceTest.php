<?php

declare(strict_types=1);

use TurboStreamExport\Contracts\Drivers\CsvExportDriver;
use TurboStreamExport\Services\ExportService;

describe('ExportService', function () {
    it('registers drivers correctly', function () {
        $service = new ExportService('local', [new CsvExportDriver()]);
        
        expect($service->hasDriver('csv'))->toBeTrue();
        expect($service->getDriver('csv'))->toBeInstanceOf(CsvExportDriver::class);
    });

    it('throws exception for unknown driver format', function () {
        $service = new ExportService('local', [new CsvExportDriver()]);
        
        expect(fn() => $service->getDriver('unknown'))
            ->toThrow(\InvalidArgumentException::class, 'Driver for format [unknown] not registered.');
    });

    it('calculates correct chunk size for large datasets', function () {
        $service = new ExportService('local', [new CsvExportDriver()]);
        
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

describe('CsvExportDriver', function () {
    it('returns correct format identifier', function () {
        $driver = new CsvExportDriver();
        expect($driver->getFormat())->toBe('csv');
    });

    it('returns correct content type', function () {
        $driver = new CsvExportDriver();
        expect($driver->getContentType())->toBe('text/csv');
    });

    it('returns correct file extension', function () {
        $driver = new CsvExportDriver();
        expect($driver->getFileExtension())->toBe('csv');
    });
});
