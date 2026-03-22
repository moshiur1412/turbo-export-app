<?php

namespace App\Jobs;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Services\ReportFormatter;
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
        $reportName = $this->type->label();
        $formattedFilters = $this->getFormattedFiltersForExport();
        
        if (method_exists($driver, 'setReportInfo')) {
            $driver->setReportInfo($reportName, $formattedFilters);
        }
        
        if (method_exists($driver, 'setNumericColumns')) {
            $driver->setNumericColumns($columnKeys);
        }

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
        $reportName = $this->type->label();
        $formattedFilters = $this->getFormattedFiltersForExport();
        
        if (method_exists($driver, 'setReportInfo')) {
            $driver->setReportInfo($reportName, $formattedFilters);
        }
        
        if (method_exists($driver, 'setNumericColumns')) {
            $driver->setNumericColumns($columnKeys);
        }

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

    private function getFormattedFiltersForExport(): array
    {
        $filters = [];
        
        if (isset($this->filters['department_ids']) && !empty($this->filters['department_ids'])) {
            try {
                $departments = \App\Models\Department::whereIn('id', $this->filters['department_ids'])->pluck('name')->toArray();
                $filters['department'] = implode(', ', $departments);
            } catch (\Exception $e) {
                $filters['department'] = implode(', ', $this->filters['department_ids']);
            }
        }
        
        if (isset($this->filters['designation_ids']) && !empty($this->filters['designation_ids'])) {
            try {
                $designations = \App\Models\Designation::whereIn('id', $this->filters['designation_ids'])->pluck('name')->toArray();
                $filters['designation'] = implode(', ', $designations);
            } catch (\Exception $e) {
                $filters['designation'] = implode(', ', $this->filters['designation_ids']);
            }
        }
        
        if (isset($this->filters['user_ids']) && !empty($this->filters['user_ids'])) {
            try {
                $users = \App\Models\User::whereIn('id', $this->filters['user_ids'])->pluck('name')->toArray();
                $filters['employee'] = implode(', ', $users);
            } catch (\Exception $e) {
                $filters['employee'] = implode(', ', $this->filters['user_ids']);
            }
        }
        
        if (isset($this->filters['location_ids']) && !empty($this->filters['location_ids'])) {
            $locationMap = [
                'head_office' => 'Head Office',
                'branch_1' => 'Branch 1',
                'branch_2' => 'Branch 2',
                'remote' => 'Remote',
            ];
            $locations = array_map(function($id) use ($locationMap) {
                return $locationMap[$id] ?? $id;
            }, $this->filters['location_ids']);
            $filters['location'] = implode(', ', $locations);
        }
        
        if (isset($this->filters['employment_status']) && !empty($this->filters['employment_status'])) {
            $statusMap = [
                'active' => 'Active',
                'probation' => 'Probation',
                'contract' => 'Contract',
                'part_time' => 'Part Time',
                'intern' => 'Intern',
                'resigned' => 'Resigned',
                'terminated' => 'Terminated',
            ];
            $statuses = array_map(function($s) use ($statusMap) {
                return $statusMap[$s] ?? ucfirst($s);
            }, $this->filters['employment_status']);
            $filters['employment_status'] = implode(', ', $statuses);
        }
        
        if (isset($this->filters['gender']) && !empty($this->filters['gender'])) {
            $filters['gender'] = implode(', ', array_map('ucfirst', $this->filters['gender']));
        }
        
        if (isset($this->filters['year'])) {
            $filters['year'] = $this->filters['year'];
        }
        
        if (isset($this->filters['salary_min'])) {
            $filters['salary_min'] = ReportFormatter::bangladeshNumber($this->filters['salary_min']);
        }
        
        if (isset($this->filters['salary_max'])) {
            $filters['salary_max'] = ReportFormatter::bangladeshNumber($this->filters['salary_max']);
        }
        
        if (isset($this->filters['include_inactive'])) {
            $filters['include_inactive'] = $this->filters['include_inactive'] ? 'Yes' : 'No';
        }
        
        if (isset($this->filters['start_date'])) {
            $filters['start_date'] = $this->filters['start_date'];
        }
        
        if (isset($this->filters['end_date'])) {
            $filters['end_date'] = $this->filters['end_date'];
        }
        
        if (isset($this->filters['date'])) {
            $filters['date'] = $this->filters['date'];
        }
        
        return $filters;
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
