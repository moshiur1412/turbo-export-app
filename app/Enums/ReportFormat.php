<?php

namespace App\Enums;

enum ReportFormat: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';
    case PDF = 'pdf';
    case DOCX = 'docx';
    case SQL = 'sql';

    public function label(): string
    {
        return match ($this) {
            self::CSV => 'CSV',
            self::XLSX => 'Excel (XLSX)',
            self::PDF => 'PDF',
            self::DOCX => 'Word (DOCX)',
            self::SQL => 'SQL',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::PDF => 'application/pdf',
            self::DOCX => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            self::SQL => 'application/sql',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }

    public static function options(): array
    {
        return array_map(fn ($format) => [
            'value' => $format->value,
            'label' => $format->label(),
        ], self::cases());
    }
}
