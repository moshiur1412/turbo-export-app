<?php

namespace App\Jobs;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Services\ReportQueryBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use TurboStreamExport\Contracts\ExportDriverInterface;
use TurboStreamExport\Services\ExportService;

class ProcessReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 3600;
    public int $maxExceptions = 2;

    private array $chunkSizes = [
        'default' => 5000,
        'large' => 10000,
        'xlarge' => 20000,
    ];

    public function __construct(
        public readonly string $exportId,
        public readonly ReportType $type,
        public readonly ReportFormat $format,
        public readonly array $filters,
        public readonly string $filename,
        public readonly int $userId,
        public readonly ?int $customChunkSize = null,
        public readonly bool $highPriority = false,
    ) {
        $this->onQueue($this->highPriority ? 'exports-high' : 'exports');
        
        Log::info("ProcessReportExportJob created", [
            'export_id' => $this->exportId,
            'type' => $this->type->value,
            'format' => $this->format->value,
            'user_id' => $this->userId,
        ]);
    }

    public function handle(ExportService $exportService): void
    {
        $startTime = microtime(true);
        
        try {
            $queryBuilder = new ReportQueryBuilder($this->type, $this->filters);
            $query = $queryBuilder->buildQuery();
            $columns = $queryBuilder->getColumns();
            
            $columnKeys = array_keys($columns);
            $estimatedRecords = $this->estimateRecordCount($query);
            $chunkSize = $this->determineChunkSize($estimatedRecords);

            Log::info("Starting report export processing", [
                'export_id' => $this->exportId,
                'type' => $this->type->value,
                'estimated_records' => $estimatedRecords,
                'columns_count' => count($columnKeys),
                'chunk_size' => $chunkSize,
                'format' => $this->format->value,
            ]);

            $this->increaseMemoryLimit();

            $driver = $exportService->getDriver($this->format->value);
            $filePath = $this->getFilePath($this->filename, $driver->getFileExtension());
            
            $this->processExportWithClosures(
                $query,
                $columns,
                $columnKeys,
                $filePath,
                $driver,
                $estimatedRecords,
                $chunkSize
            );

            $relativePath = $this->makeRelativePath($filePath);
            $this->updateProgress(100, $estimatedRecords, 'completed', $filePath);

            $duration = round(microtime(true) - $startTime, 2);

            Log::info("Report export completed successfully", [
                'export_id' => $this->exportId,
                'type' => $this->type->value,
                'duration_seconds' => $duration,
                'format' => $this->format->value,
                'file_path' => $filePath,
            ]);

        } catch (\Throwable $e) {
            Log::error("Report export failed", [
                'export_id' => $this->exportId,
                'type' => $this->type->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->updateProgress(0, 0, 'failed', null, $e->getMessage());
            
            throw $e;
        }
    }

    private function processExportWithClosures(
        Builder $query,
        array $columns,
        array $columnKeys,
        string $filePath,
        ExportDriverInterface $driver,
        int $totalRecords,
        int $chunkSize
    ): void {
        $fullPath = storage_path('app/' . $filePath);
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $format = $this->format->value;
        
        if ($format === 'csv' || $format === 'sql') {
            $this->processStreamingExport($query, $columns, $columnKeys, $fullPath, $driver, $totalRecords, $chunkSize);
        } else {
            $this->processMemoryExport($query, $columns, $columnKeys, $fullPath, $driver, $totalRecords, $chunkSize);
        }
    }

    private function processStreamingExport(
        Builder $query,
        array $columns,
        array $columnKeys,
        string $fullPath,
        ExportDriverInterface $driver,
        int $totalRecords,
        int $chunkSize
    ): void {
        $handle = fopen($fullPath, 'w');
        $driver->writeHeader($columnKeys, $handle);

        $exportedRecords = 0;
        $lastLoggedProgress = 0;

        $query->chunk($chunkSize, function ($records) use (
            $driver,
            $columns,
            $columnKeys,
            $handle,
            &$exportedRecords,
            $totalRecords,
            &$lastLoggedProgress,
            $chunkSize
        ) {
            $processedRecords = $this->processRecordsWithClosures($records, $columns);
            $driver->writeBatch($processedRecords, $columnKeys, $handle);

            $exportedRecords += $records->count();
            $progress = $totalRecords > 0 ? (int)(($exportedRecords / $totalRecords) * 100) : 100;
            
            if (($progress - $lastLoggedProgress >= 5) || $exportedRecords === $totalRecords) {
                $this->updateProgress($progress, $totalRecords, 'processing', null);
                $lastLoggedProgress = $progress;
                gc_collect_cycles();
            }
        });

        fclose($handle);
        $driver->finalize($fullPath, null);
    }

    private function processMemoryExport(
        Builder $query,
        array $columns,
        array $columnKeys,
        string $fullPath,
        ExportDriverInterface $driver,
        int $totalRecords,
        int $chunkSize
    ): void {
        $driver->writeHeader($columnKeys, null);

        $exportedRecords = 0;
        $lastLoggedProgress = 0;

        $query->chunk($chunkSize, function ($records) use (
            $driver,
            $columns,
            $columnKeys,
            &$exportedRecords,
            $totalRecords,
            &$lastLoggedProgress,
            $chunkSize
        ) {
            $processedRecords = $this->processRecordsWithClosures($records, $columns);
            $driver->writeBatch($processedRecords, $columnKeys, null);

            $exportedRecords += $records->count();
            $progress = $totalRecords > 0 ? (int)(($exportedRecords / $totalRecords) * 100) : 100;
            
            if (($progress - $lastLoggedProgress >= 5) || $exportedRecords === $totalRecords) {
                $this->updateProgress($progress, $totalRecords, 'processing', null);
                $lastLoggedProgress = $progress;
                gc_collect_cycles();
            }
        });

        $driver->finalize($fullPath, null);
    }

    private function processRecordsWithClosures(Collection $records, array $columns): Collection
    {
        return $records->map(function ($record) use ($columns) {
            $row = [];
            foreach ($columns as $key => $column) {
                if ($column instanceof \Closure) {
                    $row[$key] = $column($record);
                } else {
                    $row[$key] = data_get($record, $column, $column);
                }
            }
            return (object) $row;
        });
    }

    private function getFilePath(string $filename, string $extension): string
    {
        $directory = 'exports';
        if (!file_exists(storage_path('app/' . $directory))) {
            mkdir(storage_path('app/' . $directory), 0755, true);
        }
        return $directory . '/' . $filename . '.' . $extension;
    }

    private function makeRelativePath(string $fullPath): string
    {
        $storagePath = storage_path('app');
        if (str_starts_with($fullPath, $storagePath)) {
            return ltrim(str_replace($storagePath, '', $fullPath), '/\\');
        }
        return $fullPath;
    }

    private function estimateRecordCount($query): int
    {
        try {
            return $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function determineChunkSize(int $estimatedRecords): int
    {
        if ($this->customChunkSize !== null) {
            return $this->customChunkSize;
        }

        if ($estimatedRecords > 100000000) {
            return $this->chunkSizes['xlarge'];
        }

        if ($estimatedRecords > 10000000) {
            return $this->chunkSizes['large'];
        }

        return $this->chunkSizes['default'];
    }

    private function increaseMemoryLimit(): void
    {
        $memoryLimit = config('turbo-export.memory_limit', '512M');
        
        if (function_exists('ini_set')) {
            ini_set('memory_limit', $memoryLimit);
        }
    }

    private function updateProgress(int $progress, int $total, string $status, ?string $filePath, ?string $error = null): void
    {
        $key = 'export:progress:' . $this->exportId;
        $ttl = (int) config('turbo-export.retention_hours', 24) * 3600;
        
        $data = [
            'progress' => $progress,
            'total' => $total,
            'status' => $status,
            'file_path' => $filePath,
            'error' => $error,
            'filters' => $this->filters,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($key, json_encode($data), $ttl);
    }

    public function tags(): array
    {
        return [
            'report-export',
            'report-export:' . $this->exportId,
            'report-export:type:' . $this->type->value,
            'report-export:format:' . $this->format->value,
            'user:' . $this->userId,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Report export job failed permanently", [
            'export_id' => $this->exportId,
            'type' => $this->type->value,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->updateProgress(0, 0, 'failed', null, $exception->getMessage());
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }
}
