<?php

declare(strict_types=1);

namespace TurboStreamExport\Contracts\Drivers;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\Style\Cell;
use TurboStreamExport\Contracts\ExportDriverInterface;

class DocxExportDriver implements ExportDriverInterface
{
    private PhpWord $phpWord;
    private \PhpOffice\PhpWord\Element\Table $table;
    private array $columns = [];
    private int $currentRowIndex = 0;
    private string $title = 'Export Data';

    public function __construct()
    {
        $this->phpWord = new PhpWord();
        $this->phpWord->getDocumentProperties()
            ->setCreator('TurboStream Export Engine')
            ->setTitle($this->title)
            ->setSubject('Data Export');
        
        $section = $this->phpWord->addSection();
        $section->addTitle($this->title, 1);
        $section->addText('Generated: ' . date('Y-m-d H:i:s'));
        $section->addTextBreak(2);
        
        $this->table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '4472C4',
            'cellMargin' => 50,
        ]);
    }

    public function getFormat(): string
    {
        return 'docx';
    }

    public function getContentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function getFileExtension(): string
    {
        return 'docx';
    }

    public function writeHeader(array $columns, $handle = null): void
    {
        $this->columns = $columns;
        $this->currentRowIndex = 0;
        
        $headerRow = $this->table->addRow();
        
        foreach ($columns as $column) {
            $cell = $headerRow->addCell();
            $cell->setWidth(2500);
            $cell->addText(
                $this->formatHeaderName($column),
                [
                    'bold' => true,
                    'color' => 'FFFFFF',
                    'size' => 11,
                ],
                [
                    'backgroundColor' => '4472C4',
                    'alignment' => 'center',
                ]
            );
        }
    }

    public function writeRow(array $data, $handle = null): void
    {
        $row = $this->table->addRow();
        $isAlternate = ($this->currentRowIndex % 2) == 0;
        
        foreach ($data as $value) {
            $cell = $row->addCell();
            $cell->setWidth(2500);
            $cell->addText(
                $this->formatValue($value),
                [
                    'size' => 10,
                ],
                [
                    'backgroundColor' => $isAlternate ? 'F2F2F2' : 'FFFFFF',
                    'valign' => 'middle',
                ]
            );
        }
        
        $this->currentRowIndex++;
    }

    public function writeBatch($records, array $columns, $handle = null): void
    {
        foreach ($records as $record) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = data_get($record, $column);
            }
            $this->writeRow($row);
        }
    }

    public function finalize(string $filePath, $handle = null): string
    {
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $writer = IOFactory::createWriter($this->phpWord, 'Word2007');
        $writer->save($filePath);
        
        unset($this->phpWord);
        
        return $filePath;
    }

    private function formatHeaderName(string $column): string
    {
        return strtoupper($this->humanize($column));
    }

    private function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return '-';
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
        
        return (string) $value;
    }

    private function humanize(string $column): string
    {
        return ucwords(str_replace(['_', '.'], ' ', $column));
    }
}
