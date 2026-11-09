<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\HistoricalKpi;
use App\Models\HistoricalKpiMapping;
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

class MasterComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private Department $department;

    private HistoricalKpi $historical;

    private StrategicObjective $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->department = Department::create(['name' => 'Operation', 'code' => 'OP', 'is_active' => true, 'reporting_type' => 'monthly']);
        $this->viewer = User::factory()->create(['department_id' => $this->department->id, 'must_change_password' => false]);
        $this->viewer->roles()->attach(Role::where('name', 'plan_report_department_expert')->firstOrFail());
        $import = HistoricalPerformanceImport::create(['year' => 2018, 'original_filename' => 'Original 2018.xlsx', 'source_hash' => str_repeat('f', 64), 'status' => 'locked']);
        $this->historical = HistoricalKpi::create(['historical_performance_import_id' => $import->id, 'source_row' => 17, 'code' => '2.5', 'kpi_code' => 'K7', 'description' => 'Historical cargo damage rate', 'unit' => '%', 'annual_target' => 10, 'annual_actual' => 8, 'reported_achievement' => 80, 'goal_direction' => 'increase', 'direction_status' => 'confirmed', 'performers' => 'Operation']);
        $this->current = $this->native(2026, '1.7', 'K7', 'Current cargo damage indicator', 90);
        HistoricalKpiMapping::create(['historical_kpi_id' => $this->historical->id, 'current_objective_id' => $this->current->id, 'mapping_type' => 'renamed', 'status' => 'approved', 'comparable' => true]);
    }

    private function native(int $year, string $code, string $kpi, string $description, ?float $actual, ?Department $department = null): StrategicObjective
    {
        $department ??= $this->department;
        $objective = StrategicObjective::create(['code' => $code, 'kpi_code' => $kpi, 'description' => $description, 'unit' => 'Percentage (%)', 'department_id' => $department->id, 'target_year' => $year, 'status' => 'approved', 'is_active' => true, 'annual_target' => 100, 'weight' => 10, 'goal_direction' => 'increase', 'target_distribution' => 'sum']);
        $plan = Plan::create(['department_id' => $department->id, 'year' => $year, 'title' => 'Annual plan '.$year, 'type' => 'annual', 'status' => 'approved', 'created_by' => $this->viewer->id]);
        $item = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, 'annual_target' => 100, 'july_target' => 50, 'august_target' => 50]);
        if ($actual !== null) {
            PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => $year.'-annual', 'actual_value' => $actual, 'status' => 'approved', 'workflow_status' => 'approved_by_head_office']);
        }

        return $objective;
    }

    private function compare(array $query = [])
    {
        return $this->actingAs($this->viewer)->get(route('performance.comparison', array_merge(['comparison_method' => 'historical', 'comparison_type' => 'kpi', 'reference_source' => 'history:2018', 'current_source' => 'plan:2026', 'reference_entity' => 'h:'.$this->historical->id, 'current_entity' => 'o:'.$this->current->id], $query)));
    }

    public function test_master_exposes_every_comparison_type_and_two_sided_selections(): void
    {
        $page = $this->actingAs($this->viewer)->get(route('performance.comparison'))->assertOk();
        $page->assertSee('name="comparison_type"', false)->assertSee('name="reference_source"', false)->assertSee('name="current_source"', false);
        foreach (['Year versus year', 'Period versus period', 'Department over time', 'Objective versus objective', 'KPI versus KPI', 'Actual versus target'] as $label) {
            $page->assertSee($label);
        }
    }

    public function test_uncoded_unmatched_historical_activities_remain_visible_when_sorting_comparisons(): void
    {
        $uncoded = HistoricalKpi::create([
            'historical_performance_import_id' => $this->historical->historical_performance_import_id,
            'source_row' => 18,
            'code' => null,
            'description' => 'Historical activity without an activity code',
            'unit' => '%',
            'annual_target' => 10,
            'annual_actual' => 4,
            'direction_status' => 'confirmed',
            'performers' => 'Operation',
        ]);

        foreach (['code', 'name', 'achievement', 'change'] as $sort) {
            $page = $this->actingAs($this->viewer)->get(route('performance.comparison', [
                'comparison_type' => 'year',
                'reference_source' => 'history:2018',
                'current_source' => 'plan:2026',
                'current_period' => 'july',
                'comparison_sort' => $sort,
            ]))->assertOk();

            $rows = $page->viewData('comparison')['pairedRows'];
            $row = $rows->first(fn ($row) => ($row['reference']['id'] ?? null) === 'h:'.$uncoded->id);
            $this->assertCount(2, $rows);
            $this->assertNotNull($row);
            $this->assertNull($row['reference']['code']);
            $this->assertSame(4.0, $row['reference']['actual']);
            $this->assertSame(40.0, $row['reference']['achievement']);
            $this->assertNull($row['current']);
            $this->assertNull($row['delta']);
            $page->assertSee('Historical activity without an activity code');
        }

        $this->assertNull($uncoded->fresh()->code);
    }

    public function test_k7_comparison_preserves_both_wordings_source_and_expected_change(): void
    {
        $page = $this->compare()->assertOk();
        $row = $page->viewData('comparison')['pairedRows']->first();
        $this->assertSame('K7', $row['reference']['kpi']);
        $this->assertSame('K7', $row['current']['kpi']);
        $this->assertSame(80.0, $row['reference']['achievement']);
        $this->assertSame(90.0, $row['current']['achievement']);
        $this->assertSame(10.0, $row['delta']);
        $page->assertSee('Historical cargo damage rate')->assertSee('Current cargo damage indicator')->assertSee('Original 2018.xlsx')->assertSee('Source row 17')->assertSee('height:260px', false);
    }

    public function test_future_native_years_match_stable_kpi_codes_despite_renamed_activities(): void
    {
        $this->native(2027, '4.7', 'K7', 'Next year renamed indicator', 95);
        $page = $this->compare(['comparison_type' => 'year', 'reference_source' => 'plan:2026', 'current_source' => 'plan:2027', 'reference_entity' => null, 'current_entity' => null])->assertOk();
        $c = $page->viewData('comparison');
        $this->assertSame(5.0, $c['pairedRows']->first()['delta']);
        $page->assertSee('2019 EFY / 2026')->assertSee('2020 EFY / 2027');
    }

    public function test_partial_future_year_does_not_publish_an_annual_improvement(): void
    {
        $future = $this->native(2027, '4.7', 'K7', 'Future indicator', null);
        PeriodicPerformance::create(['plan_item_id' => $future->planItems()->firstOrFail()->id, 'period' => '2027-july', 'actual_value' => 40, 'status' => 'approved', 'workflow_status' => 'approved_by_head_office']);
        $row = $this->compare(['comparison_type' => 'year', 'reference_source' => 'plan:2026', 'current_source' => 'plan:2027', 'reference_entity' => null, 'current_entity' => null])->assertOk()->viewData('comparison')['pairedRows']->first();
        $this->assertSame(40.0, $row['current']['actual']);
        $this->assertFalse($row['current']['complete']);
        $this->assertNull($row['delta']);
    }

    public function test_different_kpis_are_context_not_a_fabricated_improvement(): void
    {
        $other = $this->native(2026, '2.8', 'K8', 'Unrelated service quality indicator', 50);
        $row = $this->compare(['reference_source' => 'plan:2026', 'reference_entity' => 'o:'.$this->current->id, 'current_entity' => 'o:'.$other->id])->assertOk()->viewData('comparison')['pairedRows']->first();
        $this->assertSame(90.0, $row['reference']['achievement']);
        $this->assertSame(50.0, $row['current']['achievement']);
        $this->assertNull($row['delta']);
        $this->assertSame('context', $row['status']);
    }

    public function test_explicit_zero_actual_is_a_result_not_missing(): void
    {
        $this->current->planItems()->first()->performances()->update(['actual_value' => 0]);
        $row = $this->compare()->assertOk()->viewData('comparison')['pairedRows']->first();
        $this->assertSame(0.0, $row['current']['actual']);
        $this->assertSame(-80.0, $row['delta']);
    }

    public function test_historical_annual_values_cannot_be_used_as_monthly_evidence(): void
    {
        $this->compare(['reference_period' => 'july', 'current_period' => 'july'])->assertSessionHasErrors('reference_period');
    }

    public function test_department_user_cannot_request_another_departments_entity_or_options(): void
    {
        $other = Department::create(['name' => 'Private department', 'code' => 'PRIVATE', 'reporting_type' => 'monthly', 'is_active' => true]);
        $secret = $this->native(2026, '9.9', 'K9', 'Private objective description', 90, $other);
        $this->viewer->roles()->sync([Role::where('name', 'department_chief')->value('id')]);
        $this->compare(['current_entity' => 'o:'.$secret->id])->assertSessionHasErrors('current_entity');
        $this->compare(['current_department' => $other->id])->assertSessionHasErrors('current_department');
    }

    public function test_main_objectives_compare_their_indicator_sets_without_summing_unlike_units(): void
    {
        $root = $this->native(2026, 'ROOT', '', 'Current main objective', null);
        $this->current->update(['parent_id' => $root->id]);
        $oldRoot = HistoricalKpi::create(['historical_performance_import_id' => $this->historical->historical_performance_import_id, 'source_row' => 1, 'code' => '2', 'description' => 'Historical main objective', 'is_main_objective' => true]);
        $this->historical->update(['parent_code' => '2']);
        $page = $this->compare(['comparison_type' => 'objective', 'reference_entity' => 'h:'.$oldRoot->id, 'current_entity' => 'o:'.$root->id])->assertOk();
        $rows = $page->viewData('comparison')['pairedRows'];
        $this->assertCount(1, $rows);
        $this->assertSame(10.0, $rows->first()['delta']);
        $page->assertSee('Historical main objective')->assertSee('Current main objective');
    }

    public function test_master_period_mode_uses_different_months_and_preserves_the_master(): void
    {
        $item = $this->current->planItems()->first();
        foreach (['july' => 20, 'august' => 40] as $month => $actual) {
            PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-'.$month, 'actual_value' => $actual, 'status' => 'approved']);
        }
        $page = $this->compare(['comparison_type' => 'period', 'reference_source' => 'plan:2026', 'reference_entity' => null, 'current_entity' => null])->assertOk();
        $c = $page->viewData('comparison');
        $this->assertSame('master', $c['activeView']);
        $this->assertSame('july', $c['builder']['referencePeriod']);
        $this->assertSame('august', $c['builder']['currentPeriod']);
        $this->assertSame(40.0, $c['delta']);
        $page->assertSee('Master Performance Comparison');
    }

    public function test_department_annual_totals_do_not_require_the_same_kpi_or_wording(): void
    {
        $other = Department::create(['name' => 'Passenger', 'code' => 'PA', 'reporting_type' => 'monthly', 'is_active' => true]);
        $this->native(2026, '3.7', 'K8', 'Different departmental activity', 70, $other);
        $future = $this->native(2027, '4.8', 'K8', 'Changed activity in the same department', 70);
        $this->historical->update(['weight' => 10]);
        $page = $this->compare(['comparison_type' => 'department', 'reference_source' => 'plan:2026', 'current_source' => 'plan:2027', 'reference_entity' => null, 'current_entity' => null, 'current_department' => $this->department->id, 'reference_period' => 'annual', 'current_period' => 'annual'])->assertOk();
        $this->assertSame(-20.0, $page->viewData('comparison')['delta']);
        $this->assertSame($this->department->id, $page->viewData('comparison')['referenceCard']['id']);
    }

    public function test_annual_target_mode_keeps_overachievement_visible_and_can_select_an_activity(): void
    {
        $other = $this->native(2026, '8.1', 'K8', 'Another activity', 50);
        $this->current->planItems()->first()->performances()->update(['actual_value' => 120]);
        $c = $this->compare(['comparison_type' => 'target', 'reference_source' => 'plan:2026', 'reference_entity' => null, 'current_period' => 'annual'])->assertOk()->viewData('comparison');
        $this->assertCount(1, $c['pairedRows']);
        $this->assertSame(120.0, $c['currentAverage']);
        $this->assertSame(20.0, $c['delta']);
    }

    public function test_historical_annual_source_is_not_silently_compared_with_current_month(): void
    {
        $c = $this->compare(['comparison_type' => 'year', 'reference_entity' => null, 'current_entity' => null, 'current_period' => 'july'])->assertOk()->viewData('comparison');
        $this->assertSame('paired', $c['workspace']);
        $this->assertNull($c['pairedRows']->first()['delta']);
        $this->assertStringContainsString('different lengths', $c['pairedRows']->first()['reason']);
    }

    public function test_department_totals_ignore_old_period_filters_but_period_mode_rejects_self_comparisons(): void
    {
        $other = Department::create(['name' => 'Passenger', 'code' => 'PA', 'reporting_type' => 'monthly', 'is_active' => true]);
        $departmentPage = $this->compare(['comparison_type' => 'department', 'reference_source' => 'history:2018', 'reference_entity' => null, 'current_entity' => null, 'current_department' => $this->department->id, 'current_period' => 'july', 'reference_period' => 'august'])->assertOk();
        $this->assertSame('annual', $departmentPage->viewData('comparison')['builder']['currentPeriod']);
        $this->compare(['comparison_type' => 'period', 'reference_source' => 'plan:2026', 'reference_entity' => null, 'current_entity' => null, 'current_period' => 'july', 'reference_period' => 'july'])->assertSessionHasErrors('reference_period');
        $this->compare(['comparison_type' => 'period', 'reference_source' => 'plan:2026', 'reference_entity' => null, 'current_entity' => null, 'reference_department' => $this->department->id, 'current_department' => $other->id, 'current_period' => 'july', 'reference_period' => 'august'])->assertSessionHasErrors('reference_department');
    }

    public function test_focused_presets_do_not_repeat_the_master_shared_settings_form(): void
    {
        $page = $this->compare(['comparison_view' => 'kpi'])->assertOk();
        $page->assertSee('Open full Master workspace')->assertDontSee('Shared filters &amp; result settings', false)->assertDontSee('id="pc-frequency"', false);
        $page->assertSee('name="reporting_frequency"', false);
    }

    public function test_a_source_refresh_discards_obsolete_entity_ids_instead_of_erroring(): void
    {
        $next = $this->native(2027, '4.7', 'K7', 'Future renamed indicator', 95);
        $c = $this->compare(['current_source' => 'plan:2027', 'configure' => 1])->assertOk()->viewData('comparison');
        $this->assertSame('o:'.$next->id, $c['builder']['currentEntity']);
    }

    public function test_future_year_can_complete_from_all_scheduled_months_without_annual_record(): void
    {
        $next = $this->native(2027, '4.7', 'K7', 'Future indicator', null);
        foreach (['july' => 40, 'august' => 55] as $month => $actual) {
            PeriodicPerformance::create(['plan_item_id' => $next->planItems()->first()->id, 'period' => '2027-'.$month, 'actual_value' => $actual, 'status' => 'approved']);
        }
        $c = $this->compare(['comparison_type' => 'year', 'reference_source' => 'plan:2026', 'current_source' => 'plan:2027', 'reference_entity' => null, 'current_entity' => null])->assertOk()->viewData('comparison');
        $this->assertTrue($c['pairedRows']->first()['current']['complete']);
        $this->assertSame(5.0, $c['delta']);
    }

    public function test_master_is_available_without_any_historical_workbook(): void
    {
        HistoricalKpiMapping::query()->delete();
        HistoricalKpi::query()->delete();
        HistoricalPerformanceImport::query()->delete();
        $this->native(2027, '4.7', 'K7', 'Future indicator', 95);
        $page = $this->actingAs($this->viewer)->get(route('performance.comparison'))->assertOk();
        $page->assertSee('name="comparison_type"', false);
        $this->assertSame(5.0, $page->viewData('comparison')['delta']);
    }

    public function test_draft_changed_or_ambiguous_relationships_cannot_become_improvement_claims(): void
    {
        $mapping = HistoricalKpiMapping::first();
        $mapping->update(['status' => 'draft']);
        $this->assertNull($this->compare()->assertOk()->viewData('comparison')['pairedRows']->first()['delta']);
        $mapping->update(['status' => 'approved', 'mapping_type' => 'definition_changed']);
        $this->assertSame('context', $this->compare()->assertOk()->viewData('comparison')['pairedRows']->first()['status']);
        $mapping->delete();
        $this->native(2026, '7.7', 'K7', 'Duplicate KPI identity', 70);
        $rows = $this->compare(['current_entity' => null])->assertOk()->viewData('comparison')['pairedRows'];
        $this->assertSame(0, $rows->whereNotNull('delta')->count());
    }

    public function test_native_organization_result_uses_accountable_owner_not_sum_of_departments(): void
    {
        $other = Department::create(['name' => 'Passenger', 'code' => 'PA', 'reporting_type' => 'monthly', 'is_active' => true]);
        $plan = Plan::create(['department_id' => $other->id, 'year' => 2026, 'title' => 'Other plan', 'type' => 'annual', 'status' => 'approved', 'created_by' => $this->viewer->id]);
        $item = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $this->current->id, 'annual_target' => 100]);
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-annual', 'actual_value' => 40, 'status' => 'approved']);
        $row = $this->compare()->assertOk()->viewData('comparison')['pairedRows']->first();
        $this->assertSame(90.0, $row['current']['actual']);
        $this->assertSame(10.0, $row['delta']);
    }

    public function test_target_period_is_derived_from_current_not_the_old_hidden_benchmark(): void
    {
        $item = $this->current->planItems()->first();
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-july', 'actual_value' => 40, 'status' => 'approved']);
        $c = $this->compare(['comparison_type' => 'target', 'reference_period' => 'annual', 'current_period' => 'july'])->assertOk()->viewData('comparison');
        $this->assertSame('july', $c['builder']['referencePeriod']);
        $this->assertSame(-20.0, $c['delta']);
    }

    public function test_conflicting_kpi_codes_cannot_match_on_identical_wording(): void
    {
        $this->historical->update(['description' => $this->current->description, 'kpi_code' => 'K8']);
        HistoricalKpiMapping::query()->delete();
        $row = $this->compare()->assertOk()->viewData('comparison')['pairedRows']->first();
        $this->assertSame('context', $row['status']);
        $this->assertNull($row['delta']);
    }

    public function test_duplicate_reference_codes_do_not_arbitrarily_consume_a_unique_current_code(): void
    {
        $this->native(2026, '1.8', 'K7', 'Another ambiguous reference', 60);
        $this->native(2027, '3.7', 'K7', 'Unique future KPI', 95);
        $c = $this->compare(['comparison_type' => 'year', 'reference_source' => 'plan:2026', 'current_source' => 'plan:2027', 'reference_entity' => null, 'current_entity' => null])->assertOk()->viewData('comparison');
        $this->assertSame(0, $c['matched']);
        $this->assertNull($c['delta']);
    }

    public function test_percentage_only_evidence_does_not_invent_missing_raw_changes(): void
    {
        $this->historical->update(['annual_target' => null, 'annual_actual' => null]);
        $row = $this->compare()->assertOk()->viewData('comparison')['pairedRows']->first();
        $this->assertSame(10.0, $row['delta']);
        $this->assertNull($row['actualChange']);
        $this->assertNull($row['targetChange']);
    }

    public function test_native_parent_containers_are_selectable_but_not_counted_twice_in_summaries(): void
    {
        $root = $this->native(2026, 'ROOT', '', 'Main container', 50);
        $this->current->update(['parent_id' => $root->id]);
        $futureRoot = $this->native(2027, 'ROOT', '', 'Main container', 70);
        $future = $this->native(2027, '3.7', 'K7', 'Future leaf indicator', 95);
        $future->update(['parent_id' => $futureRoot->id]);
        $c = $this->compare(['comparison_type' => 'year', 'reference_source' => 'plan:2026', 'current_source' => 'plan:2027', 'reference_entity' => null, 'current_entity' => null])->assertOk()->viewData('comparison');
        $this->assertCount(1, $c['pairedRows']);
        $this->assertSame(90.0, $c['referenceAvailable']);
        $this->assertSame(95.0, $c['currentAvailable']);
        $this->assertTrue($c['builder']['currentOptions']->contains('id', 'o:'.$futureRoot->id));
    }

    public function test_single_department_has_no_fabricated_department_to_organization_comparison(): void
    {
        $this->historical->update(['weight' => 10]);
        $c = $this->compare(['comparison_type' => 'department', 'reference_entity' => null, 'current_entity' => null])->assertOk()->viewData('comparison');
        $this->assertSame(1, $c['matched']);
        $this->assertSame(10.0, $c['delta']);
        $this->assertSame($c['currentCard']['id'], $c['referenceCard']['id']);
    }

    public function test_focused_navigation_retains_applicable_department_and_shared_search(): void
    {
        $page = $this->compare(['current_department' => $this->department->id, 'reference_department' => $this->department->id, 'search' => 'K7'])->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        foreach ((new \DOMXPath($document))->query('//nav[contains(@class,"pc-modes")]/a') as $link) {
            if (! str_contains($link->getAttribute('href'), 'comparison_type=period')) {
                continue;
            }
            $c = $this->get($link->getAttribute('href'))->assertOk()->viewData('comparison');
            $this->assertSame($this->department->id, $c['builder']['currentDepartment']);
            $this->assertSame($this->department->id, $c['builder']['referenceDepartment']);
            $this->assertSame('K7', $c['search']);

            return;
        }
        $this->fail('Period preset link not found.');
    }

    public function test_department_default_prefers_departments_with_approved_plan_evidence(): void
    {
        $this->current->planItems()->first()->performances()->delete();
        foreach (['A first empty department', 'B second empty department'] as $i => $name) {
            Department::create(['name' => $name, 'code' => 'EMPTY'.$i, 'is_active' => true, 'reporting_type' => 'monthly']);
        }
        $c = $this->compare(['comparison_type' => 'department', 'reference_entity' => null, 'current_entity' => null])->assertOk()->viewData('comparison');
        $this->assertSame($this->department->id, $c['builder']['currentDepartment']);
        $this->assertSame('Operation', $c['currentCard']['name']);
        $this->assertNotSame($c['currentLabel'], $c['referenceLabel']);
    }

    public function test_legacy_efy_objective_year_does_not_create_a_phantom_native_source(): void
    {
        $this->current->update(['target_year' => 2019]);
        $c = $this->actingAs($this->viewer)->get(route('performance.comparison', ['comparison_method' => 'historical', 'performance_year' => 2019]))->assertOk()->viewData('comparison');
        $this->assertFalse($c['builder']['sources']->has('plan:2019'));
        $this->assertSame('plan:2026', $c['builder']['currentSource']['key']);
        $this->assertSame('2019 EFY / 2026', $c['currentLabel']);
    }
}
