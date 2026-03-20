<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FreshSeed extends Command
{
    protected $signature = 'db:fresh-seed 
                            {--scale=medium : Data scale (small|medium|large|xlarge|xxlarge)}
                            {--seed : Run the seeder}
                            {--seeder : The seeder class to use (defaults to GeneralDataSeeder)}
                            {--chunk=10000 : Chunk size for batch inserts}
                            {--skip-attendance : Skip attendance data}
                            {--skip-leaves : Skip leave data}
                            {--skip-balances : Skip leave balance data}
                            {--force : Force the operation without confirmation}';

    protected $description = 'Drop all tables and re-run migrations with optional data seeding';

    private array $scaleDescriptions = [
        'small' => '~200K records (1K employees, 3 months attendance)',
        'medium' => '~2M records (10K employees, 6 months attendance)',
        'large' => '~30M records (50K employees, 24 months attendance)',
        'xlarge' => '~150M records (100K employees, 60 months attendance)',
        'xxlarge' => '~300M+ records (200K employees, 60 months attendance)',
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '512M');
        
        $scale = $this->option('scale');
        $runSeed = $this->option('seed');
        $seederOption = $this->option('seeder');
        $seederClass = $seederOption 
            ? 'Database\\Seeders\\' . $seederOption 
            : 'Database\\Seeders\\GeneralDataSeeder';
        $chunkSize = (int) $this->option('chunk');

        if (!in_array($scale, array_keys($this->scaleDescriptions))) {
            $this->error("Invalid scale. Available: " . implode(', ', array_keys($this->scaleDescriptions)));
            return self::FAILURE;
        }

        $this->info('========================================');
        $this->info('       DATABASE FRESH WITH SEEDING');
        $this->info('========================================');
        $this->info("Scale: {$scale}");
        $this->info("Description: {$this->scaleDescriptions[$scale]}");
        $this->info("Seeder: {$seederClass}");
        $this->info("Chunk size: " . number_format($chunkSize));
        
        if ($this->option('skip-attendance')) {
            $this->warn("  - Skipping attendance data");
        }
        if ($this->option('skip-leaves')) {
            $this->warn("  - Skipping leave data");
        }
        if ($this->option('skip-balances')) {
            $this->warn("  - Skipping leave balance data");
        }
        $this->info('========================================');

        if (!$this->option('force') && !$this->confirm('This will drop all existing tables. Continue?', false)) {
            $this->info('Operation cancelled.');
            return self::FAILURE;
        }

        $startTime = microtime(true);

        $this->info('Step 1: Refreshing migrations...');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->info(Artisan::output());

        if ($runSeed) {
            $this->info('Step 2: Seeding data...');
            $this->seedData($seederClass, $scale, $chunkSize);
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime);

        $this->newLine();
        $this->info('========================================');
        $this->info('🎉       OPERATION COMPLETE!');
        $this->info('========================================');
        $this->info("Total time: " . $this->formatDuration($duration));

        $this->printTableCounts();
        
        $this->newLine();
        $this->info('Done! All data has been generated successfully.');

        return self::SUCCESS;
    }

    private function seedData(string $seederClass, string $scale, int $chunkSize): void
    {
        $skip = [];
        if ($this->option('skip-attendance')) {
            $skip[] = 'attendance';
        }
        if ($this->option('skip-leaves')) {
            $skip[] = 'leaves';
        }
        if ($this->option('skip-balances')) {
            $skip[] = 'balances';
        }

        $config = [
            'scale' => $scale,
            'chunk_size' => $chunkSize,
            'skip' => $skip,
        ];

        $seeder = new $seederClass();
        
        if (method_exists($seeder, 'configure')) {
            $seeder->configure($config);
        }

        $seeder->run();
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        }
        
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        
        if ($minutes < 60) {
            return "{$minutes}m {$seconds}s";
        }
        
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;
        
        return "{$hours}h {$minutes}m {$seconds}s";
    }

    private function printTableCounts(): void
    {
        $tables = [
            'users' => DB::table('users')->count(),
            'salaries' => DB::table('salaries')->count(),
            'departments' => DB::table('departments')->count(),
            'designations' => DB::table('designations')->count(),
            'attendances' => DB::table('attendances')->count(),
            'leaves' => DB::table('leaves')->count(),
            'leave_balances' => DB::table('leave_balances')->count(),
        ];

        $this->table(
            ['Table', 'Records'],
            collect($tables)->map(fn($count, $table) => [$table, number_format($count)])->toArray()
        );
    }
}
