<?php

namespace App\Services;

use App\Enums\ReportType;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportQueryBuilder
{
    private ReportType $type;
    private array $filters;

    public function __construct(ReportType $type, array $filters = [])
    {
        $this->type = $type;
        $this->filters = $filters;
    }

    public function buildQuery(): Builder
    {
        return match ($this->type) {
            ReportType::SALARY_MASTER => $this->buildSalaryMasterQuery(),
            ReportType::SALARY_BY_DEPARTMENT => $this->buildSalaryByDepartmentQuery(),
            ReportType::SALARY_BY_DESIGNATION => $this->buildSalaryByDesignationQuery(),
            ReportType::SALARY_BY_LOCATION => $this->buildSalaryByLocationQuery(),
            ReportType::SALARY_MONTHLY_COMPARATIVE => $this->buildSalaryComparativeQuery(),
            ReportType::SALARY_BANK_ADVICE => $this->buildSalaryBankAdviceQuery(),
            
            ReportType::ATTENDANCE_DAILY => $this->buildAttendanceDailyQuery(),
            ReportType::ATTENDANCE_MONTHLY => $this->buildAttendanceMonthlyQuery(),
            ReportType::ATTENDANCE_LATE_TRENDS => $this->buildAttendanceLateTrendsQuery(),
            ReportType::ATTENDANCE_OVERTIME => $this->buildAttendanceOvertimeQuery(),
            
            ReportType::LEAVE_BALANCE => $this->buildLeaveBalanceQuery(),
            ReportType::LEAVE_ENCASHMENT => $this->buildLeaveEncashmentQuery(),
            ReportType::LEAVE_DEPARTMENT_HEATMAP => $this->buildLeaveHeatmapQuery(),
            ReportType::LEAVE_AVAILED => $this->buildLeaveAvailedQuery(),
            
            ReportType::EMPLOYEE_RECRUITMENT => $this->buildRecruitmentQuery(),
            ReportType::EMPLOYEE_ATTRITION => $this->buildAttritionQuery(),
            ReportType::EMPLOYEE_SERVICE_LENGTH => $this->buildServiceLengthQuery(),
            ReportType::EMPLOYEE_PROFILE_EXPORT => $this->buildEmployeeProfileQuery(),
        };
    }

    private function getStartDate(): Carbon
    {
        return Carbon::parse($this->filters['start_date'] ?? now()->startOfMonth());
    }

    private function getEndDate(): Carbon
    {
        return Carbon::parse($this->filters['end_date'] ?? now()->endOfMonth());
    }

    private function applyDepartmentFilter(Builder $query): Builder
    {
        if (!empty($this->filters['department_ids'])) {
            $query->whereIn('department_id', $this->filters['department_ids']);
        }
        return $query;
    }

    private function applyUserFilter(Builder $query): Builder
    {
        if (!empty($this->filters['user_ids'])) {
            $query->whereIn('id', $this->filters['user_ids']);
        }
        return $query;
    }

    private function buildSalaryMasterQuery(): Builder
    {
        return User::with(['salary', 'department', 'designation'])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->when($this->filters['user_ids'] ?? null, fn($q) => $q->whereIn('id', $this->filters['user_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildSalaryByDepartmentQuery(): Builder
    {
        return User::with(['salary', 'department', 'designation'])
            ->where('status', 'active')
            ->whereHas('department')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildSalaryByDesignationQuery(): Builder
    {
        return User::with(['salary', 'department', 'designation'])
            ->where('status', 'active')
            ->whereHas('designation')
            ->when($this->filters['designation_ids'] ?? null, fn($q) => $q->whereIn('designation_id', $this->filters['designation_ids']))
            ->orderBy('designation_id')
            ->orderBy('name');
    }

    private function buildSalaryByLocationQuery(): Builder
    {
        return User::with(['salary', 'department', 'designation'])
            ->where('status', 'active')
            ->whereHas('department', fn($q) => $q->whereNotNull('location'))
            ->when($this->filters['locations'] ?? null, fn($q) => $q->whereIn('departments.location', $this->filters['locations']))
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->orderBy('departments.location')
            ->orderBy('users.name')
            ->select('users.*');
    }

    private function buildSalaryComparativeQuery(): Builder
    {
        return User::with(['salary', 'department', 'designation'])
            ->where('status', 'active')
            ->orderBy('name');
    }

    private function buildSalaryBankAdviceQuery(): Builder
    {
        return User::with(['salary', 'department'])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildAttendanceDailyQuery(): Builder
    {
        return User::with(['department', 'designation'])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildAttendanceMonthlyQuery(): Builder
    {
        return User::with(['department', 'designation'])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildAttendanceLateTrendsQuery(): Builder
    {
        return User::with(['department', 'designation'])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildAttendanceOvertimeQuery(): Builder
    {
        return User::with(['department', 'designation'])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildLeaveBalanceQuery(): Builder
    {
        $year = $this->filters['year'] ?? now()->year;
        
        return User::with(['department', 'designation', 'leaveBalances' => fn($q) => $q->where('year', $year)])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildLeaveEncashmentQuery(): Builder
    {
        return User::with(['department', 'designation', 'salary', 'leaveBalances' => fn($q) => $q->where('year', now()->year)])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildLeaveHeatmapQuery(): Builder
    {
        return User::with(['department'])
            ->where('status', 'active')
            ->whereNotNull('department_id')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id');
    }

    private function buildLeaveAvailedQuery(): Builder
    {
        return User::with(['department', 'designation'])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    private function buildRecruitmentQuery(): Builder
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        
        return User::with(['department', 'designation'])
            ->whereBetween('join_date', [$startDate, $endDate])
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('join_date');
    }

    private function buildAttritionQuery(): Builder
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        
        return User::onlyTrashed()
            ->with(['department', 'designation'])
            ->whereBetween('deleted_at', [$startDate, $endDate])
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('deleted_at');
    }

    private function buildServiceLengthQuery(): Builder
    {
        return User::with(['department', 'designation'])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('join_date', 'asc');
    }

    private function buildEmployeeProfileQuery(): Builder
    {
        return User::with(['department', 'designation', 'salary', 'leaveBalances' => fn($q) => $q->where('year', now()->year)->latest()->take(1)])
            ->where('status', 'active')
            ->when($this->filters['department_ids'] ?? null, fn($q) => $q->whereIn('department_id', $this->filters['department_ids']))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    public function getColumns(): array
    {
        return match ($this->type) {
            ReportType::SALARY_MASTER => $this->getSalaryMasterColumns(),
            ReportType::SALARY_BY_DEPARTMENT => $this->getSalaryByDepartmentColumns(),
            ReportType::SALARY_BY_DESIGNATION => $this->getSalaryByDesignationColumns(),
            ReportType::SALARY_BY_LOCATION => $this->getSalaryByLocationColumns(),
            ReportType::SALARY_MONTHLY_COMPARATIVE => $this->getSalaryComparativeColumns(),
            ReportType::SALARY_BANK_ADVICE => $this->getSalaryBankAdviceColumns(),
            
            ReportType::ATTENDANCE_DAILY => $this->getAttendanceDailyColumns(),
            ReportType::ATTENDANCE_MONTHLY => $this->getAttendanceMonthlyColumns(),
            ReportType::ATTENDANCE_LATE_TRENDS => $this->getAttendanceLateTrendsColumns(),
            ReportType::ATTENDANCE_OVERTIME => $this->getAttendanceOvertimeColumns(),
            
            ReportType::LEAVE_BALANCE => $this->getLeaveBalanceColumns(),
            ReportType::LEAVE_ENCASHMENT => $this->getLeaveEncashmentColumns(),
            ReportType::LEAVE_DEPARTMENT_HEATMAP => $this->getLeaveHeatmapColumns(),
            ReportType::LEAVE_AVAILED => $this->getLeaveAvailedColumns(),
            
            ReportType::EMPLOYEE_RECRUITMENT => $this->getRecruitmentColumns(),
            ReportType::EMPLOYEE_ATTRITION => $this->getAttritionColumns(),
            ReportType::EMPLOYEE_SERVICE_LENGTH => $this->getServiceLengthColumns(),
            ReportType::EMPLOYEE_PROFILE_EXPORT => $this->getEmployeeProfileColumns(),
        };
    }

    private function getSalaryMasterColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Designation' => fn($r) => $r->designation?->name ?? 'N/A',
            'Basic Salary' => fn($r) => $r->salary?->basic_salary ?? 0,
            'House Rent' => fn($r) => $r->salary?->house_rent ?? 0,
            'Medical Allowance' => fn($r) => $r->salary?->medical_allowance ?? 0,
            'Transport Allowance' => fn($r) => $r->salary?->transport_allowance ?? 0,
            'Special Allowance' => fn($r) => $r->salary?->special_allowance ?? 0,
            'Gross Salary' => fn($r) => $r->salary?->gross_salary ?? 0,
            'Provident Fund' => fn($r) => $r->salary?->provident_fund ?? 0,
            'Tax' => fn($r) => $r->salary?->tax ?? 0,
            'Net Salary' => fn($r) => $r->salary?->net_salary ?? 0,
        ];
    }

    private function getSalaryByDepartmentColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Designation' => fn($r) => $r->designation?->name ?? 'N/A',
            'Basic Salary' => fn($r) => $r->salary?->basic_salary ?? 0,
            'Net Salary' => fn($r) => $r->salary?->net_salary ?? 0,
        ];
    }

    private function getSalaryByDesignationColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Designation' => fn($r) => $r->designation?->name ?? 'N/A',
            'Basic Salary' => fn($r) => $r->salary?->basic_salary ?? 0,
            'Net Salary' => fn($r) => $r->salary?->net_salary ?? 0,
        ];
    }

    private function getSalaryByLocationColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Location' => fn($r) => $r->department?->location ?? 'N/A',
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Net Salary' => fn($r) => $r->salary?->net_salary ?? 0,
        ];
    }

    private function getSalaryComparativeColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Net Salary' => fn($r) => $r->salary?->net_salary ?? 0,
        ];
    }

    private function getSalaryBankAdviceColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Bank Account' => fn($r) => $r->bank_account ?? 'N/A',
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Net Salary' => fn($r) => $r->salary?->net_salary ?? 0,
            'Payment Mode' => 'Bank Transfer',
        ];
    }

    private function getAttendanceDailyColumns(): array
    {
        $date = $this->filters['date'] ?? now()->format('Y-m-d');
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Date' => $date,
            'Check In' => fn($r) => $r->attendances->first()?->check_in ?? 'N/A',
            'Check Out' => fn($r) => $r->attendances->first()?->check_out ?? 'N/A',
            'Status' => fn($r) => $r->attendances->first()?->status ?? 'Absent',
        ];
    }

    private function getAttendanceMonthlyColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Present Days' => fn($r) => $r->attendances->where('status', 'present')->count(),
            'Late Days' => fn($r) => $r->attendances->where('status', 'late')->count(),
            'Absent Days' => fn($r) => $r->attendances->where('status', 'absent')->count(),
        ];
    }

    private function getAttendanceLateTrendsColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Total Late Days' => fn($r) => $r->attendances->where('status', 'late')->count(),
        ];
    }

    private function getAttendanceOvertimeColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Total Hours' => fn($r) => $r->attendances->sum('worked_hours'),
            'Overtime Hours' => fn($r) => max(0, $r->attendances->sum('worked_hours') - ($r->attendances->count() * 8)),
        ];
    }

    private function getLeaveBalanceColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Casual Leave' => fn($r) => $r->leaveBalances->first()?->casual_leave ?? 0,
            'Used Casual' => fn($r) => $r->leaveBalances->first()?->used_casual ?? 0,
            'Sick Leave' => fn($r) => $r->leaveBalances->first()?->sick_leave ?? 0,
            'Used Sick' => fn($r) => $r->leaveBalances->first()?->used_sick ?? 0,
            'Annual Leave' => fn($r) => $r->leaveBalances->first()?->annual_leave ?? 0,
            'Used Annual' => fn($r) => $r->leaveBalances->first()?->used_annual ?? 0,
        ];
    }

    private function getLeaveEncashmentColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Join Date' => fn($r) => $r->join_date?->format('Y-m-d') ?? 'N/A',
            'Leave Balance' => fn($r) => $r->leaveBalances->first()?->total_available ?? 0,
            'Daily Rate' => fn($r) => $r->salary?->basic_salary ? $r->salary->basic_salary / 30 : 0,
        ];
    }

    private function getLeaveHeatmapColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Casual Leave' => fn($r) => $r->leaves->where('leave_type', 'casual')->sum('days'),
            'Sick Leave' => fn($r) => $r->leaves->where('leave_type', 'sick')->sum('days'),
            'Annual Leave' => fn($r) => $r->leaves->where('leave_type', 'annual')->sum('days'),
        ];
    }

    private function getLeaveAvailedColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Leave Type' => fn($r) => $r->leaves->pluck('leave_type')->implode(', ') ?: 'N/A',
            'Total Days' => fn($r) => $r->leaves->sum('days'),
        ];
    }

    private function getRecruitmentColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Email' => 'email',
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Designation' => fn($r) => $r->designation?->name ?? 'N/A',
            'Join Date' => fn($r) => $r->join_date?->format('Y-m-d') ?? 'N/A',
            'Status' => 'status',
        ];
    }

    private function getAttritionColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Email' => 'email',
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Join Date' => fn($r) => $r->join_date?->format('Y-m-d') ?? 'N/A',
            'Left Date' => fn($r) => $r->deleted_at?->format('Y-m-d') ?? 'N/A',
            'Status' => 'Inactive',
        ];
    }

    private function getServiceLengthColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Join Date' => fn($r) => $r->join_date?->format('Y-m-d') ?? 'N/A',
            'Years of Service' => fn($r) => $r->join_date ? $r->join_date->diffInYears(now()) : 0,
        ];
    }

    private function getEmployeeProfileColumns(): array
    {
        return [
            'Employee ID' => 'employee_id',
            'Employee Name' => fn($r) => $r->name,
            'Email' => 'email',
            'Phone' => fn($r) => $r->phone ?? 'N/A',
            'Department' => fn($r) => $r->department?->name ?? 'N/A',
            'Designation' => fn($r) => $r->designation?->name ?? 'N/A',
            'Join Date' => fn($r) => $r->join_date?->format('Y-m-d') ?? 'N/A',
            'Status' => 'status',
            'Basic Salary' => fn($r) => $r->salary?->basic_salary ?? 'N/A',
            'Gross Salary' => fn($r) => $r->salary?->gross_salary ?? 'N/A',
            'Net Salary' => fn($r) => $r->salary?->net_salary ?? 'N/A',
        ];
    }
}
