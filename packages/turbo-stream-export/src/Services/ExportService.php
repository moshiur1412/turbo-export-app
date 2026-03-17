<?php

declare(strict_types=1);

namespace TurboStreamExport\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Redis;

class ExportService
{
    private const CHUNK_SIZE = 1000;
    private const PROGRESS_KEY_PREFIX = 'export:progress:';

    public function __construct(
        private readonly string $disk = 'local'
    ) {}

    public function processExport(
        string $exportId,
        Builder $query,
        array $columns,
        string $filename,
        string $format = 'csv'
    ): string {
        $totalRecords = $query->count();
        $exportedRecords = 0;
        $filePath = $this->getFilePath($filename, $format);

        $this->updateProgress($exportId, 0, $totalRecords, 'processing');

        $handle = fopen(storage_path('app/' . $filePath), 'w');
        
        if ($format === 'csv') {
            fputcsv($handle, $columns);
        }

        $query->chunk(self::CHUNK_SIZE, function ($records) use ($handle, $columns, &$exportedRecords, $exportId, $totalRecords, $format) {
            $data = $records->map(function ($record) use ($columns) {
                return array_map(fn($col) => data_get($record, $col), $columns);
            })->toArray();

            if ($format === 'csv') {
                foreach ($data as $row) {
                    fputcsv($handle, $row);
                }
            }

            $exportedRecords += count($records);
            $progress = (int) (($exportedRecords / $totalRecords) * 100);
            $this->updateProgress($exportId, $progress, $totalRecords, 'processing');
        });

        fclose($handle);
        $this->updateProgress($exportId, 100, $totalRecords, 'completed', $filePath);

        return $filePath;
    }

    public function getProgress(string $exportId): array
    {
        $key = self::PROGRESS_KEY_PREFIX . $exportId;
        $data = Redis::get($key);

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

        Redis::setex($key, 3600, json_encode($data));
    }

    private function getFilePath(string $filename, string $format): string
    {
        $directory = 'exports';
        
        if (!file_exists(storage_path('app/' . $directory))) {
            mkdir(storage_path('app/' . $directory), 0755, true);
        }

        return $directory . '/' . $filename . '.' . $format;
    }
}
