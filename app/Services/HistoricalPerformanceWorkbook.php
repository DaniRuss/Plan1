<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class HistoricalPerformanceWorkbook
{
    private const UNIT_ALIASES = [
        'no'=>'number','no.'=>'number','nr'=>'number','nr.'=>'number','qty'=>'number','number'=>'number',
        '%'=>'percentage','percent'=>'percentage','percentage'=>'percentage','percentage (%)'=>'percentage','percent (%)'=>'percentage',
        'tons (in 1000)'=>'thousand_tons','ton (in 1000)'=>'thousand_tons','tons'=>'tons','ton'=>'tons',
        'days'=>'days','day'=>'days','hrs'=>'hours','hr'=>'hours','hour'=>'hours','hours'=>'hours','km'=>'kilometres','km.'=>'kilometres',
        'min'=>'minutes','mins'=>'minutes','minute'=>'minutes','minutes'=>'minutes',
        'sec'=>'seconds','secs'=>'seconds','second'=>'seconds','seconds'=>'seconds',
        'etb'=>'etb','birr'=>'etb','usd'=>'usd','ratio'=>'ratio','index'=>'index',
    ];

    public function parse(string $path): Collection
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = collect();
        $highest = $sheet->getHighestDataRow();
        $header = $this->findHeader($sheet, $highest);
        $nextDescription = trim((string) $sheet->getCell('B'.($header + 1))->getFormattedValue());
        $start = $header + ($nextDescription === '' ? 2 : 1); // supplied workbook uses a two-row heading

        for ($row = $start; $row <= $highest; $row++) {
            $originalCode = $this->cleanText($sheet->getCell("A{$row}")->getFormattedValue());
            $description = $this->cleanText($sheet->getCell("B{$row}")->getFormattedValue());
            $unit = $this->cleanText($sheet->getCell("D{$row}")->getFormattedValue());
            $target = $this->cellNumber($sheet->getCell("E{$row}"), $unit);
            $actual = $this->cellNumber($sheet->getCell("F{$row}"), $unit);
            if (!$description || (!$originalCode && $target === null && $actual === null)) continue;

            $code = $this->correctCode($originalCode, $description, $row);
            $isMain = (bool) preg_match('/^\d+$/', $code ?? '') || str_starts_with(mb_strtolower($description), 'objective ');
            $rows->push([
                'source_row' => $row,
                'original_code' => $originalCode,
                'code' => $code,
                'parent_code' => str_contains((string) $code, '.') ? explode('.', (string) $code)[0] : null,
                'description' => $description,
                'weight' => $this->cellNumber($sheet->getCell("C{$row}")),
                'unit' => $unit,
                'normalized_unit' => $this->normalizeUnit($unit),
                'annual_target' => $target,
                'annual_actual' => $actual,
                'reported_achievement' => $this->cellNumber($sheet->getCell("G{$row}")),
                'goal_direction' => $this->suggestDirection($description),
                'direction_status' => 'needs_review',
                'performers' => $this->cleanText($sheet->getCell("H{$row}")->getFormattedValue()),
                'is_main_objective' => $isMain,
                'unit_status' => $unit === null ? 'missing' : 'specified',
            ]);
        }

        return $rows;
    }

    private function findHeader($sheet, int $highest): int
    {
        for ($row = 1; $row <= min($highest, 20); $row++) {
            $a = mb_strtolower((string) $sheet->getCell("A{$row}")->getFormattedValue());
            $b = mb_strtolower((string) $sheet->getCell("B{$row}")->getFormattedValue());
            if ((str_contains($a, 's.no') || str_contains($a, 'code')) && (str_contains($b, 'objective') || str_contains($b, 'description'))) return $row;
        }
        return 1;
    }

    private function correctCode(?string $code, string $description, int $row): ?string
    {
        $code = $code ? rtrim(trim($code), ". \t\n\r\0\x0B") : null;
        $descriptionKey = mb_strtolower($description);
        if ($row === 181 || ($code === '11.2' && str_contains($descriptionKey, 'twentieth'))) return '11.20';
        if ($row === 225 || ($code === '14.1' && str_contains($descriptionKey, 'tenth'))) return '14.10';
        $confirmedRows = [207=>'13.7',208=>'13.8',209=>'13.9',211=>'13.10',212=>'13.11',213=>'13.12',214=>'13.13'];
        if (isset($confirmedRows[$row])) return $confirmedRows[$row];
        $known = [
            'rail laying machine'=>'13.7', 'rail lying machine'=>'13.7', 'surge arrester'=>'13.8', 'serge arrester'=>'13.8',
            'last-mile truck transportation'=>'13.9', 'last-mile truck service'=>'13.9',
            'truck utilization rate'=>'13.10', 'on-time truck availability'=>'13.11',
            'safe driving'=>'13.12', 'asset utilization rate'=>'13.13',
        ];
        foreach ($known as $needle => $corrected) if (str_contains($descriptionKey, $needle)) return $corrected;
        return $code;
    }

    public function normalizeUnit(?string $unit): ?string
    {
        if ($unit === null || trim($unit) === '') return null;
        $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $unit)));
        return self::UNIT_ALIASES[$key] ?? preg_replace('/[^a-z0-9%]+/', '_', $key);
    }

    public function equivalentUnits(?string $left, ?string $right): bool
    {
        $key = fn ($unit) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $unit ?? '')));
        $left = $key($left);
        $right = $key($right);
        if ($left === $right) return true;
        // The general normalization key is deliberately lossy; never use its
        // punctuation-stripping fallback to authorize restoring measurements.
        return isset(self::UNIT_ALIASES[$left], self::UNIT_ALIASES[$right])
            && self::UNIT_ALIASES[$left] === self::UNIT_ALIASES[$right];
    }

    private function suggestDirection(string $description): string
    {
        $text = mb_strtolower($description);
        foreach (['reduce','decrease','minimize','lower','turnaround time','dwell time','absenteeism','damage rate','cost variance','accident'] as $needle) {
            if (str_contains($text, $needle)) return 'decrease';
        }
        return 'increase';
    }

    private function cleanText($value): ?string
    {
        if ($value === null) return null;
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));
        return $value === '' ? null : $value;
    }

    private function number($value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        $clean = str_replace([',','%',' '], '', (string) $value);
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function cellNumber(Cell $cell, ?string $unit = null): ?float
    {
        // Display strings can hide precision or turn elapsed days into "72:00:00".
        // Keep the calculated value; consult formatting only for its numeric scale.
        $value = $cell->getCalculatedValue();
        if ($value === null || trim((string) $value) === '') return null;
        $daysToUnit = match ($this->normalizeUnit($unit)) {
            'hours' => 24, 'days' => 1, 'minutes' => 1440, 'seconds' => 86400,
            default => null,
        };
        if (is_string($value) && $daysToUnit !== null && preg_match('/^(-?)(\d+):([0-5]\d)(?::([0-5]\d(?:\.\d+)?))?$/', trim($value), $parts)) {
            $days = ((float) $parts[2] * 3600 + (float) $parts[3] * 60 + (float) ($parts[4] ?? 0)) / 86400;
            return ($parts[1] === '-' ? -1 : 1) * $days * $daysToUnit;
        }
        if (!is_numeric($value)) return $this->number($value);

        $format = $cell->getStyle()->getNumberFormat()->getFormatCode();
        $tokens = $this->applicableFormatTokens($format, (float) $value);
        $tokens = preg_replace('/\[(?!(?:h+|m+|s+)\])[^\]]*\]/i', '', $tokens);
        if (Date::isDateTimeFormatCode($tokens) || preg_match('/\[(?:h+|m+|s+)\]/i', $tokens)) {
            // Do not invent a unit or interpret a calendar date as performance.
            if ($daysToUnit === null || preg_match('/[dy]/i', $tokens) || !preg_match('/h|s|\[m+\]/i', $tokens)) return null;
            return (float) $value * $daysToUnit;
        }
        return (float) $value * (str_contains($tokens, '%') ? 100 : 1);
    }

    private function applicableFormatTokens(string $format, float $value): string
    {
        // Remove literal text before splitting: an escaped or quoted semicolon
        // is not a section separator, and a literal percent sign does not scale.
        $sections = explode(';', preg_replace('/"[^"]*"|\\\\.|_.|\*./', '', $format));
        $matchesSection = function (string $section, bool $default) use ($value): bool {
            if (!preg_match('/\[(>=|<=|<>|>|<|=)([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?)\]/', $section, $match)) return $default;
            $limit = (float) $match[2];
            return match ($match[1]) {
                '>=' => $value >= $limit, '<=' => $value <= $limit, '<>' => $value != $limit,
                '>' => $value > $limit, '<' => $value < $limit, '=' => $value == $limit,
            };
        };
        if (count($sections) === 1) return $sections[0];
        if (count($sections) === 2) return $matchesSection($sections[0], $value >= 0) ? $sections[0] : $sections[1];
        if ($matchesSection($sections[0], $value > 0)) return $sections[0];
        return $matchesSection($sections[1], $value < 0) ? $sections[1] : $sections[2];
    }
}
