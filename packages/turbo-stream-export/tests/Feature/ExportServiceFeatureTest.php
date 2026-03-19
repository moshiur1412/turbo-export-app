<?php

declare(strict_types=1);

namespace TurboStreamExport\Tests\Feature;

use TurboStreamExport\Services\ExportService;
use TurboStreamExport\Contracts\Drivers\CsvExportDriver;
use TurboStreamExport\Tests\TestCase;

class ExportServiceFeatureTest extends TestCase
{
    public function test_export_service_integration_with_csv_driver(): void
    {
        $driver = new CsvExportDriver();
        $service = new ExportService('local', [$driver]);

        $this->assertTrue($service->hasDriver('csv'));
        $this->assertEquals('csv', $service->getDriver('csv')->getFormat());
        $this->assertContains('csv', $service->getAvailableFormats());
    }

    public function test_multiple_drivers_can_be_registered(): void
    {
        $csvDriver = new CsvExportDriver();
        $sqlDriver = new \TurboStreamExport\Contracts\Drivers\SqlExportDriver();
        
        $service = new ExportService('local', [$csvDriver, $sqlDriver]);

        $this->assertTrue($service->hasDriver('csv'));
        $this->assertTrue($service->hasDriver('sql'));
        $this->assertCount(2, $service->getAvailableFormats());
    }

    public function test_filter_summary_generation(): void
    {
        $service = new ExportService('local', [new CsvExportDriver()]);
        
        $filters = [
            ['status', '=', 'active'],
            ['category_id', '=', '5'],
        ];
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildFilterSummary');
        $method->setAccessible(true);
        
        $summary = $method->invoke($service, $filters);
        
        $this->assertStringContainsString('status', $summary);
        $this->assertStringContainsString('active', $summary);
        $this->assertStringContainsString('category_id', $summary);
    }

    public function test_filename_with_filters_generation(): void
    {
        $service = new ExportService('local', [new CsvExportDriver()]);
        
        $filters = [
            ['status', '=', 'completed'],
            ['date', '>=', '2026-01-01'],
        ];
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildFilenameWithFilters');
        $method->setAccessible(true);
        
        $filename = $method->invoke($service, 'report', $filters, 'csv');
        
        $this->assertStringStartsWith('report_filtered_', $filename);
        $this->assertStringContainsString('status', $filename);
        $this->assertStringContainsString('completed', $filename);
    }

    public function test_filename_without_filters_remains_unchanged(): void
    {
        $service = new ExportService('local', [new CsvExportDriver()]);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildFilenameWithFilters');
        $method->setAccessible(true);
        
        $filename = $method->invoke($service, 'simple_export', [], 'csv');
        
        $this->assertEquals('simple_export', $filename);
    }
}
