<?php

namespace Database\Seeders;

use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveSeeder extends Seeder
{
    private int $chunkSize = 10000;
    private array $leaveTypes = ['casual', 'sick', 'annual', 'unpaid', 'maternity', 'paternity'];
    private array $typeWeights = [30, 20, 25, 15, 5, 5];
    private array $statuses = ['approved', 'approved', 'approved', 'approved', 'pending', 'pending', 'rejected', 'cancelled'];

    public function run(): void
    {
        $this->command->info('Generating leave records...');
        
        $totalEmployees = User::count();
        $leavesPerEmployee = 10;
        $totalRecords = $totalEmployees * $leavesPerEmployee;
        
        $this->command->info("Creating {$totalRecords} leave records...");
        
        $startTime = microtime(true);
        
        $this->disableKeys('leaves');
        
        $batch = [];
        
        for ($userId = 1; $userId <= $totalEmployees; $userId++) {
            for ($i = 0; $i < $leavesPerEmployee; $i++) {
                $batch[] = $this->generateLeaveRecord($userId);
                
                if (count($batch) >= $this->chunkSize) {
                    Leave::insert($batch);
                    $batch = [];
                    
                    $progress = round($userId / $totalEmployees * 100);
                    $this->command->info("Progress: {$progress}% ({$userId}/{$totalEmployees})");
                }
            }
        }
        
        if (!empty($batch)) {
            Leave::insert($batch);
        }
        
        $this->enableKeys('leaves');
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $this->command->info('Leave records: ' . number_format(Leave::count()));
        $this->command->info('Completed in ' . $duration . ' seconds');
    }

    private function generateLeaveRecord(int $userId): array
    {
        $startDate = Carbon::now()->subMonths(rand(0, 59))->startOfMonth()->addDays(rand(0, 25));
        $days = rand(1, 10);
        $status = $this->getWeightedStatus();
        $leaveType = $this->getWeightedLeaveType();
        
        return [
            'user_id' => $userId,
            'leave_type' => $leaveType,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDays($days - 1)->format('Y-m-d'),
            'days' => $days,
            'reason' => $this->getRandomReason($leaveType),
            'status' => $status,
            'is_paid' => $status === 'approved' ? ($leaveType !== 'unpaid' && rand(1, 100) <= 70) : false,
            'approved_by' => $status === 'approved' ? rand(1, min(10, User::count())) : null,
            'approved_at' => $status === 'approved' ? now()->subDays(rand(1, 5)) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function getWeightedStatus(): string
    {
        return $this->statuses[array_rand($this->statuses)];
    }

    private function getWeightedLeaveType(): string
    {
        $rand = rand(1, 100);
        $cumulative = 0;
        
        foreach ($this->leaveTypes as $index => $type) {
            $cumulative += $this->typeWeights[$index];
            if ($rand <= $cumulative) {
                return $type;
            }
        }
        
        return 'unpaid';
    }

    private function getRandomReason(string $type): string
    {
        $reasons = [
            'casual' => ['Family event', 'Personal work', 'Home repair', 'Social commitment'],
            'sick' => ['Medical appointment', 'Not feeling well', 'Dental emergency', 'Follow-up checkup'],
            'annual' => ['Vacation trip', 'Family gathering', 'Home visit', 'Rest and recreation'],
            'unpaid' => ['Emergency work', 'Personal crisis', 'Extended travel', 'Special circumstances'],
            'maternity' => ['Childbirth recovery', 'Newborn care'],
            'paternity' => ['Newborn care', 'Family support'],
        ];
        
        return $reasons[$type][array_rand($reasons[$type])];
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
