<?php

declare(strict_types=1);

namespace TurboStreamExport\Tests\LargeData;

use TurboStreamExport\Contracts\Drivers\CsvExportDriver;
use TurboStreamExport\Contracts\Drivers\SqlExportDriver;
use TurboStreamExport\Tests\TestCase;

class LargeDataExportTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/turbo_export_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupDirectory($this->tempDir);
        parent::tearDown();
    }

    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->cleanupDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_csv_driver_handles_large_batch(): void
    {
        $driver = new CsvExportDriver();
        $filePath = $this->tempDir . '/large_export.csv';
        $handle = fopen($filePath, 'w');
        
        $columns = ['id', 'name', 'email', 'status', 'created_at'];
        $driver->writeHeader($columns, $handle);
        
        $recordCount = 10000;
        $startTime = microtime(true);
        
        for ($i = 1; $i <= $recordCount; $i++) {
            $driver->writeRow([
                $i,
                "User Name $i",
                "user$i@example.com",
                $i % 2 === 0 ? 'active' : 'inactive',
                '2026-01-15 10:30:00',
            ], $handle);
        }
        
        $driver->finalize($handle, $filePath);
        
        $duration = microtime(true) - $startTime;
        
        $this->assertFileExists($filePath);
        $this->assertLessThan(5, $duration, 'Large batch export took too long');
        
        $fileSize = filesize($filePath);
        $this->assertGreaterThan(500000, $fileSize, 'File size seems too small');
        
        $lineCount = count(file($filePath));
        $this->assertEquals($recordCount + 1, $lineCount, 'Line count mismatch (including header)');
    }

    public function test_sql_driver_handles_large_batch(): void
    {
        $driver = new SqlExportDriver();
        $driver->setTableName('test_large_table');
        $driver->setBatchSize(1000);
        
        $filePath = $this->tempDir . '/large_export.sql';
        $handle = fopen($filePath, 'w');
        
        $columns = ['id', 'name', 'email', 'status'];
        $driver->writeHeader($columns, $handle);
        
        $recordCount = 5000;
        $startTime = microtime(true);
        
        for ($i = 1; $i <= $recordCount; $i++) {
            $driver->writeRow([
                $i,
                "Large User $i",
                "large_user_$i@example.com",
                $i % 3 === 0 ? 'active' : 'pending',
            ], $handle);
        }
        
        $driver->finalize($handle, $filePath);
        
        $duration = microtime(true) - $startTime;
        
        $this->assertFileExists($filePath);
        $this->assertLessThan(10, $duration, 'SQL export took too long');
        
        $content = file_get_contents($filePath);
        $this->assertStringContainsString('CREATE TABLE', $content);
        $this->assertStringContainsString('INSERT INTO', $content);
        $this->assertStringContainsString('COMMIT', $content);
    }

    public function test_csv_memory_efficiency(): void
    {
        $driver = new CsvExportDriver();
        $filePath = $this->tempDir . '/memory_test.csv';
        $handle = fopen($filePath, 'w');
        
        $initialMemory = memory_get_usage(true);
        
        $columns = ['id', 'data'];
        $driver->writeHeader($columns, $handle);
        
        $iterations = 1000;
        for ($i = 0; $i < $iterations; $i++) {
            $driver->writeRow([$i, str_repeat('x', 100)]);
            
            if ($i % 100 === 0) {
                gc_collect_cycles();
            }
        }
        
        $driver->finalize($handle, $filePath);
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory increase too high');
    }

    public function test_chunk_size_determination(): void
    {
        $service = new \TurboStreamExport\Services\ExportService('local', [new CsvExportDriver()]);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('determineChunkSize');
        $method->setAccessible(true);
        
        $smallChunk = $method->invoke($service, 10000);
        $this->assertEquals(5000, $smallChunk);
        
        $mediumChunk = $method->invoke($service, 10000000);
        $this->assertEquals(10000, $mediumChunk);
        
        $largeChunk = $method->invoke($service, 100000001);
        $this->assertEquals(20000, $largeChunk);
    }

    public function test_special_characters_handling(): void
    {
        $driver = new CsvExportDriver();
        $filePath = $this->tempDir . '/special_chars.csv';
        $handle = fopen($filePath, 'w');
        
        $columns = ['name', 'comment'];
        $driver->writeHeader($columns, $handle);
        
        $specialData = [
            "John O'Brien",
            "Line1\nLine2",
            "Column1,Column2",
            'Value with "quotes"',
            "Tab\there",
        ];
        
        $driver->writeRow($specialData, $handle);
        $driver->finalize($handle, $filePath);
        
        $this->assertFileExists($filePath);
        
        $content = file_get_contents($filePath);
        $this->assertStringContainsString("John O'Brien", $content);
        $this->assertStringContainsString('quotes', $content);
    }

    public function test_null_value_handling(): void
    {
        $driver = new CsvExportDriver();
        $filePath = $this->tempDir . '/null_test.csv';
        $handle = fopen($filePath, 'w');
        
        $driver->writeHeader(['id', 'name', 'nullable_field'], $handle);
        $driver->writeRow([1, 'Test', null], $handle);
        $driver->writeRow([2, null, 'value'], $handle);
        $driver->finalize($handle, $filePath);
        
        $this->assertFileExists($filePath);
        
        $lines = file($filePath);
        $this->assertCount(3, $lines);
    }

    public function test_numeric_precision(): void
    {
        $driver = new CsvExportDriver();
        $filePath = $this->tempDir . '/numeric_test.csv';
        $handle = fopen($filePath, 'w');
        
        $driver->writeHeader(['id', 'amount', 'rate'], $handle);
        $driver->writeRow([1, 123456789.123456, 0.000001], $handle);
        $driver->writeRow([2, PHP_INT_MAX, PHP_INT_MIN], $handle);
        $driver->finalize($handle, $filePath);
        
        $this->assertFileExists($filePath);
    }
}
