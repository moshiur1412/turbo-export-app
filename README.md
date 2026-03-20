# Turbo-Stream Export Engine

A high-performance Laravel 11 package for exporting large datasets (CSV/Excel) using chunked queries with async processing via Redis queues.

## System Purpose

This application provides a **scalable data export system** that allows authenticated users to export large datasets from any Eloquent model. It uses chunked queries and background processing to handle 100k+ records efficiently without memory issues.

**Use Cases:**
- Export user lists, transaction reports, or any model data
- Generate downloadable reports in CSV/Excel format
- Process large datasets in the background without blocking the user
- Salary reports with automated deductions (late attendance, unpaid leave)
- Custom dynamic reports with joins and filters

## Features

- **Memory Efficient**: Uses chunked queries to process 100k+ records under 50MB memory
- **Async Processing**: Laravel Queues with Redis for background processing
- **Real-time Progress**: Live progress tracking via Redis with polling
- **Secure Downloads**: Signed URLs for secure file downloads
- **Modern Testing**: Pest PHP unit and integration tests
- **PSR-12 Compliant**: Strict typing and PSR coding standards
- **Salary Reports**: Automated salary deduction reports (late days, leave cuts)
- **Dynamic Reports**: Build custom queries with joins, filters, and aggregations

## Tech Stack

- Laravel 11
- PHP 8.3+
- React (Inertia.js)
- MySQL
- Redis
- Pest Testing
- Tailwind CSS

---

## Installation

### Prerequisites

- PHP 8.3+ with extensions: `pdo_mysql`, `json`, `mbstring`, `xml`, `zip`
- Composer
- MySQL 8.0+
- Redis Server
- Node.js 18+ (for frontend assets)

### 1. Clone & Install Dependencies

```bash
git clone <repository-url>
cd turbo-export-app
composer install
npm install
```

### 2. Configure Environment

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` with your database and Redis settings:

```env
APP_NAME="Turbo Export"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

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
REDIS_PASSWORD=null

SESSION_DRIVER=file
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Redis Installation

#### Option A: Memurai (Recommended for Windows)

1. Download Memurai from https://www.memurai.com/get-memurai
2. Install and start the Memurai service
3. Memurai is compatible with Redis and works out of the box

#### Option B: Redis on Wamp64

1. Download Redis for Windows from:
   - https://github.com/microsoftarchive/redis/releases (older but stable)
   - https://github.com/tporadowski/redis/releases (more recent builds)

2. Extract to a folder (e.g., `C:\redis`)

3. Run Redis server:
   ```bash
   cd C:\redis
   redis-server.exe
   ```

#### Option C: Enable PHP Redis Extension in Wamp

1. Right-click Wamp icon → PHP → PHP Extensions → check `php_redis`
2. Restart all Wamp services

#### Option D: Use Docker

```bash
docker run -d -p 6379:6379 redis:latest
```

#### Option E: Laravel Sail / Database Queue (Alternative to Redis)

If Redis is unavailable, use the database queue driver:

```env
QUEUE_CONNECTION=database
CACHE_DRIVER=file
```

Then run queue worker:
```bash
php artisan queue:work
```

### 5. Create Database

Create a MySQL database named `turbo_export`:
```sql
CREATE DATABASE turbo_export CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 6. Run Migrations & Seed Data

```bash
php artisan migrate
php artisan db:seed
```

**Default login credentials (after seeding):**
- Admin: `admin@example.com` / `password`
- Employees: `employee1@example.com` to `employee1000@example.com` / `password`

**Quick Setup:**
```bash
php artisan db:fresh-seed --scale=small --seed --force
```

### 7. Large Scale Data Seeding

Generate test data for stress testing the export system with large datasets.

**Unified Commands:**

```bash
# Fresh database + seed in one command (recommended)
php artisan db:fresh-seed --scale=medium --seed --force

