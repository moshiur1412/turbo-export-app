<?php

namespace App\Http\Controllers;

use App\Services\ReportBuilder;
use App\Services\SalaryReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryReportController extends Controller
{
    public function __construct(
        private readonly SalaryReportService $salaryReportService
    ) {}

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'format' => ['sometimes', 'string', 'in:csv,xlsx,pdf,docx'],
            'filename' => ['sometimes', 'string'],
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $departmentIds = $validated['department_ids'] ?? null;
        $userIds = $validated['user_ids'] ?? null;

        $reportData = $this->salaryReportService->generateSalaryReport(
            $startDate,
            $endDate,
            $departmentIds,
            $userIds
        );

        return response()->json([
            'success' => true,
            'data' => $reportData,
            'meta' => [
                'period' => $startDate->format('M Y'),
                'total_employees' => $reportData->count(),
                'total_late_days' => $reportData->sum('late_days'),
                'total_deductions' => $reportData->sum('total_deduction_amount'),
            ],
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer'],
            'format' => ['required', 'string', 'in:csv,xlsx,pdf,docx'],
            'filename' => ['sometimes', 'string'],
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $departmentIds = $validated['department_ids'] ?? null;
        $userIds = $validated['user_ids'] ?? null;

        $reportData = $this->salaryReportService->generateSalaryReport(
            $startDate,
            $endDate,
            $departmentIds,
            $userIds
        );

        return response()->json([
            'success' => true,
            'message' => 'Export job created',
            'data' => $reportData->toArray(),
            'meta' => [
                'period' => $startDate->format('M Y'),
                'total_employees' => $reportData->count(),
                'total_late_days' => $reportData->sum('late_days'),
                'total_deductions' => $reportData->sum('total_deduction_amount'),
            ],
        ]);
    }

    public function dynamicReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
            'columns' => ['required', 'array', 'min:1'],
            'joins' => ['sometimes', 'array'],
            'filters' => ['sometimes', 'array'],
            'aggregations' => ['sometimes', 'array'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'format' => ['sometimes', 'string', 'in:csv,xlsx,pdf,docx,sql'],
            'filename' => ['sometimes', 'string'],
        ]);

        $reportBuilder = app(ReportBuilder::class)
            ->from('App\\Models\\' . $validated['model'])
            ->select($validated['columns']);

        if (!empty($validated['joins'])) {
            foreach ($validated['joins'] as $join) {
                $reportBuilder->addJoin(
                    $join['table'],
                    $join['first'],
                    $join['operator'] ?? '=',
                    $join['second']
                );
            }
        }

        if (!empty($validated['filters'])) {
            foreach ($validated['filters'] as $filter) {
                $reportBuilder->addFilter(
                    $filter['column'],
                    $filter['value'],
                    $filter['operator'] ?? '='
                );
            }
        }

        if (!empty($validated['aggregations'])) {
            foreach ($validated['aggregations'] as $agg) {
                $reportBuilder->addAggregation(
                    $agg['column'],
                    $agg['function'],
                    $agg['alias']
                );
            }
        }

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $dateColumn = $validated['date_column'] ?? 'created_at';
            $reportBuilder->whereDateRange(
                $dateColumn,
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date'])
            );
        }

        $result = $reportBuilder->execute();

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'total_records' => $result->count(),
                'columns' => $validated['columns'],
            ],
        ]);
    }
}
