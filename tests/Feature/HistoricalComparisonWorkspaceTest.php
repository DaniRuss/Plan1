<?php

namespace Tests\Feature;

use App\Models\{Department, HistoricalKpi, HistoricalKpiMapping, HistoricalPerformanceImport, PeriodicPerformance, Plan, PlanItem, Role, StrategicObjective, User};
use App\Services\PerformanceComparison;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class HistoricalComparisonWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;
    private Department $department;
    private HistoricalKpi $historical;
    private StrategicObjective $objective;
    private PlanItem $item;
    private HistoricalKpiMapping $mapping;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->department = Department::create(['name'=>'Operation', 'code'=>'OP', 'is_active'=>true, 'reporting_type'=>'monthly']);
        $this->viewer = User::factory()->create(['department_id'=>$this->department->id, 'must_change_password'=>false]);
        $this->viewer->roles()->attach(Role::where('name', 'plan_report_department_expert')->firstOrFail());
        $import = HistoricalPerformanceImport::create(['year'=>2018, 'original_filename'=>'2018 annual source.xlsx', 'source_hash'=>str_repeat('e',64), 'status'=>'locked']);
        $this->historical = HistoricalKpi::create(['historical_performance_import_id'=>$import->id, 'source_row'=>23, 'code'=>'1.1', 'original_code'=>'1.1', 'parent_code'=>'1', 'description'=>'Historical cargo volume', 'unit'=>'Tons', 'normalized_unit'=>'tons', 'annual_target'=>100, 'annual_actual'=>80, 'reported_achievement'=>80, 'goal_direction'=>'increase', 'direction_status'=>'confirmed', 'weight'=>10, 'performers'=>'Operation']);
        $this->objective = StrategicObjective::create(['department_id'=>$this->department->id, 'code'=>'1.2', 'description'=>'Current cargo volume', 'target_year'=>2026, 'unit'=>'Tons', 'annual_target'=>120, 'weight'=>10, 'status'=>'approved', 'is_active'=>true, 'goal_direction'=>'increase', 'target_distribution'=>'sum']);
        $plan = Plan::create(['department_id'=>$this->department->id, 'year'=>2026, 'title'=>'Annual plan', 'type'=>'annual', 'status'=>'approved', 'created_by'=>$this->viewer->id]);
        $this->item = PlanItem::create(['plan_id'=>$plan->id, 'strategic_objective_id'=>$this->objective->id, 'annual_target'=>120, 'july_target'=>60, 'august_target'=>60]);
        $this->mapping = HistoricalKpiMapping::create(['historical_kpi_id'=>$this->historical->id, 'current_objective_id'=>$this->objective->id, 'mapping_type'=>'renamed', 'status'=>'approved', 'comparable'=>true]);
    }

    private function comparison(array $query = []): array
    {
        return app(PerformanceComparison::class)->build($this->viewer, Request::create('/', 'GET', $query));
    }

    private function actual(string $period, float $value, string $state = 'approved_by_head_office'): void
    {
        PeriodicPerformance::create(['plan_item_id'=>$this->item->id, 'period'=>$period, 'actual_value'=>$value, 'status'=>'submitted', 'workflow_status'=>$state]);
    }

    public function test_default_opens_imported_history_without_overriding_an_explicit_standard_comparison(): void
    {
        $this->assertSame('historical', $this->comparison()['method']);
        $this->assertSame('year', $this->comparison(['comparison_method'=>'year'])['method']);
        $this->assertSame('year', $this->comparison(['reference_year'=>2025])['method']);
    }

    public function test_source_and_baseline_remain_visible_before_current_results_exist(): void
    {
        $result = $this->comparison(['comparison_method'=>'historical']);
        $this->assertSame(80.0, $result['baselineAverage']);
        $this->assertNull($result['delta']);
        $this->assertSame(0, $result['readyPairs']);
        $this->assertSame('missing_current', $result['rows']->first()['readiness']);
        $this->actingAs($this->viewer)->get(route('performance.comparison'))
            ->assertOk()->assertSee('2018 annual source.xlsx')->assertSee('Historical cargo volume')
            ->assertSee('Current cargo volume')->assertSee('Source row 23')->assertSee('80.00%')
            ->assertSee('Awaiting current results');
    }

    public function test_gregorian_baseline_identifier_resolves_the_efy_workbook_without_duplicate_imports(): void
    {
        $result = $this->comparison(['comparison_method'=>'historical', 'historical_year'=>2025, 'performance_year'=>2026]);
        $this->assertSame(2018, $result['historicalYear']);
        $this->assertSame('2018 EFY / 2025', $result['referenceLabel']);
        $this->assertSame('2019 EFY / 2026', $result['currentLabel']);
        $this->assertSame(1, HistoricalPerformanceImport::count());
    }

    public function test_efy_current_identifier_uses_the_existing_2026_plan_instead_of_searching_for_a_second_dataset(): void
    {
        $this->actual('2026-annual',108);
        $result = $this->comparison(['comparison_method'=>'historical','historical_year'=>2025,'performance_year'=>2019]);
        $this->assertSame(2026,$result['year']);
        $this->assertSame(10.0,$result['rows']->first()['delta']);
    }

    public function test_enterprise_result_uses_accountable_department_not_a_collaborator_or_double_sum(): void
    {
        $other = Department::create(['name'=>'Logistics','code'=>'LOG','is_active'=>true,'reporting_type'=>'monthly']);
        $this->objective->update(['lead_department_id'=>$other->id]);
        $this->actual('2026-annual',108);
        $result = $this->comparison(['comparison_method'=>'historical']);
        $this->assertNull($result['rows']->first()['currentActual']);
        $plan = Plan::create(['department_id'=>$other->id,'year'=>2026,'title'=>'Lead annual plan','type'=>'annual','status'=>'approved','created_by'=>$this->viewer->id]);
        $item = PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$this->objective->id,'annual_target'=>120]);
        PeriodicPerformance::create(['plan_item_id'=>$item->id,'period'=>'2026-annual','actual_value'=>96,'status'=>'approved','workflow_status'=>'approved_by_head_office']);
        $result = $this->comparison(['comparison_method'=>'historical']);
        $this->assertSame(96.0,$result['rows']->first()['currentActual']);
        $this->assertSame(0.0,$result['rows']->first()['delta']);
    }

    public function test_partial_approved_months_are_visible_but_not_misrepresented_as_a_final_annual_comparison(): void
    {
        $this->actual('2026-july', 50);
        $row = $this->comparison(['comparison_method'=>'historical'])['rows']->first();
        $this->assertSame(50.0, $row['currentActual']);
        $this->assertSame('incomplete_current', $row['readiness']);
        $this->assertSame(1, $row['completedPeriods']);
        $this->assertSame(2, $row['expectedPeriods']);
        $this->assertNull($row['delta']);
        $this->assertNull($row['actualChange']);
    }

    public function test_complete_approved_annual_values_produce_the_expected_pairwise_change(): void
    {
        $this->actual('2026-july', 50);
        $this->actual('2026-august', 58);
        $result = $this->comparison(['comparison_method'=>'historical']);
        $row = $result['rows']->first();
        $this->assertSame('ready', $row['readiness']);
        $this->assertSame(90.0, $row['currentAchievement']);
        $this->assertSame(10.0, $row['delta']);
        $this->assertSame(28.0, $row['actualChange']);
        $this->assertSame(1, $result['readyPairs']);
    }

    public function test_explicit_zero_target_month_still_requires_an_approved_report(): void
    {
        $this->item->update(['july_target'=>120,'august_target'=>0]);
        $this->actual('2026-july',108);
        $row = $this->comparison(['comparison_method'=>'historical'])['rows']->first();
        $this->assertSame(2,$row['expectedPeriods']);
        $this->assertNull($row['delta']);
        $this->actual('2026-august',0);
        $this->assertSame('ready',$this->comparison(['comparison_method'=>'historical'])['rows']->first()['readiness']);
    }

    public function test_explicit_annual_record_is_used_without_adding_its_months_again(): void
    {
        $this->actual('2026-annual', 108);
        $this->actual('2026-july', 50);
        $row = $this->comparison(['comparison_method'=>'historical'])['rows']->first();
        $this->assertSame(108.0, $row['currentActual']);
        $this->assertSame(10.0, $row['delta']);
    }

    public function test_provisional_performance_never_enters_the_historical_annual_result(): void
    {
        $this->actual('2026-annual', 108, 'submitted_to_directorate');
        $row = $this->comparison(['comparison_method'=>'historical'])['rows']->first();
        $this->assertNull($row['currentActual']);
        $this->assertSame('missing_current', $row['readiness']);
    }

    public function test_draft_mapping_does_not_publish_raw_growth_or_official_changes(): void
    {
        $this->actual('2026-annual', 108);
        $this->mapping->update(['status'=>'draft']);
        $row = $this->comparison(['comparison_method'=>'historical'])['rows']->first();
        $this->assertSame('mapping_review', $row['readiness']);
        $this->assertNull($row['delta']);
        $this->assertNull($row['actualChange']);
        $this->assertNull($row['targetChange']);
    }

    public function test_incompatible_units_and_split_mappings_are_context_not_a_false_comparison(): void
    {
        $this->actual('2026-annual', 108);
        $this->objective->update(['unit'=>'Hours']);
        $row = $this->comparison(['comparison_method'=>'historical'])['rows']->first();
        $this->assertSame('unit_mismatch', $row['readiness']);
        $this->assertNull($row['delta']);
        $this->objective->update(['unit'=>'Tons']);
        $this->mapping->update(['mapping_type'=>'split']);
        $row = $this->comparison(['comparison_method'=>'historical'])['rows']->first();
        $this->assertSame('definition_review', $row['readiness']);
        $this->assertNull($row['delta']);
    }

    public function test_reported_historical_achievement_is_preserved_and_percentage_unit_aliases_match(): void
    {
        $this->historical->update(['unit'=>'%', 'normalized_unit'=>'percentage', 'reported_achievement'=>75]);
        $this->objective->update(['unit'=>'Percentage (%)']);
        $this->actual('2026-annual',108);
        $row = $this->comparison(['comparison_method'=>'historical'])['rows']->first();
        $this->assertSame(75.0, $row['historicalAchievement']);
        $this->assertSame(15.0, $row['delta']);
    }

    public function test_mappings_to_a_different_plan_year_do_not_count_as_current_mapping_coverage(): void
    {
        $this->objective->update(['target_year'=>2019]);
        $result = $this->comparison(['comparison_method'=>'historical', 'performance_year'=>2026]);
        $this->assertSame(0, $result['matched']);
        $this->assertSame('unmapped', $result['rows']->first()['readiness']);
    }

    public function test_year_comparison_with_an_annual_import_does_not_claim_to_have_historical_monthly_results(): void
    {
        $this->actingAs($this->viewer)->get(route('performance.comparison', ['comparison_method'=>'year', 'performance_year'=>2026, 'reference_year'=>2025, 'performance_period'=>'september']))
            ->assertOk()->assertSee('2018 EFY / 2025')->assertSee('annual results only');
    }

    public function test_year_view_uses_the_imported_reference_instead_of_searching_an_empty_native_year(): void
    {
        $page=$this->actingAs($this->viewer)->get(route('performance.comparison',['comparison_method'=>'year']))->assertOk();
        $result=$page->viewData('comparison');
        $this->assertArrayHasKey('historicalYear',$result);
        $this->assertSame('year',$result['method']);
        $this->assertSame(2018,$result['historicalYear']);
        $this->assertSame(80.0,$result['rows']->first()['historicalAchievement']);
        $this->assertSame(80.0,$result['baselineAverage']);
        $page->assertSee('2018 annual source.xlsx')->assertSee('80.00%')->assertSee('Year-over-Year Comparison');
    }

    public function test_master_keeps_relationship_controls_out_of_the_focused_year_form(): void
    {
        $this->actingAs($this->viewer)->get(route('performance.comparison'))
            ->assertOk()->assertSee('name="mapping_type"',false)->assertSee('name="main_objective"',false);
        $this->get(route('performance.comparison',['comparison_method'=>'year']))
            ->assertOk()->assertSee('name="reference_year"',false)
            ->assertDontSee('name="mapping_type"',false)->assertDontSee('name="main_objective"',false)
            ->assertDontSee('name="workbook"',false);
    }

    public function test_master_shared_controls_are_available_and_carried_into_reporting_views(): void
    {
        $page=$this->actingAs($this->viewer)->get(route('performance.comparison',['comparison_method'=>'historical','reporting_frequency'=>'monthly','result_scope'=>'reported','movement'=>'unmatched','comparison_sort'=>'name']))->assertOk();
        $page->assertSee('select id="pc-frequency"',false)->assertSee('select id="pc-reporting-scope"',false);
        $document=new \DOMDocument();
        @$document->loadHTML($page->getContent());
        $links=(new \DOMXPath($document))->query('//nav[contains(@class,"pc-modes")]/a');
        $url=null;
        foreach($links as $link) if(str_contains($link->getAttribute('href'),'comparison_method=period')) $url=$link->getAttribute('href');
        $this->assertNotNull($url);
        $result=$this->get($url)->assertOk()->viewData('comparison');
        $this->assertSame('monthly',$result['frequency']);
        $this->assertSame('reported',$result['scope']);
        $this->assertSame('unmatched',$result['movement']);
        $this->assertSame('name',$result['sort']);
    }

    public function test_year_view_rejects_the_same_fiscal_year_under_two_calendar_labels(): void
    {
        $this->actingAs($this->viewer)->get(route('performance.comparison',['comparison_method'=>'year','performance_year'=>2025,'reference_year'=>2018]))->assertSessionHasErrors('reference_year');
    }

    public function test_master_frequency_filter_uses_the_current_kpis_effective_reporting_frequency(): void
    {
        $monthly=$this->comparison(['comparison_method'=>'historical','reporting_frequency'=>'monthly']);
        $this->assertCount(1,$monthly['rows']);
        $weekly=$this->comparison(['comparison_method'=>'historical','reporting_frequency'=>'weekly']);
        $this->assertCount(0,$weekly['rows']);
    }

    public function test_mapping_editor_is_loaded_for_one_selected_kpi_instead_of_every_table_row(): void
    {
        $page = $this->actingAs($this->viewer)->get(route('performance.comparison'))->assertOk();
        $this->assertSame(0, substr_count($page->getContent(), 'name="current_objective_id"'));
        $editor = $this->get(route('performance.comparison', ['comparison_method'=>'historical', 'edit_kpi'=>$this->historical->id]))->assertOk();
        $this->assertSame(1, substr_count($editor->getContent(), 'name="current_objective_id"'));
    }

    public function test_another_years_editor_creates_a_new_relationship_without_overwriting_the_approved_link(): void
    {
        $other = $this->objective->replicate();
        $other->code = '1.2027';
        $other->target_year = 2027;
        $other->save();
        $page = $this->actingAs($this->viewer)->get(route('performance.comparison',['comparison_method'=>'historical','performance_year'=>2027,'edit_kpi'=>$this->historical->id]))->assertOk();
        $page->assertDontSee('name="mapping_id" value="'.$this->mapping->id.'"',false);
        $this->post(route('performance.historical.mappings.save',$this->historical->id),['current_objective_id'=>$other->id,'mapping_type'=>'renamed','comparable'=>1])->assertRedirect();
        $this->assertSame('approved',$this->mapping->fresh()->status);
        $this->assertSame($this->objective->id,$this->mapping->fresh()->current_objective_id);
        $this->assertSame(2,$this->historical->mappings()->count());
    }

    public function test_intentionally_retired_kpi_is_context_not_an_unmapped_activity(): void
    {
        $this->mapping->update(['current_objective_id'=>null,'mapping_type'=>'retired','comparable'=>false]);
        $result = $this->comparison(['comparison_method'=>'historical','mapping_type'=>'retired']);
        $this->assertCount(1,$result['rows']);
        $this->assertSame('retired',$result['rows']->first()['mappingType']);
        $this->assertSame('definition_review',$result['rows']->first()['readiness']);
        $this->assertNull($result['rows']->first()['delta']);
    }
}
