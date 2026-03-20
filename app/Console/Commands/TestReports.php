<?php

namespace App\Console\Commands;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Console\Command;

class TestReports extends Command
{
    protected $signature = 'reports:test 
                            {type? : Specific report type to test}
                            {--format=csv : Export format (csv, xlsx, pdf, docx, sql)}
                            {--user=1 : User ID for testing}
                            {--departments= : Comma-separated department IDs}
                            {--start= : Start date (Y-m-d)}
                            {--end= : End date (Y-m-d)}';

    protected $description = 'Test all or specific report types';

    public function handle(ReportService $reportService): int
    {
        $typeInput = $this->argument('type');
        $format = ReportFormat::from($this->option('format'));
        $userId = (int) $this->option('user');
        
        $filters = [];
        if ($depts = $this->option('departments')) {
            $filters['department_ids'] = array_map('intval', explode(',', $depts));
        }
        if ($start = $this->option('start')) {
            $filters['start_date'] = $start;
        }
        if ($end = $this->option('end')) {
            $filters['end_date'] = $end;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("User #{$userId} not found.");
            return Command::FAILURE;
        }

        $this->info("Testing reports as user: {$user->name} ({$user->email})");
        $this->info("Format: {$format->value}");
        if ($filters) {
            $this->info("Filters: " . json_encode($filters));
        }
        $this->newLine();

        if ($typeInput) {
            $type = ReportType::tryFrom($typeInput);
            if (!$type) {
                $this->error("Invalid report type: {$typeInput}");
                $this->info("Valid types: " . implode(', ', array_column(ReportType::cases(), 'value')));
                return Command::FAILURE;
            }
            return $this->testReport($reportService, $userId, $type, $format, $filters);
        }

        $this->info("Testing ALL report types...");
        $this->newLine();

        $results = [];
        $types = ReportType::cases();

        foreach ($types as $type) {
            $this->info("Testing: {$type->label()} ({$type->value})");
            
            $typeFilters = $filters;
            if ($type->requiresDateRange()) {
                $typeFilters['start_date'] = $typeFilters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
                $typeFilters['end_date'] = $typeFilters['end_date'] ?? now()->endOfMonth()->format('Y-m-d');
            }

            try {
                $report = $reportService->createReport($userId, $type, $typeFilters, $format);
                $this->info("  [✓] Report created: {$report->id}");
                $results[$type->value] = ['status' => 'created', 'id' => $report->id];
            } catch (\Exception $e) {
                $this->error("  [✗] Failed: {$e->getMessage()}");
                $results[$type->value] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->table(
            ['Type', 'Status', 'Details'],
            collect($results)->map(fn($r, $t) => [$t, $r['status'], $r['message'] ?? $r['id'] ?? '-'])->toArray()
        );

        return Command::SUCCESS;
    }

    private function testReport(ReportService $reportService, int $userId, ReportType $type, ReportFormat $format, array $filters): int
    {
        $this->info("Testing: {$type->label()}");
        $this->info("  Category: {$type->category()}");
        $this->info("  Requires date range: " . ($type->requiresDateRange() ? 'Yes' : 'No'));
        $this->info("  Default format: {$type->defaultFormat()}");
        $this->newLine();

        if ($type->requiresDateRange()) {
            $filters['start_date'] = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
            $filters['end_date'] = $filters['end_date'] ?? now()->endOfMonth()->format('Y-m-d');
            $this->info("  Date range: {$filters['start_date']} to {$filters['end_date']}");
        }

        try {
            $report = $reportService->createReport($userId, $type, $filters, $format);
            $this->info("✓ Report created successfully!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $report->id],
                    ['Name', $report->name],
                    ['Type', $report->type->value],
                    ['Format', $report->format->value],
                    ['Status', $report->status->value],
                ]
            );

            $this->newLine();
            $this->warn("Note: Run `php artisan queue:work redis --queue=exports` to process the report.");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Failed to create report: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
