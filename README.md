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

| Format | Description | Driver |
|--------|-------------|--------|
| CSV | Comma-separated values | Streaming |
| XLSX | Excel spreadsheet | Memory-based |
| PDF | Portable document format | Memory-based |
| DOCX | Word document | Memory-based |
| SQL | SQL INSERT statements | Streaming |

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
2. Check memory limit: Set `memory_limit = -1` in php.ini for large reports
3. Check disk space: Ensure storage/app/exports has available space

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
