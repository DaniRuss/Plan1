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
    private array $brand;

    public function __construct(DocumentBranding $branding)
    {
        $this->brand = $branding->resolve();
    }

    public function download(Collection $plans, ?EdrPlanDocument $narrative, Collection $departmentNames, int $year)
    {
        $word = new PhpWord();
        $word->setDefaultFontName('Aptos');
        $word->setDefaultFontSize(10);
        $word->addTitleStyle(1, ['name' => 'Aptos Display', 'size' => 18, 'bold' => true, 'color' => $this->brand['dark']], ['spaceBefore' => 280, 'spaceAfter' => 120]);
        $word->addTitleStyle(2, ['name' => 'Aptos Display', 'size' => 13, 'bold' => true, 'color' => $this->brand['accent']], ['spaceBefore' => 260, 'spaceAfter' => 100, 'borderBottomSize' => 8, 'borderBottomColor' => $this->brand['accent']]);

        $section = $word->addSection(['marginTop' => 1650, 'headerHeight' => 450, 'marginBottom' => 850, 'marginLeft' => 1050, 'marginRight' => 1050]);
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
            'pageSizeH' => Converter::inchToTwip(8.27), 'marginTop' => 1500, 'headerHeight' => 450,
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
        $left = $table->addCell(6500, ['borderBottomSize' => 10, 'borderBottomColor' => $this->brand['accent']]);
        if ($this->brand['logo_path']) {
            [$width,$height] = getimagesize($this->brand['logo_path']);
            $scale = min(110/$width,36/$height);
            $left->addImage($this->brand['logo_path'], ['width'=>$width*$scale,'height'=>$height*$scale,'wrappingStyle'=>'inline']);
        }
        $left->addText($this->brand['name'], ['bold' => true, 'size' => 9, 'color' => $this->brand['dark']]);
        $right = $table->addCell(2500, ['borderBottomSize' => 10, 'borderBottomColor' => $this->brand['accent']]);
        $right->addText("{$year} EFY | ".$this->brand['abbreviation'], ['bold' => true, 'size' => 8, 'color' => $this->brand['accent']], ['alignment' => Jc::END]);
    }

    private function addFooter($section): void
    {
        $footer = $section->addFooter();
        $footer->addPreserveText($this->brand['name'].'   |   Page {PAGE} of {NUMPAGES}', ['size' => 8, 'color' => '7B8B94'], ['alignment' => Jc::CENTER]);
    }

    private function addCover($section, Collection $plans, ?EdrPlanDocument $narrative, int $year): void
    {
        $section->addTextBreak(4);
        $section->addText('COMPLETE APPROVED ORGANIZATION PLAN', ['bold' => true, 'size' => 11, 'color' => $this->brand['accent'], 'allCaps' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 180]);
        $section->addText('ORGANIZATION EDR PLAN', ['bold' => true, 'size' => 30, 'color' => $this->brand['dark']], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);
        $section->addText("{$year} EFY", ['bold' => true, 'size' => 19, 'color' => $this->brand['accent']], ['alignment' => Jc::CENTER, 'spaceAfter' => 500]);
        $meta = $section->addTable(['alignment' => Jc::CENTER, 'cellMargin' => 140]);
        $meta->addRow(650);
        foreach ([[$plans->count(), 'APPROVED PLANS'], [$plans->sum(fn ($plan) => $plan->items->count()), 'ACTION-PLAN ITEMS'], [strtoupper(str_replace('_', ' ', $narrative?->status ?? 'draft')), 'DOCUMENT STATUS']] as [$value, $label]) {
            $cell = $meta->addCell(2800, ['bgColor' => $this->brand['pale'], 'borderColor' => 'D8E3E8', 'borderSize' => 6, 'valign' => 'center']);
            $cell->addText((string) $value, ['bold' => true, 'size' => 15, 'color' => $this->brand['dark']], ['alignment' => Jc::CENTER]);
            $cell->addText($label, ['bold' => true, 'size' => 7, 'color' => $this->brand['accent']], ['alignment' => Jc::CENTER]);
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
            $section->addText(trim($paragraph), ['color' => '24343F'], ['alignment' => Jc::BOTH, 'lineHeight' => 1.35, 'spaceAfter' => 140]);
        }
    }

    private function addObjectiveSummary($section, Collection $plans, Collection $departmentNames): void
    {
        $table = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'borderSize' => 5, 'borderColor' => 'CCD9DF', 'cellMargin' => 85]);
        $this->headerRow($table, ['Code', 'Objective / Detailed Activity', 'Unit', 'Weight', 'Implementing Departments']);
        foreach (app(ApprovedPlanExport::class)->items($plans) as $item) {
                $plan = $item->plan;
                $objective = $item->strategicObjective;
                $bodies = implode('; ',app(ApprovedPlanExport::class)->implementers($item,$departmentNames,$plan->department_id));
                $this->dataRow($table, [
                    $objective?->code, $objective?->description,
                    $item->unit ?: $objective?->unit, \App\Support\NumberFormatter::format($item->weight ?: $objective?->weight).'%',
                    $bodies ?: $plan->department->name,
                ]);
        }
    }

    private function addSchedule($section, Collection $plans, Collection $departmentNames): void
    {
        $periods = ApprovedPlanExport::PERIODS;
        $table = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'borderSize' => 4, 'borderColor' => 'C7D5DC', 'cellMargin' => 35]);
        $this->headerRow($table, array_merge(['Code', 'Planned Activity / KPI', 'Unit', 'Baseline', 'Weight', 'Annual'], array_values($periods), ['Implementing Departments']), 6.5);
        foreach (app(ApprovedPlanExport::class)->items($plans) as $item) {
                $plan = $item->plan;
                $objective = $item->strategicObjective;
                $bodies = implode('; ',app(ApprovedPlanExport::class)->implementers($item,$departmentNames,$plan->department_id));
                $values = [$objective?->code, $objective?->description, $item->unit ?: $objective?->unit, $item->baseline_value ?? $objective?->baseline_value ?? '-', \App\Support\NumberFormatter::format($item->weight ?: $objective?->weight).'%', $item->annual_target ?? '-'];
                foreach ($periods as $period=>$label) $values[] = $item->{$period.'_target'} ?? '-';
                $values[] = $bodies ?: $plan->department->name;
                foreach (range(3, 21) as $numericColumn) {
                    if (isset($values[$numericColumn]) && is_numeric($values[$numericColumn])) $values[$numericColumn] = \App\Support\NumberFormatter::format($values[$numericColumn]);
                }
                $this->dataRow($table, $values, 6.2);
        }
    }

    private function headerRow($table, array $labels, float $size = 8): void
    {
        $table->addRow(420, ['tblHeader' => true]);
        foreach ($labels as $label) {
            $table->addCell(null, ['bgColor' => $this->brand['dark'], 'valign' => 'center'])
                ->addText($label, ['bold' => true, 'size' => $size, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
        }
    }

    private function dataRow($table, array $values, float $size = 8): void
    {
        $table->addRow();
        foreach ($values as $index => $value) {
            $table->addCell(null, ['valign' => 'center'])
                ->addText((string) ($value ?? '-'), ['size' => $size, 'color' => '24343F'], ['alignment' => $index === 1 ? Jc::START : Jc::CENTER]);
        }
    }
}
