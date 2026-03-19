<?php

declare(strict_types=1);

namespace TurboStreamExport\Tests\Unit;

use TurboStreamExport\Contracts\Drivers\CsvExportDriver;
use TurboStreamExport\Contracts\Drivers\XlsxExportDriver;
use TurboStreamExport\Contracts\Drivers\SqlExportDriver;
use TurboStreamExport\Contracts\Drivers\PdfExportDriver;
use TurboStreamExport\Contracts\Drivers\DocxExportDriver;
use TurboStreamExport\Tests\TestCase;

class ExportDriverTest extends TestCase
{
    public function test_csv_driver_returns_correct_format(): void
    {
        $driver = new CsvExportDriver();
        
        $this->assertEquals('csv', $driver->getFormat());
        $this->assertEquals('text/csv', $driver->getContentType());
        $this->assertEquals('csv', $driver->getFileExtension());
    }

    public function test_xlsx_driver_returns_correct_format(): void
    {
        $driver = new XlsxExportDriver();
        
        $this->assertEquals('xlsx', $driver->getFormat());
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $driver->getContentType()
        );
        $this->assertEquals('xlsx', $driver->getFileExtension());
    }

    public function test_sql_driver_returns_correct_format(): void
    {
        $driver = new SqlExportDriver();
        
        $this->assertEquals('sql', $driver->getFormat());
        $this->assertEquals('application/sql', $driver->getContentType());
        $this->assertEquals('sql', $driver->getFileExtension());
    }

    public function test_pdf_driver_returns_correct_format(): void
    {
        $driver = new PdfExportDriver();
        
        $this->assertEquals('pdf', $driver->getFormat());
        $this->assertEquals('application/pdf', $driver->getContentType());
        $this->assertEquals('pdf', $driver->getFileExtension());
    }

    public function test_docx_driver_returns_correct_format(): void
    {
        $driver = new DocxExportDriver();
        
        $this->assertEquals('docx', $driver->getFormat());
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $driver->getContentType()
        );
        $this->assertEquals('docx', $driver->getFileExtension());
    }

    public function test_csv_driver_writes_header_correctly(): void
    {
        $driver = new CsvExportDriver();
        $handle = fopen('php://memory', 'r+');
        
        $columns = ['id', 'name', 'email'];
        $driver->writeHeader($columns, $handle);
        
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        
        $this->assertStringContainsString('id', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('email', $content);
    }

    public function test_csv_driver_writes_row_correctly(): void
    {
        $driver = new CsvExportDriver();
        $handle = fopen('php://memory', 'r+');
        
        $driver->writeHeader(['id', 'name', 'email'], $handle);
        $driver->writeRow([1, 'John Doe', 'john@example.com'], $handle);
        
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        
        $this->assertStringContainsString('John Doe', $content);
        $this->assertStringContainsString('john@example.com', $content);
    }

    public function test_sql_driver_generates_valid_create_statement(): void
    {
        $driver = new SqlExportDriver();
        $driver->setTableName('test_users');
        
        $handle = fopen('php://memory', 'r+');
        $driver->writeHeader(['id', 'name', 'email'], $handle);
        
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        
        $this->assertStringContainsString('CREATE TABLE', $content);
        $this->assertStringContainsString('test_users', $content);
    }

    public function test_sql_driver_generates_valid_insert_statement(): void
    {
        $driver = new SqlExportDriver();
        $driver->setTableName('users');
        $driver->setBatchSize(100);
        
        $handle = fopen('php://memory', 'r+');
        $driver->writeHeader(['id', 'name'], $handle);
        $driver->writeRow([1, "O'Brien"], $handle);
        $driver->finalize($handle, sys_get_temp_dir() . '/test_export.sql');
        
        $this->assertFileExists(sys_get_temp_dir() . '/test_export.sql');
        
        $content = file_get_contents(sys_get_temp_dir() . '/test_export.sql');
        $this->assertStringContainsString("INSERT INTO", $content);
        $this->assertStringContainsString("O\\'Brien", $content);
        
        @unlink(sys_get_temp_dir() . '/test_export.sql');
    }

    public function test_xlsx_driver_header_formatting(): void
    {
        $driver = new XlsxExportDriver();
        $sheet = $driver->getSpreadsheet()->getActiveSheet();
        
        $driver->writeHeader(['user_id', 'full_name', 'created_at'], $sheet);
        
        $this->assertEquals('User Id', $sheet->getCell('A1')->getValue());
        $this->assertEquals('Full Name', $sheet->getCell('B1')->getValue());
        $this->assertEquals('Created At', $sheet->getCell('C1')->getValue());
    }
}
