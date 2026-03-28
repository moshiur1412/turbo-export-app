# HRM Reporting Engine

A high-performance HRM Reporting Engine for Laravel with Redis queues, supporting 5,000+ employees and 20+ pre-built reports.

## Features

- **20+ Pre-built Reports** across 4 categories
- **Async Processing** with Redis queues for large datasets
- **Multiple Export Formats**: CSV, XLSX, PDF, DOCX, SQL
- **Real-time Progress Tracking** with live updates
- **Modern React Frontend** with pagination and filtering

## Report Categories

### Salary & Financial (6 reports)
| Report Type | Description | Requires Date Range |
|-------------|-------------|---------------------|
| `salary_master` | Master Salary Sheet | No |
| `salary_by_department` | Salary breakdown by department | No |
| `salary_by_designation` | Salary breakdown by designation | No |
| `salary_by_location` | Salary breakdown by location | No |
| `salary_monthly_comparative` | Monthly salary comparison | No |
| `salary_bank_advice` | Bank advice format for transfers | No |

### Attendance (4 reports)
| Report Type | Description | Requires Date Range |
|-------------|-------------|---------------------|
| `attendance_daily` | Daily attendance records | Yes |
| `attendance_monthly` | Monthly attendance summary | Yes |
| `attendance_late_trends` | Late arrival trends analysis | Yes |
| `attendance_overtime` | Overtime hours summary | Yes |

### Leave Management (4 reports)
| Report Type | Description | Requires Date Range |
|-------------|-------------|---------------------|
| `leave_balance` | Leave balance report | No |
| `leave_encashment` | Leave encashment report | Yes |
| `leave_department_heatmap` | Department leave heatmap | No |
| `leave_availed` | Leave availed report | Yes |

### Employee Lifecycle (4 reports)
| Report Type | Description | Requires Date Range |
|-------------|-------------|---------------------|
| `employee_recruitment` | Recruitment report | No |
| `employee_attrition` | Attrition/farewell report | No |
| `employee_service_length` | Service length report | No |
| `employee_profile_export` | Complete employee profiles | No |

## Installation

1. **Install Dependencies**
```bash
composer install
npm install
```

2. **Environment Setup**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Configure Database & Redis** in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
```

4. **Run Migrations**
```bash
php artisan migrate
```

5. **Start Queue Worker**
```bash
php artisan queue:work redis --queue=exports
```

## Database Seeding

### Seeders Overview

This application includes several seeders for populating test data:

| Seeder | Description | Records Created |
|--------|-------------|-----------------|
| `UserSeeder` | Basic user accounts | 2 users |
| `DepartmentSeeder` | Departments (auto via GeneralDataSeeder) | 100+ departments |
| `DesignationSeeder` | Job titles (auto via GeneralDataSeeder) | 100+ designations |
| `GeneralDataSeeder` | Large-scale test data | 1K - 200K employees |

### Running Seeders

#### Basic Seeding (Recommended for development)
```bash
php artisan db:seed
```
This runs `DatabaseSeeder` which includes:
- `UserSeeder` - Creates admin and demo users with additional info (gender, phone, address)

> **Note**: By default, only basic users are created. Use `GeneralDataSeeder` explicitly for large-scale data.

#### Large-Scale Data Generation

The `GeneralDataSeeder` generates realistic test data at various scales:

```bash
php artisan db:seed --class=GeneralDataSeeder
```

**Available Scales:**

| Scale | Employees | Attendance Months | Total Records | Est. Time |
|-------|-----------|-------------------|---------------|-----------|
| `small` | 1,000 | 3 | ~200K | 30-60 sec |
| `medium` | 10,000 | 6 | ~2M | 3-5 min |
| `large` | 50,000 | 24 | ~30M | 15-30 min |
| `xlarge` | 100,000 | 60 | ~150M | 45-90 min |
| `xxlarge` | 200,000 | 60 | ~300M+ | 2-3 hours |

#### Selecting Scale

```bash
# Small scale (~200K records)
php artisan db:seed --class=GeneralDataSeeder --scale=small

# Medium scale (~2M records) - default
php artisan db:seed --class=GeneralDataSeeder --scale=medium

