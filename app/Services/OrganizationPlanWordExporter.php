<?php

namespace App\Services;

use App\Models\EdrPlanDocument;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

class OrganizationPlanWordExporter
{
    private const NAVY = '173B54';
    private const TEAL = '078F83';
    private const MINT = 'DFF4F0';
    private const PALE = 'F3F7F9';
    private const TEXT = '24343F';

    public function download(Collection $plans, ?EdrPlanDocument $narrative, Collection $departmentNames, int $year)
    {
        $word = new PhpWord();
        $word->setDefaultFontName('Aptos');
        $word->setDefaultFontSize(10);
        $word->addTitleStyle(1, ['name' => 'Aptos Display', 'size' => 18, 'bold' => true, 'color' => self::NAVY], ['spaceBefore' => 280, 'spaceAfter' => 120]);
        $word->addTitleStyle(2, ['name' => 'Aptos Display', 'size' => 13, 'bold' => true, 'color' => self::TEAL], ['spaceBefore' => 260, 'spaceAfter' => 100, 'borderBottomSize' => 8, 'borderBottomColor' => self::TEAL]);

        $section = $word->addSection(['marginTop' => 900, 'marginBottom' => 850, 'marginLeft' => 1050, 'marginRight' => 1050]);
        $this->addHeader($section, $year);
        $this->addFooter($section);
        $this->addCover($section, $plans, $narrative, $year);

        $sections = [
            ['1. Introduction', 'introduction'],
            ["2. Past Year's Performance, Strengths, Weaknesses and Future Focus Areas", 'past_performance'],
            ['3. Strategic Issues (Vision, Mission, Values and Goals)', 'strategic_issues'],
        ];
        foreach ($sections as [$heading, $field]) {
            $this->addNarrativeSection($section, $heading, $narrative?->$field);
        }

        $section->addTitle('4. Objectives and Detailed Activities', 1);
        $section->addText('Approved organization objectives and detailed activities, consolidated from departmental plans.', ['color' => '60727D', 'italic' => true]);
        $this->addObjectiveSummary($section, $plans, $departmentNames);

        foreach ([
            ['5. Implementation Strategies', 'implementation_strategies'],
            ['6. Monitoring and Evaluation System', 'monitoring_evaluation'],
            ['7. Challenges and Possible Solutions', 'challenges_solutions'],
        ] as [$heading, $field]) {
            $this->addNarrativeSection($section, $heading, $narrative?->$field);
        }

        $schedule = $word->addSection([
            'orientation' => 'landscape', 'pageSizeW' => Converter::inchToTwip(11.69),
            'pageSizeH' => Converter::inchToTwip(8.27), 'marginTop' => 700,
            'marginBottom' => 700, 'marginLeft' => 650, 'marginRight' => 650,
        ]);
        $this->addHeader($schedule, $year);
        $this->addFooter($schedule);
        $schedule->addTitle('8. Approved Action Plan Schedule', 1);
        $schedule->addText('Approved targets only; actual performance and achievement results are excluded.', ['color' => '60727D', 'italic' => true]);
        $this->addSchedule($schedule, $plans, $departmentNames);

        $path = tempnam(sys_get_temp_dir(), 'iprpms_word_');
        IOFactory::createWriter($word, 'Word2007')->save($path);

        return response()->download($path, "Organization_EDR_Plan_{$year}_EFY.docx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ])->deleteFileAfterSend(true);
    }

    private function addHeader($section, int $year): void
    {
        $header = $section->addHeader();
        $table = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
        $table->addRow();
        $left = $table->addCell(6500, ['borderBottomSize' => 10, 'borderBottomColor' => self::TEAL]);
        $left->addText('ETHIO-DJIBOUTI RAILWAY', ['bold' => true, 'size' => 9, 'color' => self::NAVY]);
        $right = $table->addCell(2500, ['borderBottomSize' => 10, 'borderBottomColor' => self::TEAL]);
        $right->addText("{$year} EFY | IPRPMS", ['bold' => true, 'size' => 8, 'color' => self::TEAL], ['alignment' => Jc::END]);
    }

    private function addFooter($section): void
    {
        $footer = $section->addFooter();
        $footer->addPreserveText('EDR Integrated Planning, Reporting & Performance Management System   |   Page {PAGE} of {NUMPAGES}', ['size' => 8, 'color' => '7B8B94'], ['alignment' => Jc::CENTER]);
    }

    private function addCover($section, Collection $plans, ?EdrPlanDocument $narrative, int $year): void
    {
        $section->addTextBreak(4);
        $section->addText('COMPLETE APPROVED ORGANIZATION PLAN', ['bold' => true, 'size' => 11, 'color' => self::TEAL, 'allCaps' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 180]);
        $section->addText('ORGANIZATION EDR PLAN', ['bold' => true, 'size' => 30, 'color' => self::NAVY], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);
        $section->addText("{$year} EFY", ['bold' => true, 'size' => 19, 'color' => self::TEAL], ['alignment' => Jc::CENTER, 'spaceAfter' => 500]);
        $meta = $section->addTable(['alignment' => Jc::CENTER, 'cellMargin' => 140]);
        $meta->addRow(650);
        foreach ([[$plans->count(), 'APPROVED PLANS'], [$plans->sum(fn ($plan) => $plan->items->count()), 'ACTION-PLAN ITEMS'], [strtoupper(str_replace('_', ' ', $narrative?->status ?? 'draft')), 'DOCUMENT STATUS']] as [$value, $label]) {
            $cell = $meta->addCell(2800, ['bgColor' => self::PALE, 'borderColor' => 'D8E3E8', 'borderSize' => 6, 'valign' => 'center']);
            $cell->addText((string) $value, ['bold' => true, 'size' => 15, 'color' => self::NAVY], ['alignment' => Jc::CENTER]);
            $cell->addText($label, ['bold' => true, 'size' => 7, 'color' => self::TEAL], ['alignment' => Jc::CENTER]);
        }
        $section->addTextBreak(3);
        $section->addText('Prepared by: '.($narrative?->preparer?->name ?? 'Plan and Report Department'), ['size' => 10, 'color' => '60727D'], ['alignment' => Jc::CENTER]);
        $section->addText('Generated '.now()->format('d F Y, H:i'), ['size' => 9, 'color' => '8A989F'], ['alignment' => Jc::CENTER]);
        $section->addPageBreak();
    }

    private function addNarrativeSection($section, string $heading, ?string $content): void
    {
        $section->addTitle($heading, 1);
        $text = trim((string) $content);
        if ($text === '') {
            $section->addText('Narration has not yet been prepared for this section.', ['italic' => true, 'color' => '8A989F'], ['spaceAfter' => 180]);
            return;
        }
        foreach (preg_split('/\R{2,}/', $text) as $paragraph) {
            $section->addText(trim($paragraph), ['color' => self::TEXT], ['alignment' => Jc::BOTH, 'lineHeight' => 1.35, 'spaceAfter' => 140]);
        }
    }

    private function addObjectiveSummary($section, Collection $plans, Collection $departmentNames): void
    {
        $table = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'borderSize' => 5, 'borderColor' => 'CCD9DF', 'cellMargin' => 85]);
        $this->headerRow($table, ['Code', 'Objective / Detailed Activity', 'Department', 'Unit', 'Weight', 'Implementing Bodies']);
        foreach ($plans as $plan) {
            $row = $table->addRow();
            $cell = $row->addCell(10000, ['gridSpan' => 6, 'bgColor' => self::MINT]);
            $cell->addText($plan->department->name.' - '.$plan->title, ['bold' => true, 'color' => self::NAVY]);
            foreach ($plan->items->sortBy(fn ($item) => $item->strategicObjective?->code) as $item) {
                $objective = $item->strategicObjective;
                $bodies = collect($item->performers ?? [])->map(fn ($id) => $departmentNames[$id] ?? null)->filter()->implode(', ');
                $this->dataRow($table, [
                    $objective?->code, $objective?->description, $plan->department->name,
                    $item->unit ?: $objective?->unit, \App\Support\NumberFormatter::format($item->weight ?: $objective?->weight).'%',
                    $bodies ?: $plan->department->name,
                ]);
            }
        }
    }

    private function addSchedule($section, Collection $plans, Collection $departmentNames): void
    {
        $months = ['Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun'];
        $table = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'borderSize' => 4, 'borderColor' => 'C7D5DC', 'cellMargin' => 35]);
        $this->headerRow($table, array_merge(['Code', 'Planned Activity / KPI', 'Unit', 'Baseline', 'Weight', 'Annual'], $months, ['Q1','Q2','Q3','Q4','Implementers']), 6.5);
        $monthFields = ['july','august','september','october','november','december','january','february','march','april','may','june'];
        foreach ($plans as $plan) {
            $row = $table->addRow();
            $cell = $row->addCell(10000, ['gridSpan' => 23, 'bgColor' => self::MINT]);
            $cell->addText($plan->department->name.' - '.$plan->title, ['bold' => true, 'size' => 7.5, 'color' => self::NAVY]);
            foreach ($plan->items->sortBy(fn ($item) => $item->strategicObjective?->code) as $item) {
                $objective = $item->strategicObjective;
                $bodies = collect($item->performers ?? [])->map(fn ($id) => $departmentNames[$id] ?? null)->filter()->implode(', ');
                $values = [$objective?->code, $objective?->description, $item->unit ?: $objective?->unit, $item->baseline_value ?? $objective?->baseline_value ?? '-', \App\Support\NumberFormatter::format($item->weight ?: $objective?->weight).'%', $item->annual_target ?? '-'];
                foreach ($monthFields as $month) $values[] = $item->{$month.'_target'} ?? '-';
                array_push($values, $item->q1_target ?? '-', $item->q2_target ?? '-', $item->q3_target ?? '-', $item->q4_target ?? '-', $bodies ?: $plan->department->name);
                foreach (range(3, 21) as $numericColumn) {
                    if (isset($values[$numericColumn]) && is_numeric($values[$numericColumn])) $values[$numericColumn] = \App\Support\NumberFormatter::format($values[$numericColumn]);
                }
                $this->dataRow($table, $values, 6.2);
            }
        }
    }

    private function headerRow($table, array $labels, float $size = 8): void
    {
        $table->addRow(420, ['tblHeader' => true]);
        foreach ($labels as $label) {
            $table->addCell(null, ['bgColor' => self::NAVY, 'valign' => 'center'])
                ->addText($label, ['bold' => true, 'size' => $size, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
        }
    }

    private function dataRow($table, array $values, float $size = 8): void
    {
        $table->addRow();
        foreach ($values as $index => $value) {
            $table->addCell(null, ['valign' => 'center'])
                ->addText((string) ($value ?? '-'), ['size' => $size, 'color' => self::TEXT], ['alignment' => $index === 1 ? Jc::START : Jc::CENTER]);
        }
    }
}
