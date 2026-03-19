<?php

declare(strict_types=1);

namespace TurboStreamExport\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use TurboStreamExport\Contracts\ExportDriverInterface;

class ExportService
{
    private const DEFAULT_CHUNK_SIZE = 5000;
    private const LARGE_DATA_CHUNK_SIZE = 10000;
    private const PROGRESS_KEY_PREFIX = 'export:progress:';
    private const LARGE_DATA_THRESHOLD = 1000000;

    private array $drivers = [];

    public function __construct(
        private readonly string $disk = 'local',
        ?iterable $drivers = null
    ) {
        if ($drivers) {
            foreach ($drivers as $driver) {
                $this->registerDriver($driver);
            }
        }
    }

    public function registerDriver(ExportDriverInterface $driver): void
    {
        $this->drivers[$driver->getFormat()] = $driver;
    }

    public function getDriver(string $format): ExportDriverInterface
    {
        if (!isset($this->drivers[$format])) {
            throw new \InvalidArgumentException("Driver for format [{$format}] not registered.");
        }

        return $this->drivers[$format];
    }

    public function hasDriver(string $format): bool
    {
        return isset($this->drivers[$format]);
    }

    public function getAvailableFormats(): array
    {
        return array_keys($this->drivers);
    }

    public function processExport(
        string $exportId,
        Builder $query,
        array $columns,
        string $filename,
        string $format = 'csv',
        array $filters = [],
        ?int $chunkSize = null
    ): string {
        $driver = $this->getDriver($format);
        $totalRecords = $query->count();
        
        $effectiveChunkSize = $this->determineChunkSize($totalRecords, $chunkSize);
        
        $finalFilename = $this->buildFilenameWithFilters($filename, $filters, $format);
        $filePath = $this->getFilePath($finalFilename, $driver->getFileExtension());

        $this->updateProgress($exportId, 0, $totalRecords, 'processing', null, $filters);

        if ($format === 'csv' || $format === 'sql') {
            $filePath = $this->processStreamingExport(
                $exportId,
                $query,
                $columns,
                $filePath,
                $driver,
                $totalRecords,
                $effectiveChunkSize
            );
        } else {
            $filePath = $this->processMemoryExport(
                $exportId,
                $query,
                $columns,
                $filePath,
                $driver,
                $totalRecords,
                $effectiveChunkSize
            );
        }

        $this->updateProgress($exportId, 100, $totalRecords, 'completed', $filePath, $filters);

        return $filePath;
    }

    private function determineChunkSize(int $totalRecords, ?int $requestedChunkSize): int
    {
        if ($requestedChunkSize !== null) {
            return $requestedChunkSize;
        }

        $configChunkSize = (int) config('turbo-export.chunk_size', self::DEFAULT_CHUNK_SIZE);
        $largeDataChunkSize = (int) config('turbo-export.large_data_chunk_size', self::LARGE_DATA_CHUNK_SIZE);

        if ($totalRecords > self::LARGE_DATA_THRESHOLD) {
            return $largeDataChunkSize;
        }

        return $configChunkSize;
    }

    private function processStreamingExport(
        string $exportId,
        Builder $query,
        array $columns,
        string $filePath,
        ExportDriverInterface $driver,
        int $totalRecords,
        int $chunkSize
    ): string {
        $directory = dirname(storage_path('app/' . $filePath));
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $handle = fopen(storage_path('app/' . $filePath), 'w');
        
        $driver->writeHeader($columns, $handle);

        $exportedRecords = 0;
        $lastLoggedProgress = 0;
        $logInterval = (int) config('turbo-export.log_progress_interval', 100000);

        $query->chunk($chunkSize, function ($records) use (
            $driver,
            $columns,
            $handle,
            &$exportedRecords,
            $exportId,
            $totalRecords,
            &$lastLoggedProgress,
            $logInterval
        ) {
            $driver->writeBatch($records, $columns, $handle);

            $exportedRecords += $records->count();
            $progress = (int) (($exportedRecords / $totalRecords) * 100);
            
            $shouldLog = ($progress - $lastLoggedProgress >= 5) || 
                         ($exportedRecords % $logInterval < $chunkSize) ||
                         $exportedRecords === $totalRecords;

            if ($shouldLog) {
                $this->updateProgress($exportId, $progress, $totalRecords, 'processing');
                $lastLoggedProgress = $progress;
                
                if (config('turbo-export.enable_progress_logging', true)) {
                    \Illuminate\Support\Facades\Log::info("Export [{$exportId}] progress: {$progress}% ({$exportedRecords}/{$totalRecords})");
                }
            }

            gc_collect_cycles();
        });

        fclose($handle);
        $driver->finalize(null, storage_path('app/' . $filePath));

        return $filePath;
    }