# Large scale (~30M records)
php artisan db:seed --class=GeneralDataSeeder --scale=large

# Extra large (~150M records)
php artisan db:seed --class=GeneralDataSeeder --scale=xlarge

# Extreme (~300M+ records)
php artisan db:seed --class=GeneralDataSeeder --scale=xxlarge
```

#### Selective Data Generation

Skip specific data types:

```bash
php artisan db:seed --class=GeneralDataSeeder \
    --scale=medium \
    --skip=attendance,leaves
```

Available skip options: `attendance`, `leaves`, `balances`

#### Custom Configuration

```bash
php artisan db:seed --class=GeneralDataSeeder \
    --scale=small \
    --chunk-size=5000
```

### Seeded Users

After running seeders, you can login with:

| Email | Password | Role |
|-------|----------|------|
| `admin@example.com` | `password` | Admin |
| `employee1@example.com` | `password` | Demo Employee |

### User Additional Information

Users have additional information stored in `user_details` table:

- `gender` - male, female, other
- `phone` - contact number
- `address` - full address

### Fresh Seed Command (Recommended)

The `db:fresh-seed` command provides a convenient way to drop all tables, run migrations, and seed data in one step:

```bash
php artisan db:fresh-seed --scale=small
```

The command will show estimated time based on scale and ask for confirmation before executing:

**Available Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--scale` | Data scale (small, medium, large, xlarge, xxlarge) | small |
| `--seeder` | Custom seeder class | GeneralDataSeeder |
| `--chunk` | Chunk size for batch inserts | 10000 |
| `--no-seed` | Skip seeding | - |
| `--skip-attendance` | Skip attendance data | - |
| `--skip-leaves` | Skip leave data | - |
| `--skip-balances` | Skip leave balance data | - |

**Examples:**

```bash
# Small scale (~200K records) - default
php artisan db:fresh-seed

# Medium scale (~2M records)
php artisan db:fresh-seed --scale=medium

# Large scale
php artisan db:fresh-seed --scale=large

# Just migrate, no seeding
php artisan db:fresh-seed --no-seed

# Skip attendance data
php artisan db:fresh-seed --scale=small --skip-attendance
```

**Output Example:**

When the command completes, it displays a table showing the number of records and time taken for each table:

```
+----------------+-----------+--------+
| Table          | Records   | Time   |
+----------------+-----------+--------+
| users          | 1,000     | 3s     |
| salaries       | 1,000     | 3s     |
| departments    | 106       | <1s    |
| designations   | 118       | <1s    |
| user_details   | 1,000     | 1s     |
| attendances    | 57,000    | 2s     |
| leaves         | 5,000     | 1s     |
| leave_balances | 12,000    | 1s     |
+----------------+-----------+--------+
```

### Performance Notes

- Use `medium` scale for typical development (2M records)
- For production-like testing, use `large` or `xlarge`
- Enable Redis queue for async processing
- Monitor memory usage for large scales

## API Endpoints

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/reports` | List all reports (paginated) |
| POST | `/api/reports` | Create a new report |
| GET | `/api/reports/types` | Get all report types by category |
| GET | `/api/reports/formats` | Get available export formats |
| GET | `/api/reports/{id}` | Get single report details |
| GET | `/api/reports/{id}/progress` | Get report progress |
| GET | `/api/reports/{id}/download` | Download completed report |
| POST | `/api/reports/{id}/cancel` | Cancel pending/processing report |
| POST | `/api/reports/{id}/retry` | Retry failed report |
| DELETE | `/api/reports/{id}` | Delete a report |

### Departments
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/departments` | List all departments |

### Create Report Request
```json
POST /api/reports
{
    "type": "salary_master",
    "format": "csv",
    "name": "Custom Report Name",
    "filters": {
        "start_date": "2026-01-01",
        "end_date": "2026-03-31",
        "department_ids": [1, 2, 3],
        "year": 2026,
        "month": 3,
        "date": "2026-03-20"
    }
}
```

**Note**: Date range is only required for specific reports (see table above). Reports that don't require date range will accept empty `start_date` and `end_date`.

