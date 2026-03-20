<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\Salary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeneralDataSeeder extends Seeder
{
    protected array $config = [];
    protected int $chunkSize;
    protected ?object $output = null;

    public function configure(array $config, ?object $output = null): self
    {
        $this->config = $config;
        $this->chunkSize = $config['chunk_size'] ?? 10000;
        $this->output = $output ?? $this->command;
        return $this;
    }

    protected function info(string $message): void
    {
        if ($this->output) {
            $this->output->info($message);
        }
        fwrite(STDOUT, $message . PHP_EOL);
        if (function_exists('fflush')) {
            fflush(STDOUT);
        }
    }

    protected function line(string $message): void
    {
        if ($this->output) {
            $this->output->line($message);
        }
        fwrite(STDOUT, $message . PHP_EOL);
        if (function_exists('fflush')) {
            fflush(STDOUT);
        }
    }

    protected function warn(string $message): void
    {
        if ($this->output) {
            $this->output->warn($message);
        }
    }

    protected function createProgressBar(int $max = 0): object
    {
        $output = $this->output ?? $this->command;
        if ($output) {
            return $output->getOutput()->createProgressBar($max);
        }
        return new class {
            public function start(): void {}
            public function advance(int $steps = 1): void {}
            public function finish(): void {}
            public function setMaxSteps(int $max): void {}
        };
    }

    public function run(): void
    {
        $scale = $this->config['scale'] ?? 'medium';
        $this->loadScaleConfig($scale);

        $this->printHeader($scale);
        $this->generateBaseData();
        
        if ($this->shouldGenerate('attendance')) {
            $this->generateAttendanceData();
        }
        
        if ($this->shouldGenerate('leaves')) {
            $this->generateLeaveData();
        }
        
        if ($this->shouldGenerate('balances')) {
            $this->generateLeaveBalances();
        }
        
        $this->printSummary();
    }

    private function loadScaleConfig(string $scale): void
    {
        $scales = [
            'small' => [
                'employees' => 1000,
                'months' => 3,
                'leaves_per_employee' => 5,
                'balance_years' => 1,
                'description' => '~200K records (quick testing)',
            ],
            'medium' => [
                'employees' => 10000,
                'months' => 6,
                'leaves_per_employee' => 10,
                'balance_years' => 1,
                'description' => '~2M records (standard testing)',
            ],
            'large' => [
                'employees' => 50000,
                'months' => 24,
                'leaves_per_employee' => 15,
                'balance_years' => 2,
                'description' => '~30M records (stress testing)',
            ],
            'xlarge' => [
                'employees' => 100000,
                'months' => 60,
                'leaves_per_employee' => 20,
                'balance_years' => 5,
                'description' => '~150M records (load testing)',
            ],
            'xxlarge' => [
                'employees' => 200000,
                'months' => 60,
                'leaves_per_employee' => 25,
                'balance_years' => 5,
                'description' => '~300M+ records (extreme load)',
            ],
        ];

        if (isset($scales[$scale])) {
            $this->config = array_merge($this->config, $scales[$scale]);
        }
    }

    private function shouldGenerate(string $type): bool
    {
        if (isset($this->config['skip'])) {
            return !in_array($type, $this->config['skip']);
        }
        return true;
    }

    private function printHeader(string $scale): void
    {
        $this->info('========================================');
        $this->info('       GENERAL DATA SEEDER');
        $this->info('========================================');
        $this->info("Scale: {$scale}");
        $this->info("Employees: " . number_format($this->config['employees']));
        $this->info("Attendance months: " . $this->config['months']);
        $this->info("Leaves/employee: " . $this->config['leaves_per_employee']);
        $this->info("Balance years: " . $this->config['balance_years']);
        $this->info("Chunk size: " . number_format($this->chunkSize));
        $this->info('========================================');
    }

    private function generateBaseData(): void
    {
        Schema::disableForeignKeyConstraints();
        
        $this->disableKeys('users');
        $this->disableKeys('salaries');
        
        $departments = $this->getDepartments();
        $designations = $this->getDesignations();
        
        Department::insert($departments);
        Designation::insert($designations);

        $totalDepts = Department::count();
        $totalDesigs = Designation::count();
        
        $this->info("Created {$totalDepts} departments, {$totalDesigs} designations");

        $totalEmployees = $this->config['employees'];
        $hashedPassword = bcrypt('password');
        $now = now()->format('Y-m-d H:i:s');
        
        $this->info("Creating {$totalEmployees} employees...");
        
        $salariesBatch = [];
        $usersBatch = [];
        $batchSize = 100;
        
        for ($num = 1; $num <= $totalEmployees; $num++) {
            $basicSalary = rand(20000, 150000);
            $houseRent = round($basicSalary * 0.3, 2);
            $medical = round($basicSalary * 0.1, 2);
            $transport = round($basicSalary * 0.1, 2);
            $special = round($basicSalary * 0.15, 2);
            $provident = round($basicSalary * 0.12, 2);
            $tax = round($basicSalary * 0.05, 2);
            $gross = round($basicSalary + $houseRent + $medical + $transport + $special, 2);
            $net = round($gross - $provident - $tax, 2);
            
            $salariesBatch[] = [
                'basic_salary' => $basicSalary,
                'house_rent' => $houseRent,
                'medical_allowance' => $medical,
                'transport_allowance' => $transport,
                'special_allowance' => $special,
                'provident_fund' => $provident,
                'tax' => $tax,
                'gross_salary' => $gross,
                'net_salary' => $net,
                'is_active' => 1,
                'effective_date' => date('Y-m-d', strtotime("-".rand(1, 365)." days")),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            
            $usersBatch[] = [
                'employee_id' => 'EMP' . str_pad((string)$num, 6, '0', STR_PAD_LEFT),
                'name' => "Employee {$num}",
                'email' => "employee{$num}@example.com",
                'password' => $hashedPassword,
                'department_id' => rand(1, $totalDepts),
                'designation_id' => rand(1, $totalDesigs),
                'salary_id' => $num,
                'join_date' => date('Y-m-d', strtotime("-".rand(1, 1800)." days")),
                'status' => rand(1, 100) <= 95 ? 'active' : (rand(1, 2) == 1 ? 'inactive' : 'on_leave'),
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            
            if ($num % $batchSize === 0) {
                DB::table('salaries')->insert($salariesBatch);
                DB::table('users')->insert($usersBatch);
                $salariesBatch = [];
                $usersBatch = [];
                
                $this->info("  [{$num}/{$totalEmployees}] Employees inserted");
            }
        }
        
        if (!empty($salariesBatch)) {
            DB::table('salaries')->insert($salariesBatch);
            DB::table('users')->insert($usersBatch);
        }
        
        $this->enableKeys('users');
        $this->enableKeys('salaries');
        Schema::enableForeignKeyConstraints();
        
        $this->info("✅ Created " . number_format(User::count()) . " employees");
    }

    private function generateSalary(float $basicSalary): array
    {
        $houseRent = $basicSalary * 0.3;
        $medical = $basicSalary * 0.1;
        $transport = $basicSalary * 0.1;
        $special = $basicSalary * 0.15;
        $provident = $basicSalary * 0.12;
        $tax = $basicSalary * 0.05;
        $gross = $basicSalary + $houseRent + $medical + $transport + $special;
        $net = $gross - $provident - $tax;
        
        return [
            'basic_salary' => $basicSalary,
            'house_rent' => $houseRent,
            'medical_allowance' => $medical,
            'transport_allowance' => $transport,
            'special_allowance' => $special,
            'provident_fund' => $provident,
            'tax' => $tax,
            'gross_salary' => $gross,
            'net_salary' => $net,
            'is_active' => true,
            'effective_date' => Carbon::now()->subMonths(rand(1, 12)),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function generateAttendanceData(): void
    {
        $this->disableKeys('attendances');
        
        $totalEmployees = User::count();
        $months = $this->config['months'];
        $daysPerMonth = 26;
        $totalRecords = $totalEmployees * $months * $daysPerMonth;
        $now = now()->format('Y-m-d H:i:s');
        
        $this->info("Target: " . number_format($totalRecords) . " records");
        
        $bar = $this->createProgressBar($totalRecords);
        $batchSize = 500;
        $values = [];
        $insertedCount = 0;
        $lastProgress = 0;
        
        for ($month = $months; $month >= 1; $month--) {
            $monthStart = Carbon::now()->subMonths($month)->startOfMonth();
            
            for ($day = 0; $day < $daysPerMonth; $day++) {
                $date = $monthStart->copy()->addDays($day);
                
                if ($date->isWeekend()) {
                    continue;
                }
                
                $dateStr = $date->format('Y-m-d');
                
                for ($userId = 1; $userId <= $totalEmployees; $userId++) {
                    $rand = rand(1, 100);
                    
                    if ($rand <= 75) {
                        $values[] = "({$userId}, '{$dateStr}', '".sprintf('%02d:%02d', rand(8, 10), rand(0, 59))."', '".sprintf('%02d:%02d', rand(17, 20), rand(0, 59))."', ".(rand(7, 10) + (rand(0, 59) / 60)).", 'present', '{$now}', '{$now}')";
                    } elseif ($rand <= 90) {
                        $values[] = "({$userId}, '{$dateStr}', '".sprintf('%02d:%02d', rand(9, 12), rand(0, 59))."', '".sprintf('%02d:%02d', rand(14, 17), rand(0, 59))."', ".(rand(4, 6) + (rand(0, 59) / 60)).", 'present', '{$now}', '{$now}')";
                    } elseif ($rand <= 95) {
                        $values[] = "({$userId}, '{$dateStr}', '".sprintf('%02d:%02d', rand(10, 12), rand(0, 59))."', '".sprintf('%02d:%02d', rand(17, 20), rand(0, 59))."', ".(rand(7, 9) + (rand(0, 59) / 60)).", 'late', '{$now}', '{$now}')";
                    } elseif ($rand <= 98) {
                        $values[] = "({$userId}, '{$dateStr}', NULL, NULL, 0, 'absent', '{$now}', '{$now}')";
                    } else {
                        $values[] = "({$userId}, '{$dateStr}', NULL, NULL, 0, 'leave', '{$now}', '{$now}')";
                    }
                    
                    if (count($values) >= $batchSize) {
                        $sql = "INSERT INTO attendances (user_id, attendance_date, check_in, check_out, worked_hours, status, created_at, updated_at) VALUES " . implode(', ', $values);
                        DB::statement($sql);
                        $insertedCount += $batchSize;
                        $bar->advance($batchSize);
                        $values = [];
                        
                        $progress = round(($insertedCount / $totalRecords) * 100);
                        if ($progress >= $lastProgress + 1) {
                            $this->info("  [{$insertedCount}/{$totalRecords}] Attendance records inserted");
                            $lastProgress = $progress;
                        }
                        
                        gc_collect_cycles();
                    }
                }
            }
        }
        
        if (!empty($values)) {
            $sql = "INSERT INTO attendances (user_id, attendance_date, check_in, check_out, worked_hours, status, created_at, updated_at) VALUES " . implode(', ', $values);
            DB::statement($sql);
            $bar->advance(count($values));
        }
        
        $bar->finish();
        $this->info('');
        $this->info('✅ Attendance: ' . number_format(Attendance::count()) . ' records');
        
        $this->enableKeys('attendances');
    }

    private function generateLeaveData(): void
    {
        $this->disableKeys('leaves');
        
        $totalEmployees = User::count();
        $totalLeaves = $totalEmployees * $this->config['leaves_per_employee'];
        $now = now()->format('Y-m-d H:i:s');
        
        $bar = $this->createProgressBar($totalLeaves);
        
        $values = [];
        $leaveTypes = ['casual', 'sick', 'annual', 'unpaid'];
        $statuses = ['approved', 'approved', 'approved', 'approved', 'pending', 'rejected'];
        $batchSize = 500;
        $insertedCount = 0;
        
        for ($userId = 1; $userId <= $totalEmployees; $userId++) {
            for ($i = 0; $i < $this->config['leaves_per_employee']; $i++) {
                $startDate = date('Y-m-d', strtotime("-".rand(0, 1800)." days"));
                $days = rand(1, 10);
                $endDate = date('Y-m-d', strtotime($startDate . " + " . ($days - 1) . " days"));
                $status = $statuses[array_rand($statuses)];
                $leaveType = $leaveTypes[array_rand($leaveTypes)];
                $isPaid = ($status === 'approved' && rand(1, 100) <= 70) ? 1 : 0;
                $approvedBy = $status === 'approved' ? rand(1, min(10, $totalEmployees)) : 'NULL';
                $approvedAt = $status === 'approved' ? "'{$now}'" : 'NULL';
                
                $values[] = "({$userId}, '{$leaveType}', '{$startDate}', '{$endDate}', {$days}, 'Personal reason', '{$status}', {$isPaid}, {$approvedBy}, {$approvedAt}, '{$now}', '{$now}')";
                
                if (count($values) >= $batchSize) {
                    $sql = "INSERT INTO leaves (user_id, leave_type, start_date, end_date, days, reason, status, is_paid, approved_by, approved_at, created_at, updated_at) VALUES " . implode(', ', $values);
                    DB::statement($sql);
                    $insertedCount += $batchSize;
                    $bar->advance($batchSize);
                    $values = [];
                    
                    if ($insertedCount % 10000 === 0) {
                        $this->info("  [{$insertedCount}/{$totalLeaves}] Leave records inserted");
                    }
                    
                    gc_collect_cycles();
                }
            }
        }
        
        if (!empty($values)) {
            $sql = "INSERT INTO leaves (user_id, leave_type, start_date, end_date, days, reason, status, is_paid, approved_by, approved_at, created_at, updated_at) VALUES " . implode(', ', $values);
            DB::statement($sql);
            $bar->advance(count($values));
        }
        
        $bar->finish();
        $this->info('');
        $this->info('✅ Leave records: ' . number_format(Leave::count()));
        
        $this->enableKeys('leaves');
    }

    private function generateLeaveBalances(): void
    {
        $this->disableKeys('leave_balances');
        
        $totalEmployees = User::count();
        $years = $this->config['balance_years'];
        $totalBalances = $totalEmployees * $years * 12;
        $now = now()->format('Y-m-d H:i:s');
        
        $bar = $this->createProgressBar($totalBalances);
        
        $values = [];
        $currentYear = Carbon::now()->year;
        $batchSize = 500;
        $insertedCount = 0;
        
        for ($yearOffset = $years - 1; $yearOffset >= 0; $yearOffset--) {
            $year = $currentYear - $yearOffset;
            
            for ($month = 1; $month <= 12; $month++) {
                for ($userId = 1; $userId <= $totalEmployees; $userId++) {
                    $values[] = "({$userId}, {$year}, {$month}, 2.0, 1.0, 2.0, ".rand(0, 2).", ".rand(0, 1).", ".rand(0, 2).", '{$now}', '{$now}')";
                    
                    if (count($values) >= $batchSize) {
                        $sql = "INSERT INTO leave_balances (user_id, year, month, casual_leave, sick_leave, annual_leave, used_casual, used_sick, used_annual, created_at, updated_at) VALUES " . implode(', ', $values);
                        DB::statement($sql);
                        $insertedCount += $batchSize;
                        $bar->advance($batchSize);
                        $values = [];
                        
                        if ($insertedCount % 10000 === 0) {
                            $this->info("  [{$insertedCount}/{$totalBalances}] Leave balances inserted");
                        }
                        
                        gc_collect_cycles();
                    }
                }
            }
        }
        
        if (!empty($values)) {
            $sql = "INSERT INTO leave_balances (user_id, year, month, casual_leave, sick_leave, annual_leave, used_casual, used_sick, used_annual, created_at, updated_at) VALUES " . implode(', ', $values);
            DB::statement($sql);
            $bar->advance(count($values));
        }
        
        $bar->finish();
        $this->info('');
        $this->info('✅ Leave balances: ' . number_format(LeaveBalance::count()));
        
        $this->enableKeys('leave_balances');
    }

    private function disableKeys(string $table): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DISABLE KEYS");
        } catch (\Exception $e) {
        }
    }

    private function enableKeys(string $table): void
    {
        try {
            DB::statement("ALTER TABLE {$table} ENABLE KEYS");
        } catch (\Exception $e) {
        }
    }

    private function printSummary(): void
    {
        $this->info('');
        $this->info('========================================');
        $this->info('           DATA GENERATION COMPLETE');
        $this->info('========================================');
        $this->info('Departments:     ' . number_format(Department::count()));
        $this->info('Designations:    ' . number_format(Designation::count()));
        $this->info('Employees:       ' . number_format(User::count()));
        $this->info('Salaries:        ' . number_format(Salary::count()));
        $this->info('Attendance:      ' . number_format(Attendance::count()));
        $this->info('Leaves:          ' . number_format(Leave::count()));
        $this->info('Leave Balances:  ' . number_format(LeaveBalance::count()));
        $this->info('========================================');
    }

    
    private function getDepartments(): array
    {
        $departments = [
            ['name' => 'Software Development', 'code' => 'SWD'],
            ['name' => 'Database Administration', 'code' => 'DBA'],
            ['name' => 'Network Operations', 'code' => 'NET'],
            ['name' => 'Cybersecurity', 'code' => 'SEC'],
            ['name' => 'Cloud Infrastructure', 'code' => 'CLO'],
            ['name' => 'DevOps Engineering', 'code' => 'DOPS'],
            ['name' => 'Quality Assurance', 'code' => 'QA'],
            ['name' => 'Business Intelligence', 'code' => 'BI'],
            ['name' => 'Data Science', 'code' => 'DS'],
            ['name' => 'Machine Learning', 'code' => 'ML'],
            ['name' => 'Artificial Intelligence', 'code' => 'AI'],
            ['name' => 'Mobile Development', 'code' => 'MOB'],
            ['name' => 'Frontend Development', 'code' => 'FRT'],
            ['name' => 'Backend Development', 'code' => 'BAC'],
            ['name' => 'Full Stack Development', 'code' => 'FST'],
            ['name' => 'UI/UX Design', 'code' => 'UID'],
            ['name' => 'Product Management', 'code' => 'PM'],
            ['name' => 'Project Management', 'code' => 'PRM'],
            ['name' => 'Scrum Master', 'code' => 'SCR'],
            ['name' => 'Technical Writing', 'code' => 'TWR'],
            ['name' => 'System Administration', 'code' => 'SYS'],
            ['name' => 'IT Support', 'code' => 'SUP'],
            ['name' => 'Help Desk', 'code' => 'HDS'],
            ['name' => 'IT Procurement', 'code' => 'PRC'],
            ['name' => 'Vendor Management', 'code' => 'VND'],
            ['name' => 'Outsourcing Management', 'code' => 'OUT'],
            ['name' => 'BPO Operations', 'code' => 'BPO'],
            ['name' => 'Call Center Operations', 'code' => 'CAL'],
            ['name' => 'Customer Success', 'code' => 'CSU'],
            ['name' => 'Sales & Marketing', 'code' => 'SAL'],
            ['name' => 'Digital Marketing', 'code' => 'DGM'],
            ['name' => 'Content Management', 'code' => 'CM'],
            ['name' => 'Social Media', 'code' => 'SMM'],
            ['name' => 'SEO & SEM', 'code' => 'SEO'],
            ['name' => 'Graphic Design', 'code' => 'GRD'],
            ['name' => 'Video Production', 'code' => 'VID'],
            ['name' => 'Human Resources', 'code' => 'HR'],
            ['name' => 'Talent Acquisition', 'code' => 'TAL'],
            ['name' => 'Learning & Development', 'code' => 'LND'],
            ['name' => 'Compensation & Benefits', 'code' => 'CBP'],
            ['name' => 'Employee Relations', 'code' => 'EMP'],
            ['name' => 'HR Analytics', 'code' => 'HRA'],
            ['name' => 'Finance & Accounting', 'code' => 'FIN'],
            ['name' => 'Budgeting & Planning', 'code' => 'BUD'],
            ['name' => 'Tax & Compliance', 'code' => 'TAX'],
            ['name' => 'Internal Audit', 'code' => 'AUD'],
            ['name' => 'Treasury Operations', 'code' => 'TRS'],
            ['name' => 'Payroll', 'code' => 'PAY'],
            ['name' => 'Legal & Compliance', 'code' => 'LGL'],
            ['name' => 'Corporate Governance', 'code' => 'CG'],
            ['name' => 'Risk Management', 'code' => 'RSK'],
            ['name' => 'Business Continuity', 'code' => 'BCM'],
            ['name' => 'Facilities Management', 'code' => 'FAC'],
            ['name' => 'Procurement', 'code' => 'PRO'],
            ['name' => 'Supply Chain', 'code' => 'SCM'],
            ['name' => 'Logistics', 'code' => 'LOG'],
            ['name' => 'Warehouse Operations', 'code' => 'WHO'],
            ['name' => 'Inventory Management', 'code' => 'INV'],
            ['name' => 'Operations Management', 'code' => 'OPS'],
            ['name' => 'Process Optimization', 'code' => 'POC'],
            ['name' => 'Lean Six Sigma', 'code' => 'LSS'],
            ['name' => 'Research & Development', 'code' => 'RD'],
            ['name' => 'Innovation Lab', 'code' => 'INL'],
            ['name' => 'Product Design', 'code' => 'PRD'],
            ['name' => 'Solutions Architecture', 'code' => 'SAA'],
            ['name' => 'Enterprise Architecture', 'code' => 'EAA'],
            ['name' => 'Technical Architecture', 'code' => 'TAA'],
            ['name' => 'Integration Services', 'code' => 'INT'],
            ['name' => 'API Development', 'code' => 'API'],
            ['name' => 'Microservices', 'code' => 'MIC'],
            ['name' => 'Blockchain Development', 'code' => 'BCK'],
            ['name' => 'IoT Engineering', 'code' => 'IOT'],
            ['name' => 'Embedded Systems', 'code' => 'EMB'],
            ['name' => 'Game Development', 'code' => 'GME'],
            ['name' => 'Augmented Reality', 'code' => 'AR'],
            ['name' => 'Virtual Reality', 'code' => 'VR'],
            ['name' => 'Big Data Engineering', 'code' => 'BDE'],
            ['name' => 'Data Engineering', 'code' => 'DE'],
            ['name' => 'Data Analytics', 'code' => 'DAN'],
            ['name' => 'Business Analysis', 'code' => 'BA'],
            ['name' => 'Requirements Engineering', 'code' => 'RE'],
            ['name' => 'Systems Analysis', 'code' => 'SA'],
            ['name' => 'Change Management', 'code' => 'CMG'],
            ['name' => 'IT Governance', 'code' => 'ITG'],
            ['name' => 'Security Operations', 'code' => 'SOC'],
            ['name' => 'Penetration Testing', 'code' => 'PEN'],
            ['name' => 'Security Architecture', 'code' => 'SRA'],
            ['name' => 'Identity Management', 'code' => 'IDM'],
            ['name' => 'Compliance & Privacy', 'code' => 'CPR'],
            ['name' => 'Disaster Recovery', 'code' => 'DR'],
            ['name' => 'Performance Engineering', 'code' => 'PE'],
            ['name' => 'Site Reliability', 'code' => 'SRE'],
            ['name' => 'Platform Engineering', 'code' => 'PLE'],
            ['name' => 'Infrastructure Engineering', 'code' => 'IE'],
            ['name' => 'Network Security', 'code' => 'NSEC'],
            ['name' => 'Telecommunications', 'code' => 'TEL'],
            ['name' => 'VoIP Engineering', 'code' => 'VOIP'],
            ['name' => 'Video Conferencing', 'code' => 'VIDC'],
            ['name' => 'Collaboration Tools', 'code' => 'COL'],
            ['name' => 'Enterprise Systems', 'code' => 'ESY'],
            ['name' => 'ERP Administration', 'code' => 'ERP'],
            ['name' => 'CRM Administration', 'code' => 'CRM'],
            ['name' => 'SaaS Operations', 'code' => 'SAAS'],
            ['name' => 'Managed Services', 'code' => 'MSP'],
            ['name' => 'Professional Services', 'code' => 'PS'],
            ['name' => 'Consulting Services', 'code' => 'CON'],
        ];

        return array_map(fn($d) => array_merge($d, ['created_at' => now(), 'updated_at' => now()]), $departments);
    }

    private function getDesignations(): array
    {
        $designations = [
            ['name' => 'Junior Software Engineer', 'code' => 'JSE', 'min_salary' => 25000, 'max_salary' => 45000],
            ['name' => 'Software Engineer', 'code' => 'SE', 'min_salary' => 40000, 'max_salary' => 70000],
            ['name' => 'Senior Software Engineer', 'code' => 'SSE', 'min_salary' => 65000, 'max_salary' => 100000],
            ['name' => 'Staff Software Engineer', 'code' => 'SSE2', 'min_salary' => 95000, 'max_salary' => 140000],
            ['name' => 'Principal Software Engineer', 'code' => 'PSE', 'min_salary' => 130000, 'max_salary' => 180000],
            ['name' => 'Distinguished Engineer', 'code' => 'DSE', 'min_salary' => 170000, 'max_salary' => 250000],
            ['name' => 'Software Architect', 'code' => 'SA', 'min_salary' => 120000, 'max_salary' => 200000],
            ['name' => 'Senior Software Architect', 'code' => 'SSA', 'min_salary' => 180000, 'max_salary' => 280000],
            ['name' => 'Solutions Architect', 'code' => 'SOA', 'min_salary' => 150000, 'max_salary' => 220000],
            ['name' => 'Enterprise Architect', 'code' => 'EA', 'min_salary' => 160000, 'max_salary' => 250000],
            ['name' => 'Technical Lead', 'code' => 'TL', 'min_salary' => 90000, 'max_salary' => 150000],
            ['name' => 'Engineering Manager', 'code' => 'EM', 'min_salary' => 120000, 'max_salary' => 200000],
            ['name' => 'Senior Engineering Manager', 'code' => 'SEM', 'min_salary' => 180000, 'max_salary' => 280000],
            ['name' => 'Director of Engineering', 'code' => 'DOE', 'min_salary' => 200000, 'max_salary' => 350000],
            ['name' => 'VP of Engineering', 'code' => 'VPE', 'min_salary' => 250000, 'max_salary' => 450000],
            ['name' => 'CTO', 'code' => 'CTO', 'min_salary' => 300000, 'max_salary' => 600000],
            ['name' => 'Junior Database Administrator', 'code' => 'JDBA', 'min_salary' => 30000, 'max_salary' => 50000],
            ['name' => 'Database Administrator', 'code' => 'DBA', 'min_salary' => 50000, 'max_salary' => 90000],
            ['name' => 'Senior Database Administrator', 'code' => 'SDBA', 'min_salary' => 85000, 'max_salary' => 130000],
            ['name' => 'Database Architect', 'code' => 'DBA2', 'min_salary' => 120000, 'max_salary' => 180000],
            ['name' => 'Junior Network Engineer', 'code' => 'JNE', 'min_salary' => 28000, 'max_salary' => 45000],
            ['name' => 'Network Engineer', 'code' => 'NE', 'min_salary' => 45000, 'max_salary' => 80000],
            ['name' => 'Senior Network Engineer', 'code' => 'SNE', 'min_salary' => 75000, 'max_salary' => 120000],
            ['name' => 'Network Architect', 'code' => 'NA', 'min_salary' => 110000, 'max_salary' => 170000],
            ['name' => 'Junior Security Analyst', 'code' => 'JSA', 'min_salary' => 35000, 'max_salary' => 55000],
            ['name' => 'Security Analyst', 'code' => 'SA2', 'min_salary' => 55000, 'max_salary' => 95000],
            ['name' => 'Senior Security Analyst', 'code' => 'SSA2', 'min_salary' => 90000, 'max_salary' => 140000],
            ['name' => 'Security Engineer', 'code' => 'SE2', 'min_salary' => 70000, 'max_salary' => 120000],
            ['name' => 'Senior Security Engineer', 'code' => 'SSE3', 'min_salary' => 115000, 'max_salary' => 170000],
            ['name' => 'Security Architect', 'code' => 'SCA', 'min_salary' => 140000, 'max_salary' => 220000],
            ['name' => 'Penetration Tester', 'code' => 'PT', 'min_salary' => 70000, 'max_salary' => 130000],
            ['name' => 'Senior Penetration Tester', 'code' => 'SPT', 'min_salary' => 125000, 'max_salary' => 180000],
            ['name' => 'SOC Analyst', 'code' => 'SOCA', 'min_salary' => 50000, 'max_salary' => 85000],
            ['name' => 'Senior SOC Analyst', 'code' => 'SSOCA', 'min_salary' => 80000, 'max_salary' => 120000],
            ['name' => 'Junior DevOps Engineer', 'code' => 'JDE', 'min_salary' => 40000, 'max_salary' => 65000],
            ['name' => 'DevOps Engineer', 'code' => 'DE2', 'min_salary' => 65000, 'max_salary' => 110000],
            ['name' => 'Senior DevOps Engineer', 'code' => 'SDE2', 'min_salary' => 105000, 'max_salary' => 160000],
            ['name' => 'Platform Engineer', 'code' => 'PLE2', 'min_salary' => 90000, 'max_salary' => 150000],
            ['name' => 'Senior Platform Engineer', 'code' => 'SPLE', 'min_salary' => 145000, 'max_salary' => 210000],
            ['name' => 'SRE Engineer', 'code' => 'SRE2', 'min_salary' => 85000, 'max_salary' => 140000],
            ['name' => 'Senior SRE Engineer', 'code' => 'SSRE', 'min_salary' => 135000, 'max_salary' => 200000],
            ['name' => 'Site Reliability Manager', 'code' => 'SRM', 'min_salary' => 150000, 'max_salary' => 220000],
            ['name' => 'Junior QA Engineer', 'code' => 'JQA', 'min_salary' => 25000, 'max_salary' => 45000],
            ['name' => 'QA Engineer', 'code' => 'QA', 'min_salary' => 45000, 'max_salary' => 80000],
            ['name' => 'Senior QA Engineer', 'code' => 'SQA', 'min_salary' => 75000, 'max_salary' => 120000],
            ['name' => 'QA Lead', 'code' => 'QAL', 'min_salary' => 100000, 'max_salary' => 160000],
            ['name' => 'QA Manager', 'code' => 'QAM', 'min_salary' => 120000, 'max_salary' => 180000],
            ['name' => 'Test Automation Engineer', 'code' => 'TAE', 'min_salary' => 60000, 'max_salary' => 100000],
            ['name' => 'Senior Test Automation Engineer', 'code' => 'STAE', 'min_salary' => 95000, 'max_salary' => 150000],
            ['name' => 'Performance Test Engineer', 'code' => 'PTE', 'min_salary' => 70000, 'max_salary' => 120000],
            ['name' => 'Junior Data Scientist', 'code' => 'JDS', 'min_salary' => 45000, 'max_salary' => 75000],
            ['name' => 'Data Scientist', 'code' => 'DS', 'min_salary' => 75000, 'max_salary' => 130000],
            ['name' => 'Senior Data Scientist', 'code' => 'SDS', 'min_salary' => 125000, 'max_salary' => 190000],
            ['name' => 'Lead Data Scientist', 'code' => 'LDS', 'min_salary' => 180000, 'max_salary' => 280000],
            ['name' => 'ML Engineer', 'code' => 'MLE', 'min_salary' => 90000, 'max_salary' => 150000],
            ['name' => 'Senior ML Engineer', 'code' => 'SMLE', 'min_salary' => 145000, 'max_salary' => 220000],
            ['name' => 'MLOps Engineer', 'code' => 'MLOPS', 'min_salary' => 100000, 'max_salary' => 170000],
            ['name' => 'AI Research Scientist', 'code' => 'AIRS', 'min_salary' => 120000, 'max_salary' => 200000],
            ['name' => 'Senior AI Research Scientist', 'code' => 'SAIRS', 'min_salary' => 190000, 'max_salary' => 300000],
            ['name' => 'Junior Frontend Developer', 'code' => 'JFD', 'min_salary' => 28000, 'max_salary' => 50000],
            ['name' => 'Frontend Developer', 'code' => 'FD', 'min_salary' => 50000, 'max_salary' => 90000],
            ['name' => 'Senior Frontend Developer', 'code' => 'SFD', 'min_salary' => 85000, 'max_salary' => 140000],
            ['name' => 'Frontend Architect', 'code' => 'FEA', 'min_salary' => 130000, 'max_salary' => 200000],
            ['name' => 'Junior Backend Developer', 'code' => 'JBD', 'min_salary' => 30000, 'max_salary' => 55000],
            ['name' => 'Backend Developer', 'code' => 'BD', 'min_salary' => 55000, 'max_salary' => 95000],
            ['name' => 'Senior Backend Developer', 'code' => 'SBD', 'min_salary' => 90000, 'max_salary' => 150000],
            ['name' => 'Backend Architect', 'code' => 'BKA', 'min_salary' => 140000, 'max_salary' => 220000],
            ['name' => 'Junior Full Stack Developer', 'code' => 'JFSD', 'min_salary' => 32000, 'max_salary' => 55000],
            ['name' => 'Full Stack Developer', 'code' => 'FSD', 'min_salary' => 55000, 'max_salary' => 95000],
            ['name' => 'Senior Full Stack Developer', 'code' => 'SFSD', 'min_salary' => 90000, 'max_salary' => 150000],
            ['name' => 'Junior Mobile Developer', 'code' => 'JMD', 'min_salary' => 35000, 'max_salary' => 60000],
            ['name' => 'Mobile Developer', 'code' => 'MD', 'min_salary' => 60000, 'max_salary' => 100000],
            ['name' => 'Senior Mobile Developer', 'code' => 'SMD', 'min_salary' => 95000, 'max_salary' => 160000],
            ['name' => 'React Native Developer', 'code' => 'RND', 'min_salary' => 65000, 'max_salary' => 120000],
            ['name' => 'Flutter Developer', 'code' => 'FLD', 'min_salary' => 70000, 'max_salary' => 130000],
            ['name' => 'iOS Developer', 'code' => 'IOS', 'min_salary' => 60000, 'max_salary' => 110000],
            ['name' => 'Android Developer', 'code' => 'AND', 'min_salary' => 60000, 'max_salary' => 110000],
            ['name' => 'UI Designer', 'code' => 'UID', 'min_salary' => 40000, 'max_salary' => 80000],
            ['name' => 'Senior UI Designer', 'code' => 'SUI', 'min_salary' => 75000, 'max_salary' => 120000],
            ['name' => 'UX Designer', 'code' => 'UXD', 'min_salary' => 45000, 'max_salary' => 90000],
            ['name' => 'Senior UX Designer', 'code' => 'SUXD', 'min_salary' => 85000, 'max_salary' => 140000],
            ['name' => 'Product Designer', 'code' => 'PRD2', 'min_salary' => 60000, 'max_salary' => 120000],
            ['name' => 'Senior Product Designer', 'code' => 'SPRD', 'min_salary' => 115000, 'max_salary' => 180000],
            ['name' => 'UX Researcher', 'code' => 'UXR', 'min_salary' => 50000, 'max_salary' => 95000],
            ['name' => 'Senior UX Researcher', 'code' => 'SUXR', 'min_salary' => 90000, 'max_salary' => 150000],
            ['name' => 'Junior Business Analyst', 'code' => 'JBA', 'min_salary' => 35000, 'max_salary' => 60000],
            ['name' => 'Business Analyst', 'code' => 'BA', 'min_salary' => 60000, 'max_salary' => 100000],
            ['name' => 'Senior Business Analyst', 'code' => 'SBA', 'min_salary' => 95000, 'max_salary' => 150000],
            ['name' => 'Lead Business Analyst', 'code' => 'LBA', 'min_salary' => 140000, 'max_salary' => 200000],
            ['name' => 'Junior Product Manager', 'code' => 'JPM', 'min_salary' => 55000, 'max_salary' => 90000],
            ['name' => 'Product Manager', 'code' => 'PM', 'min_salary' => 90000, 'max_salary' => 150000],
            ['name' => 'Senior Product Manager', 'code' => 'SPM', 'min_salary' => 145000, 'max_salary' => 220000],
            ['name' => 'Principal Product Manager', 'code' => 'PPM', 'min_salary' => 210000, 'max_salary' => 320000],
            ['name' => 'Associate Project Manager', 'code' => 'APM', 'min_salary' => 40000, 'max_salary' => 70000],
            ['name' => 'Project Manager', 'code' => 'PM2', 'min_salary' => 70000, 'max_salary' => 120000],
            ['name' => 'Senior Project Manager', 'code' => 'SPM2', 'min_salary' => 115000, 'max_salary' => 180000],
            ['name' => 'Scrum Master', 'code' => 'SM', 'min_salary' => 55000, 'max_salary' => 95000],
            ['name' => 'Senior Scrum Master', 'code' => 'SSM', 'min_salary' => 90000, 'max_salary' => 140000],
            ['name' => 'Agile Coach', 'code' => 'AC', 'min_salary' => 100000, 'max_salary' => 170000],
            ['name' => 'Junior Data Engineer', 'code' => 'JDE2', 'min_salary' => 50000, 'max_salary' => 85000],
            ['name' => 'Data Engineer', 'code' => 'DE', 'min_salary' => 85000, 'max_salary' => 140000],
            ['name' => 'Senior Data Engineer', 'code' => 'SDE', 'min_salary' => 135000, 'max_salary' => 200000],
            ['name' => 'Big Data Engineer', 'code' => 'BDE', 'min_salary' => 100000, 'max_salary' => 170000],
            ['name' => 'Senior Big Data Engineer', 'code' => 'SBDE', 'min_salary' => 165000, 'max_salary' => 250000],
            ['name' => 'Junior Cloud Engineer', 'code' => 'JCE', 'min_salary' => 50000, 'max_salary' => 85000],
            ['name' => 'Cloud Engineer', 'code' => 'CE', 'min_salary' => 85000, 'max_salary' => 140000],
            ['name' => 'Senior Cloud Engineer', 'code' => 'SCE', 'min_salary' => 135000, 'max_salary' => 200000],
            ['name' => 'Cloud Architect', 'code' => 'CLA', 'min_salary' => 160000, 'max_salary' => 250000],
            ['name' => 'AWS Solutions Architect', 'code' => 'AWSSA', 'min_salary' => 130000, 'max_salary' => 200000],
            ['name' => 'Azure Solutions Architect', 'code' => 'AZSA', 'min_salary' => 135000, 'max_salary' => 210000],
            ['name' => 'GCP Solutions Architect', 'code' => 'GCPSA', 'min_salary' => 140000, 'max_salary' => 220000],
            ['name' => 'Junior Systems Administrator', 'code' => 'JSA2', 'min_salary' => 30000, 'max_salary' => 50000],
            ['name' => 'Systems Administrator', 'code' => 'SYSA', 'min_salary' => 50000, 'max_salary' => 85000],
            ['name' => 'Senior Systems Administrator', 'code' => 'SSYSA', 'min_salary' => 80000, 'max_salary' => 130000],
            ['name' => 'Systems Engineer', 'code' => 'SE3', 'min_salary' => 55000, 'max_salary' => 95000],
            ['name' => 'Senior Systems Engineer', 'code' => 'SSE4', 'min_salary' => 90000, 'max_salary' => 145000],
            ['name' => 'Infrastructure Engineer', 'code' => 'IE', 'min_salary' => 70000, 'max_salary' => 120000],
            ['name' => 'Senior Infrastructure Engineer', 'code' => 'SIE', 'min_salary' => 115000, 'max_salary' => 180000],
        ];

        return array_map(fn($d) => array_merge($d, ['created_at' => now(), 'updated_at' => now()]), $designations);
    }
}
