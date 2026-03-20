<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalaryReportService
{
    private int $lateDaysForOneDayCut;
    private float $salaryCutPercentage;

    public function __construct(
        int $lateDaysForOneDayCut = 3,
        float $salaryCutPercentage = 100
    ) {
        $this->lateDaysForOneDayCut = $lateDaysForOneDayCut;
        $this->salaryCutPercentage = $salaryCutPercentage;
    }

    public function generateSalaryReport(
        Carbon $startDate,
        Carbon $endDate,
        ?array $departmentIds = null,
        ?array $userIds = null
    ): Collection {
        $query = User::with(['salary', 'department', 'designation'])
            ->where('status', 'active');

        if ($departmentIds) {
            $query->whereIn('department_id', $departmentIds);
        }

        if ($userIds) {
            $query->whereIn('id', $userIds);
        }

        $employees = $query->get();

        return $employees->map(function ($employee) use ($startDate, $endDate) {
            return $this->calculateEmployeeSalary($employee, $startDate, $endDate);
        });
    }

    public function calculateEmployeeSalary(User $employee, Carbon $startDate, Carbon $endDate): array
    {
        $salary = $employee->salary;
        $dailyRate = $salary ? $salary->basic_salary / 30 : 0;

        $lateDays = $this->getLateDays($employee->id, $startDate, $endDate);
        $lateDeductionDays = floor($lateDays / $this->lateDaysForOneDayCut);

        $leaveDeduction = $this->getLeaveDeduction($employee->id, $startDate, $endDate, $dailyRate);

        $totalDeductionDays = $lateDeductionDays + $leaveDeduction['days'];
        $totalDeduction = $totalDeductionDays * $dailyRate * ($this->salaryCutPercentage / 100);

        return [
            'employee_id' => $employee->employee_id,
            'employee_name' => $employee->name,
            'department' => $employee->department?->name,
            'designation' => $employee->designation?->name,
            'basic_salary' => $salary?->basic_salary ?? 0,
            'gross_salary' => $salary?->gross_salary ?? 0,
            'net_salary' => $salary?->net_salary ?? 0,
            'daily_rate' => round($dailyRate, 2),
            'late_days' => $lateDays,
            'late_deduction_days' => $lateDeductionDays,
            'leave_without_balance' => $leaveDeduction['days'],
            'leave_deduction_amount' => round($leaveDeduction['amount'], 2),
            'total_deduction_days' => $totalDeductionDays,
            'total_deduction_amount' => round($totalDeduction, 2),
            'final_salary' => round(($salary?->net_salary ?? 0) - $totalDeduction, 2),
            'period' => $startDate->format('M Y'),
        ];
    }

    private function getLateDays(int $userId, Carbon $startDate, Carbon $endDate): int
    {
        return Attendance::where('user_id', $userId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->where('status', 'late')
            ->count();
    }

    private function getLeaveDeduction(int $userId, Carbon $startDate, Carbon $endDate, float $dailyRate): array
    {
        $unpaidLeaves = Leave::where('user_id', $userId)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->where('is_paid', false)
            ->sum('days');

        $leaveBalance = LeaveBalance::where('user_id', $userId)
            ->where('year', $startDate->year)
            ->where('month', $startDate->month)
            ->first();

        $availableBalance = $leaveBalance?->total_available ?? 0;

        if ($availableBalance < $unpaidLeaves) {
            $deductibleDays = $unpaidLeaves - $availableBalance;
            return [
                'days' => $deductibleDays,
                'amount' => $deductibleDays * $dailyRate,
            ];
        }

        return ['days' => 0, 'amount' => 0];
    }
}
