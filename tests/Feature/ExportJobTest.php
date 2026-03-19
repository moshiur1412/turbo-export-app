<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use TurboStreamExport\Contracts\Drivers\CsvExportDriver;
use TurboStreamExport\Jobs\ProcessExportJob;
use TurboStreamExport\Services\ExportService;

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    
    Redis::flushdb();
});

describe('CsvExportDriver', function () {
    beforeEach(function () {
        $this->csvDriver = new CsvExportDriver();
    });

    it('returns correct format', function () {
        expect($this->csvDriver->getFormat())->toBe('csv');
    });

    it('returns correct content type', function () {
        expect($this->csvDriver->getContentType())->toBe('text/csv');
    });

    it('returns correct file extension', function () {
        expect($this->csvDriver->getFileExtension())->toBe('csv');
    });

    it('writes header to csv', function () {
        $handle = fopen('php://memory', 'w+');
        
        $this->csvDriver->writeHeader(['id', 'name', 'email'], $handle);
        
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        
        expect($content)->toContain('id,name,email');
    });

    it('writes row to csv', function () {
        $handle = fopen('php://memory', 'w+');
        
        $this->csvDriver->writeRow(['1', 'John Doe', 'john@example.com'], $handle);
        
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        
        expect($content)->toContain('1,John Doe,john@example.com');
    });

    it('writes batch of records to csv', function () {
        $handle = fopen('php://memory', 'w+');
        
        $records = collect([
            (object) ['id' => '1', 'name' => 'John', 'email' => 'john@example.com'],
            (object) ['id' => '2', 'name' => 'Jane', 'email' => 'jane@example.com'],
        ]);
        
        $this->csvDriver->writeBatch($records, ['id', 'name', 'email'], $handle);
        
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        
        expect($content)->toContain('1,John,john@example.com');
        expect($content)->toContain('2,Jane,jane@example.com');
    });
});

describe('ExportService', function () {
    beforeEach(function () {
        $this->csvDriver = new CsvExportDriver();
        $this->exportService = new ExportService('local', [$this->csvDriver]);
    });

    it('registers csv driver', function () {
        expect($this->exportService->hasDriver('csv'))->toBeTrue();
    });

    it('throws exception for unregistered driver', function () {
        expect(fn() => $this->exportService->getDriver('xlsx'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('can get csv driver', function () {
        $driver = $this->exportService->getDriver('csv');
        expect($driver)->toBeInstanceOf(CsvExportDriver::class);
    });

    it('returns not found for non-existent export progress', function () {
        $progress = $this->exportService->getProgress('non-existent-id');
        
        expect($progress['status'])->toBe('not_found');
        expect($progress['progress'])->toBe(0);
    });

    it('processes export and updates progress in redis', function () {
        $user = User::factory()->count(10)->create();
        $query = User::query();
        $exportId = 'test-export-' . uniqid();
        
        $this->exportService->processExport(
            $exportId,
            $query,
            ['id', 'name', 'email'],
            'test_export',
            'csv'
        );
        
        $progress = $this->exportService->getProgress($exportId);
        
        expect($progress['progress'])->toBe(100);
        expect($progress['status'])->toBe('completed');
        expect($progress['total'])->toBe(10);
    });

    it('creates export file on disk', function () {
        $user = User::factory()->count(5)->create();
        $query = User::query();
        $exportId = 'test-export-' . uniqid();
        
        $filePath = $this->exportService->processExport(
            $exportId,
            $query,
            ['id', 'name', 'email'],
            'test_export',
            'csv'
        );
        
        Storage::disk('local')->assertExists($filePath);
    });
});

describe('ProcessExportJob', function () {
    it('implements should queue interface', function () {
        $job = new ProcessExportJob(
            'test-id',
            User::query(),
            ['id', 'name'],
            'test',
            'csv',
            1
        );
        
        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('is dispatched to exports queue', function () {
        $job = new ProcessExportJob(
            'test-id',
            User::query(),
            ['id', 'name'],
            'test',
            'csv',
            1
        );
        
        expect($job->queue)->toBe('exports');
    });

    it('has correct retry configuration', function () {
        $job = new ProcessExportJob(
            'test-id',
            User::query(),
            ['id', 'name'],
            'test',
            'csv',
            1
        );
        
        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe(60);
    });

    it('returns correct tags', function () {
        $job = new ProcessExportJob(
            'test-id',
            User::query(),
            ['id', 'name'],
            'test',
            'csv',
            42
        );
        
        expect($job->tags())->toContain('export', 'export:test-id', 'user:42');
    });

    it('is pushed to queue when dispatched', function () {
        User::factory()->count(5)->create();
        
        ProcessExportJob::dispatch(
            'test-export-id',
            User::query(),
            ['id', 'name'],
            'test_export',
            'csv',
            1
        );
        
        Queue::assertPushedOn('exports', ProcessExportJob::class);
    });
});

describe('Export Integration', function () {
    beforeEach(function () {
        $this->csvDriver = new CsvExportDriver();
        $this->exportService = new ExportService('local', [$this->csvDriver]);
    });

    it('full export workflow from job dispatch to file generation', function () {
        $users = User::factory()->count(20)->create();
        
        $exportId = 'integration-test-' . uniqid();
        
        ProcessExportJob::dispatch(
            $exportId,
            User::query(),
            ['id', 'name', 'email'],
            'integration_test',
            'csv',
            1
        );
        
        Queue::assertPushed(ProcessExportJob::class, function ($job) use ($exportId) {
            return $job->exportId === $exportId;
        });
        
        $this->exportService->processExport(
            $exportId,
            User::query(),
            ['id', 'name', 'email'],
            'integration_test',
            'csv'
        );
        
        $progress = $this->exportService->getProgress($exportId);
        
        expect($progress['progress'])->toBe(100);
        expect($progress['status'])->toBe('completed');
        expect($progress['total'])->toBe(20);
        
        Storage::disk('local')->assertExists($progress['file_path']);
    });

    it('handles empty query result', function () {
        $exportId = 'empty-test-' . uniqid();
        
        $this->exportService->processExport(
            $exportId,
            User::query()->where('id', '>', 999999),
            ['id', 'name'],
            'empty_test',
            'csv'
        );
        
        $progress = $this->exportService->getProgress($exportId);
        
        expect($progress['progress'])->toBe(100);
        expect($progress['total'])->toBe(0);
    });
});
