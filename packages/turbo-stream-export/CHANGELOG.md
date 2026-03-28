# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-26

### Added
- **100M+ Records Support**: Optimized for massive datasets
- **5 Export Formats**: CSV, XLSX, PDF, DOCX, SQL
- **Auto Chunk Sizing**: Automatically adjusts chunk size (5K-20K) based on data volume
- **Filter Names in Filename**: Downloaded files include applied filters
- **Comprehensive Test Suite**: Unit, Feature, and LargeData tests
- **Performance Monitoring**: Progress logging and performance tracking
- **High Priority Queue**: Support for urgent exports
- **Unified PDF Driver**: Simple reports, subtotals, grand totals, colspan/rowspan support
- **Streaming Export**: Memory-efficient export for 100M+ records

### Features
- Export any Eloquent model to multiple formats
- Filter exports with custom where clauses
- Configurable chunk sizes
- Multiple queue driver support (Redis, Database)
- RESTful API controller ready
- Signed URL generation for secure downloads
- Support for Laravel 9, 10, and 11

### Formats
- `csv` - Fastest, best for 100M+ records (streaming)
- `xlsx` - Excel format with styled headers
- `pdf` - Professional document with pagination
- `docx` - Word document with tables
- `sql` - Database import ready with CREATE TABLE

### Configuration
- `large_data_chunk_size` - Chunk size for large exports
- `include_filter_in_filename` - Include filters in filename
- `memory_limit` - Memory limit for exports
- `log_progress_interval` - Progress logging frequency
