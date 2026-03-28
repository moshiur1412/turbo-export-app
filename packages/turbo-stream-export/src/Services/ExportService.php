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

    public function processExportWithClosures(
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
        
        if ($format === 'pdf' && $totalRecords > 300000) {
            throw new \RuntimeException(
                "PDF export is not supported for more than 300,000 records. " .
                "Current record count: {$totalRecords}. Please use CSV or XLSX format for large exports."
            );
        }
        
        $effectiveChunkSize = $this->determineChunkSize($totalRecords, $chunkSize);
        
        $finalFilename = $this->buildFilenameWithFilters($filename, $filters, $format);
        $filePath = $this->getFilePath($finalFilename, $driver->getFileExtension());

        $this->updateProgress($exportId, 0, $totalRecords, 'processing', null, $filters);

        $driver->setReportInfo($filename, $filters);

        if ($format === 'csv' || $format === 'sql') {
            $filePath = $this->processStreamingExportWithClosures(
                $exportId,
                $query,
                $columns,
                $filePath,
                $driver,
                $totalRecords,
                $effectiveChunkSize
            );
        } else {
            $filePath = $this->processMemoryExportWithClosures(
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

        $driver->setReportInfo($filename, $filters);

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

        $format = $driver->getFormat();
        $progressInterval = in_array($format, ['xlsx', 'docx']) ? 1 : 5;
        
        $exportedRecords = 0;
        $lastLoggedProgress = 0;
        $batchRecords = [];

        foreach ($query->cursor() as $record) {
            $batchRecords[] = $record;
            $exportedRecords++;

            if (count($batchRecords) >= $chunkSize) {
                $driver->writeBatch(collect($batchRecords), $columns, $handle);
                $batchRecords = [];
                gc_collect_cycles();
            }

            $progress = $totalRecords > 0 ? (int)(($exportedRecords / $totalRecords) * 100) : 100;
            $shouldLog = ($progress - $lastLoggedProgress >= $progressInterval) || $exportedRecords === $totalRecords;

            if ($shouldLog) {
                $this->updateProgress($exportId, $progress, $totalRecords, 'processing');
                $lastLoggedProgress = $progress;
                
                if (config('turbo-export.enable_progress_logging', true)) {
                    \Illuminate\Support\Facades\Log::info("Export [{$exportId}] progress: {$progress}% ({$exportedRecords}/{$totalRecords})");
                }
            }
        }

        if (!empty($batchRecords)) {
            $driver->writeBatch(collect($batchRecords), $columns, $handle);
        }

        fclose($handle);
        $driver->finalize(storage_path('app/' . $filePath), null);

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
        $driver->writeHeader($columns, null);

        $format = $driver->getFormat();
        $progressInterval = in_array($format, ['xlsx', 'docx']) ? 1 : 5;
        
        $exportedRecords = 0;
        $lastLoggedProgress = 0;
        $batchRecords = [];

        foreach ($query->cursor() as $record) {
            $batchRecords[] = $record;
            $exportedRecords++;

            if (count($batchRecords) >= $chunkSize) {
                $driver->writeBatch(collect($batchRecords), $columns, null);
                $batchRecords = [];
                gc_collect_cycles();
            }

            $progress = $totalRecords > 0 ? (int)(($exportedRecords / $totalRecords) * 100) : 100;
            $shouldLog = ($progress - $lastLoggedProgress >= $progressInterval) || $exportedRecords === $totalRecords;
            
            if ($shouldLog) {
                $this->updateProgress($exportId, $progress, $totalRecords, 'processing');
                $lastLoggedProgress = $progress;
                
                if (config('turbo-export.enable_progress_logging', true)) {
                    \Illuminate\Support\Facades\Log::info("Export [{$exportId}] progress: {$progress}% ({$exportedRecords}/{$totalRecords})");
                }
            }
        }

        if (!empty($batchRecords)) {
            $driver->writeBatch(collect($batchRecords), $columns, null);
        }

        $driver->finalize(storage_path('app/' . $filePath), null);
        
        return $filePath;
    }

    private function processStreamingExportWithClosures(
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
        
        $columnKeys = array_keys($columns);
        $driver->writeHeader($columnKeys, $handle);

        $format = $driver->getFormat();
        $progressInterval = in_array($format, ['xlsx', 'docx']) ? 1 : 5;
        
        $exportedRecords = 0;
        $lastLoggedProgress = 0;
        $batchRecords = [];

        foreach ($query->cursor() as $record) {
            $row = [];
            foreach ($columns as $key => $column) {
                if ($column instanceof \Closure) {
                    $row[$key] = $column($record);
                } else {
                    $row[$key] = data_get($record, $column, $column);
                }
            }
            $batchRecords[] = (object) $row;
            $exportedRecords++;

            if (count($batchRecords) >= $chunkSize) {
                $driver->writeBatch(collect($batchRecords), $columnKeys, $handle);
                $batchRecords = [];
                gc_collect_cycles();
            }

            $progress = $totalRecords > 0 ? (int)(($exportedRecords / $totalRecords) * 100) : 100;
            $shouldLog = ($progress - $lastLoggedProgress >= $progressInterval) || $exportedRecords === $totalRecords;

            if ($shouldLog) {
                $this->updateProgress($exportId, $progress, $totalRecords, 'processing');
                $lastLoggedProgress = $progress;
                
                if (config('turbo-export.enable_progress_logging', true)) {
                    \Illuminate\Support\Facades\Log::info("Export [{$exportId}] progress: {$progress}% ({$exportedRecords}/{$totalRecords})");
                }
            }
        }

        if (!empty($batchRecords)) {
            $driver->writeBatch(collect($batchRecords), $columnKeys, $handle);
        }

        fclose($handle);
        $driver->finalize(storage_path('app/' . $filePath), null);

        return $filePath;
    }

    private function processMemoryExportWithClosures(
        string $exportId,
        Builder $query,
        array $columns,
        string $filePath,
        ExportDriverInterface $driver,
        int $totalRecords,
        int $chunkSize
    ): string {
        $columnKeys = array_keys($columns);
        $driver->writeHeader($columnKeys, null);
        
        $format = $driver->getFormat();
        $progressInterval = in_array($format, ['xlsx', 'docx']) ? 1 : 5;
        
        if ($format === 'pdf') {
            $recordCount = 0;
            $lastLoggedProgress = 0;
            
            foreach ($query->cursor() as $record) {
                $row = [];
                foreach ($columns as $key => $column) {
                    if ($column instanceof \Closure) {
                        $row[$key] = $column($record);
                    } else {
                        $row[$key] = data_get($record, $column, $column);
                    }
                }
                
                $driver->writeRow(array_values($row), null);
                $recordCount++;
                
                $progress = $totalRecords > 0 ? (int)(($recordCount / $totalRecords) * 100) : 100;
                $shouldLog = ($progress - $lastLoggedProgress >= $progressInterval) || $recordCount === $totalRecords;
                
                if ($shouldLog) {
                    $this->updateProgress($exportId, $progress, $totalRecords, 'processing');
                    $lastLoggedProgress = $progress;
                    
                    if (config('turbo-export.enable_progress_logging', true)) {
                        \Illuminate\Support\Facades\Log::info("Export [{$exportId}] progress: {$progress}% ({$recordCount}/{$totalRecords})");
                    }
                    
                    gc_collect_cycles();
                }
            }
        } else {
            $exportedRecords = 0;
            $lastLoggedProgress = 0;
            $batchRecords = [];

            foreach ($query->cursor() as $record) {
                $row = [];
                foreach ($columns as $key => $column) {
                    if ($column instanceof \Closure) {
                        $row[$key] = $column($record);
                    } else {
                        $row[$key] = data_get($record, $column, $column);
                    }
                }
                $batchRecords[] = (object) $row;
                $exportedRecords++;

                if (count($batchRecords) >= $chunkSize) {
                    $driver->writeBatch(collect($batchRecords), $columnKeys, null);
                    $batchRecords = [];
                    gc_collect_cycles();
                }

                $progress = $totalRecords > 0 ? (int)(($exportedRecords / $totalRecords) * 100) : 100;
                $shouldLog = ($progress - $lastLoggedProgress >= $progressInterval) || $exportedRecords === $totalRecords;
                
                if ($shouldLog) {
                    $this->updateProgress($exportId, $progress, $totalRecords, 'processing');
                    $lastLoggedProgress = $progress;
                    
                    if (config('turbo-export.enable_progress_logging', true)) {
                        \Illuminate\Support\Facades\Log::info("Export [{$exportId}] progress: {$progress}% ({$exportedRecords}/{$totalRecords})");
                    }
                }
            }

            if (!empty($batchRecords)) {
                $driver->writeBatch(collect($batchRecords), $columnKeys, null);
            }
        }

        $driver->finalize(storage_path('app/' . $filePath), null);
        
        return $filePath;
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
