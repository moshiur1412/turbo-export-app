<?php

namespace App\Services;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Jobs\ProcessReportExportJob;
use App\Models\Report;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportService
{
    public function createReport(
        int $userId,
        ReportType $type,
        array $filters = [],
        ?ReportFormat $format = null,
        ?string $name = null
    ): Report {
        try {
            $format = $format ?? ReportFormat::from($type->defaultFormat());
            $exportId = Str::uuid()->toString();

            $report = Report::create([
                'user_id' => $userId,
                'type' => $type,
                'format' => $format,
                'status' => \App\Enums\ReportStatus::PENDING,
                'name' => $name ?? $type->label(),
                'filters' => array_merge($filters, ['_report_type' => $type->value]),
                'parameters' => [
                    'export_id' => $exportId,
                    'requested_at' => now()->toIso8601String(),
                ],
            ]);

            $this->dispatchExportJob($report, $filters, $format);

            return $report;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to create report '{$type->label()}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function dispatchExportJob(
        Report $report,
        array $filters,
        ReportFormat $format
    ): void {
        $exportId = $report->parameters['export_id'];
        $filename = $this->generateFilename($report);

        $this->initializeProgress($exportId, $filters);

        dispatch(new ProcessReportExportJob(
            $exportId,
            $report->type,
            $format,
            $filters,
            $filename,
            $report->user_id
        ));
    }

    private function initializeProgress(string $exportId, array $filters): void
    {
        $key = 'export:progress:' . $exportId;
        Cache::put($key, json_encode([
            'progress' => 0,
            'total' => 0,
            'status' => 'pending',
            'filters' => $filters,
            'updated_at' => now()->toIso8601String(),
        ]), 86400);
    }

    private function generateFilename(Report $report): string
    {
        $typeSlug = str_replace('_', '-', $report->type->value);
        $date = now()->format('Ymd');
        return "report-{$typeSlug}-{$date}";
    }

    public function getUserReports(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        $reports = Report::forUser($userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        foreach ($reports as $report) {
            $this->syncReportFromCache($report);
        }

        return $reports;
    }

    public function getReportById(string $id): ?Report
    {
        $report = Report::find($id);

        if ($report) {
            $this->syncReportFromCache($report);
        }

        return $report;
    }

    private function syncReportFromCache(Report $report): void
    {
        if ($report->status->isTerminal()) {
            return;
        }

        $exportId = $report->parameters['export_id'] ?? $report->id;
        $cacheKey = 'export:progress:' . $exportId;
        $cacheData = Cache::get($cacheKey);

        if (!$cacheData) {
            return;
        }

        $data = json_decode($cacheData, true);
        $newStatus = $data['status'] ?? 'pending';

        if ($newStatus === $report->status->value) {
            return;
        }

        $updateData = [];

        if (in_array($newStatus, ['processing', 'completed', 'failed'])) {
            $updateData['progress'] = $data['progress'] ?? 0;
            $updateData['total_records'] = $data['total'] ?? 0;
        }

        if ($newStatus === 'processing') {
            $updateData['status'] = \App\Enums\ReportStatus::PROCESSING;
            $updateData['started_at'] = $report->started_at ?? now();
        } elseif ($newStatus === 'completed') {
            $updateData['status'] = \App\Enums\ReportStatus::COMPLETED;
            $updateData['progress'] = 100;
            $updateData['completed_at'] = now();
            
            if (!empty($data['file_path'])) {
                $relativePath = $this->makeRelativePath($data['file_path']);
                $updateData['file_path'] = $relativePath;
                $updateData['file_name'] = basename($data['file_path']);
                
                if (Storage::disk('local')->exists($relativePath)) {
                    $updateData['file_size'] = Storage::disk('local')->size($relativePath);
                }
            }
        } elseif ($newStatus === 'failed') {
            $updateData['status'] = \App\Enums\ReportStatus::FAILED;
            $updateData['error_message'] = $data['error'] ?? 'Unknown error';
            $updateData['completed_at'] = now();
        }

        if (!empty($updateData)) {
            $report->update($updateData);
        }
    }

    private function makeRelativePath(string $fullPath): string
    {
        $storagePath = storage_path('app');
        if (str_starts_with($fullPath, $storagePath)) {
            return ltrim(str_replace($storagePath, '', $fullPath), '/\\');
        }
        return $fullPath;
    }

    public function getReportProgress(string $id): ?array
    {
        $report = Report::find($id);

        if (!$report) {
            return null;
        }

        $exportId = $report->parameters['export_id'] ?? $report->id;
        $cacheKey = 'export:progress:' . $exportId;
        $cacheData = Cache::get($cacheKey);

        if (!$cacheData) {
            return [
                'id' => $report->id,
                'status' => $report->status->value,
                'progress' => $report->progress,
                'total' => $report->total_records,
                'file_path' => $report->file_path,
                'file_name' => $report->file_name,
                'file_size_formatted' => $report->file_size_formatted,
                'download_url' => $report->download_url,
                'error' => $report->error_message,
                'updated_at' => $report->completed_at?->toIso8601String(),
            ];
        }

        $data = json_decode($cacheData, true);

        return [
            'id' => $report->id,
            'status' => $data['status'] ?? $report->status->value,
            'progress' => $data['progress'] ?? $report->progress,
            'total' => $data['total'] ?? $report->total_records,
            'file_path' => $data['file_path'] ?? null,
            'file_name' => isset($data['file_path']) ? basename($data['file_path']) : null,
            'error' => $data['error'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }

    public function cancelReport(string $id): bool
    {
        $report = Report::find($id);

        if (!$report || !$report->status->canCancel()) {
            return false;
        }

        $exportId = $report->parameters['export_id'] ?? $id;
        Cache::forget('export:progress:' . $exportId);

        $report->update([
            'status' => \App\Enums\ReportStatus::CANCELLED,
            'completed_at' => now(),
        ]);

        return true;
    }

    public function retryReport(string $id): ?Report
    {
        $report = Report::find($id);

        if (!$report || !$report->status->canRetry()) {
            return null;
        }

        $report->update([
            'status' => \App\Enums\ReportStatus::PENDING,
            'progress' => 0,
            'processed_records' => 0,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
        ]);

        $this->dispatchExportJob($report, $report->filters ?? [], $report->format);

        return $report;
    }

    public function deleteReport(string $id): bool
    {
        $report = Report::find($id);

        if (!$report) {
            return false;
        }

        $exportId = $report->parameters['export_id'] ?? $id;
        Cache::forget('export:progress:' . $exportId);

        if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
            Storage::disk('local')->delete($report->file_path);
        }

        $report->delete();
        return true;
    }

    public function getReportTypesByCategory(): array
    {
        return [
            'Salary & Financial' => array_map(
                fn(ReportType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'requires_date_range' => $type->requiresDateRange(),
                ],
                ReportType::salaryReports()
            ),
            'Attendance' => array_map(
                fn(ReportType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'requires_date_range' => $type->requiresDateRange(),
                ],
                ReportType::attendanceReports()
            ),
            'Leave Management' => array_map(
                fn(ReportType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'requires_date_range' => $type->requiresDateRange(),
                ],
                ReportType::leaveReports()
            ),
            'Employee Lifecycle' => array_map(
                fn(ReportType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'requires_date_range' => $type->requiresDateRange(),
                ],
                ReportType::employeeReports()
            ),
        ];
    }

    public function getFormats(): array
    {
        return ReportFormat::options();
    }

    public function getPreview(ReportType $type, array $filters = [], int $limit = 5): array
    {
        $query = $this->buildReportQuery($type, $filters);
        return $query->limit($limit)->get()->toArray();
    }

    public function getCount(ReportType $type, array $filters = []): int
    {
        $query = $this->buildReportQuery($type, $filters);
        return $query->count();
    }

    private function buildReportQuery(ReportType $type, array $filters)
    {
        $query = match ($type) {
            ReportType::SALARY_MASTER => \App\Models\User::query(),
            ReportType::SALARY_BY_DEPARTMENT => \App\Models\User::query(),
            ReportType::SALARY_BY_DESIGNATION => \App\Models\User::query(),
            ReportType::SALARY_BY_LOCATION => \App\Models\User::query(),
            ReportType::SALARY_MONTHLY_COMPARATIVE => \App\Models\User::query(),
            ReportType::SALARY_BANK_ADVICE => \App\Models\User::query(),
            ReportType::ATTENDANCE_DAILY => \App\Models\Attendance::query(),
            ReportType::ATTENDANCE_MONTHLY => \App\Models\Attendance::query(),
            ReportType::ATTENDANCE_LATE_TRENDS => \App\Models\Attendance::query(),
            ReportType::ATTENDANCE_OVERTIME => \App\Models\Attendance::query(),
            ReportType::LEAVE_BALANCE => \App\Models\LeaveBalance::query(),
            ReportType::LEAVE_ENCASHMENT => \App\Models\Leave::query(),
            ReportType::LEAVE_DEPARTMENT_HEATMAP => \App\Models\Leave::query(),
            ReportType::LEAVE_AVAILED => \App\Models\Leave::query(),
            ReportType::EMPLOYEE_RECRUITMENT => \App\Models\User::query(),
            ReportType::EMPLOYEE_ATTRITION => \App\Models\User::query(),
            ReportType::EMPLOYEE_SERVICE_LENGTH => \App\Models\User::query(),
            ReportType::EMPLOYEE_PROFILE_EXPORT => \App\Models\User::query(),
            default => \App\Models\User::query(),
        };

        $query = $this->applyFilters($query, $type, $filters);

        return $query;
    }

    private function applyFilters($query, ReportType $type, array $filters)
    {
        if (isset($filters['department_ids']) && !empty($filters['department_ids'])) {
            $query->whereIn('department_id', $filters['department_ids']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            if ($type === ReportType::ATTENDANCE_DAILY || $type === ReportType::ATTENDANCE_MONTHLY ||
                $type === ReportType::ATTENDANCE_LATE_TRENDS || $type === ReportType::ATTENDANCE_OVERTIME) {
                $query->whereBetween('attendance_date', [$filters['start_date'], $filters['end_date']]);
            } elseif ($type === ReportType::LEAVE_ENCASHMENT || $type === ReportType::LEAVE_AVAILED) {
                $query->whereBetween('start_date', [$filters['start_date'], $filters['end_date']]);
            }
        }

        if (isset($filters['year'])) {
            if ($type === ReportType::LEAVE_BALANCE || $type === ReportType::LEAVE_DEPARTMENT_HEATMAP) {
                $query->where('year', $filters['year']);
            }
        }

        if (isset($filters['date'])) {
            if ($type === ReportType::ATTENDANCE_DAILY) {
                $query->where('attendance_date', $filters['date']);
            }
        }

        return $query;
    }
}
