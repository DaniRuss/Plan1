<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\HistoricalKpi;
use App\Models\HistoricalPerformanceImport;
use App\Models\PeriodicPerformance;
use App\Models\PeriodWeightDistribution;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Services\PerformanceComparison;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AnnualDepartmentComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private Department $operation;

    private Department $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->operation = Department::create(['name' => 'Operation', 'code' => 'OP', 'is_active' => true, 'reporting_type' => 'monthly']);
        $this->hr = Department::create(['name' => 'Human Resources', 'code' => 'HR', 'is_active' => true, 'reporting_type' => 'monthly']);
        $this->viewer = User::factory()->create(['department_id' => $this->operation->id, 'must_change_password' => false]);
        $this->viewer->roles()->attach(Role::where('name', 'plan_report_department_expert')->firstOrFail());
    }

    private function activity(Department $department, string $code, float $weight, ?float $actual = 80, array $attributes = []): PlanItem
    {
        $objective = StrategicObjective::create(array_merge(['department_id' => $department->id, 'code' => $code, 'description' => $code.' activity', 'unit' => 'Number', 'weight' => $weight, 'annual_target' => 100, 'target_year' => 2026, 'target_distribution' => 'sum', 'goal_direction' => 'increase', 'status' => 'approved', 'is_active' => true], $attributes));
        $plan = Plan::firstOrCreate(['department_id' => $department->id, 'year' => 2026, 'title' => $department->name.' annual'], ['type' => 'annual', 'status' => 'approved', 'created_by' => $this->viewer->id]);
        $item = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, 'annual_target' => 100, 'july_target' => 50, 'august_target' => 50, 'weight' => $weight]);
        if ($actual !== null) {
            PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-annual', 'actual_value' => $actual, 'status' => 'approved', 'workflow_status' => 'approved_by_head_office']);
        }

        return $item;
    }

    private function historical(string $code, ?float $weight, ?float $achievement, ?string $performers, array $attributes = []): HistoricalKpi
    {
        $import = HistoricalPerformanceImport::firstOrCreate(['year' => 2018], ['status' => 'locked', 'source_hash' => str_repeat('d', 64), 'original_filename' => 'Original annual 2018.xlsx']);

        return HistoricalKpi::create(array_merge(['historical_performance_import_id' => $import->id, 'source_row' => HistoricalKpi::count() + 10, 'code' => $code, 'description' => $code.' historical activity', 'weight' => $weight, 'unit' => 'Number', 'annual_target' => 100, 'annual_actual' => $achievement, 'reported_achievement' => $achievement, 'performers' => $performers, 'goal_direction' => 'increase', 'direction_status' => 'confirmed'], $attributes));
    }

    private function compare(array $query = []): array
    {
        $result = app(PerformanceComparison::class)->build($this->viewer, Request::create('/performance-comparison', 'GET', array_merge(['comparison_type' => 'department', 'comparison_view' => 'master', 'current_source' => 'plan:2026', 'current_department' => $this->operation->id], $query)));
        $this->assertSame('departments', $result['workspace'] ?? null, 'Department mode must use its annual department population, not matched KPI rows.');

        return $result;
    }

    public function test_one_department_compares_its_own_weighted_annual_results_across_years(): void
    {
        $this->activity($this->operation, 'cargo', 75, 80, ['unit' => 'Tons']);
        $this->activity($this->operation, 'trains', 25, 100);
        $this->activity($this->hr, 'training', 100, 60, ['unit' => 'Hours']);
        $this->historical('OP.old', 100, 80, 'Operation');
        $c = $this->compare(['reference_source' => 'history:2018']);
        $this->assertSame(85.0, $c['currentCard']['score']);
        $this->assertSame(80.0, $c['referenceCard']['score']);
        $this->assertSame('Tons', $c['currentCard']['activities']->firstWhere('code', 'cargo')['unit']);
        $this->assertSame($this->operation->id, $c['referenceCard']['id']);
        $this->assertSame($this->operation->id, $c['currentCard']['id']);
        $this->assertSame(5.0, $c['delta']);
        $this->assertTrue($c['populationChanged']);
    }

    public function test_selected_department_without_data_is_visible_without_other_department_scores(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $c = $this->compare(['current_department' => $this->hr->id]);
        $this->assertCount(1, $c['departmentRows']);
        $this->assertNull($c['currentCard']['score']);
        $this->assertSame('no_data', $c['currentCard']['state']);
    }

    public function test_missing_reports_are_not_silently_removed_from_the_score_denominator(): void
    {
        $item = $this->activity($this->operation, 'cargo', 75, null);
        $this->activity($this->operation, 'trains', 25, 100);
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-july', 'actual_value' => 40, 'status' => 'approved']);
        $card = $this->compare()['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertNull($card['score']);
        $this->assertSame(50.0, $card['reportingCoverage']);
        $this->assertSame(25.0, $card['weightCoverage']);
        $this->assertSame(40.0, $card['activities']->firstWhere('code', 'cargo')['actual']);
    }

    public function test_annual_department_totals_ignore_stale_activity_and_frequency_filters(): void
    {
        $this->activity($this->operation, 'cargo', 75, 80);
        $this->activity($this->operation, 'trains', 25, 100);
        $c = $this->compare(['reporting_frequency' => 'weekly', 'search' => 'unrelated KPI', 'performance_kpi' => 'not present', 'movement' => 'declined', 'result_scope' => 'reported', 'current_period' => 'july', 'reference_period' => 'q4']);
        $this->assertSame(85.0, $c['departmentRows']->firstWhere('id', $this->operation->id)['score']);
        $this->assertSame('annual', $c['builder']['currentPeriod']);
        $this->assertSame('official', $c['scope']);
    }

    public function test_unreviewed_annual_results_cannot_become_final_even_in_reported_scope(): void
    {
        $item = $this->activity($this->operation, 'cargo', 100, 80);
        $item->performances()->update(['status' => 'submitted', 'workflow_status' => 'submitted_to_chief']);
        $card = $this->compare(['result_scope' => 'reported'])['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertNull($card['score']);
        $this->assertSame(0.0, $card['reportingCoverage']);
    }

    public function test_explicit_zero_is_a_complete_result_not_missing(): void
    {
        $this->activity($this->operation, 'cargo', 100, 0);
        $card = $this->compare()['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertSame(0.0, $card['score']);
        $this->assertSame(100.0, $card['reportingCoverage']);
        $this->assertSame('complete', $card['state']);
        $this->assertNull($card['rank']);
    }

    public function test_parent_containers_and_their_children_are_not_scored_twice(): void
    {
        $root = $this->activity($this->operation, 'root', 100, 10, ['kpi_code' => 'K1']);
        $this->activity($this->operation, 'unrelated-code-A', 75, 80, ['parent_id' => $root->strategic_objective_id]);
        $this->activity($this->operation, 'unrelated-code-B', 25, 100, ['parent_id' => $root->strategic_objective_id]);
        $card = $this->compare()['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertSame(85.0, $card['score']);
        $this->assertSame(2, $card['expected']);
        $this->assertSame('container', $card['activities']->firstWhere('code', 'root')['status']);
    }

    public function test_duplicate_approved_plan_records_require_review_not_arbitrary_selection(): void
    {
        $item = $this->activity($this->operation, 'cargo', 100, 80);
        $plan = Plan::create(['department_id' => $this->operation->id, 'year' => 2026, 'title' => 'Another approved plan', 'type' => 'annual', 'status' => 'approved', 'created_by' => $this->viewer->id]);
        $duplicate = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $item->strategic_objective_id, 'annual_target' => 100, 'weight' => 100]);
        PeriodicPerformance::create(['plan_item_id' => $duplicate->id, 'period' => '2026-annual', 'actual_value' => 100, 'status' => 'approved']);
        $card = $this->compare()['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertNull($card['score']);
        $this->assertNull($card['rank']);
        $this->assertSame(1, $card['expected']);
        $this->assertSame('duplicate', $card['activities']->first()['status']);
    }

    public function test_approved_annual_allocations_are_used_and_missing_allocations_are_not_replaced(): void
    {
        $first = $this->activity($this->operation, 'cargo', 75, 80);
        $second = $this->activity($this->operation, 'trains', 25, 100);
        $allocation = PeriodWeightDistribution::create(['department_id' => $this->operation->id, 'year' => 2026, 'period' => 'annual', 'mode' => 'manual', 'status' => 'approved']);
        $allocation->allocations()->create(['plan_item_id' => $first->id, 'original_weight' => 75, 'effective_weight' => 25]);
        $card = $this->compare()['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertNull($card['score']);
        $allocation->allocations()->create(['plan_item_id' => $second->id, 'original_weight' => 25, 'effective_weight' => 75]);
        $this->assertSame(95.0, $this->compare()['departmentRows']->firstWhere('id', $this->operation->id)['score']);
    }

    public function test_historical_reported_annual_results_do_not_require_current_kpi_mappings(): void
    {
        $this->historical('OP.1', 25, 80, 'Operation', ['annual_actual' => 70]);
        $this->historical('OP.2', 75, 100, 'OP');
        $this->historical('HR.1', 100, 60, 'Human Resources');
        $c = $this->compare(['current_source' => 'history:2018']);
        $this->assertSame(95.0, $c['departmentRows']->firstWhere('id', $this->operation->id)['score']);
        $this->assertSame(60.0, $this->compare(['current_source' => 'history:2018', 'current_department' => $this->hr->id])['currentCard']['score']);
        $this->assertSame('Original annual 2018.xlsx', $c['sourceFilename']);
        $this->assertSame(80.0, $c['departmentRows']->firstWhere('id', $this->operation->id)['activities']->first()['achievement']);
    }

    public function test_shared_historical_results_are_context_not_invented_department_actuals(): void
    {
        $shared = $this->historical('shared', 20, 90, 'Operation, Human Resources');
        $this->historical('OP.1', 80, 75, 'Operation');
        foreach ([$this->operation->id, $this->hr->id] as $id) {
            $card = $this->compare(['current_source' => 'history:2018', 'current_department' => $id])['currentCard'];
            $this->assertNull($card['score']);
            $this->assertNull($card['rank']);
            $this->assertSame('shared', $card['activities']->firstWhere('code', 'shared')['status']);
        }
        $this->assertSame('90.0000', $shared->fresh()->annual_actual);
        $this->assertSame('Operation, Human Resources', $shared->fresh()->performers);
    }

    public function test_blank_historical_performers_inherit_real_ancestor_responsibility(): void
    {
        $this->historical('1', 100, 20, 'Operation', ['is_main_objective' => true]);
        $this->historical('1.1', 60, 80, null, ['parent_code' => '1']);
        $this->historical('1.2', 40, 100, null, ['parent_code' => '1']);
        $card = $this->compare(['current_source' => 'history:2018'])['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertSame(88.0, $card['score']);
        $this->assertSame(2, $card['expected']);
        $this->assertSame(3, $card['activities']->count());
    }

    public function test_all_departments_is_shared_even_when_only_one_department_is_active(): void
    {
        $this->hr->update(['is_active' => false]);
        $this->historical('all', 100, 90, 'All departments');
        $c = $this->compare(['current_source' => 'history:2018']);
        $card = $c['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertNull($card['score']);
        $this->assertSame('shared', $card['activities']->first()['status']);
        $this->assertCount(1, $c['attributionWarnings']);
    }

    public function test_unknown_legacy_annual_year_cannot_silently_select_another_source(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $this->actingAs($this->viewer)->get(route('performance.comparison', ['comparison_method' => 'department', 'performance_year' => 2024]))->assertSessionHasErrors('performance_year');
    }

    public function test_missing_historical_weights_cannot_be_silently_renormalized_away(): void
    {
        $this->historical('OP.1', null, 80, 'Operation');
        $this->historical('OP.2', 25, 100, 'Operation');
        $card = $this->compare(['current_source' => 'history:2018'])['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertNull($card['score']);
        $this->assertSame(100.0, $card['reportingCoverage']);
        $this->assertNull($card['weightCoverage']);
        $this->assertSame(1, $card['missingWeights']);
    }

    public function test_unattributed_and_partially_matched_historical_owners_remain_visible(): void
    {
        $this->historical('OP.1', 100, 80, 'Operation, Unknown group');
        $this->historical('x', 100, 100, 'Unknown division');
        $c = $this->compare(['current_source' => 'history:2018']);
        $this->assertNull($c['departmentRows']->firstWhere('id', $this->operation->id)['score']);
        $this->assertSame(2, $c['attributionWarnings']->count());
    }

    public function test_calculating_historical_achievement_requires_confirmed_direction_but_reported_percentage_does_not(): void
    {
        $row = $this->historical('OP.1', 100, null, 'Operation', ['annual_actual' => 80, 'direction_status' => 'needs_review']);
        $this->assertNull($this->compare(['current_source' => 'history:2018'])['departmentRows']->firstWhere('id', $this->operation->id)['score']);
        $row->update(['reported_achievement' => 80, 'unit' => null]);
        $this->assertSame(80.0, $this->compare(['current_source' => 'history:2018'])['departmentRows']->firstWhere('id', $this->operation->id)['score']);
    }

    public function test_master_and_focused_department_forms_are_annual_only(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $this->historical('HR.1', 100, 60, 'Human Resources');
        foreach (['master', 'department'] as $view) {
            $page = $this->actingAs($this->viewer)->get(route('performance.comparison', ['comparison_type' => 'department', 'comparison_view' => $view, 'current_source' => 'plan:2026']))->assertOk();
            $page->assertSee('Annual Department Performance')->assertSee('80.00%')->assertSee('name="reference_source"', false)->assertSee('history:2018', false);
            $page->assertSee('name="current_department"', false)->assertDontSee('name="reference_department"', false)->assertDontSee('name="department_scope"', false)->assertDontSee('Annual rank');
            foreach (['current_period', 'reference_period', 'performance_period', 'reporting_frequency', 'performance_kpi', 'result_scope'] as $field) {
                $page->assertDontSee('name="'.$field.'"', false);
            }
            $page->assertSee('cargo activity')->assertSee('Activity evidence')->assertSee('id="pc-export-data"', false);
        }
    }

    public function test_legacy_department_urls_open_the_annual_scorecard_not_monthly_kpi_pairs(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $page = $this->actingAs($this->viewer)->get(route('performance.comparison', ['comparison_method' => 'department', 'performance_year' => 2026, 'performance_period' => 'july']))->assertOk();
        $c = $page->viewData('comparison');
        $this->assertSame('departments', $c['workspace']);
        $this->assertSame(80.0, $c['departmentRows']->firstWhere('id', $this->operation->id)['score']);
        $page->assertDontSee('name="performance_period"', false);
    }

    public function test_stale_department_search_does_not_change_the_selected_department_population(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $this->activity($this->hr, 'training', 100, 90);
        $cards = $this->compare(['department_search' => 'Human Resources'])['departmentRows'];
        $this->assertCount(1, $cards);
        $this->assertSame(80.0, $cards->first()['score']);
        $this->assertNull($cards->first()['rank']);
    }

    public function test_department_codes_are_searchable_and_retained_for_export(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $c = $this->compare(['department_search' => 'OP']);
        $this->assertCount(1, $c['departmentRows']);
        $this->assertSame('OP', $c['departmentRows']->first()['code']);
    }

    public function test_equal_annual_scores_for_the_same_department_produce_zero_change(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $this->activity($this->hr, 'training', 100, 80);
        $this->historical('OP.old', 100, 80, 'Operation');
        $c = $this->compare(['reference_source' => 'history:2018']);
        $this->assertSame(0.0, $c['delta']);
        $this->assertSame($c['currentCard']['id'], $c['referenceCard']['id']);
    }

    public function test_non_scoring_children_do_not_remove_a_valid_parent_activity(): void
    {
        foreach (['is_reference_only', 'exclude_from_overall_score'] as $flag) {
            $parent = $this->activity($this->operation, $flag.'-parent', 100, 10);
            $this->activity($this->operation, $flag.'-child', 0, null, ['parent_id' => $parent->strategic_objective_id, $flag => true]);
            $this->activity($this->operation, $flag.'-separate', 100, 100);
            $card = $this->compare()['departmentRows']->firstWhere('id', $this->operation->id);
            $this->assertSame(55.0, $card['score']);
            $this->assertSame('ready', $card['activities']->firstWhere('code', $flag.'-parent')['status']);
        }
    }

    public function test_department_access_and_source_validation_are_not_bypassed(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $this->actingAs($this->viewer)->get(route('performance.comparison', ['comparison_type' => 'department', 'current_source' => 'history:9999']))->assertSessionHasErrors('current_source');
        $this->get(route('performance.comparison', ['comparison_type' => 'department', 'current_department' => 999999]))->assertSessionHasErrors('current_department');
        $this->viewer->roles()->sync([Role::where('name', 'department_chief')->value('id')]);
        $this->actingAs($this->viewer->fresh());
        $this->get(route('performance.comparison', ['comparison_type' => 'department']))->assertSessionHasErrors('comparison_type');
        $this->get(route('performance.comparison', ['comparison_method' => 'department']))->assertSessionHasErrors('comparison_method');
    }

    public function test_a_future_native_year_uses_its_own_complete_annual_results(): void
    {
        $item = $this->activity($this->operation, 'future', 100, 95);
        $item->plan->update(['year' => 2027]);
        $item->strategicObjective->update(['target_year' => 2027]);
        $item->performances()->update(['period' => '2027-annual']);
        $c = $this->compare(['current_source' => 'plan:2027']);
        $this->assertSame(95.0, $c['departmentRows']->firstWhere('id', $this->operation->id)['score']);
        $this->assertSame('2020 EFY / 2027', $c['sourceLabel']);
    }

    public function test_only_partial_annual_progress_is_labelled_incomplete_not_a_final_result(): void
    {
        $item = $this->activity($this->operation, 'cargo', 100, null);
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-july', 'actual_value' => 40, 'status' => 'approved']);
        $card = $this->compare()['departmentRows']->firstWhere('id', $this->operation->id);
        $this->assertSame('partial', $card['state']);
        $this->assertNull($card['score']);
    }

    public function test_different_departments_and_same_fiscal_year_aliases_are_rejected(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $this->historical('OP.old', 100, 70, 'Operation');
        $query = ['comparison_type' => 'department', 'current_source' => 'plan:2026', 'reference_source' => 'history:2018', 'current_department' => $this->operation->id];
        $this->actingAs($this->viewer)->get(route('performance.comparison', $query + ['reference_department' => $this->hr->id]))->assertSessionHasErrors('reference_department');
        $this->get(route('performance.comparison', array_replace($query, ['reference_source' => 'plan:2026'])))->assertSessionHasErrors('reference_source');
        $item = $this->activity($this->operation, 'alias-year', 100, 90);
        $item->plan->update(['year' => 2025]);
        $this->get(route('performance.comparison', array_replace($query, ['current_source' => 'plan:2025'])))->assertSessionHasErrors('reference_source');
    }

    public function test_future_native_years_are_independent_of_the_actual_current_date(): void
    {
        $this->activity($this->operation, '2026-cargo', 100, 85);
        $future = $this->activity($this->operation, '2027-training', 100, 95);
        $futurePlan = Plan::create(['department_id' => $this->operation->id, 'year' => 2027, 'title' => 'Future approved', 'type' => 'annual', 'status' => 'approved', 'created_by' => $this->viewer->id]);
        $future->update(['plan_id' => $futurePlan->id]);
        $future->strategicObjective->update(['target_year' => 2027]);
        $future->performances()->update(['period' => '2027-annual']);
        $this->travelTo(now()->setYear(2030));
        $c = $this->compare(['reference_source' => 'plan:2026', 'current_source' => 'plan:2027']);
        $this->assertSame(85.0, $c['referenceCard']['score']);
        $this->assertSame(95.0, $c['currentCard']['score']);
        $this->assertSame(10.0, $c['delta']);
        $swapped = $this->compare(['reference_source' => 'plan:2027', 'current_source' => 'plan:2026']);
        $this->assertSame(-10.0, $swapped['delta']);
    }

    public function test_only_one_available_year_does_not_compare_it_with_itself(): void
    {
        $this->activity($this->operation, 'cargo', 100, 80);
        $c = $this->compare();
        $this->assertNull($c['referenceCard']);
        $this->assertNull($c['delta']);
        $this->assertNotNull($c['selectionNotice']);
    }
}