# Just seed data without refreshing (if database already exists)
php artisan seed:large --scale=medium
```

**Available Scales:**

| Scale | Employees | Attendance | Est. Records | Est. Time | Use Case |
|-------|-----------|------------|--------------|-----------|----------|
| `small` | 1,000 | 3 months | ~75K | ~5s | Quick testing |
| `medium` | 10,000 | 6 months | ~1.3M | ~40s | Standard testing |
| `large` | 50,000 | 24 months | ~31M | ~15-30min | Stress testing |
| `xlarge` | 100,000 | 60 months | ~156M | ~1-2h | Load testing |
| `xxlarge` | 200,000 | 60 months | ~312M | ~3-4h | Extreme load |

**Usage Examples:**

```bash
# Quick test (~1K employees)
php artisan db:fresh-seed --scale=small --seed --force

# Standard testing (~10K employees) - Recommended
php artisan db:fresh-seed --scale=medium --seed --force

# Stress testing (~50K employees)
php artisan db:fresh-seed --scale=large --seed --force

# Skip attendance for faster seeding
php artisan db:fresh-seed --scale=large --seed --force --skip-attendance

# Seed additional data without refresh
php artisan seed:large --scale=medium
```

**Progress Tracking:**

The seeder shows real-time progress logs:
```
[100/50000] Employees inserted
[200/50000] Employees inserted
...
✅ Created 50,000 employees
Target: 31,200,000 records
  [156000/31200000] Attendance records inserted
  [468000/31200000] Attendance records inserted
...
✅ Attendance: 31,200,000 records
✅ Leave records: 750,000
✅ Leave balances: 1,200,000

🎉 OPERATION COMPLETE!
```

**Data Generated (Example - large scale):**

| Table | Records |
|-------|---------|
| Departments | 106 |
| Designations | 120 |
| Users | 50,000 |
| Salaries | 50,000 |
| Attendance | ~31,200,000 |
| Leaves | 750,000 |
| Leave Balances | 1,200,000 |

**Important:**
- Large scales (large/xlarge/xxlarge) require significant time and disk space
- Ensure sufficient database disk space (~1.5GB for large scale)
- Monitor MySQL `max_allowed_packet` setting:
  ```sql
  SET GLOBAL max_allowed_packet=256M;
  ```
- For very large datasets, run during off-peak hours
- Use `--force` flag to skip confirmation prompt

---

## Running the Application

### Start the Development Server

```bash
php artisan serve --port=8000
```

### Start the Queue Worker (Background Processing)

In a separate terminal:

```bash
php artisan queue:work redis --queue=exports
```

For production, use Supervisor or similar process managers.

### Build Frontend Assets (Optional)

```bash
npm run dev
```

---

## Usage Guide

### Authentication

All export endpoints require a Bearer token. Get one via login:

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

**Response:**
```json
{
    "token": "2|abc123...",
    "user": {
        "id": 1,
        "name": "Admin",
        "email": "admin@example.com"
    }
}
```

### Create Export Job

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

**Response (202 Accepted):**
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

**Response:**
```json
{
    "progress": 75,
    "total": 10000,
    "status": "processing",
    "file_path": null
}
```

When completed:
```json
{
    "progress": 100,
    "total": 10000,
    "status": "completed",
    "file_path": "exports/users_export.csv",
    "updated_at": "2026-03-19T10:30:00Z"
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

---

## API Endpoints Reference

### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/login` | No | Get authentication token |
| POST | `/api/logout` | Yes | Revoke current token |

### Data Export

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/exports` | Yes | Create new export job |
| GET | `/api/exports/{id}/progress` | No | Check export status |
| GET | `/api/exports/{id}/download` | No | Download exported file |

### Salary Reports

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/reports/salary` | Yes | Generate salary report with deductions |
| POST | `/api/reports/salary/export` | Yes | Export salary report to file |

### Dynamic Reports

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/reports/dynamic` | Yes | Create dynamic query reports |

---

## Request Parameters

### POST /api/exports

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| model | string | Yes | Full model class path |
| columns | array | Yes | Columns to export |
| format | string | No | `csv` (default) |
| filename | string | No | Custom filename (without extension) |
| filters | array | No | Query where clauses |

### POST /api/reports/salary

Generate salary report with automatic deductions for late attendance and unpaid leave.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| start_date | date | Yes | Report period start |
| end_date | date | Yes | Report period end |
| department_ids | array | No | Filter by departments |
| user_ids | array | No | Filter by specific users |
| format | string | No | `csv`, `xlsx`, `pdf`, `docx` |

**Deduction Rules:**
- **Late Attendance:** Every 3 late days = 1 day salary cut
- **Unpaid Leave:** Deducted if no leave balance available

**Example:**
```bash
curl -X POST http://localhost:8000/api/reports/salary \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "start_date": "2024-03-01",
    "end_date": "2024-03-31",
    "department_ids": [1, 2],
    "format": "csv"
  }'
