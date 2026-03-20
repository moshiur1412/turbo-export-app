<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OptimizedAttendanceSeeder extends Seeder
{
    private int $chunkSize = 10000;
    private int $totalEmployees;
    private int $daysPerMonth = 26;
    private int $months = 60;

    public function run(): void
    {
        $this->totalEmployees = User::count();
        $totalRecords = $this->totalEmployees * $this->months * $this->daysPerMonth;
        
        $this->command->info("Generating {$this->totalEmployees} employees × {$this->months} months × {$this->daysPerMonth} days");
        $this->command->info("Total attendance records: " . number_format($totalRecords));
        
        $startTime = microtime(true);
        
        $this->disableKeys('attendances');
        $this->generateAttendanceData();
        $this->enableKeys('attendances');
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $this->command->info('');
        $this->command->info('Completed in ' . $this->formatDuration($duration));
        $this->command->info('Records: ' . number_format(Attendance::count()));
        $this->command->info('Rate: ' . number_format(floor(Attendance::count() / max($duration, 1))) . ' records/sec');
    }

    private function generateAttendanceData(): void
    {
        $bar = $this->command->getOutput()->createProgressBar();
        $bar->setMaxSteps(100);
        $bar->start();
        
        $totalBatches = ceil($this->totalEmployees / $this->chunkSize);
        $insertedCount = 0;
        
        for ($offset = 0; $offset < $this->totalEmployees; $offset += $this->chunkSize) {
            $batchUsers = range($offset + 1, min($offset + $this->chunkSize, $this->totalEmployees));
            $records = $this->generateBatchRecords($batchUsers);
            
            $this->insertBatch($records);
            $insertedCount += count($records);
            
            $progress = ceil(($offset + $this->chunkSize) / $this->totalEmployees * 100);
            $bar->setProgress($progress);
        }
        
        $bar->finish();
    }

    private function generateBatchRecords(array $userIds): array
    {
        $records = [];
        
        foreach ($userIds as $userId) {
            $records = array_merge($records, $this->generateUserRecords($userId));
        }
        
        return $records;
    }

    private function generateUserRecords(int $userId): array
    {
        $records = [];
        
        for ($month = $this->months; $month >= 1; $month--) {
            $monthStart = Carbon::now()->subMonths($month)->startOfMonth();
            
            for ($day = 0; $day < $this->daysPerMonth; $day++) {
                $date = $monthStart->copy()->addDays($day);
                
                if ($date->isWeekend()) {
                    continue;
                }
                
                $records[] = $this->generateAttendanceRecord($userId, $date);
            }
        }
        
        return $records;
    }

    private function generateAttendanceRecord(int $userId, Carbon $date): array
    {
        $rand = rand(1, 100);
        
        if ($rand <= 75) {
            $status = 'present';
            $workedHours = rand(7, 10) + (rand(0, 59) / 60);
            $checkIn = sprintf('%02d:%02d', rand(8, 10), rand(0, 59));
            $checkOut = sprintf('%02d:%02d', rand(17, 20), rand(0, 59));
        } elseif ($rand <= 90) {
            $status = 'present';
            $workedHours = rand(4, 6) + (rand(0, 59) / 60);
            $checkIn = sprintf('%02d:%02d', rand(9, 12), rand(0, 59));
            $checkOut = sprintf('%02d:%02d', rand(14, 17), rand(0, 59));
        } elseif ($rand <= 95) {
            $status = 'late';
            $workedHours = rand(7, 9) + (rand(0, 59) / 60);
            $checkIn = sprintf('%02d:%02d', rand(10, 12), rand(0, 59));
            $checkOut = sprintf('%02d:%02d', rand(17, 20), rand(0, 59));
        } elseif ($rand <= 98) {
            $status = 'absent';
            $workedHours = 0;
            $checkIn = null;
            $checkOut = null;
        } else {
            $status = 'leave';
            $workedHours = 0;
            $checkIn = null;
            $checkOut = null;
        }
        
        return [
            'user_id' => $userId,
            'attendance_date' => $date->format('Y-m-d'),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'worked_hours' => $workedHours,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function insertBatch(array $records): void
    {
        foreach (array_chunk($records, 1000) as $chunk) {
            Attendance::insert($chunk);
        }
    }

    private function disableKeys(string $table): void
    {
        DB::statement("ALTER TABLE {$table} DISABLE KEYS");
    }

    private function enableKeys(string $table): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE KEYS");
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes < 60) {
            return $minutes . ' minutes ' . round($remainingSeconds) . ' seconds';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $hours . ' hours ' . $remainingMinutes . ' minutes';
    }
}
