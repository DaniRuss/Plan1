<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\HistoricalKpi;
use App\Models\HistoricalPerformanceImport;
use App\Models\PeriodicPerformance;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndependentComparisonPeriodsTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->department = Department::create(['name' => 'Operation', 'code' => 'OP', 'is_active' => true, 'reporting_type' => 'monthly']);
        $this->viewer = User::factory()->create(['department_id' => $this->department->id, 'must_change_password' => false]);
        $this->viewer->roles()->attach(Role::where('name', 'plan_report_department_expert')->firstOrFail());
        foreach ([2026 => [40, 60, 80, 85], 2027 => [70, 90, 90, 95]] as $year => $actuals) {
            $o = StrategicObjective::create(['department_id' => $this->department->id, 'code' => 'cargo-'.$year, 'kpi_code' => 'K1', 'description' => 'Transport cargo', 'unit' => 'Number', 'weight' => 100, 'annual_target' => 100, 'target_year' => $year, 'target_distribution' => 'sum', 'goal_direction' => 'increase', 'status' => 'approved', 'is_active' => true]);
            $plan = Plan::create(['department_id' => $this->department->id, 'year' => $year, 'title' => 'Annual '.$year, 'type' => 'annual', 'status' => 'approved', 'created_by' => $this->viewer->id]);
            $item = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $o->id, 'unit' => 'number', 'weight' => 100, 'annual_target' => 100, 'september_target' => 100, 'october_target' => 100, 'q1_target' => 100]);
            foreach (array_combine(['september', 'october', 'q1', 'annual'], $actuals) as $period => $actual) {
                PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => $year.'-'.$period, 'actual_value' => $actual, 'status' => 'approved', 'workflow_status' => 'approved_by_head_office']);
            }
        }
        $import = HistoricalPerformanceImport::create(['year' => 2018, 'status' => 'locked', 'original_filename' => 'Original annual.xlsx', 'source_hash' => str_repeat('b', 64)]);
        HistoricalKpi::create(['historical_performance_import_id' => $import->id, 'source_row' => 1, 'code' => 'oldcargo', 'kpi_code' => 'K1', 'description' => 'Transport cargo', 'performers' => 'Operation', 'unit' => 'Number', 'weight' => 100, 'annual_target' => 100, 'annual_actual' => 80, 'reported_achievement' => 80, 'goal_direction' => 'increase', 'direction_status' => 'confirmed']);
    }

    private function compare(array $query = [])
    {
        return $this->actingAs($this->viewer)->get(route('performance.comparison', array_merge(['comparison_type' => 'period', 'comparison_view' => 'period', 'reference_source' => 'plan:2026', 'current_source' => 'plan:2027', 'current_department' => $this->department->id, 'reference_department' => $this->department->id, 'reference_period' => 'september', 'current_period' => 'september'], $query)));
    }

    public function test_same_month_across_independent_years_compares_the_correct_records(): void
    {
        $c = $this->compare()->assertOk()->viewData('comparison');
        $this->assertSame('plan:2026', $c['builder']['referenceSource']['key']);
        $this->assertSame('plan:2027', $c['builder']['currentSource']['key']);
        $this->assertSame(40.0, $c['referenceAverage']);
        $this->assertSame(70.0, $c['currentAverage']);
        $this->assertSame(30.0, $c['delta']);
    }

    public function test_quarters_across_years_and_months_within_a_year_remain_supported(): void
    {
        $quarter = $this->compare(['reference_period' => 'q1', 'current_period' => 'q1'])->assertOk()->viewData('comparison');
        $this->assertSame(10.0, $quarter['delta']);
        $month = $this->compare(['current_source' => 'plan:2026', 'current_period' => 'october'])->assertOk()->viewData('comparison');
        $this->assertSame(20.0, $month['delta']);
    }

    public function test_imported_annual_source_is_selectable_but_never_becomes_a_month(): void
    {
        $c = $this->compare(['reference_source' => 'history:2018', 'current_source' => 'plan:2026', 'reference_period' => 'annual', 'current_period' => 'annual'])->assertOk()->viewData('comparison');
        $this->assertSame(80.0, $c['referenceAverage']);
        $this->assertSame(85.0, $c['currentAverage']);
        $this->assertSame(5.0, $c['delta']);
        $this->compare(['reference_source' => 'history:2018'])->assertSessionHasErrors('reference_period');
        $this->compare(['reference_source' => 'history:2018', 'reference_period' => 'annual'])->assertSessionHasErrors('reference_period');
    }

    public function test_same_year_same_period_and_different_period_lengths_are_rejected(): void
    {
        $this->compare(['current_source' => 'plan:2026'])->assertSessionHasErrors('reference_period');
        $this->compare(['reference_period' => 'q1'])->assertSessionHasErrors('reference_period');
        $this->compare(['reference_period' => 'annual'])->assertSessionHasErrors('reference_period');
        $this->compare(['reference_period' => 'all'])->assertSessionHasErrors('reference_period');
    }

    public function test_period_defaults_follow_the_selected_sources_not_todays_year(): void
    {
        $this->travelTo(now()->setYear(2030));
        $c = $this->compare(['reference_period' => null, 'current_period' => null])->assertOk()->viewData('comparison');
        $this->assertSame('plan:2026', $c['builder']['referenceSource']['key']);
        $this->assertSame('plan:2027', $c['builder']['currentSource']['key']);
        $this->assertSame('october', $c['builder']['referencePeriod']);
        $this->assertSame('october', $c['builder']['currentPeriod']);
        $this->assertSame(30.0, $c['delta']);
    }

    public function test_both_years_are_selectable_and_the_evaluated_side_is_labelled_comparison(): void
    {
        $page = $this->compare()->assertOk();
        $page->assertSee('id="pc-reference-source"', false)->assertSee('id="pc-current-source"', false)->assertSee('Reference year')->assertSee('Comparison year');
        $page->assertDontSee('Same IPRPMS year as Current')->assertDontSee('Current available achievement');
    }

    public function test_legacy_period_url_with_explicit_sources_uses_both_selected_years(): void
    {
        $c = $this->actingAs($this->viewer)->get(route('performance.comparison', ['comparison_method' => 'period', 'reference_source' => 'plan:2026', 'current_source' => 'plan:2027', 'reference_period' => 'september', 'current_period' => 'september', 'current_department' => $this->department->id]))->assertOk()->viewData('comparison');
        $this->assertSame(30.0, $c['delta']);
        $this->assertSame('period', $c['method']);
        $this->assertSame(2026, $c['builder']['referenceSource']['year']);
        $this->assertSame(2027, $c['builder']['currentSource']['year']);
    }

    public function test_shared_historical_department_evidence_remains_visible_but_cannot_be_a_final_pair(): void
    {
        HistoricalKpi::query()->update(['performers' => 'All departments']);
        $c = $this->compare(['reference_source' => 'history:2018', 'reference_period' => 'annual', 'current_period' => 'annual'])->assertOk()->viewData('comparison');
        $this->assertSame(80.0, $c['referenceAvailable']);
        $this->assertNull($c['delta']);
        $this->assertFalse($c['pairedRows']->first()['reference']['complete']);
    }

    public function test_year_navigation_from_same_year_period_or_target_does_not_submit_a_self_comparison(): void
    {
        foreach (['period', 'target'] as $type) {
            $page = $this->compare(['comparison_type' => $type, 'current_source' => 'plan:2026', 'reference_source' => 'plan:2026', 'current_period' => 'october'])->assertOk();
            $dom = new \DOMDocument;
            @$dom->loadHTML($page->getContent());
            $links = (new \DOMXPath($dom))->query('//nav[contains(@class,"pc-modes")]/a');
            $yearUrl = null;
            foreach ($links as $link) {
                if (trim($link->textContent) === 'Year over year') {
                    $yearUrl = $link->getAttribute('href');
                }
            }
            $this->assertNotNull($yearUrl);
            $c = $this->get($yearUrl)->assertOk()->viewData('comparison');
            $this->assertNotSame($c['builder']['referenceSource']['gregorian'], $c['builder']['currentSource']['gregorian']);
        }
    }

    public function test_cross_year_periods_do_not_infer_kpi_identity_from_wording_alone(): void
    {
        StrategicObjective::query()->update(['kpi_code' => null]);
        $c = $this->compare()->assertOk()->viewData('comparison');
        $this->assertSame(40.0, $c['referenceAvailable']);
        $this->assertSame(70.0, $c['currentAvailable']);
        $this->assertNull($c['delta']);
        $this->assertSame(0, $c['matched']);
        $sameObjective = $this->compare(['current_source' => 'plan:2026', 'current_period' => 'october'])->assertOk()->viewData('comparison');
        $this->assertSame(20.0, $sameObjective['delta']);
    }
}