```

### POST /api/reports/salary/export

Export salary report data to various formats.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| start_date | date | Yes | Report period start |
| end_date | date | Yes | Report period end |
| department_ids | array | No | Filter by departments |
| user_ids | array | No | Filter by specific users |
| format | string | Yes | `csv`, `xlsx`, `pdf`, `docx` |
| filename | string | No | Custom filename |

**Example:**
```bash
curl -X POST http://localhost:8000/api/reports/salary/export \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "start_date": "2024-03-01",
    "end_date": "2024-03-31",
    "department_ids": [1, 2],
    "format": "csv",
    "filename": "march_salary_report"
  }'
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "employee_id": "EMP001",
            "employee_name": "John Doe",
            "department": "Engineering",
            "designation": "Developer",
            "basic_salary": 50000,
            "late_days": 5,
            "late_deduction_days": 1,
            "leave_without_balance": 2,
            "leave_deduction_amount": 3333.33,
            "total_deduction_days": 3,
            "total_deduction_amount": 5000,
            "final_salary": 45000
        }
    ],
    "meta": {
        "period": "Mar 2024",
        "total_employees": 50,
        "total_late_days": 120,
        "total_deductions": 25000
    }
}
```

### POST /api/reports/dynamic

Create dynamic query reports with joins, filters, and aggregations.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| model | string | Yes | Model name (e.g., `User`, `Attendance`) |
| columns | array | Yes | Columns to select (use `table.column` format) |
| joins | array | No | Table joins |
| filters | array | No | Query filters |
| aggregations | array | No | Aggregate functions |
| start_date | date | No | Date range start |
| end_date | date | No | Date range end |
| date_column | string | No | Date column for filtering |
| format | string | No | `csv`, `xlsx`, `pdf`, `docx`, `sql` |

**Important:** Use fully qualified column names with table prefix (e.g., `users.name`, `departments.name`) to avoid ambiguity errors.

**Examples:**

1. Simple User Export:
```bash
curl -X POST http://localhost:8000/api/reports/dynamic \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "User",
    "columns": ["users.name", "users.email", "users.status"],
    "filters": [
      {"column": "users.status", "value": "active", "operator": "="}
    ]
  }'
```

2. With Joins (User + Department):
```bash
curl -X POST http://localhost:8000/api/reports/dynamic \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "User",
    "columns": ["users.name", "users.email", "departments.name as department"],
    "joins": [
      {"table": "departments", "first": "users.department_id", "second": "departments.id"}
    ],
    "filters": [
      {"column": "users.status", "value": "active", "operator": "="}
    ]
  }'
```

3. With Aggregations (Attendance Summary):
```bash
curl -X POST http://localhost:8000/api/reports/dynamic \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "Attendance",
    "columns": ["user_id"],
    "aggregations": [
      {"column": "id", "function": "COUNT", "alias": "total_days"},
      {"column": "status", "function": "COUNT", "alias": "present_days"}
    ],
    "filters": [
      {"column": "attendances.status", "value": "present", "operator": "="}
    ],
    "group_by": "user_id"
  }'
```

4. With Date Range:
```bash
curl -X POST http://localhost:8000/api/reports/dynamic \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "Attendance",
    "columns": ["user_id", "status", "attendance_date"],
    "start_date": "2024-03-01",
    "end_date": "2024-03-31",
    "date_column": "attendance_date"
  }'
```

5. Complex Report (Users with Salaries):
```bash
curl -X POST http://localhost:8000/api/reports/dynamic \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "User",
    "columns": [
      "users.employee_id",
      "users.name",
      "departments.name as department",
      "designations.name as designation",
      "salaries.basic_salary",
      "salaries.net_salary"
    ],
    "joins": [
      {"table": "departments", "first": "users.department_id", "second": "departments.id"},
      {"table": "designations", "first": "users.designation_id", "second": "designations.id"},
      {"table": "salaries", "first": "users.salary_id", "second": "salaries.id"}
    ],
    "filters": [
      {"column": "users.status", "value": "active", "operator": "="}
    ]
  }'
