<?php

declare(strict_types=1);

return [
    'disk' => env('EXPORT_DISK', 'local'),
    
    'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),
    
    'queue' => env('EXPORT_QUEUE', 'exports'),
    
    'retention_hours' => env('EXPORT_RETENTION_HOURS', 24),
    
    'max_records' => env('EXPORT_MAX_RECORDS', 1000000),
    
    'formats' => [
        'csv',
        'xlsx',
    ],
    
    'default_format' => env('EXPORT_DEFAULT_FORMAT', 'csv'),
];
