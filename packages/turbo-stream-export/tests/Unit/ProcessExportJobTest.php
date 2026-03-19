<?php

declare(strict_types=1);

namespace TurboStreamExport\Tests\Unit;

use TurboStreamExport\Jobs\ProcessExportJob;
use TurboStreamExport\Tests\TestCase;

class ProcessExportJobTest extends TestCase
{
    public function test_job_can_be_instantiated(): void
    {
        $job = new ProcessExportJob(
            exportId: 'test-123',
            modelClass: 'App\Models\User',
            columns: ['id', 'name', 'email'],
            filters: [['status', '=', 'active']],
            filename: 'users_export',
            format: 'csv',
            userId: 1
        );

        $this->assertEquals('test-123', $job->exportId);
        $this->assertEquals('App\Models\User', $job->modelClass);
        $this->assertEquals(['id', 'name', 'email'], $job->columns);
        $this->assertEquals('csv', $job->format);
        $this->assertEquals(1, $job->userId);
    }

    public function test_job_stores_filters_correctly(): void
    {
        $filters = [
            ['status', '=', 'active'],
            ['created_at', '>=', '2026-01-01'],
            ['category_id', 'IN', [1, 2, 3]],
        ];

        $job = new ProcessExportJob(
            exportId: 'test-456',
            modelClass: 'App\Models\Post',
            columns: ['id', 'title'],
            filters: $filters,
            filename: 'posts_export',
            format: 'xlsx',
            userId: 2
        );

        $this->assertEquals($filters, $job->filters);
        $this->assertCount(3, $job->filters);
    }

    public function test_job_tags_are_correct(): void
    {
        $job = new ProcessExportJob(
            exportId: 'test-789',
            modelClass: 'App\Models\User',
            columns: ['id', 'name'],
            filters: [],
            filename: 'export',
            format: 'csv',
            userId: 5
        );

        $tags = $job->tags();

        $this->assertContains('export', $tags);
        $this->assertContains('export:test-789', $tags);
        $this->assertContains('user:5', $tags);
        $this->assertContains('export:format:csv', $tags);
    }

    public function test_job_supports_custom_chunk_size(): void
    {
        $job = new ProcessExportJob(
            exportId: 'test-chunk',
            modelClass: 'App\Models\BigTable',
            columns: ['*'],
            filters: [],
            filename: 'large_export',
            format: 'csv',
            userId: 1,
            customChunkSize: 20000
        );

        $this->assertEquals(20000, $job->customChunkSize);
    }

    public function test_job_supports_high_priority(): void
    {
        $normalJob = new ProcessExportJob(
            exportId: 'normal',
            modelClass: 'App\Models\User',
            columns: ['id'],
            filters: [],
            filename: 'normal',
            format: 'csv',
            userId: 1
        );

        $priorityJob = new ProcessExportJob(
            exportId: 'priority',
            modelClass: 'App\Models\User',
            columns: ['id'],
            filters: [],
            filename: 'priority',
            format: 'csv',
            userId: 1,
            highPriority: true
        );

        $this->assertFalse($normalJob->highPriority);
        $this->assertTrue($priorityJob->highPriority);
    }

    public function test_job_retry_until_returns_future_date(): void
    {
        $job = new ProcessExportJob(
            exportId: 'test',
            modelClass: 'App\Models\User',
            columns: ['id'],
            filters: [],
            filename: 'test',
            format: 'csv',
            userId: 1
        );

        $retryUntil = $job->retryUntil();
        
        $this->assertGreaterThan(now(), $retryUntil);
    }
}
