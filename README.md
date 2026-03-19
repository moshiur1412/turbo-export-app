# TurboStream Export

High-performance Laravel package for exporting large datasets (100k+ records) using chunked queries and async processing via Redis queues.

## Requirements

- PHP 8.3+
- Laravel 11
- Redis (or database queue fallback)
- MySQL 8.0+

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### Configure Environment

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=turbo_export
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Setup Database

```bash
php artisan migrate
php artisan db:seed --class=UserSeeder
```

### Start Queue Worker

```bash
php artisan queue:work redis --queue=exports
```

## API Authentication

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

Response:
```json
{
    "token": "2|abc123...",
    "user": {"id": 1, "name": "Admin", "email": "admin@example.com"}
}
```

## API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/login` | No | Get authentication token |
| POST | `/api/logout` | Yes | Revoke current token |
| POST | `/api/exports` | Yes | Create new export job |
| GET | `/api/exports/{id}/progress` | Yes | Check export status |
| GET | `/api/exports/{id}/download` | Yes | Download exported file |

## Usage

### Create Export

```bash
curl -X POST http://localhost:8000/api/exports \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "App\\Models\\User",
    "columns": ["id", "name", "email", "created_at"],
    "format": "csv",
    "filename": "users_export"
  }'
```

Response (202):
```json
{
    "export_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "queued",
    "message": "Export job has been queued"
}
```

### Check Progress

```bash
curl -X GET http://localhost:8000/api/exports/550e8400-e29b-41d4-a716-446655440000/progress \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Response:
```json
{
    "progress": 100,
    "total": 10000,
    "status": "completed",
    "file_path": "exports/users_export.csv"
}
```

### Download File

```bash
curl -X GET http://localhost:8000/api/exports/550e8400-e29b-41d4-a716-446655440000/download \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -o export.csv
```

### With Filters

```bash
curl -X POST http://localhost:8000/api/exports \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "App\\Models\\User",
    "columns": ["id", "name", "email"],
    "filters": [["status", "=", "active"]],
    "format": "csv"
  }'
```

## Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| model | string | Yes | Full model class path |
| columns | array | Yes | Columns to export |
| format | string | No | `csv` (default) or `xlsx` |
| filename | string | No | Custom filename (without extension) |
| filters | array | No | Query where clauses |

## Architecture

```
Client → ExportController → ProcessExportJob (Queue) → ExportService
                                                        ↓
                              ┌─────────────────────────┴─────────────────────────┐
                              ↓                                                   ↓
                         Storage Disk                                     Progress Cache (Redis)
                      (exports/*.csv)                                           
```

## Configuration

Publish config:
```bash
php artisan vendor:publish --tag=turbo-export-config
```

Config (`config/turbo-export.php`):
```php
[
    'disk' => 'local',
    'chunk_size' => 1000,
    'queue' => 'exports',
    'retention_hours' => 24,
    'max_records' => 1000000,
]
```

Environment variables:
```env
EXPORT_DISK=local
EXPORT_CHUNK_SIZE=1000
EXPORT_QUEUE=exports
EXPORT_RETENTION_HOURS=24
EXPORT_MAX_RECORDS=1000000
```

## Testing

```bash
./vendor/bin/pest
```

## Authorization

Define gates in `app/Providers/AuthServiceProvider.php`:

```php
Gate::define('export', function ($user, $model) {
    return true;
});

Gate::define('download-export', function ($user, $filePath) {
    return true;
});
```
