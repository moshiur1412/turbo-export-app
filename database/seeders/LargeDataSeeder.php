<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LargeDataSeeder extends Seeder
{
    private array $designationIds = [];
    private array $departmentIds = [];
    private array $salaryIds = [];
    private string $hashedPassword;

    public function run(): void
    {
        ini_set('memory_limit', '512M');
        
        $this->command->info('Starting 100M+ data seeding...');
        
        $this->seedDesignations(50);
        $this->seedDepartments(30);
        $this->seedSalaries(100);
        
        $this->designationIds = DB::table('designations')->pluck('id')->toArray();
        $this->departmentIds = DB::table('departments')->pluck('id')->toArray();
        $this->salaryIds = DB::table('salaries')->pluck('id')->toArray();
        
        $this->hashedPassword = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        
        $this->seedUsers(100000, 2);
        $this->seedAttendances(1000000);
        
        $this->command->info('Seeding completed!');
    }

    private function seedDesignations(int $count): void
    {
        $this->command->info("Seeding {$count} designations...");
        
        $designations = [];
        $titles = ['Manager', 'Developer', 'Designer', 'Engineer', 'Analyst', 'Director', 'Coordinator', 'Specialist', 'Administrator', 'Supervisor'];
        
        for ($i = 1; $i <= $count; $i++) {
            $title = $titles[array_rand($titles)];
            $level = rand(1, 10);
            
            $designations[] = [
                'name' => "{$title} Level {$level}",
                'code' => strtoupper(Str::random(6)),
                'description' => "{$title} position at level {$level}",
                'min_salary' => rand(30000, 50000),
                'max_salary' => rand(80000, 150000),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            if (count($designations) >= 1000) {
                DB::table('designations')->insert($designations);
                $designations = [];
            }
        }
        
        if (!empty($designations)) {
            DB::table('designations')->insert($designations);
        }
        
        $this->command->info("Designations seeded: {$count}");
    }

    private function seedDepartments(int $count): void
    {
        $this->command->info("Seeding {$count} departments...");
        
        $departments = [];
        $names = ['IT', 'HR', 'Finance', 'Marketing', 'Sales', 'Operations', 'Research', 'Legal', 'Admin', 'Engineering'];
        
        for ($i = 1; $i <= $count; $i++) {
            $name = $names[array_rand($names)] . ' ' . rand(1, 5);
            
            $departments[] = [
                'name' => $name,
                'code' => strtoupper(Str::random(5)),
                'location' => 'Building ' . rand(1, 10),
                'head_id' => null,
                'description' => "{$name} Department",
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            if (count($departments) >= 1000) {
                DB::table('departments')->insert($departments);
                $departments = [];
            }
        }
        
        if (!empty($departments)) {
            DB::table('departments')->insert($departments);
        }
        
        $this->command->info("Departments seeded: {$count}");
    }

    private function seedSalaries(int $count): void
    {
        $this->command->info("Seeding {$count} salary records...");
        
        $salaries = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $basic = rand(20000, 100000);
            $houseRent = (int)($basic * 0.2);
            $medical = rand(1000, 5000);
            $transport = rand(1000, 3000);
            $special = rand(2000, 10000);
            $provident = (int)($basic * 0.05);
            $tax = rand(500, 5000);
            
            $gross = $basic + $houseRent + $medical + $transport + $special;
            $net = $gross - $provident - $tax;
            
            $salaries[] = [
                'basic_salary' => $basic,
                'house_rent' => $houseRent,
                'medical_allowance' => $medical,
                'transport_allowance' => $transport,
                'special_allowance' => $special,
                'provident_fund' => $provident,
                'tax' => $tax,
                'gross_salary' => $gross,
                'net_salary' => $net,
                'effective_date' => now()->subDays(rand(1, 365)),
                'end_date' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            if (count($salaries) >= 1000) {
                DB::table('salaries')->insert($salaries);
                $salaries = [];
            }
        }
        
        if (!empty($salaries)) {
            DB::table('salaries')->insert($salaries);
        }
        
        $this->command->info("Salaries seeded: {$count}");
    }

    private function seedUsers(int $count, int $startNumber = 1): void
    {
        $this->command->info("Seeding {$count} users...");
        
        $batchSize = 5000;
        $totalBatches = ceil($count / $batchSize);
        
        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $users = [];
            $start = ($batch * $batchSize) + $startNumber;
            $end = min((($batch + 1) * $batchSize) + ($startNumber - 1), $count + ($startNumber - 1));
            
            for ($i = $start; $i <= $end; $i++) {
                $users[] = [
                    'employee_id' => 'EMP' . str_pad($i, 8, '0', STR_PAD_LEFT),
                    'name' => 'Employee ' . $i,
                    'email' => 'employee' . $i . '@example.com',
                    'email_verified_at' => now(),
                    'password' => $this->hashedPassword,
                    'designation_id' => $this->designationIds[array_rand($this->designationIds)],
                    'department_id' => $this->departmentIds[array_rand($this->departmentIds)],
                    'salary_id' => $this->salaryIds[array_rand($this->salaryIds)],
                    'join_date' => now()->subDays(rand(1, 1825)),
                    'status' => ['active', 'active', 'active', 'inactive', 'on_leave'][rand(0, 4)],
                    'remember_token' => Str::random(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            DB::table('users')->insert($users);
            $this->command->info("Users: {$end}/" . ($count + $startNumber - 1) . " (" . round((($end - $startNumber + 1) / $count) * 100) . "%)");
            
            gc_collect_cycles();
        }
        
        $this->command->info("Users seeded: {$count}");
    }

    private function seedAttendances(int $count): void
    {
        $this->command->info("Seeding {$count} attendance records...");
        
        $batchSize = 50000;
        $totalBatches = ceil($count / $batchSize);
        
        $statuses = ['present', 'present', 'present', 'present', 'absent', 'late', 'leave', 'holiday'];
        
        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $attendances = [];
            $start = $batch * $batchSize + 1;
            $end = min(($batch + 1) * $batchSize, $count);
            
            for ($i = $start; $i <= $end; $i++) {
                $userId = ($i % 100000) + 1;
                $daysAgo = rand(0, 365);
                $date = now()->subDays($daysAgo)->format('Y-m-d');
                $status = $statuses[array_rand($statuses)];
                
                $checkIn = in_array($status, ['present', 'late']) 
                    ? sprintf('%02d:%02d:00', rand(8, 10), rand(0, 59)) 
                    : null;
                $checkOut = $checkIn ? sprintf('%02d:%02d:00', rand(17, 20), rand(0, 59)) : null;
                
                $attendances[] = [
                    'user_id' => $userId,
                    'attendance_date' => $date,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'worked_hours' => $checkIn && $checkOut ? rand(4, 12) : 0,
                    'status' => $status,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                if (count($attendances) >= 10000) {
                    DB::table('attendances')->insert($attendances);
                    $attendances = [];
                    $this->command->info("Progress: {$i}/{$count} (" . round(($i / $count) * 100) . "%)");
                }
            }
            
            if (!empty($attendances)) {
                DB::table('attendances')->insert($attendances);
            }
            
            $this->command->info("Batch complete: {$end}/{$count} (" . round(($end / $count) * 100) . "%)");
            gc_collect_cycles();
        }
        
        $this->command->info("Attendances seeded: {$count}");
    }
}