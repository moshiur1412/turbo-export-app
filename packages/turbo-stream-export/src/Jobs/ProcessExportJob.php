<?php

declare(strict_types=1);

namespace TurboStreamExport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Builder;
use TurboStreamExport\Services\ExportService;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $exportId,
        public readonly Builder $query,
        public readonly array $columns,
        public readonly string $filename,
        public readonly string $format = 'csv',
        public readonly int $userId
    ) {
        $this->onQueue('exports');
    }

    public function handle(ExportService $exportService): void
    {
        $exportService->processExport(
            $this->exportId,
            $this->query,
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
