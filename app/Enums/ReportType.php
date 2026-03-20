<?php

namespace App\Enums;

enum ReportType: string
{
    case SALARY_MASTER = 'salary_master';
    case SALARY_BY_DEPARTMENT = 'salary_by_department';
    case SALARY_BY_DESIGNATION = 'salary_by_designation';
    case SALARY_BY_LOCATION = 'salary_by_location';
    case SALARY_MONTHLY_COMPARATIVE = 'salary_monthly_comparative';
    case SALARY_BANK_ADVICE = 'salary_bank_advice';

    case ATTENDANCE_DAILY = 'attendance_daily';
    case ATTENDANCE_MONTHLY = 'attendance_monthly';
    case ATTENDANCE_LATE_TRENDS = 'attendance_late_trends';
    case ATTENDANCE_OVERTIME = 'attendance_overtime';

    case LEAVE_BALANCE = 'leave_balance';
    case LEAVE_ENCASHMENT = 'leave_encashment';
    case LEAVE_DEPARTMENT_HEATMAP = 'leave_department_heatmap';
    case LEAVE_AVAILED = 'leave_availed';

    case EMPLOYEE_RECRUITMENT = 'employee_recruitment';
    case EMPLOYEE_ATTRITION = 'employee_attrition';
    case EMPLOYEE_SERVICE_LENGTH = 'employee_service_length';
    case EMPLOYEE_PROFILE_EXPORT = 'employee_profile_export';

    public function label(): string
    {
        return match ($this) {
            self::SALARY_MASTER => 'Master Salary Sheet',
            self::SALARY_BY_DEPARTMENT => 'Salary by Department',
            self::SALARY_BY_DESIGNATION => 'Salary by Designation',
            self::SALARY_BY_LOCATION => 'Salary by Location',
            self::SALARY_MONTHLY_COMPARATIVE => 'Monthly Comparative',
            self::SALARY_BANK_ADVICE => 'Bank Advice Format',
            self::ATTENDANCE_DAILY => 'Daily Attendance',
            self::ATTENDANCE_MONTHLY => 'Monthly Attendance',
            self::ATTENDANCE_LATE_TRENDS => 'Late Arrival Trends',
            self::ATTENDANCE_OVERTIME => 'Overtime Summary',
            self::LEAVE_BALANCE => 'Leave Balance Report',
            self::LEAVE_ENCASHMENT => 'Leave Encashment',
            self::LEAVE_DEPARTMENT_HEATMAP => 'Department Leave Heatmap',
            self::LEAVE_AVAILED => 'Leave Availed Report',
            self::EMPLOYEE_RECRUITMENT => 'Recruitment Report',
            self::EMPLOYEE_ATTRITION => 'Attrition Report',
            self::EMPLOYEE_SERVICE_LENGTH => 'Service Length Report',
            self::EMPLOYEE_PROFILE_EXPORT => 'Employee Profile Export',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::SALARY_MASTER,
            self::SALARY_BY_DEPARTMENT,
            self::SALARY_BY_DESIGNATION,
            self::SALARY_BY_LOCATION,
            self::SALARY_MONTHLY_COMPARATIVE,
            self::SALARY_BANK_ADVICE => 'Salary & Financial',

            self::ATTENDANCE_DAILY,
            self::ATTENDANCE_MONTHLY,
            self::ATTENDANCE_LATE_TRENDS,
            self::ATTENDANCE_OVERTIME => 'Attendance',

            self::LEAVE_BALANCE,
            self::LEAVE_ENCASHMENT,
            self::LEAVE_DEPARTMENT_HEATMAP,
            self::LEAVE_AVAILED => 'Leave Management',

            self::EMPLOYEE_RECRUITMENT,
            self::EMPLOYEE_ATTRITION,
            self::EMPLOYEE_SERVICE_LENGTH,
            self::EMPLOYEE_PROFILE_EXPORT => 'Employee Lifecycle',
        };
    }

    public function defaultFormat(): string
    {
        return match ($this) {
            self::SALARY_BANK_ADVICE => 'xlsx',
            default => 'csv',
        };
    }

    public function requiresDateRange(): bool
    {
        return in_array($this, [
            self::SALARY_MASTER,
            self::SALARY_BY_DEPARTMENT,
            self::SALARY_BY_DESIGNATION,
            self::SALARY_BY_LOCATION,
            self::SALARY_MONTHLY_COMPARATIVE,
            self::SALARY_BANK_ADVICE,
            self::ATTENDANCE_DAILY,
            self::ATTENDANCE_MONTHLY,
            self::ATTENDANCE_LATE_TRENDS,
            self::ATTENDANCE_OVERTIME,
            self::LEAVE_ENCASHMENT,
            self::LEAVE_AVAILED,
        ]);
    }

    public static function salaryReports(): array
    {
        return [
            self::SALARY_MASTER,
            self::SALARY_BY_DEPARTMENT,
            self::SALARY_BY_DESIGNATION,
            self::SALARY_BY_LOCATION,
            self::SALARY_MONTHLY_COMPARATIVE,
            self::SALARY_BANK_ADVICE,
        ];
    }

    public static function attendanceReports(): array
    {
        return [
            self::ATTENDANCE_DAILY,
            self::ATTENDANCE_MONTHLY,
            self::ATTENDANCE_LATE_TRENDS,
            self::ATTENDANCE_OVERTIME,
        ];
    }

    public static function leaveReports(): array
    {
        return [
            self::LEAVE_BALANCE,
            self::LEAVE_ENCASHMENT,
            self::LEAVE_DEPARTMENT_HEATMAP,
            self::LEAVE_AVAILED,
        ];
    }

    public static function employeeReports(): array
    {
        return [
            self::EMPLOYEE_RECRUITMENT,
            self::EMPLOYEE_ATTRITION,
            self::EMPLOYEE_SERVICE_LENGTH,
            self::EMPLOYEE_PROFILE_EXPORT,
        ];
    }
}
