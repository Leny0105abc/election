<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Renders a ReportService report (summary + sections) into one worksheet.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** @var list<int> rows (1-based) to render in bold */
    private array $boldRows = [];

    /** @var list<list<mixed>> */
    private array $rows = [];

    /**
     * @param  array<string, mixed>  $report
     * @param  list<string>  $preamble
     */
    public function __construct(private readonly array $report, array $preamble)
    {
        foreach ($preamble as $line) {
            $this->push([$line], true);
        }
        $this->push([]);

        foreach ($report['summary'] as $label => $value) {
            $this->push([$label, $value]);
        }
        if ($report['summary']) {
            $this->push([]);
        }

        foreach ($report['sections'] as $section) {
            if (! empty($section['heading'])) {
                $this->push([$section['heading']], true);
            }
            if (! empty($section['note'])) {
                $this->push([$section['note']]);
            }
            $this->push($section['headings'], true);
            foreach ($section['rows'] as $row) {
                $this->push(array_values($row));
            }
            $this->push([]);
        }

        foreach ($report['notes'] as $note) {
            $this->push([$note]);
        }
    }

    /** @param list<mixed> $row */
    private function push(array $row, bool $bold = false): void
    {
        $this->rows[] = $row;
        if ($bold) {
            $this->boldRows[] = count($this->rows);
        }
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', (string) $this->report['title']) ?: 'Report', 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        return collect($this->boldRows)->mapWithKeys(fn ($r) => [$r => ['font' => ['bold' => true]]])->all();
    }
}
