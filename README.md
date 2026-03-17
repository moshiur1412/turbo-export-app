# Turbo-Stream Export Engine

A high-performance Laravel 11 package for exporting large datasets (CSV/Excel) using chunked queries with async processing via Redis queues.

## Features

- **Memory Efficient**: Uses chunked queries to process 100k+ records under 50MB memory
- **Async Processing**: Laravel Queues with Redis for background processing
- **Real-time Progress**: Live progress tracking via Redis with polling
- **Secure Downloads**: Signed URLs for secure file downloads
- **Modern Testing**: Pest PHP unit and integration tests
- **PSR-12 Compliant**: Strict typing and PSR coding standards

## Tech Stack

- Laravel 11
- PHP 8.3+
- React (Inertia.js)
- MySQL
- Redis
- Pest Testing
- Tailwind CSS

## Folder Structure

```
turbo-export-app/
├── app/                          # Laravel application
│   ├── Console/
│   ├── Exceptions/
│   ├── Http/
│   │   └── Controllers/
│   ├── Models/
│   └── Providers/
├── bootstrap/
│   └── app.php                   # Laravel 11 bootstrap
├── config/
│   ├── app.php
│   ├── database.php
│   ├── queue.php
│   └── turbo-export.php          # Package config
├── packages/
│   └── turbo-stream-export/      # Main package
│       ├── src/
│       │   ├── Contracts/
│       │   │   └── ExportableInterface.php
│       │   ├── Http/
│       │   │   └── Controllers/
│       │   │       └── ExportController.php
│       │   ├── Jobs/
│       │   │   └── ProcessExportJob.php
│       │   ├── Providers/
│       │   │   └── TurboStreamExportServiceProvider.php
│       │   └── Services/
│       │       └── ExportService.php
│       ├── config/
│       │   └── turbo-export.php
│       ├── routes/
│       │   └── api.php
│       └── composer.json
├── resources/
│   └── js/
│       └── components/
│           └── ExportProgress.jsx   # React progress component
├── routes/
│   ├── api.php
│   ├── console.php
│   └── web.php
├── storage/
│   └── app/
│       └── exports/              # Exported files
├── tests/
│   ├── Feature/
│   │   └── ExportApiTest.php
│   └── Unit/
│       └── ExportServiceTest.php
├── composer.json
├── package.json
├── phpunit.xml
└── vite.config.js
```

## ERD (Entity Relationship)

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│    Export       │────<│   ExportJob     │────<│  ExportService  │
│   (Controller)  │     │  (Queue Job)     │     │  (Core Logic)   │
└────────┬────────┘     └────────┬─────────┘     └────────┬────────┘
         │                      │                        │
         │                      │                        │
         v                      v                        v
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   User Model    │     │    Redis         │     │  Storage Disk   │
│   (Auth)        │     │  (Progress)      │     │  (CSV/Excel)    │
└─────────────────┘     └──────────────────┘     └─────────────────┘
```

## Installation

### 1. Prerequisites

- PHP 8.3+
- Composer
- MySQL 8.0+
- Redis
- Node.js 18+

### 2. Clone & Install

```bash
# Clone repository
git clone <repository-url> turbo-export-app
cd turbo-export-app

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Build assets
npm run build

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Configure Environment (.env)

```env
APP_NAME="Turbo Export"
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=turbo_export
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Export Settings
EXPORT_DISK=local
EXPORT_CHUNK_SIZE=1000
EXPORT_QUEUE=exports
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Start Services

```bash
# Start queue worker (separate terminal)
php artisan queue:work redis --queue=exports

# Start development server
php artisan serve
```

## API Usage

### Create Export

```bash
POST /api/exports
Content-Type: application/json
Authorization: Bearer <token>

{
  "model": "App\\Models\\User",
  "columns": ["id", "name", "email", "created_at"],
  "filters": {"status": "active"},
  "format": "csv",
  "filename": "users_export"
}
```

**Response:**
```json
{
  "export_id": "uuid-string",
  "status": "queued",
  "message": "Export job has been queued"
}
```

### Get Progress

```bash
GET /api/exports/{exportId}/progress
```

**Response:**
```json
{
  "progress": 45,
  "total": 10000,
  "status": "processing",
  "updated_at": "2026-03-18T12:00:00+00:00"
}
```

### Download File

```bash
GET /api/exports/{exportId}/download?signed_url=true
```

## React Component Usage

```jsx
import ExportProgress from './components/ExportProgress';

function App() {
  const handleComplete = (downloadUrl) => {
    console.log('Download ready:', downloadUrl);
  };

  const handleError = (error) => {
    console.error('Export failed:', error);
  };

  return (
    <ExportProgress
      exportId="uuid-string"
      onComplete={handleComplete}
      onError={handleError}
      pollingInterval={1000}
    />
  );
}
```

## Configuration

### Package Config (config/turbo-export.php)

```php
return [
    'disk' => env('EXPORT_DISK', 'local'),
    'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),
    'queue' => env('EXPORT_QUEUE', 'exports'),
    'retention_hours' => env('EXPORT_RETENTION_HOURS', 24),
    'max_records' => env('EXPORT_MAX_RECORDS', 1000000),
    'formats' => ['csv', 'xlsx'],
    'default_format' => env('EXPORT_DEFAULT_FORMAT', 'csv'),
];
```

## Testing

### Run Tests

```bash
# Run all tests
./vendor/bin/pest

# Run unit tests
./vendor/bin/pest tests/Unit

# Run integration tests
./vendor/bin/pest tests/Feature
```

### Test Cases

1. **ExportService Unit Tests**
   - Progress storage/retrieval from Redis
   - Chunk size calculation
   - File path generation

2. **ProcessExportJob Tests**
   - Job instantiation with parameters
   - Queue configuration
   - Job tags

3. **API Integration Tests**
   - Export creation endpoint
   - Progress checking endpoint
   - Authorization checks

## Security

- **Signed URLs**: Download endpoints require valid signed URLs
- **Authorization Gates**: Export access controlled via Laravel gates
- **CSRF Protection**: Enabled for web routes

## Performance

- **Memory**: < 50MB for 100k records (chunked processing)
- **Chunk Size**: Configurable (default 1000)
- **Queue**: Dedicated Redis queue for exports

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
