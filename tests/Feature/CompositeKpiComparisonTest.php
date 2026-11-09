<?php

namespace Tests\Feature;

use App\Models\{Department, HistoricalKpi, HistoricalKpiMapping, HistoricalPerformanceImport, PeriodicPerformance, Plan, PlanItem, Role, StrategicObjective, User};
use App\Services\ComparisonEvidence;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompositeKpiComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;
    private Department $department;
    private HistoricalKpi $historical;
    private StrategicObjective $current;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->department = Department::create(['name' => 'Digital Office', 'code' => 'DO', 'is_active' => true, 'reporting_type' => 'monthly']);
        Department::create(['name' => 'Research', 'code' => 'RE', 'is_active' => true, 'reporting_type' => 'monthly']);
        $this->viewer = User::factory()->create(['department_id' => $this->department->id, 'must_change_password' => false]);
        $this->viewer->roles()->attach(Role::where('name', 'plan_report_department_expert')->firstOrFail());
        $import = HistoricalPerformanceImport::create(['year' => 2018, 'original_filename' => '2018 annual source.xlsx', 'source_hash' => str_repeat('a', 64), 'status' => 'locked']);
        $this->historical = HistoricalKpi::create(['historical_performance_import_id' => $import->id, 'source_row' => 215, 'code' => '14', 'kpi_code' => 'K22', 'description' => 'Digitalization programme', 'weight' => 5, 'is_main_objective' => true, 'performers' => 'Digital Office, Research']);
        $this->current = StrategicObjective::create(['code' => '15', 'kpi_code' => 'K22', 'description' => 'Digital transformation programme', 'weight' => 5, 'department_id' => $this->department->id, 'target_year' => 2026, 'status' => 'approved', 'is_active' => true, 'summary_type' => 'weighted']);
        $this->plan = Plan::create(['department_id' => $this->department->id, 'year' => 2026, 'title' => 'Digital annual plan', 'type' => 'annual', 'status' => 'approved', 'created_by' => $this->viewer->id]);
        PlanItem::create(['plan_id' => $this->plan->id, 'strategic_objective_id' => $this->current->id, 'annual_target' => 0, 'weight' => 5]);
        $this->mapping($this->historical, $this->current);
        $inputs = [
            [.5, '%', 100, 100, 100], [1, '%', 100, 86, 86], [.5, '%', 70, 67, 95.7],
            [.5, '%', 40, 40, 100], [.5, '%', 50, 50, 100], [.3, '%', 50, 20, 40],
            [.2, '%', 25, 24, 96], [.3, 'No.', 25, 15, 60], [.2, 'No.', 50, 45, 90],
            [.3, 'No', 100, 100, 100], [.3, '%', 100, 100, 100], [.2, '%', 1, .9, 90], [.2, '%', 1, .9, 90],
        ];
        foreach ($inputs as $index => [$weight, $unit, $target, $actual, $achievement]) {
            $number = $index + 1;
            $historical = HistoricalKpi::create(['historical_performance_import_id' => $import->id, 'source_row' => 215 + $number, 'code' => '14.'.$number, 'parent_code' => '14', 'description' => 'Historical digital activity '.$number, 'weight' => $weight, 'unit' => $unit, 'annual_target' => $target, 'annual_actual' => $actual, 'reported_achievement' => $achievement, 'direction_status' => 'needs_review', 'performers' => $number === 6 ? 'Digital Office, Research' : 'Digital Office']);
            $objective = StrategicObjective::create(['code' => '15.'.$number, 'parent_id' => $this->current->id, 'description' => 'Digital activity '.$number, 'weight' => $weight, 'unit' => $unit, 'annual_target' => $target, 'department_id' => $this->department->id, 'target_year' => 2026, 'status' => 'approved', 'is_active' => true, 'goal_direction' => 'increase']);
            PlanItem::create(['plan_id' => $this->plan->id, 'strategic_objective_id' => $objective->id, 'annual_target' => $target, 'weight' => $weight, 'unit' => $unit === '%' ? 'percent' : 'number']);
            $this->mapping($historical, $objective);
        }
    }

    private function mapping(HistoricalKpi $historical, StrategicObjective $current): void
    {
        HistoricalKpiMapping::create(['historical_kpi_id' => $historical->id, 'current_objective_id' => $current->id, 'mapping_type' => 'renamed', 'status' => 'approved', 'comparable' => true]);
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->viewer)->get(route('performance.comparison', array_merge([
            'comparison_type' => 'kpi', 'comparison_view' => 'kpi', 'reference_source' => 'history:2018', 'current_source' => 'plan:2026',
            'reference_entity' => 'h:'.$this->historical->id, 'current_entity' => 'o:'.$this->current->id,
            'reference_department' => $this->department->id, 'current_department' => $this->department->id,
        ], $query)))->assertOk();
    }

    private function fillNative(): void
    {
        foreach ($this->historical->import->kpis->where('is_main_objective', false) as $historical) {
            $objectiveId = $historical->mappings->first()->current_objective_id;
            $item = PlanItem::where('strategic_objective_id', $objectiveId)->firstOrFail();
            PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-annual', 'actual_value' => $historical->annual_actual, 'status' => 'approved', 'workflow_status' => 'approved_by_head_office']);
        }
    }

    public function test_historical_composite_matches_the_thirteen_activity_workbook_calculation(): void
    {
        $page = $this->page();
        $row = $page->viewData('comparison')['pairedRows']->sole();
        $reference = $row['reference'];
        $this->assertSame('composite', $reference['measureType']);
        $this->assertSame(89.41, $reference['achievement']);
        $this->assertSame(4.4705, $reference['rollup']['score']);
        $this->assertSame(5.0, $reference['rollup']['weight']);
        $this->assertSame(13, $reference['rollup']['completed']);
        $this->assertCount(13, $reference['rollup']['components']);
        $this->assertTrue($reference['complete']);
        $this->assertNull($reference['target']);
        $this->assertNull($reference['actual']);
        $this->assertFalse($row['current']['complete']);
        $this->assertNull($row['delta']);
        $page->assertSee('89.41%')->assertSee('4.4705')->assertSee('Weighted achievement')->assertSee('Activity breakdown')->assertSee('Historical digital activity 13')->assertSee('Source row 228');
        $page->assertSee('<dd>4.4705</dd>', false);
        $this->assertNull($this->historical->fresh()->annual_actual);
    }

    public function test_compatible_complete_components_allow_a_weighted_comparison_without_raw_differences(): void
    {
        $this->fillNative();
        $row = $this->page()->viewData('comparison')['pairedRows']->sole();
        $this->assertTrue($row['current']['complete']);
        $this->assertSame(89.41, $row['current']['achievement']);
        $this->assertSame(0.0, $row['delta']);
        $this->assertNull($row['actualChange']);
        $this->assertNull($row['targetChange']);
        $this->assertCount(13, $row['componentPairs']);
    }

    public function test_changed_relative_weights_show_scores_but_not_like_for_like_improvement(): void
    {
        $this->fillNative();
        $item = $this->plan->items()->whereHas('strategicObjective', fn ($q) => $q->where('code', '15.2'))->firstOrFail();
        $item->update(['weight' => 2]);
        $row = $this->page()->viewData('comparison')['pairedRows']->sole();
        $this->assertNotNull($row['reference']['achievement']);
        $this->assertNull($row['delta']);
        $this->assertStringContainsString('weight', strtolower($row['reason']));
    }

    public function test_missing_activity_is_not_zero_or_a_completed_composite(): void
    {
        $leaf = HistoricalKpi::where('code', '14.2')->firstOrFail();
        $leaf->update(['annual_actual' => null, 'reported_achievement' => null]);
        $reference = $this->page()->viewData('comparison')['pairedRows']->sole()['reference'];
        $this->assertFalse($reference['complete']);
        $this->assertNull($reference['achievement']);
        $this->assertEqualsWithDelta(3.6105, $reference['rollup']['score'], .00001);
        $this->assertSame(12, $reference['rollup']['completed']);
        $this->assertSame(13, $reference['rollup']['expected']);
        $this->assertSame(5.0, $reference['rollup']['weight']);
    }

    public function test_explicit_zero_activity_is_valid_evidence_in_a_composite(): void
    {
        HistoricalKpi::where('code', '14.2')->update(['annual_actual' => 0, 'reported_achievement' => 0]);
        $reference = $this->page()->viewData('comparison')['pairedRows']->sole()['reference'];
        $this->assertTrue($reference['complete']);
        $this->assertSame(72.21, $reference['achievement']);
        $this->assertSame(0.0, collect($reference['rollup']['components'])->firstWhere('code', '14.2')['actual']);
    }

    public function test_unknown_weights_do_not_create_a_reweighted_final_result(): void
    {
        HistoricalKpi::where('code', '14.2')->update(['weight' => null]);
        $reference = $this->page()->viewData('comparison')['pairedRows']->sole()['reference'];
        $this->assertFalse($reference['complete']);
        $this->assertNull($reference['achievement']);
        $this->assertNotEmpty($reference['rollup']['warnings']);
    }

    public function test_direct_measured_parent_is_not_replaced_by_a_composite(): void
    {
        $this->historical->update(['annual_target' => 100, 'annual_actual' => 73, 'unit' => '%', 'direction_status' => 'confirmed']);
        $row = $this->page()->viewData('comparison')['pairedRows']->sole();
        $this->assertSame(100.0, $row['reference']['target']);
        $this->assertSame(73.0, $row['reference']['actual']);
        $this->assertSame(73.0, $row['reference']['achievement']);
        $this->assertSame('direct', $row['reference']['measureType']);
        $this->assertNull($row['delta']);
        $this->assertStringContainsString('measurement', strtolower($row['reason']));
    }

    public function test_general_evidence_summaries_do_not_count_composite_and_its_activities_twice(): void
    {
        $this->fillNative();
        $frame = app(ComparisonEvidence::class)->load($this->viewer, ['kind' => 'plan', 'year' => 2026], $this->department->id, 'all', 'annual', 'official');
        $this->assertCount(13, $frame['records']);
        $parent = $frame['entities']->firstWhere('id', 'o:'.$this->current->id);
        $this->assertSame('composite', $parent['measureType']);
        $this->assertSame(89.41, $parent['achievement']);
    }

    public function test_csv_payload_contains_composite_points_and_each_activity(): void
    {
        $html = $this->page()->getContent();
        preg_match('/<script id="pc-export-data" type="application\/json">(.*?)<\/script>/s', $html, $matches);
        $export = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('composite', $export[0]['referenceMeasureType']);
        $this->assertSame(4.4705, $export[0]['referenceWeightedScore']);
        $this->assertCount(14, $export);
        $this->assertSame('14.1', $export[1]['referenceCode']);
        $this->assertSame('14.13', $export[13]['referenceCode']);
    }

    public function test_nested_composite_uses_terminal_activities_once(): void
    {
        $this->fillNative();
        $branch = StrategicObjective::where('code', '15.2')->firstOrFail();
        $branch->update(['annual_target' => null]);
        $branch->planItems()->first()->performances()->delete();
        $branch->planItems()->update(['annual_target' => 0]);
        foreach ([[1, .4, 80], [2, .6, 90]] as [$number, $weight, $actual]) {
            $child = StrategicObjective::create(['code' => '15.2.'.$number, 'parent_id' => $branch->id, 'description' => 'Nested component '.$number, 'department_id' => $this->department->id, 'target_year' => 2026, 'weight' => $weight, 'annual_target' => 100, 'unit' => '%', 'status' => 'approved']);
            $item = PlanItem::create(['plan_id' => $this->plan->id, 'strategic_objective_id' => $child->id, 'annual_target' => 100, 'weight' => $weight]);
            PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-annual', 'actual_value' => $actual, 'status' => 'approved']);
        }
        $current = $this->page()->viewData('comparison')['pairedRows']->sole()['current'];
        $this->assertCount(14, $current['rollup']['components']);
        $this->assertSame(5.0, $current['rollup']['weight']);
        $this->assertEqualsWithDelta(4.47055, $current['rollup']['score'], .000001);
        $this->assertSame(89.41, $current['achievement']);
        $this->assertFalse(collect($current['rollup']['components'])->contains('code', '15.2'));
    }

    public function test_department_filter_does_not_publish_an_incomplete_objective_or_leak_other_activities(): void
    {
        HistoricalKpi::where('code', '14.2')->update(['performers' => 'Research']);
        $this->viewer->roles()->sync([Role::where('name', 'department_chief')->value('id')]);
        $page = $this->page();
        $reference = $page->viewData('comparison')['pairedRows']->sole()['reference'];
        $this->assertFalse($reference['complete']);
        $this->assertNull($reference['achievement']);
        $this->assertNotEmpty($reference['rollup']['warnings']);
        $page->assertDontSee('Historical digital activity 2');
    }

    public function test_actual_vs_target_for_a_composite_uses_its_points_benchmark(): void
    {
        $this->fillNative();
        $row = $this->page(['comparison_type' => 'target', 'comparison_view' => 'target', 'reference_entity' => null])->viewData('comparison')['pairedRows']->sole();
        $this->assertSame(100.0, $row['reference']['achievement']);
        $this->assertSame(5.0, $row['reference']['rollup']['score']);
        $this->assertSame(-10.59, $row['delta']);
        $this->assertNull($row['reference']['actual']);
    }

    public function test_annual_overview_exposes_the_same_composite_without_adding_it_to_leaf_averages(): void
    {
        $page = $this->page(['comparison_type' => 'year', 'comparison_view' => 'year', 'reference_entity' => null, 'current_entity' => null]);
        $row = $page->viewData('comparison')['rows']->firstWhere('historicalId', $this->historical->id);
        $this->assertSame(89.41, $row['historicalAchievement']);
        $this->assertSame(4.4705, $row['compositePair']['reference']['rollup']['score']);
        $this->assertFalse($row['summaryEligible']);
        $page->assertSee('4.4705')->assertSee('Activity breakdown');
        preg_match('/<script id="pc-export-data" type="application\/json">(.*?)<\/script>/s', $page->getContent(), $matches);
        $export = collect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(4.4705, $export->firstWhere('historicalCode', '14')['referenceWeightedScore']);
    }

    public function test_unmatched_composite_exports_all_of_its_activities(): void
    {
        $this->historical->mappings()->delete();
        $this->current->update(['kpi_code' => 'K23']);
        $page = $this->page(['current_entity' => null]);
        $row = $page->viewData('comparison')['pairedRows']->sole();
        $this->assertNull($row['current']);
        $this->assertCount(13, $row['componentPairs']);
        preg_match('/<script id="pc-export-data" type="application\/json">(.*?)<\/script>/s', $page->getContent(), $matches);
        $this->assertCount(14, json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_composite_versus_direct_export_does_not_drop_the_composite_activities(): void
    {
        $this->current->update(['summary_type' => 'manual_override', 'annual_target' => 100]);
        $this->current->planItems()->update(['annual_target' => 100]);
        $row = $this->page()->viewData('comparison')['pairedRows']->sole();
        $this->assertSame('direct', $row['current']['measureType']);
        $this->assertNull($row['delta']);
        $this->assertCount(13, $row['componentPairs']);
    }

    public function test_ambiguous_approved_component_links_cannot_fall_back_to_a_kpi_match(): void
    {
        $this->fillNative();
        $historical = HistoricalKpi::where('code', '14.1')->firstOrFail();
        $native = StrategicObjective::where('code', '15.1')->firstOrFail();
        $historical->update(['kpi_code' => 'K1']);
        $native->update(['kpi_code' => 'K1']);
        $this->mapping($historical, StrategicObjective::where('code', '15.2')->firstOrFail());
        $row = $this->page()->viewData('comparison')['pairedRows']->sole();
        $this->assertNull($row['delta']);
        $this->assertSame('context', $row['status']);
    }

    public function test_flat_summaries_do_not_count_a_direct_container_with_its_detail(): void
    {
        $this->fillNative();
        $branch = StrategicObjective::where('code', '15.2')->firstOrFail();
        $branch->update(['kpi_code' => 'K1', 'summary_type' => 'manual_override']);
        $child = StrategicObjective::create(['code' => '15.2.1', 'parent_id' => $branch->id, 'description' => 'Direct KPI supporting detail', 'department_id' => $this->department->id, 'target_year' => 2026, 'weight' => 1, 'annual_target' => 100, 'unit' => '%', 'status' => 'approved']);
        PlanItem::create(['plan_id' => $this->plan->id, 'strategic_objective_id' => $child->id, 'annual_target' => 100, 'weight' => 1]);
        $frame = app(ComparisonEvidence::class)->load($this->viewer, ['kind' => 'plan', 'year' => 2026], $this->department->id, 'all', 'annual', 'official');
        $this->assertCount(13, $frame['records']);
        $this->assertTrue($frame['records']->contains('id', 'o:'.$branch->id));
        $this->assertFalse($frame['records']->contains('id', 'o:'.$child->id));
        $this->assertTrue($frame['entities']->contains('id', 'o:'.$child->id));
        $comparison = $this->page(['comparison_type' => 'objective'])->viewData('comparison');
        $this->assertTrue($comparison['pairedRows']->pluck('current.id')->contains('o:'.$branch->id));
    }

    public function test_filtered_annual_csv_retains_the_component_evidence_without_duplicates(): void
    {
        $page = $this->page(['comparison_type' => 'year', 'comparison_view' => 'year', 'reference_entity' => null, 'current_entity' => null, 'search' => 'K22']);
        preg_match('/<script id="pc-export-data" type="application\/json">(.*?)<\/script>/s', $page->getContent(), $matches);
        $export = collect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR));
        $this->assertCount(14, $export);
        $activity = $export->firstWhere('historicalCode', '14.2');
        $this->assertEquals(1.0, $activity['referenceWeight']);
        $this->assertSame(.86, $activity['referenceContribution']);
        $allPage = $this->page(['comparison_type' => 'year', 'comparison_view' => 'year', 'reference_entity' => null, 'current_entity' => null]);
        preg_match('/<script id="pc-export-data" type="application\/json">(.*?)<\/script>/s', $allPage->getContent(), $allMatches);
        $allExport = collect(json_decode($allMatches[1], true, flags: JSON_THROW_ON_ERROR));
        $this->assertCount(14, $allExport);
        $this->assertSame(.86, $allExport->firstWhere('historicalCode', '14.2')['referenceContribution']);
    }
}
