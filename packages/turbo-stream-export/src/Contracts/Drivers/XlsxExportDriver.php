<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TurboStreamExport\Contracts\ExportDriverInterface;

class XlsxExportDriver implements ExportDriverInterface
{
    private Spreadsheet $spreadsheet;
    private int $currentRow = 1;
    private string $tempFile;

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->getProperties()
            ->setCreator('TurboStream Export Engine')
            ->setTitle('Export Data')
            ->setSubject('Data Export');
    }

    public function getFormat(): string
    {
        return 'xlsx';
    }

    public function getContentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function getFileExtension(): string
    {
        return 'xlsx';
    }

    public function writeHeader(array $columns, $sheet = null): void
    {
        $sheet = $sheet ?? $this->spreadsheet->getActiveSheet();
        
        $headerStyle = new \PhpOffice\PhpSpreadsheet\Style\Fill();
        $headerStyle->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getStartColor()->setRGB('4472C4');
        
        $colIndex = 'A';
        foreach ($columns as $column) {
            $cell = $colIndex . '1';
            $sheet->setCellValue($cell, $this->formatHeaderName($column));
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($cell)->getFill()->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
            $colIndex++;
        }
        
        $this->currentRow = 2;
    }

    public function writeRow(array $data, $sheet = null): void
    {
        $sheet = $sheet ?? $this->spreadsheet->getActiveSheet();
        $colIndex = 'A';
        
        foreach ($data as $value) {
            $sheet->setCellValue($colIndex . $this->currentRow, $this->formatValue($value));
            $colIndex++;
        }
        
        $this->currentRow++;
    }

    public function writeBatch($records, array $columns, $sheet = null): void
    {
        $sheet = $sheet ?? $this->spreadsheet->getActiveSheet();
        
        foreach ($records as $record) {
            $colIndex = 'A';
            foreach ($columns as $column) {
                $value = data_get($record, $column);
                $sheet->setCellValue($colIndex . $this->currentRow, $this->formatValue($value));
                $colIndex++;
            }
            $this->currentRow++;
        }
    }

    public function finalize($handle = null, string $filePath): string
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'turbo_export_');
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($this->tempFile);
        
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        copy($this->tempFile, $filePath);
        unlink($this->tempFile);
        
        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);
        
        return $filePath;
    }

    public function getSpreadsheet(): Spreadsheet
    {
        return $this->spreadsheet;
    }

    private function formatHeaderName(string $column): string
    {
        return ucwords(str_replace(['_', '.'], ' ', $column));
    }

    private function formatValue(mixed $value): mixed
    {
        if (is_null($value)) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        return $value;
    }
}
