<?php

declare(strict_types=1);

namespace TurboStreamExport\Jobs;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use TurboStreamExport\Services\ExportService;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 3600;
    public int $maxExceptions = 3;

    private array $chunkSizes = [
        'default' => 5000,
        'large' => 10000,
        'xlarge' => 20000,
    ];

    public function __construct(
        public readonly string $exportId,
        public readonly string $modelClass,
        public readonly array $columns,
        public readonly array $filters,
        public readonly string $filename,
        public readonly string $format = 'csv',
        public readonly int $userId,
        public readonly ?int $customChunkSize = null,
        public readonly bool $highPriority = false,
        public readonly ?string $queryBuilder = null,
    ) {
        $this->onQueue($this->highPriority ? 'exports-high' : 'exports');
        
        if (app()->bound('log')) {
            Log::info("ProcessExportJob created", [
                'export_id' => $this->exportId,
                'format' => $this->format,
                'user_id' => $this->userId,
                'columns_count' => count($this->columns),
                'filters_count' => count($this->filters),
            ]);
        }
    }

    public function handle(ExportService $exportService): void
    {
        $startTime = microtime(true);
        
        try {
            $query = $this->buildQuery();

            $estimatedRecords = $this->estimateRecordCount($query);
            $chunkSize = $this->determineChunkSize($estimatedRecords);

            if (app()->bound('log')) {
                Log::info("Starting export processing", [
                    'export_id' => $this->exportId,
                    'estimated_records' => $estimatedRecords,
                    'chunk_size' => $chunkSize,
                    'format' => $this->format,
                ]);
            }

            $this->increaseMemoryLimit();

            $reportType = $this->filters['_report_type'] ?? null;
            $columnDefinitions = $this->getColumnDefinitions($reportType);

            // Use processExportWithClosures to handle closures in column definitions
            $exportService->processExportWithClosures(
                $this->exportId,
                $query,
                $columnDefinitions,
                $this->filename,
                $this->format,
                $this->filters,
                $chunkSize
            );

            $duration = round(microtime(true) - $startTime, 2);

            if (app()->bound('log')) {
                Log::info("Export completed successfully", [
                    'export_id' => $this->exportId,
                    'duration_seconds' => $duration,
                    'format' => $this->format,
                ]);
            }

        } catch (\Throwable $e) {
            if (app()->bound('log')) {
                Log::error("Export failed", [
                    'export_id' => $this->exportId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            
            throw $e;
        }
    }

    private function buildQuery(): Builder
    {
        if ($this->queryBuilder && class_exists($this->queryBuilder)) {
            $filters = is_array($this->filters) ? $this->filters : [];
            $reportType = $filters['_report_type'] ?? null;
            
            if ($reportType && class_exists('App\\Enums\\ReportType')) {
                $type = \App\Enums\ReportType::tryFrom($reportType);
                if ($type) {
                    $builder = new $this->queryBuilder($type, $filters);
                    return $builder->buildQuery();
                }
            }
            
            if ($this->queryBuilder === 'App\\Services\\ReportQueryBuilder') {
                $builder = new $this->queryBuilder(
                    \App\Enums\ReportType::SALARY_MASTER,
                    $filters
                );
                return $builder->buildQuery();
            }
            
            $builder = new $this->queryBuilder();
            
            if ($builder instanceof Closure) {
                return ($builder)();
            }
            
            if (method_exists($builder, 'buildQuery')) {
                return $builder->buildQuery($filters);
            }
            
            if (method_exists($builder, 'getQuery')) {
                return $builder->getQuery($filters);
            }
        }

        $query = $this->modelClass::query();

        if (!empty($this->filters)) {
            $this->applyFilters($query, $this->filters);
        }

        return $query;
    }

    private function applyFilters($query, array $filters): void
    {
        foreach ($filters as $filter) {
            if (is_array($filter) && count($filter) >= 2) {
                $field = $filter[0];
                $operator = $filter[1];
                $value = $filter[2] ?? null;

                if (count($filter) === 2) {
                    $query->where($field, $operator);
                } else {
                    $query->where($field, $operator, $value);
                }
            }
        }
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

        if ($this->format === 'pdf' && $estimatedRecords > 300000) {
            throw new \RuntimeException(
                "PDF export is not supported for more than 300,000 records. " .
                "Current record count: {$estimatedRecords}. Please use CSV or XLSX format for large exports."
            );
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
        $format = $this->format;
        
        $memoryLimit = match ($format) {
            'xlsx', 'pdf', 'docx' => '4G',
            default => '1G',
        };
        
        if (function_exists('ini_set')) {
            ini_set('memory_limit', $memoryLimit);
        }
    }

    public function tags(): array
    {
        return [
            'export',
            'export:' . $this->exportId,
            'export:format:' . $this->format,
            'user:' . $this->userId,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        if (app()->bound('log')) {
            Log::error("Export job failed permanently", [
                'export_id' => $this->exportId,
                'error' => $exception->getMessage(),
                'attempts' => $this->attempts(),
            ]);
        }

        $exportService = app(ExportService::class);
        
        $key = 'export:progress:' . $this->exportId;
        \Illuminate\Support\Facades\Cache::put($key, json_encode([
            'progress' => 0,
            'total' => 0,
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'filters' => $this->filters,
            'filter_summary' => $this->buildFilterSummary(),
            'updated_at' => now()->toIso8601String(),
        ]), 86400);
    }

    private function getColumnDefinitions(?string $reportType): array
    {
        if (!$reportType || !class_exists('App\\Enums\\ReportType')) {
            return array_combine($this->columns, $this->columns);
        }

        $type = \App\Enums\ReportType::tryFrom($reportType);
        if (!$type) {
            return array_combine($this->columns, $this->columns);
        }

        $builder = new \App\Services\ReportQueryBuilder($type, $this->filters);
        return $builder->getColumns();
    }

    private function buildFilterSummary(): string
    {
        if (empty($this->filters)) {
            return '';
        }

        $parts = [];
        foreach ($this->filters as $filter) {
            if (is_array($filter) && count($filter) >= 2) {
                $field = $filter[0] ?? 'unknown';
                $operator = $filter[1] ?? '=';
                $value = $filter[2] ?? '';
                $parts[] = str_replace([' ', '.'], '_', "{$field}_{$operator}_{$value}");
            }
        }

        return implode('_', $parts);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }
}
