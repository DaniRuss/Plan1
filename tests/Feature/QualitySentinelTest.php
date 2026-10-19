<?php
namespace Tests\Feature;

use App\Models\{Department,User,Role,Plan,PlanItem,PlanItemAssignment,StrategicObjective,WeeklyPerformance,PeriodicPerformance,ReportingSubmission,ReportingEntry};
use App\Services\{QualitySentinel,CalendarHelper};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualitySentinelTest extends TestCase
{
    use RefreshDatabase;
    private Department $department;
    private User $admin;
    private PlanItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,9,1));
        $this->department=Department::create(['name'=>'Cargo','code'=>'CG','reporting_type'=>'weekly','is_active'=>true]);
        $this->admin=User::factory()->create(['department_id'=>$this->department->id,'must_change_password'=>false,'is_active'=>true]);
        $this->admin->roles()->attach(Role::create(['name'=>'system_admin']));
        $plan=Plan::create(['title'=>'Cargo plan','department_id'=>$this->department->id,'year'=>2026,'status'=>'approved','type'=>'annual','created_by'=>$this->admin->id]);
        $objective=StrategicObjective::create(['code'=>'1.1','description'=>'Freight carried','department_id'=>$this->department->id,'status'=>'approved','unit'=>'Tons','weight'=>100]);
        $this->item=PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'july_target'=>100]);
        PlanItemAssignment::create(['plan_item_id'=>$this->item->id,'performer_id'=>$this->department->id,'status'=>'approved_for_experts']);
        $this->actingAs($this->admin);
    }

    private function scan(): array
    {
        return app(QualitySentinel::class)->scan(2026,'july');
    }

    private function weekly(int $week,float $actual,float $planned=25): WeeklyPerformance
    {
        return WeeklyPerformance::create(['plan_item_id'=>$this->item->id,'year'=>2026,'month'=>'july','week_number'=>$week,'actual_value'=>$actual,'planned_value'=>$planned,'percentage'=>100,'status'=>'submitted','workflow_status'=>'submitted_to_directorate']);
    }

    public function test_checks_every_due_week_and_zero_counts_as_reported(): void
    {
        $this->weekly(1,0);
        $scan=$this->scan();
        $weeks=count(CalendarHelper::getMonthWeeks('july',2026));
        $this->assertSame(5,$weeks);
        $this->assertSame($weeks,$scan['stats']['expected']);
        $this->assertSame(1,$scan['stats']['reported']);
        $this->assertSame(4,$scan['stats']['critical']);
        $this->assertSame(20.0,$scan['stats']['coverage']);
        $this->assertTrue(collect($scan['alerts'])->contains(fn($a)=>$a['period']==='Week 5' && $a['rule']==='missing'));
        $this->assertStringContainsString('department_id='.$this->department->id,$scan['alerts'][0]['url']);
        $this->assertDatabaseCount('weekly_performances',1);
    }

    public function test_future_periods_are_not_reported_as_missing(): void
    {
        $this->travelTo(now()->setDate(2026,6,1));
        $scan=$this->scan();
        $this->assertSame(0,$scan['stats']['expected']);
        $this->assertNull($scan['stats']['coverage']);
        $this->assertSame([],$scan['alerts']);
    }

    public function test_uncapped_outliers_and_zero_plans_are_flagged(): void
    {
        $this->weekly(1,75);
        $this->weekly(2,10,0);
        $scan=$this->scan();
        $this->assertSame(2,$scan['stats']['warning']);
        $this->assertSame(2,$scan['stats']['review']);
        $this->assertSame(1,collect($scan['alerts'])->where('rule','outlier')->count());
        $this->assertSame(1,collect($scan['alerts'])->where('rule','zero_plan')->count());
    }

    public function test_monthly_aliases_do_not_duplicate_coverage_and_canonical_value_wins(): void
    {
        $this->department->update(['reporting_type'=>'monthly']);
        PeriodicPerformance::create(['plan_item_id'=>$this->item->id,'period'=>'july','actual_value'=>999,'status'=>'pending']);
        PeriodicPerformance::create(['plan_item_id'=>$this->item->id,'period'=>'2026-july','actual_value'=>0,'status'=>'approved','workflow_status'=>'approved_by_head_office']);
        $scan=$this->scan();
        $this->assertSame(1,$scan['stats']['expected']);
        $this->assertSame(1,$scan['stats']['reported']);
        $this->assertSame(100.0,$scan['stats']['approval_rate']);
        $this->assertSame([],$scan['alerts']);
    }

    public function test_unplanned_reference_and_unassigned_items_do_not_inflate_scope(): void
    {
        $this->item->update(['july_target'=>null]);
        $this->assertSame(0,$this->scan()['stats']['expected']);
        $this->item->update(['july_target'=>100]);
        $this->item->strategicObjective->update(['is_reference_only'=>true]);
        $this->assertSame(0,$this->scan()['stats']['expected']);
        $this->item->strategicObjective->update(['is_reference_only'=>false]);
        $this->item->assignments()->delete();
        $this->assertSame(0,$this->scan()['stats']['expected']);
    }

    public function test_final_approved_not_applicable_is_covered_without_a_metric(): void
    {
        $this->department->update(['reporting_type'=>'monthly']);
        $submission=ReportingSubmission::create(['department_id'=>$this->department->id,'plan_id'=>$this->item->plan_id,'year'=>2026,'month'=>'july','week'=>0,'frequency'=>'monthly','status'=>'approved_by_head_office','version'=>1]);
        ReportingEntry::create(['submission_id'=>$submission->id,'plan_item_id'=>$this->item->id,'state'=>'not_applicable','reason'=>'No scheduled operation','version'=>1]);
        $scan=$this->scan();
        $this->assertSame(1,$scan['stats']['exempt']);
        $this->assertSame(100.0,$scan['stats']['coverage']);
        $this->assertSame([],$scan['alerts']);
        $submission->update(['status'=>'submitted_to_head_office']);
        $scan=$this->scan();
        $this->assertSame(1,$scan['stats']['critical']);
        $this->assertSame(route('shared-reporting.show',$submission),$scan['alerts'][0]['url']);
    }

    public function test_page_filters_and_csv_export_share_the_same_findings(): void
    {
        $this->weekly(1,75);
        $query=['year'=>2026,'month'=>'july','rule'=>'outlier'];
        $this->get(route('quality.console',$query))->assertOk()->assertSee('Department pulse')->assertSee('Achievement above 200%')
            ->assertViewHas('alerts',fn($rows)=>$rows->total()===1)->assertViewHas('stats',fn($stats)=>$stats['critical']===4);
        $response=$this->get(route('quality.console',$query+['download'=>'csv']))->assertOk()->assertDownload('quality-sentinel-2026-july.csv');
        $csv=$response->streamedContent();
        $this->assertStringContainsString('outlier',$csv);
        $this->assertStringNotContainsString('missing',$csv);
        $this->getJson(route('quality.console',['month'=>'invalid']))->assertUnprocessable();
    }

    public function test_daily_summaries_respect_decreasing_goals_and_shared_approval_state(): void
    {
        $this->department->update(['reporting_type'=>'daily']);
        $this->item->strategicObjective->update(['goal_direction'=>'decrease']);
        $row=$this->weekly(1,5,25);
        $row->update(['workflow_status'=>'approved_by_head_office']);
        $submission=ReportingSubmission::create(['department_id'=>$this->department->id,'plan_id'=>$this->item->plan_id,'year'=>2026,'month'=>'july','week'=>1,'frequency'=>'daily','status'=>'submitted_to_chief','version'=>1]);
        $scan=$this->scan();
        $this->assertSame(1,$scan['stats']['warning']);
        $this->assertSame(1,$scan['stats']['review']);
        $this->assertSame(0,$scan['stats']['approved']);
        $outlier=collect($scan['alerts'])->firstWhere('rule','outlier');
        $this->assertSame('daily',$outlier['frequency']);
        $this->assertStringContainsString('500.0%',$outlier['detail']);
        $this->assertSame(route('shared-reporting.show',$submission),$outlier['url']);
    }

    public function test_calculated_parents_are_excluded_but_manual_overrides_are_scanned(): void
    {
        StrategicObjective::create(['code'=>'1.1.1','description'=>'Child activity','parent_id'=>$this->item->strategic_objective_id,'department_id'=>$this->department->id,'status'=>'approved','unit'=>'Tons']);
        $this->assertSame(0,$this->scan()['stats']['expected']);
        $this->item->strategicObjective->update(['summary_type'=>'manual_override']);
        $this->assertSame(5,$this->scan()['stats']['expected']);
    }

    public function test_csv_escapes_formula_values_and_console_remains_permission_protected(): void
    {
        $this->item->strategicObjective->update(['description'=>'=SUM(1,2)']);
        $response=$this->get(route('quality.console',['year'=>2026,'month'=>'july','download'=>'csv']))->assertOk();
        $this->assertStringContainsString("'=SUM(1,2)",$response->streamedContent());
        $ordinary=User::factory()->create(['is_active'=>true,'must_change_password'=>false]);
        $this->actingAs($ordinary)->get(route('quality.console'))->assertForbidden();
    }
}