```

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           EXPORT PROCESS FLOW                                │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────┐     ┌──────────────┐     ┌─────────────┐     ┌────────────┐
    │  Client  │────▶│   Login API  │────▶│  Get Token  │────▶│  Export API│
    │ (Postman)│     │ /api/login   │     │             │     │/api/exports│
    └──────────┘     └──────────────┘     └─────────────┘     └─────┬──────┘
                                                                       │
                                                                       ▼
                                     ┌──────────────────────────────────────┐
                                     │         ExportController            │
                                     │         - validate request          │
                                     │         - check authorization       │
                                     │         - dispatch job              │
                                     └──────────────────┬───────────────────┘
                                                        │
                                                        ▼
                                     ┌──────────────────────────────────────┐
                                     │       ProcessExportJob (Queue)      │
                                     │       - process in background        │
                                     │       - chunk data                   │
                                     │       - write to CSV                │
                                     └──────────────────┬───────────────────┘
                                                        │
                                                        ▼
                                     ┌──────────────────────────────────────┐
                                     │          ExportService               │
                                     │       - manage export state          │
                                     │       - track progress               │
                                     └──────────────────────────────────────┘
                                                        │
                                     ┌────────────────┴────────────────┐
                                     ▼                                 ▼
                          ┌─────────────────┐              ┌─────────────────┐
                          │  Storage Disk   │              │  Progress Cache │
                          │  (exports/)     │              │  (Redis/File)   │
                          └─────────────────┘              └─────────────────┘
```

## Postman Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          POSTMAN WORKFLOW                                   │
└─────────────────────────────────────────────────────────────────────────────┘

     STEP 1: LOGIN                                                          STEP 2: CREATE EXPORT
     POST /api/login                STEP 3: CHECK PROGRESS                POST /api/exports
     {"email":"...", "password":"..."}      │     {"model":"...", "columns":[...]}
              │                              │               │
              ▼                              ▼               │
     ┌─────────────────┐              ┌─────────────────┐   │
     │ Get Bearer Token│              │  export_id      │───┘
     └────────┬────────┘              │  status: queued │
              │                       └─────────────────┘
              │                                │
              │                                ▼
              │                       ┌─────────────────┐
              │                       │  poll progress  │
              │                       │  until complete │
              │                       └────────┬────────┘
              │                                │
              │                                ▼
              │                       ┌─────────────────┐
              └──────────────────────▶│  DOWNLOAD FILE  │
                                      │ GET /download   │
                                      └─────────────────┘
```

## Configuration

### Queue Connection Priority

1. **Redis** (Recommended for production)
2. **Database** (Simple setup, slower)
3. **Sync** (Testing only, blocks request)

### Environment Variables

```env
# Queue settings
QUEUE_CONNECTION=redis        # redis, database, sync
QUEUE_NAME=exports

# Cache settings  
CACHE_DRIVER=redis            # redis, file, array

