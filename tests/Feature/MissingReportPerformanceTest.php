<?php

namespace Tests\Feature;

use App\Exports\MasterPerformanceExport;
use App\Exports\ReportPerformanceExport;
use App\Http\Controllers\EdrReportController;
use App\Http\Controllers\MasterReportController;
use App\Models\Department;
use App\Models\PeriodicPerformance;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Models\WeeklyPerformance;
use App\Services\EdrPerformanceCalculator;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class MissingReportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_results_do_not_become_zero_or_one_hundred_percent(): void
    {
        foreach (['increase', 'decrease'] as $direction) {
            $item = $this->item(['goal_direction' => $direction]);
            $row = $this->report($item)[0];
            $this->assertNull($row['quarter_perf']);
            $this->assertNull($row['raw_achievement']);
            $this->assertNull($row['scorecard_achievement']);
            $this->assertNull($row['weighted_score']);
            $this->assertSame('Not Reported', $row['performance_status']);
            $summary = app(EdrPerformanceCalculator::class)->summary([$row]);
            $this->assertNull($summary['achievement']);
            $this->assertNull($summary['weighted_score']);
        }
    }

    public function test_an_explicit_official_zero_remains_a_reported_result(): void
    {
        $item = $this->item();
        $this->monthly($item, 'july', 0);
        $row = $this->report($item)[0];
        $this->assertSame(0.0, $row['quarter_perf']);
        $this->assertSame(0.0, $row['raw_achievement']);
        $this->assertTrue($row['complete']);
        $this->assertSame(100.0, $row['coverage']);
        $this->assertSame(0.0, app(EdrPerformanceCalculator::class)->summary([$row])['achievement']);
    }

    public function test_unapproved_performance_does_not_enter_an_official_report(): void
    {
        $item = $this->item();
        $this->monthly($item, 'july', 75, 'submitted_to_head_office');
        $row = $this->report($item)[0];
        $this->assertNull($row['quarter_perf']);
        $this->assertNull($row['raw_achievement']);
        $this->assertSame('Pending Approval', $row['performance_status']);
    }

    public function test_a_partial_weekly_month_keeps_actual_zero_but_has_no_final_score(): void
    {
        $item = $this->item([], [], 'weekly');
        WeeklyPerformance::create(['plan_item_id' => $item->id, 'year' => 2019, 'month' => 'july',
            'week_number' => 1, 'planned_value' => 25, 'actual_value' => 0,
            'workflow_status' => 'approved_by_head_office', 'created_by' => $item->plan->created_by]);
        $row = $this->report($item)[0];
        $this->assertSame(0.0, $row['quarter_perf']);
        $this->assertSame(25.0, $row['coverage']);
        $this->assertFalse($row['complete']);
        $this->assertNull($row['raw_achievement']);
        $this->assertSame('Provisional', $row['performance_status']);
        $cell = $this->master($item)['months']['july'];
        $this->assertSame(25.0, $cell['coverage']);
        $this->assertNull($cell['scorecard_achievement']);
    }

    public function test_quarters_require_all_planned_months_not_just_one(): void
    {
        $item = $this->item([], ['august_target' => 100, 'september_target' => 100, 'q1_target' => 300]);
        $this->monthly($item, 'july', 80);
        $cell = $this->master($item)['quarters']['q1'];
        $this->assertSame(80.0, $cell['actual']);
        $this->assertEqualsWithDelta(33.3333, $cell['coverage'], 0.001);
        $this->assertSame('Provisional', $cell['status']);
        $this->assertNull($cell['scorecard_achievement']);
        $row = $this->report($item, 'Q1')[0];
        $this->assertSame(80.0, $row['quarter_perf']);
        $this->assertNull($row['raw_achievement']);
        $this->assertEqualsWithDelta($cell['coverage'], $row['coverage'], 0.001);
    }

    public function test_checkpoint_quarter_does_not_invent_a_zero_for_the_missing_last_month(): void
    {
        $item = $this->item(['target_distribution' => 'checkpoint', 'goal_direction' => 'decrease'],
            ['july_target' => 10, 'august_target' => 20, 'september_target' => 30, 'q1_target' => 30]);
        $this->monthly($item, 'july', 12);
        $cell = $this->master($item)['quarters']['q1'];
        $this->assertSame(12.0, $cell['actual']);
        $this->assertSame('Provisional', $cell['status']);
        $this->assertNull($cell['raw_achievement']);
    }

    public function test_annual_report_is_not_final_when_only_one_month_is_reported(): void
    {
        $item = $this->item([], ['annual_target' => 300, 'august_target' => 100, 'september_target' => 100]);
        $this->monthly($item, 'july', 80);
        $row = $this->report($item, 'Annual')[0];
        $this->assertSame(80.0, $row['quarter_perf']);
        $this->assertFalse($row['complete']);
        $this->assertNull($row['scorecard_achievement']);
    }

    public function test_missing_children_do_not_depress_reported_only_hierarchy_scores(): void
    {
        $parent = $this->item([], ['weight' => 10]);
        $first = $this->child($parent, '1.1', 6);
        $this->child($parent, '1.2', 4);
        $this->monthly($first, 'july', 80);
        $rows = collect($this->report($parent));
        $root = $rows->firstWhere('code', '1');
        $this->assertSame(80.0, $root['scorecard_achievement']);
        $this->assertSame(50.0, $root['coverage']);
        $this->assertSame('Provisional', $root['performance_status']);
        $this->assertNull($rows->firstWhere('code', '1.2')['weighted_score']);
        $summary = app(EdrPerformanceCalculator::class)->summary($rows->all());
        $this->assertSame(80.0, $summary['achievement']);
        $this->assertFalse($summary['complete']);
        $this->assertSame(50.0, $summary['coverage']);
    }

    public function test_missing_and_provisional_results_remain_distinct_in_views_and_exports(): void
    {
        $item = $this->item([], ['august_target' => 100, 'september_target' => 100, 'q1_target' => 300]);
        $missing = $this->report($item);
        $html = view('reports._performance_table', ['performanceData' => $missing, 'period' => 'month:july'])->render();
        $this->assertStringContainsString('Not Reported', $html);
        $this->assertStringNotContainsString('Below Target', $html);
        $this->assertStringNotContainsString('Unsatisfactory', explode('</table>', $html)[0]);
        $export = new ReportPerformanceExport($missing, 'JULY 2019');
        $mapped = $export->map($missing[0]);
        $this->assertSame('Not Reported', $mapped[5]);
        $this->assertNull($mapped[6]);
        $this->assertNull($mapped[7]);
        $this->assertNull($mapped[11]);

        $this->monthly($item, 'july', 0);
        $master = $this->master($item);
        $masterExport = new MasterPerformanceExport([$master], 2019, $item->plan->department->name);
        $values = $masterExport->collection()->first();
        $this->assertStringContainsString('0 | Raw 0%', $values[7]);
        $this->assertStringContainsString('Provisional', $values[13]);
        $this->assertStringContainsString('33.333', $values[13]);
        $this->assertStringNotContainsString('Score 0%', $values[13]);
        $pdf = view('reports.master_pdf', ['gridData' => [$master], 'deptName' => 'Office', 'year' => 2019,
            'periodGroups' => [['months' => ['july', 'august', 'september'], 'quarter' => 'q1']]])->render();
        $this->assertStringContainsString('Provisional', $pdf);
        $this->assertStringContainsString('33.333', $pdf);
    }

    public function test_official_zero_target_is_valid_but_empty_zero_target_is_unreported(): void
    {
        $item = $this->item(['goal_direction' => 'decrease'], ['july_target' => 0]);
        $this->assertNull($this->report($item)[0]['raw_achievement']);
        $this->monthly($item, 'july', 0);
        $row = $this->report($item)[0];
        $this->assertSame(100.0, $row['raw_achievement']);
        $this->assertSame(0.0, $row['quarter_perf']);
    }

    public function test_weekly_month_ignores_wrong_year_and_unapproved_weeks(): void
    {
        $item = $this->item([], [], 'weekly');
        foreach ([[2019, 1, 'approved_by_head_office'], [2018, 2, 'approved_by_head_office'], [2019, 2, 'submitted_to_head_office']] as [$year, $week, $state]) {
            WeeklyPerformance::create(['plan_item_id' => $item->id, 'year' => $year, 'month' => 'july',
                'week_number' => $week, 'planned_value' => 25, 'actual_value' => 10,
                'workflow_status' => $state, 'created_by' => $item->plan->created_by]);
        }
        $row = $this->report($item)[0];
        $this->assertSame(10.0, $row['quarter_perf']);
        $this->assertSame(25.0, $row['coverage']);
        $this->assertNull($row['raw_achievement']);
        foreach ([2, 3, 4] as $week) {
            WeeklyPerformance::updateOrCreate(['plan_item_id' => $item->id, 'year' => 2019, 'month' => 'july', 'week_number' => $week],
                ['planned_value' => 25, 'actual_value' => 10, 'workflow_status' => 'reviewed_no_correction_required', 'created_by' => $item->plan->created_by]);
        }
        $complete = $this->report($item)[0];
        $this->assertTrue($complete['complete']);
        $this->assertSame(100.0, $complete['coverage']);
        $this->assertSame(40.0, $complete['quarter_perf']);
        $this->assertSame(40.0, $complete['raw_achievement']);
    }

    public function test_nested_master_rollups_do_not_give_missing_descendants_scored_weight(): void
    {
        $root = $this->item([], ['weight' => 10]);
        $branch = $this->child($root, '1.1', 8);
        $first = $this->child($branch, '1.1.1', 1);
        $this->child($branch, '1.1.2', 7);
        $second = $this->child($root, '1.2', 2);
        $this->monthly($first, 'july', 100);
        $this->monthly($second, 'july', 0);
        $cell = $this->master($root)['months']['july'];
        $this->assertEqualsWithDelta(33.33, $cell['scorecard_achievement'], 0.01);
        $this->assertEqualsWithDelta(66.6667, $cell['coverage'], 0.001);
        $this->assertSame('Provisional', $cell['status']);
    }

    public function test_pending_explicit_quarter_and_annual_records_are_not_unreported(): void
    {
        $item = $this->item([], ['q1_target' => 100]);
        $this->monthly($item, 'q1', 80, 'submitted_to_head_office');
        $this->monthly($item, 'annual', 80, 'submitted_to_head_office');
        $this->assertSame('Pending Approval', $this->report($item, 'Q1')[0]['performance_status']);
        $this->assertSame('Pending Approval', $this->report($item, 'Annual')[0]['performance_status']);
    }

    public function test_real_excel_and_csv_files_preserve_blanks_and_entered_zeroes(): void
    {
        $item = $this->item();
        $file = tempnam(sys_get_temp_dir(), 'iprpms-report-');
        try {
            $rows = $this->report($item);
            file_put_contents($file, Excel::raw(new ReportPerformanceExport($rows, 'JULY 2019'), ExcelFormat::XLSX));
            $sheet = (new Xlsx())->load($file)->getActiveSheet();
            $this->assertSame('Not Reported', $sheet->getCell('F2')->getValue());
            foreach (['G2', 'H2', 'K2', 'L2'] as $cell) $this->assertNull($sheet->getCell($cell)->getValue());
            $csv = Excel::raw(new ReportPerformanceExport($rows, 'JULY 2019'), ExcelFormat::CSV);
            $csvRow = str_getcsv(explode("\n", $csv)[1]);
            $this->assertSame('Not Reported', $csvRow[5]);
            $this->assertSame('', $csvRow[6]);
            $this->assertSame('', $csvRow[11]);

            $this->monthly($item, 'july', 0);
            file_put_contents($file, Excel::raw(new ReportPerformanceExport($this->report($item), 'JULY 2019'), ExcelFormat::XLSX));
            $sheet = (new Xlsx())->load($file)->getActiveSheet();
            foreach (['F2', 'G2', 'H2', 'L2'] as $cell) {
                $this->assertNotNull($sheet->getCell($cell)->getValue());
                $this->assertEquals(0, $sheet->getCell($cell)->getValue());
            }
            $item->update(['july_target' => 0]);
            file_put_contents($file, Excel::raw(new MasterPerformanceExport([$this->master($item)], 2019, 'Office'), ExcelFormat::XLSX));
            $sheet = (new Xlsx())->load($file)->getActiveSheet();
            $this->assertNotNull($sheet->getCell('G3')->getValue());
            $this->assertEquals(0, $sheet->getCell('G3')->getValue());
        } finally {
            unlink($file);
        }
    }

    public function test_annual_results_include_approved_quarters_without_double_counting_months(): void
    {
        $item = $this->item([], ['annual_target' => 400, 'q1_target' => 100, 'q2_target' => 100, 'q3_target' => 100, 'q4_target' => 100]);
        foreach (['q1', 'q2', 'q3', 'q4'] as $quarter) $this->monthly($item, $quarter, 100);
        $this->monthly($item, 'july', 999);
        $row = $this->report($item, 'Annual')[0];
        $this->assertSame(400.0, $row['quarter_perf']);
        $this->assertTrue($row['complete']);
        $this->assertSame(100.0, $row['coverage']);
        $this->assertSame(100.0, $row['raw_achievement']);
    }

    public function test_missing_zero_weight_activities_still_prevent_final_completion(): void
    {
        $root = $this->item();
        $first = $this->child($root, '1.1', 10);
        $zeroWeight = $this->child($root, '1.2', 0);
        $zeroWeight->strategicObjective->update(['weight' => 0]);
        $this->monthly($first, 'july', 80);
        $summary = app(EdrPerformanceCalculator::class)->summary($this->report($root));
        $this->assertFalse($summary['complete']);
        $this->assertSame(2, $summary['expected']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(50.0, $summary['coverage']);
        $this->assertSame(80.0, $summary['achievement']);
        $this->assertSame('Provisional', $summary['status']);
    }

    public function test_saved_weekly_plan_is_not_replaced_when_its_actual_is_missing(): void
    {
        $item = $this->item([], [], 'weekly');
        $item->setRelation('weeklyPerformances', collect([new WeeklyPerformance([
            'year' => 2019, 'month' => 'july', 'week_number' => 1, 'planned_value' => 60, 'actual_value' => null,
        ])]));
        $evidence = app(\App\Services\ReportPeriodEvidence::class)->forItem($item, 'week:july:1');
        $this->assertSame(60.0, $evidence['target']);
        $this->assertNull($evidence['actual']);
        $this->assertFalse($evidence['reported']);
        $this->assertFalse($evidence['pending']);
    }

    private function item(array $objectiveAttributes = [], array $itemAttributes = [], string $frequency = 'monthly'): PlanItem
    {
        $department = Department::create(['name' => 'Office '.Department::count(), 'code' => 'O'.Department::count(), 'reporting_type' => $frequency, 'is_active' => true]);
        $user = User::factory()->create(['department_id' => $department->id]);
        $plan = Plan::create(['title' => 'Approved plan', 'type' => 'annual', 'year' => 2019,
            'department_id' => $department->id, 'status' => 'approved', 'created_by' => $user->id]);
        $objective = StrategicObjective::create(array_replace(['code' => '1', 'description' => 'Report evidence KPI',
            'weight' => 10, 'unit' => 'Number', 'department_id' => $department->id, 'lead_department_id' => $department->id,
            'performers' => [$department->id], 'goal_direction' => 'increase', 'target_distribution' => 'sum',
            'status' => 'approved', 'is_active' => true], $objectiveAttributes));
        return PlanItem::create(array_replace(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id,
            'annual_target' => 100, 'july_target' => 100, 'weight' => 10, 'unit' => 'number'], $itemAttributes));
    }

    private function child(PlanItem $parent, string $code, float $weight): PlanItem
    {
        $objective = $parent->strategicObjective->replicate();
        $objective->fill(['parent_id' => $parent->strategic_objective_id, 'code' => $code]);
        $objective->save();
        return PlanItem::create(['plan_id' => $parent->plan_id, 'strategic_objective_id' => $objective->id,
            'july_target' => 100, 'annual_target' => 100, 'weight' => $weight]);
    }

    private function monthly(PlanItem $item, string $month, float $actual, string $state = 'approved_by_head_office'): void
    {
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2019-'.$month,
            'actual_value' => $actual, 'workflow_status' => $state]);
    }

    private function report(PlanItem $item, string $period = 'month:july'): array
    {
        return app(EdrReportController::class)->calculateComparisonData($item->plan->department_id, 2019, $period);
    }

    private function master(PlanItem $item): array
    {
        $method = new ReflectionMethod(MasterReportController::class, 'buildGridData');
        return collect($method->invoke(app(MasterReportController::class), 2019, $item->plan->department_id))
            ->firstWhere('id', $item->strategic_objective_id);
    }
}
