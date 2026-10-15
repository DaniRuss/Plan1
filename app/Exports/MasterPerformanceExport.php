<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MasterPerformanceExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $data;
    protected $year;
    protected $department;

    public function __construct($data, $year, $department)
    {
        $this->data = collect($data);
        $this->year = $year;
        $this->department = $department;
    }

    public function collection()
    {
        $rows = [];
        foreach ($this->data as $item) {
            $row = [
                $item['code'],
                $item['description'],
                $item['weight'],
                $item['unit'],
                $item['baseline'],
                is_null($item['annual_target']) ? 'N/A' : $item['annual_target'],
            ];

            foreach ($this->periodGroups() as $group) {
                foreach ($group['months'] as $month) {
                    $cell = $item['months'][$month];
                    $row[] = ($cell['planned'] ?? false) ? (($cell['is_rollup'] ?? false) ? 'Roll-up' : $cell['target']) : 'N/A';
                    $row[] = $this->periodDisplay($cell, (float) $item['weight']);
                }
                $quarter = $group['quarter'];
                $cell = $item['quarters'][$quarter];
                $row[] = ($cell['planned'] ?? false) ? (($cell['is_rollup'] ?? false) ? 'Roll-up' : $cell['target']) : 'N/A';
                $row[] = $this->periodDisplay($cell, (float) $item['weight']);
            }

            $row[] = $item['performers'];
            $rows[] = $row;
        }

        return collect($rows);
    }

    public function headings(): array
    {
        $h1 = ['S.No', 'Strategic Objectives and Activities', 'Weight', 'Unit', 'Baseline', 'Annual Target'];
        foreach ($this->periodGroups() as $group) {
            foreach ($group['months'] as $month) {
                $h1[] = ucfirst(substr($month, 0, 3));
                $h1[] = '';
            }
            $h1[] = strtoupper($group['quarter']);
            $h1[] = '';
        }
        $h1[] = 'Performers';

        $h2 = ['', '', '', '', '', ''];
        for ($i=0; $i<16; $i++) { $h2[] = 'Target'; $h2[] = 'Actual'; }
        $h2[] = '';

        return [$h1, $h2];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = max(3, $sheet->getHighestRow());
        $sheet->getStyle('C3:'.$sheet->getHighestColumn().$lastRow)
            ->getNumberFormat()->setFormatCode('#,##0.###');
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');
        $sheet->mergeCells('C1:C2');
        $sheet->mergeCells('D1:D2');
        $sheet->mergeCells('E1:E2');
        $sheet->mergeCells('F1:F2');

        // Merge month headers
        $col = 7;
        for ($i=0; $i<16; $i++) {
            $start = $this->getNameFromNumber($col);
            $end = $this->getNameFromNumber($col + 1);
            $sheet->mergeCells($start . '1:' . $end . '1');
            $col += 2;
        }

        $sheet->getStyle('A1:AJ2')->getFont()->setBold(true);
        $sheet->getStyle('A1:AJ2')->getAlignment()->setHorizontal('center');
        
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            2 => ['font' => ['bold' => true]],
        ];
    }

    private function getNameFromNumber($num) {
        $numeric = ($num - 1) % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval(($num - 1) / 26);
        if ($num2 > 0) {
            return $this->getNameFromNumber($num2) . $letter;
        } else {
            return $letter;
        }
    }

    private function periodGroups(): array
    {
        return [
            ['months' => ['july', 'august', 'september'], 'quarter' => 'q1'],
            ['months' => ['october', 'november', 'december'], 'quarter' => 'q2'],
            ['months' => ['january', 'february', 'march'], 'quarter' => 'q3'],
            ['months' => ['april', 'may', 'june'], 'quarter' => 'q4'],
        ];
    }

    private function periodDisplay(array $cell, float $originalWeight): string
    {
        if (!($cell['planned'] ?? false)) return 'N/A';
        if (!($cell['reported'] ?? false)) return 'Not Reported';
        $actual = ($cell['is_rollup'] ?? false) ? 'Roll-up' : \App\Support\NumberFormatter::format($cell['actual']);
        $display = $actual . ' | Raw ' . \App\Support\NumberFormatter::format((float) $cell['raw_achievement'],3) . '% | Score ' . \App\Support\NumberFormatter::format((float) $cell['scorecard_achievement'],3) . '% | ' . $cell['status'];
        if (!($cell['is_rollup'] ?? false) && abs((float) ($cell['effective_weight'] ?? 0) - $originalWeight) > 0.0001) {
            $display .= ' | Effective weight ' . \App\Support\NumberFormatter::format((float) $cell['effective_weight'],3);
        }
        if (($cell['coverage'] ?? 100) < 100) $display .= ' | Coverage ' . \App\Support\NumberFormatter::format((float) $cell['coverage'],3) . '%';
        return $display;
    }
}