# Export settings
EXPORT_QUEUE=exports          # Queue name for export jobs
EXPORT_DISK=local            # Storage disk for exports
EXPORT_CHUNK_SIZE=1000       # Records per chunk
```

## Database Schema (ERD)

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              DATABASE ERD - HRM SYSTEM                              │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│  designations    │       │   departments    │       │    salaries      │
├──────────────────┤       ├──────────────────┤       ├──────────────────┤
│ id (PK)          │       │ id (PK)          │       │ id (PK)          │
│ name             │       │ name             │       │ basic_salary     │
│ code (unique)    │       │ code (unique)    │       │ house_rent       │
│ description      │       │ location         │       │ medical_allowance│
│ min_salary       │       │ head_id          │       │ transport_allow. │
│ max_salary       │       │ description      │       │ special_allowance│
│ created_at       │       │ created_at       │       │ provident_fund   │
│ updated_at       │       │ updated_at       │       │ tax              │
└────────┬─────────┘       └────────┬─────────┘       │ gross_salary     │
         │                          │                 │ net_salary       │
         │                          │                 │ effective_date   │
         │                          │                 │ end_date         │
         │                          │                 │ is_active        │
         │                          │                 │ created_at       │
         │                          │                 │ updated_at       │
         │                          │                 └────────┬─────────┘
         │                          │                          │
         │                          │                          │
    1:N ▼                          │                          │ 1:N
┌─────────────────────────────────┴──────────────────────────┴──────────────────────┐
│                                    users                                          │
├────────────────────────────────────────────────────────────────────────────────────┤
│ id (PK)                                                                           │
│ employee_id (unique)  ◄── Unique employee identifier                              │
│ name                                                                           │
│ email (unique)                                                                   │
│ password                                                                       │
│ designation_id (FK) ──► designations(id)  [nullOnDelete]                         │
│ department_id (FK) ──► departments(id)  [nullOnDelete]                           │
│ salary_id (FK) ─────► salaries(id)     [nullOnDelete]                           │
│ attendance_id (FK) ──► attendances(id) [nullOnDelete] (deprecated - use 1:N)    │
│ join_date                                                                     │
│ status (active|inactive|on_leave|terminated)                                    │
│ email_verified_at                                                              │
│ remember_token                                                                 │
│ created_at                                                                     │
│ updated_at                                                                     │
└─────────────────────────────────────────────────┬───────────────────────────────┘
                                                  │
                    ┌─────────────────────────────┼─────────────────────────────┐
                    │ 1:N                         │ 1:N                         │ 1:N
                    ▼                             ▼                             ▼
┌──────────────────────┐    ┌──────────────────────┐    ┌──────────────────────────┐
│    attendances       │    │       leaves         │    │    leave_balances        │
├──────────────────────┤    ├──────────────────────┤    ├──────────────────────────┤
│ id (PK)              │    │ id (PK)              │    │ id (PK)                  │
│ user_id (FK) ──► users│   │ user_id (FK) ──► users│   │ user_id (FK) ──► users   │
│ attendance_date      │    │ leave_type           │    │ year                     │
│ check_in             │    │ start_date           │    │ month                    │
│ check_out            │    │ end_date             │    │ casual_leave             │
│ worked_hours         │    │ days                 │    │ sick_leave               │
│ status (enum)        │    │ reason               │    │ annual_leave             │
│ notes                │    │ status               │    │ used_casual              │
│ created_at           │    │ is_paid              │    │ used_sick                │
│ updated_at           │    │ approved_by (FK)     │    │ used_annual              │
└──────────────────────┘    │ approved_at          │    │ created_at               │
     UNIQUE(user_id,date)   │ created_at           │    │ updated_at               │
                             │ updated_at           │    └──────────────────────────┘
                             └──────────────────────┘         UNIQUE(user,year,month)
                             INDEX(user_id, start_date, end_date)


┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                    RELATIONSHIP SUMMARY                             │
└─────────────────────────────────────────────────────────────────────────────────────┘

  designations  (1) ────── (N)  users
  departments   (1) ────── (N)  users
  salaries      (1) ────── (N)  users
  users         (1) ────── (N)  attendances
  users         (1) ────── (N)  leaves
  users         (1) ────── (N)  leave_balances


┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                    TABLE DESCRIPTIONS                              │
└─────────────────────────────────────────────────────────────────────────────────────┘

  • users            - Employee records with employee_id, status tracking
  • designations     - Job titles/positions (e.g., Manager, Developer, Designer)
  • departments     - Organizational units (e.g., IT, HR, Finance)
  • salaries        - Salary components (basic, house rent, allowances, deductions)
  • attendances     - Daily attendance tracking per employee per date
  • leaves          - Leave requests (approved/rejected/pending)
  • leave_balances  - Monthly leave balance per employee
```

---

## Salary Deduction Rules

The salary report automatically calculates deductions based on:

### Late Attendance
- **Rule**: Every **3 late days** = **1 day salary cut**
- **Calculation**: `late_days / 3 = deduction_days`

### Unpaid Leave
- **Rule**: If employee takes unpaid leave and has no leave balance
- **Calculation**: `(unpaid_leave_days - available_balance) * daily_rate`