### Response Format
```json
{
    "success": true,
    "message": "Report generation started",
    "data": {
        "id": "uuid-here",
        "type": "salary_master",
        "type_label": "Master Salary Sheet",
        "format": "csv",
        "status": "pending",
        "status_label": "Pending",
        "name": "Master Salary Sheet",
        "progress": 0,
        "total_records": 0,
        "processed_records": 0,
        "file_name": null,
        "file_size_formatted": null,
        "download_url": null,
        "error_message": null,
        "created_at": "2026-03-20T10:00:00Z",
        "started_at": null,
        "completed_at": null,
        "duration": null
    }
}
```

## Architecture

### Report Service (`app/Services/ReportService.php`)
- Creates reports and dispatches export jobs
- Syncs progress from Redis cache to database
- Manages report lifecycle (cancel, retry, delete)

### Report Query Builder (`app/Services/ReportQueryBuilder.php`)
- Builds optimized queries for each report type
- Returns columns configuration with closure-based value extractors
- Supports filtering by department, date range, etc.

### Export Job (`app/Jobs/ProcessReportExportJob.php`)
- Custom job for processing reports with closure-based columns
- Handles both streaming (CSV, SQL) and memory-based (XLSX, PDF, DOCX) exports
- Pre-processes records to resolve closure values before export

### Models
- **Report**: Stores report metadata and progress
- **ReportNotification**: Stores report completion notifications
- **User**: Employee data with relationships (department, salary, attendance, leaves)

### Enums
- **ReportType**: 20 report types with category, label, and date range requirements
- **ReportStatus**: pending, processing, completed, failed, cancelled
- **ReportFormat**: csv, xlsx, pdf, docx, sql

## Frontend Components

### ReportCreator (`resources/js/components/ReportCreator.jsx`)
- Category selection with icons
- Report type dropdown grouped by category
- Export format selector (CSV, XLSX, PDF, DOCX, SQL)
- Date range picker (shown only for reports requiring it)
- Department filter (multi-select)
- Year/Month filters (context-aware)
- Error display banner with dismiss button

### ReportList (`resources/js/components/ReportList.jsx`)
- Paginated report history
- Auto-polling for progress updates (2 second interval)
- Status badges with color coding
- Format badges (CSV, XLSX, PDF, DOCX, SQL)
- Action buttons (download, retry, delete)
- Progress bar for processing reports

## Testing Reports

### Test All Reports
```bash
php artisan reports:test
```

### Test Specific Report
```bash
php artisan reports:test salary_master --format=xlsx
```

### Test with Filters
```bash
php artisan reports:test attendance_monthly \
    --format=csv \
    --departments=1,2,3 \
    --start=2026-01-01 \
    --end=2026-03-31
```

## Report Processing Flow

1. **User Request**: API receives report creation request
2. **Validation**: Request validated (date range only required for specific types)
3. **Create Record**: Report record created with status "pending"
4. **Dispatch**: Export job pushed to Redis queue
5. **Processing**: Queue worker picks up job, updates progress in cache
6. **Completion**: File saved to storage/app/exports, cache updated to 'completed'
7. **Sync**: ReportService syncs cache status to database
8. **Download**: Frontend polls progress, enables download when complete

## Export Formats

| Format | Description | Driver | Memory Required | Tested Records |
|--------|-------------|--------|-----------------|----------------|
| CSV | Comma-separated values | Streaming | ~512MB | 100K+ |
| XLSX | Excel spreadsheet | Memory-based | 2GB | 47,520 |
| PDF | Portable document format | Memory-based | 2GB | 2,000 (slow) |
| DOCX | Word document | Memory-based | 2GB | 47,520 |
| SQL | SQL INSERT statements | Streaming | ~512MB | 47,520 |

### Memory Optimization

This application uses `cursor()` instead of `chunk()` for streaming database records, which prevents memory exhaustion when processing large datasets (50,000+ records). The ExportService processes data in batches while keeping only the current batch in memory.

### Format-Specific Configuration

The package automatically adjusts memory limits based on export format:

