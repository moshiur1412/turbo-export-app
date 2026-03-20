<?php

namespace Database\Seeders;

use App\Models\LeaveBalance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveBalanceSeeder extends Seeder
{
    private int $chunkSize = 50000;

    public function run(): void
    {
        $this->command->info('Generating leave balance records...');
        
        $totalEmployees = User::count();
        $years = range(Carbon::now()->year - 4, Carbon::now()->year);
        $monthsPerYear = 12;
        $totalRecords = $totalEmployees * count($years) * $monthsPerYear;
        
        $this->command->info("Creating {$totalRecords} leave balance records...");
        
        $startTime = microtime(true);
        
        $this->disableKeys('leave_balances');
        
        $batch = [];
        $processed = 0;
        
        foreach ($years as $year) {
            for ($month = 1; $month <= $monthsPerYear; $month++) {
                for ($userId = 1; $userId <= $totalEmployees; $userId++) {
                    $batch[] = [
                        'user_id' => $userId,
                        'year' => $year,
                        'month' => $month,
                        'casual_leave' => 2.0,
                        'sick_leave' => 1.0,
                        'annual_leave' => 2.0,
                        'used_casual' => rand(0, 2),
                        'used_sick' => rand(0, 1),
                        'used_annual' => rand(0, 2),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    if (count($batch) >= $this->chunkSize) {
                        LeaveBalance::insert($batch);
                        $batch = [];
                        
                        $processed += $this->chunkSize;
                        $progress = round($processed / $totalRecords * 100);
                        $this->command->info("Progress: {$progress}% ({$processed}/{$totalRecords})");
                    }
                }
            }
            
            $this->command->info("Year {$year} completed");
        }
        
        if (!empty($batch)) {
            LeaveBalance::insert($batch);
        }
        
        $this->enableKeys('leave_balances');
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $this->command->info('Leave balance records: ' . number_format(LeaveBalance::count()));
        $this->command->info('Completed in ' . $duration . ' seconds');
    }

    private function disableKeys(string $table): void
    {
        DB::statement("ALTER TABLE {$table} DISABLE KEYS");
    }

    private function enableKeys(string $table): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE KEYS");
    }
}
