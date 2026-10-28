<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Generic spreadsheet export used by every report and the import template.
 */
class ArrayExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     * @param  list<string>  $preamble  Lines shown above the table (school, election...)
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly string $title = 'Report',
        private readonly array $preamble = [],
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if ($this->preamble === []) {
            return $this->headings;
        }

        return [...array_map(fn ($line) => [$line], $this->preamble), [], $this->headings];
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->title) ?: 'Report', 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $headingRow = $this->preamble === [] ? 1 : count($this->preamble) + 2;

        return [
            1 => ['font' => ['bold' => true, 'size' => $this->preamble === [] ? 11 : 13]],
            $headingRow => ['font' => ['bold' => true]],
        ];
    }
}
