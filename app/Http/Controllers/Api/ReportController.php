<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = $request->get('per_page', 15);

        $reports = $this->reportService->getUserReports($userId, $perPage);

        $data = collect($reports->items())->map(function ($report) {
            return $this->formatReportResponse($report);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string'],
            'format' => ['sometimes', 'string', 'in:csv,xlsx,pdf,docx,sql'],
            'name' => ['sometimes', 'string', 'max:255'],
            'filters' => ['sometimes', 'array'],
            'filters.start_date' => ['nullable', 'date', 'required_if:type,'.implode(',', $this->getDateRangeRequiredTypes())],
            'filters.end_date' => ['nullable', 'date', 'required_if:type,'.implode(',', $this->getDateRangeRequiredTypes())],
            'filters.department_ids' => ['sometimes', 'array'],
            'filters.department_ids.*' => ['integer'],
            'filters.user_ids' => ['sometimes', 'array'],
            'filters.user_ids.*' => ['integer'],
            'filters.date' => ['nullable', 'date'],
            'filters.year' => ['sometimes', 'integer'],
            'filters.month' => ['sometimes', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = ReportType::tryFrom($request->type);

        if (!$type) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid report type',
            ], 400);
        }

        if ($type->requiresDateRange() && (!$request->filters['start_date'] || !$request->filters['end_date'])) {
            return response()->json([
                'success' => false,
                'error' => 'This report requires a date range',
            ], 400);
        }

        $userId = $request->user()->id;
        $format = $request->format ? ReportFormat::from($request->format) : null;

        try {
            $report = $this->reportService->createReport(
                $userId,
                $type,
                $request->filters ?? [],
                $format,
                $request->name
            );

            return response()->json([
                'success' => true,
                'message' => 'Report generation started',
                'data' => $this->formatReportResponse($report),
            ], 202);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $report = $this->reportService->getReportById($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'error' => 'Report not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatReportResponse($report),
        ]);
    }

    public function progress(string $id): JsonResponse
    {
        $report = $this->reportService->getReportById($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'error' => 'Report not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatReportResponse($report),
        ]);
    }

    public function download(string $id, Request $request): Response|JsonResponse
    {
        $report = $this->reportService->getReportById($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'error' => 'Report not found',
            ], 404);
        }

        if ($report->status->value !== 'completed') {
            return response()->json([
                'success' => false,
                'error' => 'Report is not ready for download',
                'status' => $report->status->value,
                'progress' => $report->progress,
            ], 400);
        }

        if (!$report->file_path || !Storage::disk('local')->exists($report->file_path)) {
            return response()->json([
                'success' => false,
                'error' => 'File not found on disk',
            ], 404);
        }

        $content = Storage::disk('local')->get($report->file_path);

        return response($content, 200, [
            'Content-Type' => $report->format->mimeType(),
            'Content-Disposition' => 'attachment; filename="' . $report->file_name . '"',
            'Content-Length' => strlen($content),
        ]);
    }

    public function cancel(string $id): JsonResponse
    {
        $success = $this->reportService->cancelReport($id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to cancel report',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Report cancelled',
        ]);
    }

    public function retry(string $id): JsonResponse
    {
        $report = $this->reportService->retryReport($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to retry report',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Report generation restarted',
            'data' => $this->formatReportResponse($report),
        ], 202);
    }

    public function destroy(string $id): JsonResponse
    {
        $success = $this->reportService->deleteReport($id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to delete report',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Report deleted',
        ]);
    }

    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->getReportTypesByCategory(),
        ]);
    }

    public function formats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->getFormats(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string'],
            'filters' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = ReportType::tryFrom($request->type);

        if (!$type) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid report type',
            ], 400);
        }

        try {
            $filters = $request->filters ?? [];
            $preview = $this->reportService->getPreview($type, $filters, 5);
            $formattedFilters = $this->reportService->getFormattedFilters($filters);
            
            return response()->json([
                'success' => true,
                'data' => $preview,
                'total_count' => $this->reportService->getCount($type, $filters),
                'filters' => $formattedFilters,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function formatReportResponse(Report $report): array
    {
        return [
            'id' => $report->id,
            'type' => $report->type->value,
            'type_label' => $report->type->label(),
            'format' => $report->format->value,
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'name' => $report->name,
            'description' => $report->description,
            'progress' => $report->progress,
            'total_records' => $report->total_records,
            'processed_records' => $report->processed_records,
            'file_name' => $report->file_name,
            'file_size_formatted' => $report->file_size_formatted,
            'download_url' => $report->download_url,
            'error_message' => $report->error_message,
            'created_at' => $report->created_at?->toIso8601String(),
            'started_at' => $report->started_at?->toIso8601String(),
            'completed_at' => $report->completed_at?->toIso8601String(),
            'duration' => $report->duration,
        ];
    }

    private function getDateRangeRequiredTypes(): array
    {
        return array_filter(array_column(ReportType::cases(), 'value'), function ($type) {
            return ReportType::tryFrom($type)?->requiresDateRange() ?? false;
        });
    }
}
