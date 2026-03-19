<?php

declare(strict_types=1);

namespace TurboStreamExport\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use TurboStreamExport\Contracts\ExportDriverInterface;

class ExportService
{
    private const CHUNK_SIZE = 1000;
    private const PROGRESS_KEY_PREFIX = 'export:progress:';

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

    public function processExport(
        string $exportId,
        Builder $query,
        array $columns,
        string $filename,
        string $format = 'csv'
    ): string {
        $driver = $this->getDriver($format);
        $totalRecords = $query->count();
        $exportedRecords = 0;
        $filePath = $this->getFilePath($filename, $driver->getFileExtension());

        $this->updateProgress($exportId, 0, $totalRecords, 'processing');

        $handle = fopen(storage_path('app/' . $filePath), 'w');
        
        $driver->writeHeader($columns, $handle);

        $query->chunk(self::CHUNK_SIZE, function ($records) use ($driver, $columns, $handle, &$exportedRecords, $exportId, $totalRecords) {
            $driver->writeBatch($records, $columns, $handle);

            $exportedRecords += $records->count();
            $progress = (int) (($exportedRecords / $totalRecords) * 100);
            $this->updateProgress($exportId, $progress, $totalRecords, 'processing');
        });

        $driver->finalize($handle, storage_path('app/' . $filePath));
        $this->updateProgress($exportId, 100, $totalRecords, 'completed', $filePath);

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
            ];
        }

        return json_decode($data, true);
    }

    private function updateProgress(
        string $exportId,
        int $progress,
        int $total,
        string $status,
        ?string $filePath = null
    ): void {
        $key = self::PROGRESS_KEY_PREFIX . $exportId;
        $data = [
            'progress' => $progress,
            'total' => $total,
            'status' => $status,
            'file_path' => $filePath,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($key, json_encode($data), 3600);
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