### Daily Rate Formula
```
daily_rate = basic_salary / 30
```

### Example
```
Employee: John Doe
Basic Salary: 30,000
Late Days: 5 (results in 1 day deduction)
Unpaid Leave: 3 days, Available Balance: 1 day

Deductions:
- Late: 5/3 = 1.67 ≈ 1 day cut
- Leave: (3-1) = 2 days unpaid
- Total Days: 3 days
- Daily Rate: 30,000/30 = 1,000
- Total Deduction: 3,000
- Final Salary: 27,000
```

---

## Folder Structure

```
turbo-export-app/
├── app/
│   ├── Console/Commands/
│   │   ├── FreshSeed.php               # Fresh DB + seed in one command
│   │   └── SeedLargeData.php           # Seed large data without refresh
│   ├── Http/Controllers/
│   │   ├── AuthController.php
│   │   └── SalaryReportController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Attendance.php
│   │   ├── Salary.php
│   │   ├── Department.php
│   │   ├── Designation.php
│   │   ├── Leave.php
│   │   └── LeaveBalance.php
│   └── Services/
│       ├── SalaryReportService.php
│       └── ReportBuilder.php
├── packages/turbo-stream-export/
│   └── src/
│       ├── Http/Controllers/
│       │   └── ExportController.php
│       ├── Jobs/
│       │   └── ProcessExportJob.php
│       ├── Services/
│       │   ├── ExportService.php
│       │   └── Contracts/
│       │       ├── ExportDriverInterface.php
│       │       └── Drivers/
│       │           ├── CsvExportDriver.php
│       │           ├── XlsxExportDriver.php
│       │           ├── PdfExportDriver.php
│       │           └── DocxExportDriver.php
│       ├── Providers/
│       │   └── TurboStreamExportServiceProvider.php
│       └── config/
│           └── turbo-export.php
├── routes/
│   ├── api.php
│   └── web.php
└── database/
    ├── migrations/
    └── seeders/
        ├── DatabaseSeeder.php
        └── GeneralDataSeeder.php   # Unified seeder (111+ departments, 118+ designations)
```

## Testing

```bash
# Run all tests
./vendor/bin/pest

# Run unit tests
./vendor/bin/pest tests/Unit

# Run feature tests
./vendor/bin/pest tests/Feature

# Run with coverage
./vendor/bin/pest --coverage
```

## Security

- **Authentication**: Bearer token required for export creation
- **Authorization**: Gate checks for export/download permissions
- **Signed URLs**: Download endpoint requires valid signature
- **File Access**: Exported files stored outside public directory

## Troubleshooting

### "Class Redis not found"
Redis PHP extension is not installed. See [Redis Installation](#4-redis-installation) section.

### "Serialization of PDO not allowed"
Fixed - the job now serializes only model class and filters, not the query builder.

### Queue jobs not processing
Ensure the queue worker is running:
```bash
php artisan queue:work redis --queue=exports
```

### Export stuck in "processing"
Check Laravel logs: `storage/logs/laravel.log`

### Large Seeding: "MySQL server has gone away"
Increase `max_allowed_packet` in MySQL:
```ini
max_allowed_packet=256M
```
Or in MySQL console:
```sql
SET GLOBAL max_allowed_packet=268435456;
```

### Large Seeding: Out of memory
The commands already set `memory_limit` to 512M internally. For very large scales, run:
```bash
php -d memory_limit=1G artisan db:fresh-seed --scale=xlarge --seed --force
```

### Large Seeding: Slow performance
The seeder already optimizes by:
- Disabling keys during insert
- Using batch inserts (500 records per batch)
- Garbage collecting between batches

For additional optimization:
```sql
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;
SET autocommit = 0;
```
Remember to re-enable after seeding.

### Large Seeding: "Duplicate entry" or table errors
Always run fresh first:
```bash
php artisan db:fresh-seed --scale=medium --seed --force
```

### Check seeding progress
```bash
php artisan tinker --execute="echo 'Users: ' . \App\Models\User::count() . PHP_EOL . 'Attendance: ' . \App\Models\Attendance::count();"
```

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
