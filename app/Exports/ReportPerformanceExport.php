<?php

namespace App\Exports;

use App\Services\EdrPerformanceCalculator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReportPerformanceExport implements FromCollection, WithHeadings, WithMapping, WithEvents, WithColumnWidths
{
    private Collection $data;

    public function __construct($data, private readonly string $periodLabel)
    {
        $this->data = collect($data);
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [[
            'S.No', 'KPIs', 'Weight', 'Unit', 'Plan', 'Performance', 'Raw %',
            'Scorecard %', 'Status', 'Performer', 'Achievement Factor', 'Weighted Score', 'TOTAL',
        ]];
    }

    public function map($row): array
    {
        $rawAchievement = (float) ($row['raw_achievement'] ?? $row['quarter_comp']);
        $scorecardAchievement = array_key_exists('scorecard_achievement', $row)
            ? $row['scorecard_achievement']
            : $row['quarter_comp'];
        $status = $row['performance_status']
            ?? app(EdrPerformanceCalculator::class)->performanceStatus($row['is_planned'] ? $rawAchievement : null);

        return [
            $row['code'],
            str_repeat('   ', max(0, (int) $row['hierarchy_level'] - 1)) . $row['objective'],
            $row['is_planned'] ? (float) $row['weight'] : 'N/A',
            $row['measurement'],
            $row['has_children'] && $row['is_planned'] ? 'Roll-up' : ($row['is_planned'] ? (float) $row['quarter_plan'] : 'N/A'),
            $row['has_children'] && $row['is_planned'] ? 'Roll-up' : ($row['is_planned'] ? (float) $row['quarter_perf'] : 'N/A'),
            $row['is_planned'] ? $rawAchievement / 100 : 'N/A',
            $row['is_planned'] && !is_null($scorecardAchievement)
                ? (float) $scorecardAchievement / 100
                : 'Operational',
            $status,
            $row['performer'] ?: 'N/A',
            $row['is_planned'] ? (float) $row['score_factor'] : 'N/A',
            (float) $row['weighted_score'],
            $row['has_children'] ? (float) $row['weighted_score'] : null,
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 11, 'B' => 58, 'C' => 11, 'D' => 18, 'E' => 14, 'F' => 16, 'G' => 12, 'H' => 14, 'I' => 18, 'J' => 28, 'K' => 18, 'L' => 16, 'M' => 14];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastDataRow = $this->data->count() + 1;
                $summary = app(EdrPerformanceCalculator::class)->summary($this->data->all());
                $totalRow = $lastDataRow + 1;
                $levelRow = $lastDataRow + 2;

                $sheet->insertNewRowBefore($totalRow, 2);
                $sheet->mergeCells("A{$totalRow}:K{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL EDR ACHIEVEMENT');
                $sheet->setCellValue("L{$totalRow}", $summary['weighted_score']);
                $sheet->setCellValue("M{$totalRow}", is_null($summary['achievement']) ? 'N/A' : $summary['achievement'] / 100);
                $sheet->mergeCells("A{$levelRow}:K{$levelRow}");
                $sheet->setCellValue("A{$levelRow}", 'PERFORMANCE LEVEL');
                $sheet->mergeCells("L{$levelRow}:M{$levelRow}");
                $sheet->setCellValue("L{$levelRow}", $summary['performance_level']['label']);

                $sheet->getStyle('A1:M1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '13714C']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);

                foreach ($this->data->values() as $index => $row) {
                    $excelRow = $index + 2;
                    $level = $row['performance_level'];
                    $sheet->getStyle("H{$excelRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => ltrim($level['textHex'], '#')]],
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => ltrim($level['hex'], '#')]],
                    ]);
                    if ($row['has_children']) {
                        $sheet->getStyle("A{$excelRow}:M{$excelRow}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2F0D9']],
                        ]);
                        // Restore the performance-level color after the parent-row fill.
                        $sheet->getStyle("H{$excelRow}")->getFill()->setFillType('solid')->getStartColor()->setRGB(ltrim($level['hex'], '#'));
                    }
                    if ($row['is_planned'] && ($row['raw_achievement'] ?? $row['quarter_comp']) > 100) {
                        $sheet->getStyle("I{$excelRow}")->getFill()->setFillType('solid')->getStartColor()->setRGB('17A2B8');
                        $sheet->getStyle("I{$excelRow}")->getFont()->getColor()->setRGB('FFFFFF');
                    }
                }

                $level = $summary['performance_level'];
                $sheet->getStyle("A{$totalRow}:M{$levelRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$totalRow}:M{$levelRow}")->getFill()->setFillType('solid')->getStartColor()->setRGB('13714C');
                $sheet->getStyle("M{$totalRow}:M{$totalRow}")->getFill()->setFillType('solid')->getStartColor()->setRGB(ltrim($level['hex'], '#'));
                $sheet->getStyle("L{$levelRow}:M{$levelRow}")->getFill()->setFillType('solid')->getStartColor()->setRGB(ltrim($level['hex'], '#'));

                $sheet->getStyle("G2:H{$lastDataRow}")->getNumberFormat()->setFormatCode('0.###%');
                $sheet->getStyle("K2:K{$lastDataRow}")->getNumberFormat()->setFormatCode('0.###');
                $sheet->getStyle("C2:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.###');
                $sheet->getStyle("L2:M{$levelRow}")->getNumberFormat()->setFormatCode('0.###');
                $sheet->getStyle("M{$totalRow}")->getNumberFormat()->setFormatCode('0.###%');
                $sheet->getStyle("A1:M{$levelRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('808080');
                $sheet->getStyle("A1:M{$levelRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getStyle("A2:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:M{$lastDataRow}");
                $sheet->getRowDimension(1)->setRowHeight(32);
            },
        ];
    }
}
