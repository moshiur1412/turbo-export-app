<?php

declare(strict_types=1);

namespace TurboStreamExport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use TurboStreamExport\Services\ExportService;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $exportId,
        public readonly string $modelClass,
        public readonly array $columns,
        public readonly array $filters,
        public readonly string $filename,
        public readonly string $format = 'csv',
        public readonly int $userId
    ) {
        $this->onQueue('exports');
    }

    public function handle(ExportService $exportService): void
    {
        $query = $this->modelClass::query();

        if (!empty($this->filters)) {
            $query->where($this->filters);
        }

        $exportService->processExport(
            $this->exportId,
            $query,
            $this->columns,
            $this->filename,
            $this->format
        );
    }

    public function tags(): array
    {
        return [
            'export',
            'export:' . $this->exportId,
            'user:' . $this->userId,
        ];
    }
}
