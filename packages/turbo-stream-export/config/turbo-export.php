<?php

declare(strict_types=1);

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
    
    'memory_limit' => env('EXPORT_MEMORY_LIMIT', '1G'),
    
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
    'high_priority_queue' => env('EXPORT_HIGH_PRIORITY_QUEUE', 'exports-high'),
];