    private function processMemoryExport(
        string $exportId,
        Builder $query,
        array $columns,
        string $filePath,
        ExportDriverInterface $driver,
        int $totalRecords,
        int $chunkSize
    ): string {
        $driver->writeHeader($columns);

        $exportedRecords = 0;
        $lastLoggedProgress = 0;

        $query->chunk($chunkSize, function ($records) use (
            $driver,
            $columns,
            &$exportedRecords,
            $exportId,
            $totalRecords,
            &$lastLoggedProgress
        ) {
            $driver->writeBatch($records, $columns);

            $exportedRecords += $records->count();
            $progress = (int) (($exportedRecords / $totalRecords) * 100);

            if (($progress - $lastLoggedProgress >= 5) || $exportedRecords === $totalRecords) {
                $this->updateProgress($exportId, $progress, $totalRecords, 'processing');
                $lastLoggedProgress = $progress;
                
                gc_collect_cycles();
            }
        });

        return $driver->finalize(null, storage_path('app/' . $filePath));
    }

    public function getProgress(string $exportId): array
    {
        $key = self::PROGRESS_KEY_PREFIX . $exportId;
        $data = Cache::get($key);

        if (!$data) {
            return [
                'progress' => 0,
                'total' => 0,
                'status' => 'not_found',
                'filters' => [],
            ];
        }

        return json_decode($data, true);
    }

    private function updateProgress(
        string $exportId,
        int $progress,
        int $total,
        string $status,
        ?string $filePath = null,
        array $filters = []
    ): void {
        $key = self::PROGRESS_KEY_PREFIX . $exportId;
        $ttl = (int) config('turbo-export.retention_hours', 24) * 3600;
        
        $data = [
            'progress' => $progress,
            'total' => $total,
            'status' => $status,
            'file_path' => $filePath,
            'filters' => $filters,
            'filter_summary' => $this->buildFilterSummary($filters),
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($key, json_encode($data), $ttl);
    }

    private function buildFilterSummary(array $filters): string
    {
        if (empty($filters)) {
            return '';
        }

        $parts = [];
        foreach ($filters as $filter) {
            if (is_array($filter) && count($filter) >= 2) {
                $field = $filter[0] ?? 'unknown';
                $operator = $filter[1] ?? '=';
                $value = $filter[2] ?? '';
                $parts[] = str_replace([' ', '.'], '_', "{$field}_{$operator}_{$value}");
            }
        }

        return implode('_', $parts);
    }

    private function buildFilenameWithFilters(string $baseFilename, array $filters, string $format): string
    {
        if (empty($filters) || !config('turbo-export.include_filter_in_filename', true)) {
            return $baseFilename;
        }

        $filterSuffix = $this->buildFilterSummary($filters);
        
        if (empty($filterSuffix)) {
            return $baseFilename;
        }

        return $baseFilename . '_filtered_' . $filterSuffix;
    }

    private function getFilePath(string $filename, string $extension): string
    {
        $directory = 'exports';
        
        if (!file_exists(storage_path('app/' . $directory))) {
            mkdir(storage_path('app/' . $directory), 0755, true);
        }

        return $directory . '/' . $filename . '.' . $extension;
    }
}