```env
# Default for CSV/SQL (streaming)
EXPORT_MEMORY_LIMIT=1G

# Higher for XLSX/PDF/DOCX (memory-based)
EXPORT_MEMORY_LIMIT_XLSX=2G
EXPORT_MEMORY_LIMIT_PDF=2G
EXPORT_MEMORY_LIMIT_DOCX=2G
```

### Bangladesh Number Formatting

Numbers are formatted using Bangladesh locale convention (comma as thousands separator):
- `40588` → `40,588`
- `40588.50` → `40,588.50`
- `1000000` → `1,000,000`

This is implemented in `ReportFormatter::bangladeshNumber()` which uses PHP's `number_format()` function.

## Package Dependencies

This module uses the `turbo-stream-export` package with these modifications:
- Custom `ProcessReportExportJob` for closure-based column support
- Supports streaming exports for CSV and SQL formats
- Supports memory-based exports for XLSX, PDF, DOCX formats
- Fixed file path handling for Laravel Storage

## Troubleshooting

### Report stuck in "processing"
1. Check Redis connection: `php artisan tinker` then `Redis::ping()`
2. Check queue worker: Ensure `php artisan queue:work redis --queue=exports` is running
3. Manually sync: Call `/api/reports/{id}/progress` to sync from cache
4. Retry report: Use `/api/reports/{id}/retry`

### 422 Validation Error (Unprocessable Content)
- Reports that don't require date range will accept empty `start_date` and `end_date`
- Only these reports require date range:
  - Attendance: daily, monthly, late_trends, overtime
  - Leave: encashment, availed

### PDF export not working
1. Check TCPDF installation: `composer show tecnickcom/tcpdf`
2. Check memory limit: PDF exports require 2GB+ memory for large datasets
3. For 50,000+ records, PDF export will timeout - consider using CSV format
4. Check disk space: Ensure storage/app/exports has available space

### XLSX export memory issues
1. XLSX requires 2GB memory for large datasets (50,000+ records)
2. The driver now uses running totals instead of storing all records
3. Increase memory limit: `EXPORT_MEMORY_LIMIT_XLSX=2G` in .env

### SQL/DOCX exports
- These formats work well with large datasets (tested with 47,520 records)
- Uses streaming for memory efficiency

### No data in reports
1. Verify data exists in database: `php artisan tinker` then `User::count()`
2. Check User model relationships (department, salary, attendance, leaves)
3. Check department filter if applied

### Jobs not being processed
1. Start queue worker: `php artisan queue:work redis --queue=exports`
2. Check for failed jobs: `php artisan queue:failed`
3. Retry failed jobs: `php artisan queue:retry all`

## File Storage

Reports are stored in `storage/app/exports/` by default. To change:
```env
FILESYSTEM_DISK=local
```

## Performance Tips

1. **Use Redis**: Ensure `QUEUE_CONNECTION=redis` in `.env`
2. **Index columns**: Add indexes on `user_id`, `department_id`, `join_date`
3. **Chunk processing**: Large reports processed in chunks of 5000 records
4. **Cleanup**: Delete old reports regularly to free disk space
5. **Memory**: For large exports, use CSV or SQL format (streaming)

## File Structure

```
app/
├── Console/Commands/
│   └── TestReports.php           # Testing command
├── Enums/
│   ├── ReportFormat.php         # csv, xlsx, pdf, docx, sql
│   ├── ReportStatus.php         # pending, processing, completed, failed, cancelled
│   └── ReportType.php           # 20 report types
├── Http/Controllers/
│   ├── Api/
│   │   └── ReportController.php # REST API endpoints
│   └── DepartmentController.php  # Department API
├── Jobs/
│   └── ProcessReportExportJob.php # Custom export job
├── Models/
│   ├── Report.php               # Report model
│   ├── ReportNotification.php    # Notification model
│   └── User.php                 # Employee model
└── Services/
    ├── ReportQueryBuilder.php   # Query builders for reports
    └── ReportService.php         # Report business logic

resources/js/components/
├── ReportCreator.jsx             # Create report form
└── ReportList.jsx               # Report history table

packages/turbo-stream-export/     # Export package
└── src/Contracts/Drivers/      # Export drivers (CSV, XLSX, PDF, DOCX, SQL)
```

## License

MIT
