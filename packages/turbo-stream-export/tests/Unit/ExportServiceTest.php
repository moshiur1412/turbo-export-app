<?php

declare(strict_types=1);

namespace TurboStreamExport\Tests\Unit;

use TurboStreamExport\Services\ExportService;
use TurboStreamExport\Contracts\Drivers\CsvExportDriver;
use TurboStreamExport\Contracts\ExportDriverInterface;
use TurboStreamExport\Tests\TestCase;
use Mockery;

class ExportServiceTest extends TestCase
{
    private ExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new ExportService('local', [
            new CsvExportDriver(),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_service_can_register_driver(): void
    {
        $mockDriver = Mockery::mock(CsvExportDriver::class);
        $mockDriver->shouldReceive('getFormat')->andReturn('csv');
        
        $service = new ExportService();
        $service->registerDriver($mockDriver);
        
        $this->assertTrue($service->hasDriver('csv'));
    }

    public function test_service_returns_correct_driver(): void
    {
        $driver = $this->service->getDriver('csv');
        
        $this->assertInstanceOf(ExportDriverInterface::class, $driver);
        $this->assertEquals('csv', $driver->getFormat());
    }

    public function test_service_throws_exception_for_unregistered_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Driver for format [xlsx] not registered.");
        
        $this->service->getDriver('xlsx');
    }

    public function test_service_has_driver_returns_correctly(): void
    {
        $this->assertTrue($this->service->hasDriver('csv'));
        $this->assertFalse($this->service->hasDriver('xlsx'));
        $this->assertFalse($this->service->hasDriver('pdf'));
    }

    public function test_service_returns_available_formats(): void
    {
        $formats = $this->service->getAvailableFormats();
        
        $this->assertIsArray($formats);
        $this->assertContains('csv', $formats);
    }
}
