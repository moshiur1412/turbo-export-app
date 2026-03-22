<?php

namespace App\Services;

class ReportFormatter
{
    public static function bangladeshNumber(float|int|string $number): string
    {
        if (!is_numeric($number)) {
            return (string) $number;
        }
        
        $num = floatval($number);
        $isDecimal = $num != floor($num);
        
        if ($isDecimal) {
            $parts = explode('.', (string) $num);
            $integerPart = $parts[0];
            $decimalPart = isset($parts[1]) ? substr($parts[1], 0, 2) : '00';
            
            $result = '';
            $length = strlen($integerPart);
            
            for ($i = 0; $i < $length; $i++) {
                $posFromRight = $length - $i;
                $result = $integerPart[$i] . $result;
                
                if ($posFromRight > 3 && $posFromRight <= 8) {
                    if (($posFromRight - 4) % 2 == 0) {
                        $result = ',' . $result;
                    }
                } elseif ($posFromRight > 8) {
                    if (($posFromRight - 9) % 2 == 0) {
                        $result = ',' . $result;
                    }
                }
            }
            
            return $result . '.' . str_pad($decimalPart, 2, '0');
        }
        
        $integerPart = (string) floor($num);
        $result = '';
        $length = strlen($integerPart);
        
        for ($i = 0; $i < $length; $i++) {
            $posFromRight = $length - $i;
            $result = $integerPart[$i] . $result;
            
            if ($posFromRight > 3 && $posFromRight <= 8) {
                if (($posFromRight - 4) % 2 == 0) {
                    $result = ',' . $result;
                }
            } elseif ($posFromRight > 8) {
                if (($posFromRight - 9) % 2 == 0) {
                    $result = ',' . $result;
                }
            }
        }
        
        return $result;
    }

    public static function formatDate(string|\DateTimeInterface|null $date): string
    {
        if (empty($date)) {
            return '-';
        }
        
        try {
            if ($date instanceof \DateTimeInterface) {
                $dt = $date;
            } else {
                $dt = new \DateTime($date);
            }
            return $dt->format('d-M-y');
        } catch (\Exception $e) {
            return (string) $date;
        }
    }

    public static function formatDateTime(string|\DateTimeInterface|null $datetime): string
    {
        if (empty($datetime)) {
            return '-';
        }
        
        try {
            if ($datetime instanceof \DateTimeInterface) {
                $dt = $datetime;
            } else {
                $dt = new \DateTime($datetime);
            }
            return $dt->format('d-M-y H:i');
        } catch (\Exception $e) {
            return (string) $datetime;
        }
    }

    public static function formatHeaderName(string $column): string
    {
        $labels = [
            'id' => 'ID',
            'employee_id' => 'Employee',
            'designation_id' => 'Designation',
            'department_id' => 'Department',
            'user_id' => 'User',
            'location_id' => 'Location',
            'salary_id' => 'Salary',
            'employment_status' => 'Employment Status',
            'email_verified_at' => 'Email Verified',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
            'join_date' => 'Join Date',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
        ];
        
        $lowerColumn = strtolower($column);
        
        if (isset($labels[$lowerColumn])) {
            return $labels[$lowerColumn];
        }
        
        if (preg_match('/^(.+)id$/i', $column, $matches)) {
            return ucfirst($matches[1]);
        }
        
        return ucwords(str_replace('_', ' ', $column));
    }

    public static function formatValue(mixed $value, bool $isNumeric = false): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if (is_numeric($value)) {
            if ($isNumeric) {
                return self::bangladeshNumber(floatval($value));
            }
            return (string) $value;
        }
        
        if ($value instanceof \DateTimeInterface) {
            return self::formatDate($value);
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }

    public static function isNumericColumn(string $columnName): bool
    {
        $numericPatterns = [
            'amount',
            'salary',
            'basic',
            'gross',
            'net',
            'deduction',
            'total',
            'balance',
            'quantity',
            'count',
            'age',
            'days',
            'hours',
            'bonus',
            'allowance',
            'tax',
            'rate',
            'price',
            'cost',
            'fee',
            'commission',
            'bonus',
            'overtime',
            'late',
            'absent',
            'rent',
            'house',
            'medical',
            'transport',
            'convance',
            'utility',
            'phone',
        ];
        
        $lowerColumn = strtolower($columnName);
        
        if (str_ends_with($lowerColumn, '_id') && strlen($lowerColumn) < 20) {
            return false;
        }
        
        if ($lowerColumn === 'id') {
            return false;
        }
        
        foreach ($numericPatterns as $pattern) {
            if (str_contains($lowerColumn, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    public static function buildFilterString(array $filters): string
    {
        $parts = [];
        
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $startDate = self::formatDate($filters['start_date']);
            $endDate = self::formatDate($filters['end_date']);
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $parts[] = "<b>Date Range:</b> {$startDate} To {$endDate}";
            }
            unset($filters['start_date'], $filters['end_date']);
        }
        
        if (isset($filters['date'])) {
            $parts[] = "<b>Date:</b> " . self::formatDate($filters['date']);
            unset($filters['date']);
        }
        
        foreach ($filters as $key => $value) {
            if (empty($value)) {
                continue;
            }
            
            $label = self::formatHeaderName($key);
            
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            $parts[] = "<b>{$label}:</b> {$value}";
        }
        
        if (empty($parts)) {
            return '';
        }
        
        return 'Filters: ' . implode(' | ', $parts);
    }

    public static function getFooterText(int $pageNumber, int $totalRecords = null): string
    {
        $printDate = self::formatDateTime(now());
        $printOn = "Print On: {$printDate}";
        
        if ($pageNumber === 0) {
            $pageNumber = 1;
        }
        
        if ($totalRecords !== null && $totalRecords > 0) {
            $totalFormatted = self::bangladeshNumber($totalRecords);
            $pageInfo = "Page {$pageNumber} of {$totalFormatted}";
        } else {
            $pageInfo = "Page {$pageNumber}";
        }
        
        return "{$printOn}                    {$pageInfo}";
    }

    public static function buildReportHeader(string $reportName, array $filters = [], bool $stripHtml = true): array
    {
        $lines = [];
        
        $lines[] = $reportName;
        
        if (!empty($filters)) {
            $filterParts = [];
            
            if (isset($filters['start_date']) && isset($filters['end_date'])) {
                $startDate = self::formatDate($filters['start_date']);
                $endDate = self::formatDate($filters['end_date']);
                if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                    $part = "Date Range: {$startDate} To {$endDate}";
                    $filterParts[] = $stripHtml ? $part : "<b>{$part}</b>";
                }
                unset($filters['start_date'], $filters['end_date']);
            }
            
            if (isset($filters['date'])) {
                $part = "Date: " . self::formatDate($filters['date']);
                $filterParts[] = $stripHtml ? $part : "<b>{$part}</b>";
                unset($filters['date']);
            }
            
            foreach ($filters as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                
                $label = self::formatHeaderName($key);
                
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                
                $part = "{$label}: {$value}";
                $filterParts[] = $stripHtml ? $part : "<b>{$part}</b>";
            }
            
            if (!empty($filterParts)) {
                $lines[] = 'Filters: ' . implode(' | ', $filterParts);
            }
        }
        
        return $lines;
    }
}
