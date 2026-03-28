# TurboStream Export Engine

A high-performance Laravel package for exporting 100M+ records using chunked queries with async processing via Redis queues. Supports CSV, Excel, PDF, DOCX, and SQL formats.

[![Latest Version](https://img.shields.io/packagist/v/turbostream/export-engine.svg)](https://packagist.org/packages/turbostream/export-engine)
[![Total Downloads](https://img.shields.io/packagist/dt/turbostream/export-engine.svg)](https://packagist.org/packages/turbostream/export-engine)
[![License](https://img.shields.io/packagist/l/turbostream/export-engine.svg)](https://packagist.org/packages/turbostream/export-engine)
[![PHP Version](https://img.shields.io/packagist/php-v/turbostream/export-engine.svg)](https://packagist.org/packages/turbostream/export-engine)

## Overview

TurboStream Export Engine is designed for Laravel applications that need to export massive datasets (100M+ records) efficiently without memory issues. It uses chunked queries to process data in batches and leverages Laravel Queues with Redis for background processing.

### Key Features

- **100M+ Records Support**: Optimized chunk sizes for massive datasets (10K-20K per chunk)
- **Memory Efficient**: Uses `cursor()` instead of `chunk()` to stream records without loading all into memory
- **5 Export Formats**: CSV, XLSX, PDF, DOCX, SQL
- **Async Processing**: Background jobs via Laravel Queues with Redis
- **Real-time Progress**: Track export progress via Redis cache
- **Filter Names in Filename**: Downloaded files include applied filters in filename
- **Multiple Queue Drivers**: Redis (recommended), Database, or Sync
- **Auto Chunk Sizing**: Automatically adjusts chunk size based on data volume
- **Laravel Native**: Integrates seamlessly with Laravel 9, 10, and 11
- **Advanced PDF Reports**: Subtotals, grand totals, colspan/rowspan support
- **Streaming PDF**: Memory-efficient export for 100M+ records
- **Format-Specific Memory**: Automatic memory limit adjustment per format (2GB for XLSX/PDF/DOCX)

## Requirements

- PHP 8.1+
- Laravel 9.0, 10.0, or 11.0
- Redis (recommended) or Database queue driver
- ext-json, ext-mbstring

### Optional Dependencies

| Format | Package | Install |
|--------|---------|---------|
| XLSX | PhpSpreadsheet | Included |
| PDF | TCPDF | Included |
| PDF (Advanced) | evosys21/pdflib | Included |
| DOCX | PhpWord | Included |
| CSV | League CSV | Included |

## Installation

### 1. Install via Composer

```bash
composer require turbostream/export-engine
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=turbo-export
```

### 3. Configure Environment

Add Redis configuration to your `.env` file:

```env
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 4. Run Migrations (optional - for database queue)

```bash
php artisan migrate
```

## Quick Start

### Basic Usage

```php
use TurboStreamExport\Facades\ExportFacade;
use App\Models\User;

$exportId = ExportFacade::createExport([
    'model' => User::class,
    'columns' => ['id', 'name', 'email', 'created_at'],
    'format' => 'csv',
    'filename' => 'users_export',
]);
```

### Using Filters

```php
$exportId = ExportFacade::createExport([
    'model' => \App\Models\User::class,
    'columns' => ['id', 'name', 'email', 'status'],
    'format' => 'csv',
    'filters' => [
        ['status', '=', 'active'],
        ['created_at', '>=', '2026-01-01'],
    ],
]);
```

**Filename with filters:**
When filters are applied, the downloaded file includes filter details:
```
users_export_filtered_status=_active_created_at=_>=_2026-01-01.csv
```

### Check Progress

```php
$progress = ExportFacade::getProgress($exportId);

echo $progress['progress'];      // 75
echo $progress['status'];        // 'processing' or 'completed'
echo $progress['filters'];      // Array of applied filters
echo $progress['filter_summary']; // 'status=_active'
```

### Download File

```php
$downloadUrl = ExportFacade::getDownloadUrl($exportId);
// Returns signed URL valid for 1 hour
```

## Export Formats

### CSV (Default)
- Fastest export
- Best for large datasets (100M+ records)
- Memory efficient streaming writes

### XLSX (Excel)
- Formatted headers with styling
- Auto-sizing columns
- Best for reports and sharing
- Uses running totals instead of storing all records in memory
- Requires 2GB memory for large datasets (50,000+ records)

### PDF
- Professional document formatting
- Header/footer with page numbers
- Memory efficient (garbage collection every 1000 rows)
- Optional: Subtotals, grand totals, colspan/rowspan support
- Use `setGroupBy()` to enable subtotals
- Use `addCustomRow()` for custom headers with colspan
- Best for small to medium reports (under 5,000 records)
- Requires 2GB memory for larger datasets (may timeout for 50K+ records)

### DOCX (Word)
- Table-formatted output
- Professional document layout
- Best for documentation

### SQL
- Database import ready
- INSERT statements with batch commits
- Includes CREATE TABLE statement
- Best for database migrations/backup

## API Reference

### Create Export

```php
ExportFacade::createExport(array $config);
```

**Configuration Options:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| model | string | Yes | Full model class path |
| columns | array | Yes | Columns to export |
| format | string | No | Export format (default: 'csv') |
| filename | string | No | Custom filename (without extension) |
| filters | array | No | Query where clauses |
| chunk_size | integer | No | Records per chunk (auto-calculated) |

**Example:**

```php
$exportId = ExportFacade::createExport([
    'model' => \App\Models\Transaction::class,
    'columns' => ['id', 'amount', 'status', 'created_at'],
    'format' => 'xlsx',
    'filename' => 'transactions_report',
    'filters' => [
        ['status', '=', 'completed'],
        ['amount', '>', 100],
        ['created_at', '>=', '2026-01-01'],
        ['category_id', 'IN', [1, 2, 3]],
    ],
]);
```

### Get Progress

```php
ExportFacade::getProgress(string $exportId): array;
```

**Returns:**

```json
{
    "export_id": "550e8400-e29b-41d4-a716-446655440000",
    "progress": 75,
    "total": 100000000,
    "status": "processing",
    "file_path": null,
    "filters": [
        ["status", "=", "completed"],
        ["created_at", ">=", "2026-01-01"]
    ],
    "filter_summary": "status=_completed_created_at=_>=_2026-01-01",
    "updated_at": "2026-03-19T10:30:00Z"
}
```

When completed:

```json
{
    "export_id": "550e8400-e29b-41d4-a716-446655440000",
    "progress": 100,
    "total": 100000000,
    "status": "completed",
    "file_path": "exports/transactions_report_filtered_status=_completed.csv",
    "filters": [["status", "=", "completed"]],
    "filter_summary": "status=_completed",
    "updated_at": "2026-03-19T12:30:00Z"
}
```

### Get Download URL

```php
ExportFacade::getDownloadUrl(string $exportId, int $minutes = 60): string;
```

Returns a signed URL valid for the specified duration.

### List Exports

```php
ExportFacade::listExports(int $limit = 10): array;
```

### Delete Export

```php
ExportFacade::deleteExport(string $exportId): bool;
```

## Advanced PDF Reports

One unified PDF driver handles all use cases:

| Feature | Method | Description |
|---------|--------|-------------|
| Simple PDF | (default) | Basic table with headers and data |
| Subtotals | `setGroupBy()` | Auto-subtotals when group changes |
| Grand Total | (auto) | Final total at end of report |
| Colspan | `addCustomRow()` | Custom rows spanning columns |
| 100M+ Records | (auto) | Memory efficient with GC every 1000 rows |

### Using the PDF Driver

```php
use TurboStreamExport\Contracts\Drivers\PdfExportDriver;

$driver = new PdfExportDriver();

// Simple report
$driver->setReportInfo('Employee Report', ['status' => 'active']);
$columns = ['id', 'name', 'salary'];
$driver->writeHeader($columns);
foreach ($employees as $e) {
    $driver->writeRow([$e->id, $e->name, $e->salary]);
}
$driver->finalize($filePath);
```

### PDF with Subtotals

```php
use TurboStreamExport\Contracts\Drivers\PdfExportDriver;

$driver = new PdfExportDriver();
$driver->setReportInfo('Department Salary Report', [
    'start_date' => '2021-01-01',
    'end_date' => '2026-03-31',
    'year' => 2026
]);

// Enable automatic subtotals by department
$driver->setGroupBy('department');

// Define columns
$columns = [
    'id', 
    'employee_name', 
    'department', 
    'basic_salary', 
    'house_rent', 
    'medical', 
    'gross_salary', 
    'deductions', 
    'net_salary'
];
$driver->setNumericColumns(['basic_salary', 'house_rent', 'medical', 'gross_salary', 'deductions', 'net_salary']);

// Write header
$driver->writeHeader($columns);

// Write data rows
foreach ($employees as $employee) {
    $driver->writeRow([
        $employee->id,
        $employee->name,
        $employee->department,
        $employee->basic_salary,
        $employee->house_rent,
        $employee->medical,
        $employee->gross_salary,
        $employee->deductions,
        $employee->net_salary,
    ]);
}

// Add custom section header with colspan
$driver->addCustomRow([
    0 => [
        'TEXT' => '★ Report Summary - Last 5 Years Financial Data ★',
        'COLSPAN' => 9,
        'STYLE' => 'subtotal',
        'FONT_WEIGHT' => 'B',
        'TEXT_ALIGN' => 'C',
        'BACKGROUND_COLOR' => [68, 114, 196],
    ]
]);

// Grand total added automatically at end

// Finalize and save
$filePath = storage_path('app/exports/salary_report.pdf');
$driver->finalize($filePath);
```

### Methods Reference

#### setReportInfo(string $name, array $filters = [])

Set the report title and filters to be displayed.

```php
$driver->setReportInfo('Monthly Sales Report', [
    'start_date' => '2026-01-01',
    'end_date' => '2026-03-31',
    'region' => 'Dhaka'
]);
```

#### setGroupBy(string $column)

Enable automatic subtotals when a column value changes.

```php
// Subtotals will be added automatically when department changes
$driver->setGroupBy('department');

// Can also group by year, category, region, etc.
$driver->setGroupBy('year');
```

#### setNumericColumns(array $columns)

Define which columns contain numeric values (for formatting).

```php
$driver->setNumericColumns([
    'basic_salary', 
    'house_rent', 
    'gross_salary', 
    'net_salary'
]);
```

#### addCustomRow(array $cellData)

Add a custom row with full control over each cell.

```php
// Section header spanning all columns
$driver->addCustomRow([
    0 => [
        'TEXT' => 'Quarterly Summary - Q1 2026',
        'COLSPAN' => 8,
        'STYLE' => 'subtotal',
        'FONT_WEIGHT' => 'B',
        'TEXT_ALIGN' => 'C',
    ]
]);

// Custom row with specific cell values
$driver->addCustomRow([
    0 => ['TEXT' => 'Section A', 'STYLE' => 'header'],
    1 => ['TEXT' => '', 'STYLE' => 'header'],
    2 => ['TEXT' => 'Total Amount', 'STYLE' => 'header', 'TEXT_ALIGN' => 'R'],
    3 => ['TEXT' => '1,234,567', 'STYLE' => 'subtotal', 'TEXT_ALIGN' => 'R'],
    4 => ['TEXT' => '', 'STYLE' => 'header'],
]);
```

#### addColspanRow(array $data, int $colspan, string $text, string $style = 'subtotal')

Add a row with a cell spanning multiple columns.

```php
// Create a row where first 3 columns are merged
$driver->addColspanRow(
    ['', '', '', 'Value 1', 'Value 2'],  // Cell data
    3,                                     // Span 3 columns
    'Merged Header Text',                   // Text for merged cell
    'subtotal'                             // Style
);
```

#### addEmptyRow()

Add a blank row for visual separation.

```php
$driver->addEmptyRow();
```

### Cell Configuration Options

Each cell in `addCustomRow()` supports these options:

| Option | Type | Description |
|--------|------|-------------|
| TEXT | string | Cell text content |
| COLSPAN | integer | Number of columns to span |
| ROWSPAN | integer | Number of rows to span |
| STYLE | string | Style name (header, body, subtotal, grandtotal) |
| TEXT_ALIGN | string | Alignment: L, R, C |
| VERTICAL_ALIGN | string | Vertical: T, M, B |
| FONT_WEIGHT | string | 'B' for bold |
| FONT_SIZE | integer | Font size in points |
| TEXT_COLOR | array | RGB [R, G, B] |
| BACKGROUND_COLOR | array | RGB [R, G, B] |
| BORDER_SIZE | float | Border width |
| PADDING_TOP | integer | Top padding |
| PADDING_BOTTOM | integer | Bottom padding |

### Example: Financial Report with All Features

```php
use TurboStreamExport\Contracts\Drivers\PdfExportDriver;

$driver = new PdfExportDriver();
$driver->setReportInfo('Annual Financial Report 2021-2026', [
    'start_date' => '2021-01-01',
    'end_date' => '2026-03-31',
    'company' => 'ABC Corporation'
]);

// Group by department for subtotals
$driver->setGroupBy('department');

// Define columns with numeric ones
$columns = ['id', 'employee', 'department', 'year', 'basic', 'allowances', 'gross', 'tax', 'net'];
$driver->setNumericColumns(['basic', 'allowances', 'gross', 'tax', 'net']);

// Write header
$driver->writeHeader($columns);

// Data by year and department
$years = [2021, 2022, 2023, 2024, 2025, 2026];
$departments = ['HR', 'IT', 'Finance', 'Operations', 'Marketing'];

foreach ($years as $year) {
    // Year header with colspan
    $driver->addCustomRow([
        0 => [
            'TEXT' => "═══ YEAR $year ═══",
            'COLSPAN' => 9,
            'STYLE' => 'subtotal',
            'FONT_WEIGHT' => 'B',
            'TEXT_ALIGN' => 'C',
            'BACKGROUND_COLOR' => [52, 152, 219],
        ]
    ]);
    
    foreach ($departments as $dept) {
        // Write employee rows (subtotals added automatically when department changes)
        foreach ($employees as $emp) {
            if ($emp->department === $dept && $emp->year === $year) {
                $driver->writeRow([
                    $emp->id,
                    $emp->name,
                    $emp->department,
                    $emp->year,
                    $emp->basic,
                    $emp->allowances,
                    $emp->gross,
                    $emp->tax,
                    $emp->net,
                ]);
            }
        }
    }
}

// Grand total row added automatically at the end
$filePath = storage_path('app/exports/financial_report.pdf');
$driver->finalize($filePath);
```



## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=turbo-export --force
```

### config/turbo-export.php

```php
return [
    'disk' => env('EXPORT_DISK', 'local'),
    
    'chunk_size' => env('EXPORT_CHUNK_SIZE', 5000),
    
    'large_data_chunk_size' => env('EXPORT_LARGE_DATA_CHUNK_SIZE', 10000),
    
    'queue' => env('EXPORT_QUEUE', 'exports'),
    
    'retention_hours' => env('EXPORT_RETENTION_HOURS', 24),
    
    'max_records' => env('EXPORT_MAX_RECORDS', 100000000),
    
    'formats' => [
        'csv',
        'xlsx',
        'pdf',
        'docx',
        'sql',
    ],
    
    'default_format' => env('EXPORT_DEFAULT_FORMAT', 'csv'),
    
    // Default memory limit for CSV/SQL (streaming exports)
    'memory_limit' => env('EXPORT_MEMORY_LIMIT', '1G'),
    
    // Format-specific memory limits for memory-based exports
    'memory_limit_xlsx' => env('EXPORT_MEMORY_LIMIT_XLSX', '2G'),
    'memory_limit_pdf' => env('EXPORT_MEMORY_LIMIT_PDF', '2G'),
    'memory_limit_docx' => env('EXPORT_MEMORY_LIMIT_DOCX', '2G'),
    
    'batch_commit_size' => env('EXPORT_BATCH_COMMIT_SIZE', 50000),
    
    'include_filter_in_filename' => env('EXPORT_INCLUDE_FILTER_IN_FILENAME', true),
    
    'download_expiry_minutes' => env('EXPORT_DOWNLOAD_EXPIRY_MINUTES', 60),
    
    'drivers' => [
        'csv' => \TurboStreamExport\Contracts\Drivers\CsvExportDriver::class,
        'xlsx' => \TurboStreamExport\Contracts\Drivers\XlsxExportDriver::class,
        'pdf' => \TurboStreamExport\Contracts\Drivers\PdfExportDriver::class,
        'docx' => \TurboStreamExport\Contracts\Drivers\DocxExportDriver::class,
        'sql' => \TurboStreamExport\Contracts\Drivers\SqlExportDriver::class,
    ],
    
    'large_data_threshold' => env('EXPORT_LARGE_DATA_THRESHOLD', 1000000),
    
    'enable_progress_logging' => env('EXPORT_PROGRESS_LOGGING', true),
    
    'log_progress_interval' => env('EXPORT_LOG_PROGRESS_INTERVAL', 100000),
];
```

### Memory Management

The package uses `cursor()` instead of `chunk()` to stream database records without loading all into memory:

| Format | Export Type | Default Memory | Notes |
|--------|-------------|----------------|-------|
| CSV | Streaming | 1GB | Best for 100M+ records |
| SQL | Streaming | 1GB | Best for database backup |
| XLSX | Memory-based | 2GB | Uses running totals |
| PDF | Memory-based | 2GB | Slow for 50K+ records |
| DOCX | Memory-based | 2GB | Works well with 47K+ records |

## Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run unit tests only
composer test:unit

# Run feature tests only
composer test:feature

# Run large data tests (memory/performance)
composer test:large

# Or using Pest directly
./vendor/bin/pest
./vendor/bin/pest --coverage
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/LargeData
```

### Test Coverage

| Suite | Description | Files |
|-------|-------------|-------|
| Unit | Driver tests, Service tests, Job tests | ExportDriverTest, ExportServiceTest, ProcessExportJobTest |
| Feature | Integration tests, Filter tests | ExportServiceFeatureTest |
| LargeData | Performance tests, Memory tests | LargeDataExportTest |

### Writing Tests

```php
use TurboStreamExport\Contracts\Drivers\CsvExportDriver;
use TurboStreamExport\Services\ExportService;

class ExportServiceTest extends TestCase
{
    public function test_csv_driver_returns_correct_format(): void
    {
        $driver = new CsvExportDriver();
        
        $this->assertEquals('csv', $driver->getFormat());
        $this->assertEquals('text/csv', $driver->getContentType());
        $this->assertEquals('csv', $driver->getFileExtension());
    }
    
    public function test_filter_summary_generation(): void
    {
        $service = new ExportService('local', [new CsvExportDriver()]);
        
        $filters = [
            ['status', '=', 'active'],
            ['category_id', '=', '5'],
        ];
        
        // Access private method via reflection
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildFilterSummary');
        $method->setAccessible(true);
        
        $summary = $method->invoke($service, $filters);
        
        $this->assertStringContainsString('status', $summary);
        $this->assertStringContainsString('active', $summary);
    }
}
```

### Large Data Testing

```php
public function test_csv_driver_handles_large_batch(): void
{
    $driver = new CsvExportDriver();
    $filePath = $this->tempDir . '/large_export.csv';
    $handle = fopen($filePath, 'w');
    
    $columns = ['id', 'name', 'email', 'status', 'created_at'];
    $driver->writeHeader($columns, $handle);
    
    $recordCount = 100000;
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
    $this->assertLessThan(30, $duration, 'Large batch export took too long');
}
```

## Architecture

### Export Process Flow

```
┌──────────────┐
│ createExport │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│   Validate   │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ Dispatch Job │
└──────┬───────┘
       │
       ▼
┌──────────────┐     ┌─────────────┐
│ Queue Worker│────▶│ 100M+ Data  │
└─────────────┘     └──────┬──────┘
                           │
                           ▼
                    ┌─────────────┐
                    │ Auto Chunk  │
                    │ (5K-20K)    │
                    └──────┬──────┘
                           │
                           ▼
                    ┌─────────────┐
                    │  Streaming  │
                    │   Export    │
                    └──────┬──────┘
                           │
                           ▼
                    ┌─────────────┐
                    │ Update Cache│
                    │ + Progress  │
                    └──────┬──────┘
                           │
                           ▼
                    ┌─────────────┐
                    │   File      │
                    │ (filter_*)  │
                    └─────────────┘
```

### Chunk Size Strategy

| Records | Chunk Size | Memory |
|---------|------------|--------|
| < 1M | 5,000 | ~50MB |
| 1M - 10M | 10,000 | ~100MB |
| 10M - 100M | 15,000 | ~200MB |
| 100M+ | 20,000 | ~512MB |

### Directory Structure

```
turbostream/export-engine/
├── src/
│   ├── Contracts/
│   │   ├── ExportDriverInterface.php
│   │   ├── ExportableInterface.php
│   │   └── Drivers/
│   │       ├── CsvExportDriver.php
│   │       ├── XlsxExportDriver.php
│   │       ├── PdfExportDriver.php       # Unified: simple, subtotals, colspan, 100M+ records
│   │       ├── DocxExportDriver.php
│   │       └── SqlExportDriver.php
│   ├── Facades/
│   │   └── ExportFacade.php
│   ├── Http/Controllers/
│   │   └── ExportController.php
│   ├── Jobs/
│   │   └── ProcessExportJob.php
│   ├── Providers/
│   │   └── TurboStreamExportServiceProvider.php
│   └── Services/
│       └── ExportService.php
├── config/
│   └── turbo-export.php
├── tests/
│   ├── Unit/
│   │   ├── ExportDriverTest.php
│   │   ├── ExportServiceTest.php
│   │   └── ProcessExportJobTest.php
│   ├── Feature/
│   │   └── ExportServiceFeatureTest.php
│   └── LargeData/
│       └── LargeDataExportTest.php
├── routes/
│   └── api.php
├── composer.json
├── phpunit.xml
└── README.md
```

## Queue Workers

### Start Queue Worker

```bash
php artisan queue:work redis --queue=exports
```

### High Priority Queue

For urgent exports:

```bash
php artisan queue:work redis --queue=exports-high,exports
```

### Production Setup (Supervisor)

```ini
[program:export-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=exports --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/export-worker.log
```

## Extending the Package

### Custom Export Driver

Create your own driver by implementing `ExportDriverInterface`:

```php
<?php

namespace App\Export\Drivers;

use TurboStreamExport\Contracts\ExportDriverInterface;

class CustomExportDriver implements ExportDriverInterface
{
    public function getFormat(): string
    {
        return 'custom';
    }

    public function getContentType(): string
    {
        return 'application/custom';
    }

    public function getFileExtension(): string
    {
        return 'ext';
    }

    public function writeHeader(array $columns, $handle): void
    {
        // Write header
    }

    public function writeRow(array $data, $handle): void
    {
        // Write row
    }

    public function writeBatch($records, array $columns, $handle): void
    {
        // Write batch
    }

    public function finalize($handle, string $filePath): string
    {
        // Finalize
        return $filePath;
    }
}
```

### Register Custom Driver

```php
// In your ServiceProvider
use TurboStreamExport\Facades\ExportFacade;

ExportFacade::extendDriver('custom', CustomExportDriver::class);
```

## Troubleshooting

### "Class Redis not found"

Install the Redis PHP extension or use predis:

```bash
composer require predis/predis
```

### Queue Issues

#### Export stuck in "processing"

1. Check if queue worker is running:
```bash
php artisan queue:work redis --queue=exports
```

2. Check Laravel logs for errors:
```bash
tail -f storage/logs/laravel.log
```

3. Verify Redis connection:
```bash
php artisan tinker
Redis::ping();
```

#### Clear stuck queue jobs

If jobs are stuck in the queue or failed:

```bash
# Clear all pending jobs from exports queue
php artisan queue:clear --queue=exports

# Retry failed jobs
php artisan queue:retry

# Delete all failed jobs
php artisan queue:flush

# View failed jobs
php artisan queue:failed
```

#### Queue worker not processing

If the worker is running but jobs aren't being processed:

1. Check Redis connection in `.env`:
```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=redis
```

2. Restart the worker:
```bash
# Stop existing worker (Ctrl+C)
# Start fresh worker
php artisan queue:work redis --queue=exports
```

3. For high-volume exports, run worker with verbose output:
```bash
php artisan queue:work redis --queue=exports -vvv
```

#### Memory issues with large exports

For XLSX/PDF/DOCX with large datasets, increase memory limits:

```env
# Default for CSV/SQL (streaming)
EXPORT_MEMORY_LIMIT=1G

# Higher for XLSX/PDF/DOCX (memory-based)
EXPORT_MEMORY_LIMIT_XLSX=2G
EXPORT_MEMORY_LIMIT_PDF=2G
EXPORT_MEMORY_LIMIT_DOCX=2G
```

The XLSX driver now uses running totals instead of storing all records in memory, reducing memory usage significantly.

### Performance issues

For 100M+ records:

```env
EXPORT_LARGE_DATA_CHUNK_SIZE=20000
EXPORT_LOG_PROGRESS_INTERVAL=500000
```

## Security

If you discover security vulnerabilities, please email support@turbostream.dev instead of using the public issue tracker.

## License

The TurboStream Export Engine is open-sourced software licensed under the [MIT license](LICENSE).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.
