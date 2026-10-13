<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ObjectiveWorkbookImport
{
    public const PERIOD_COLUMNS = [
        'july_target' => 'G', 'august_target' => 'H', 'september_target' => 'I', 'q1_target' => 'J',
        'october_target' => 'K', 'november_target' => 'L', 'december_target' => 'M', 'q2_target' => 'N',
        'january_target' => 'O', 'february_target' => 'P', 'march_target' => 'Q', 'q3_target' => 'R',
        'april_target' => 'S', 'may_target' => 'T', 'june_target' => 'U', 'q4_target' => 'V',
    ];

    public function parse(string $path): array
    {
        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();
        // The source template has fixed, meaningful columns A:W. Reject unrelated workbooks.
        $first = strtolower(trim((string) $sheet->getCell('A1')->getFormattedValue()));
        $second = strtolower(trim((string) $sheet->getCell('B1')->getFormattedValue()));
        if (!str_contains($first, 's.no') || !str_contains($second, 'kpi')) {
            throw new \InvalidArgumentException('Expected the EDR action-plan workbook with S.No and KPIs in the first two columns.');
        }

        $rows = [];
        $frequencyColumn = null;
        foreach ($sheet->getRowIterator(1, 1) as $headerRow) {
            foreach ($headerRow->getCellIterator() as $cell) {
                if (strtolower(trim((string) $cell->getFormattedValue())) === 'reporting frequency') $frequencyColumn = $cell->getColumn();
            }
        }
        for ($number = 2; $number <= $sheet->getHighestDataRow(); $number++) {
            $code = preg_replace('/[\s\x{00A0}]+/u', '', (string) $sheet->getCell('A'.$number)->getFormattedValue());
            $description = trim((string) $sheet->getCell('B'.$number)->getFormattedValue());
            if ($description === '' || strtolower($description) === 'sum') continue;

            $row = [
                'source_row' => $number,
                'code' => rtrim($code, '.'),
                'description' => $description,
                'weight' => $this->number($sheet->getCell('C'.$number)),
                'unit' => trim((string) $sheet->getCell('D'.$number)->getFormattedValue()),
                'baseline_value' => $this->number($sheet->getCell('E'.$number), true),
                'annual_target' => $this->number($sheet->getCell('F'.$number), true),
                'performer_text' => trim((string) $sheet->getCell('W'.$number)->getFormattedValue()),
                'reporting_frequency' => $frequencyColumn ? $this->frequency((string) $sheet->getCell($frequencyColumn.$number)->getFormattedValue(), $number) : null,
            ];
            foreach (self::PERIOD_COLUMNS as $field => $column) {
                $row[$field] = $this->number($sheet->getCell($column.$number), true);
            }
            $rows[] = $row;
        }
        $book->disconnectWorksheets();
        return $rows;
    }

    private function number($cell, bool $temporal = false): ?float
    {
        $raw = $cell->getValue();
        if ($raw === null || trim((string) $raw) === '') return null;
        if ($raw instanceof \DateTimeInterface) return ((int) $raw->format('H')) + ((int) $raw->format('i')) / 60;
        if (is_numeric($raw)) {
            $format = strtolower($cell->getStyle()->getNumberFormat()->getFormatCode());
            if ($temporal && (str_contains($format, 'h:') || str_contains($format, '[h]'))) return round((float) $raw * 24, 4);
            return (float) $raw;
        }
        $value = str_replace(',', '', trim((string) $raw));
        if ($temporal && preg_match('/^(\d+):(\d{1,2})$/', $value, $parts)) return round((float) $parts[1] + (float) $parts[2] / 60, 4);
        return is_numeric($value) ? (float) $value : null;
    }

    private function frequency(string $value, int $row): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '' || $value === 'inherit') return null;
        if (!in_array($value, ['daily', 'weekly', 'monthly'], true)) {
            throw new \InvalidArgumentException("Row {$row}: Reporting Frequency must be INHERIT, DAILY, WEEKLY or MONTHLY.");
        }
        return $value;
    }
}

