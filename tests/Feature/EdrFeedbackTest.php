<?php
namespace Tests\Feature;
use App\Models\{Department,EdrFeedback,EdrReport,PeriodicPerformance,Plan,PlanItem,Role,StrategicObjective,User,WeeklyPerformance};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdrFeedbackTest extends TestCase {
 use RefreshDatabase;
 public function test_feedback_uses_document_weights_and_approved_performance():void{
  $this->seed(RolesAndPermissionsSeeder::class); $dept=Department::create(['name'=>'Operations','code'=>'OPS','reporting_type'=>'monthly','is_active'=>true]);
  $expert=User::factory()->create(['department_id'=>$dept->id]); $expert->roles()->sync([Role::where('name','plan_report_department_expert')->value('id')]);
  $objective=StrategicObjective::create(['code'=>'1','description'=>'KPI','weight'=>100,'unit'=>'Percentage (%)','department_id'=>$dept->id,'performers'=>[$dept->id],'status'=>'approved','is_active'=>true]);
  $plan=Plan::create(['title'=>'Plan','type'=>'annual','year'=>2026,'department_id'=>$dept->id,'status'=>'approved','created_by'=>$expert->id]);
  $item=PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'annual_target'=>100,'july_target'=>100,'weight'=>100,'unit'=>'percent','performers'=>[$dept->id]]);
  PeriodicPerformance::create(['plan_item_id'=>$item->id,'period'=>'2026-july','actual_value'=>50,'workflow_status'=>'approved_by_head_office']);
  $this->actingAs($expert)->get(route('feedbacks.create',['department_id'=>$dept->id,'year'=>2026,'period'=>'month:july']))->assertOk()->assertSee('Selected Monthly Performance')->assertSee('View Monthly Performance Report')->assertDontSee('No approved report');
  $director=User::factory()->create(['department_id'=>$dept->id]);$director->roles()->sync([Role::where('name','plan_report_directorate')->value('id')]);
  $head=User::factory()->create(['department_id'=>$dept->id]);$head->roles()->sync([Role::where('name','plan_report_head_office')->value('id')]);
  $this->actingAs($expert)->post(route('feedbacks.store'),['department_id'=>$dept->id,'year'=>2026,'reporting_period'=>'month:july','strengths'=>'Strong evidence','weaknesses'=>'Late follow-up','next_focus_areas'=>'Improve controls','plan_quality_score'=>5,'report_quality_score'=>8,'submission_score'=>7])->assertRedirect();
  $feedback=EdrFeedback::firstOrFail();
  $this->actingAs($expert)->post(route('feedbacks.submit',$feedback))->assertRedirect();
  $this->actingAs($director)->post(route('feedbacks.forward-head',$feedback))->assertRedirect();
  $this->actingAs($head)->post(route('feedbacks.finalize',$feedback))->assertRedirect();
  $feedback->refresh();$this->assertSame('finalized',$feedback->status);$this->assertSame(50.0,(float)$feedback->performance_percent); $this->assertSame(40.0,(float)$feedback->performance_score); $this->assertSame(60.0,$feedback->total_score);
 }
 public function test_department_user_sees_only_finalized_feedback_for_own_department():void{
  $this->seed(RolesAndPermissionsSeeder::class); $own=Department::create(['name'=>'Own','code'=>'OWN','is_active'=>true]); $other=Department::create(['name'=>'Other','code'=>'OTH','is_active'=>true]);
  $user=User::factory()->create(['department_id'=>$own->id]); $user->roles()->sync([Role::where('name','department_chief')->value('id')]);
  EdrFeedback::create(['department_id'=>$own->id,'year'=>2026,'reporting_period'=>'annual','strengths'=>'Visible own feedback','status'=>'finalized']); EdrFeedback::create(['department_id'=>$other->id,'year'=>2026,'reporting_period'=>'annual','strengths'=>'Hidden other feedback','status'=>'finalized']);
  $this->actingAs($user)->get(route('feedbacks.index',['year'=>2026,'period'=>'annual']))->assertOk()->assertSee('Own')->assertDontSee('Other');
 }
 public function test_feedback_index_displays_existing_feedback_before_filters_are_selected():void{
  $this->seed(RolesAndPermissionsSeeder::class);$dept=Department::create(['name'=>'Visible Feedback Department','code'=>'VFD','is_active'=>true]);
  $expert=User::factory()->create(['department_id'=>$dept->id]);$expert->roles()->sync([Role::where('name','plan_report_department_expert')->value('id')]);
  EdrFeedback::create(['department_id'=>$dept->id,'year'=>2025,'reporting_period'=>'q2','strengths'=>'Automatically visible feedback','status'=>'draft']);
  $this->actingAs($expert)->get(route('feedbacks.index'))->assertOk()->assertSee('Visible Feedback Department')->assertSee('All Plan Years')->assertSee('All Reporting Periods');
 }
 public function test_feedback_can_use_an_approved_weekly_performance_report():void{
  $this->seed(RolesAndPermissionsSeeder::class);$dept=Department::create(['name'=>'Weekly Operations','code'=>'WOP','reporting_type'=>'weekly','is_active'=>true]);
  $expert=User::factory()->create(['department_id'=>$dept->id]);$expert->roles()->sync([Role::where('name','plan_report_department_expert')->value('id')]);
  $objective=StrategicObjective::create(['code'=>'W1','description'=>'Weekly KPI','weight'=>100,'unit'=>'Number','department_id'=>$dept->id,'performers'=>[$dept->id],'status'=>'approved','is_active'=>true]);
  $plan=Plan::create(['title'=>'Weekly Plan','type'=>'annual','year'=>2026,'department_id'=>$dept->id,'status'=>'approved','created_by'=>$expert->id]);
  $item=PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'annual_target'=>100,'july_target'=>40,'weight'=>100,'unit'=>'number','performers'=>[$dept->id]]);
  WeeklyPerformance::create(['plan_item_id'=>$item->id,'year'=>2026,'month'=>'july','week_number'=>2,'planned_value'=>10,'actual_value'=>12,'percentage'=>120,'workflow_status'=>'approved_by_head_office','status'=>'submitted']);
  // Approved weekly workbench data is sufficient; no separate EDR narrative report is required.
  $this->actingAs($expert)->get(route('feedbacks.create',['department_id'=>$dept->id,'year'=>2026,'period'=>'week:july:2']))->assertOk()->assertSee('July — Week 2')->assertSee('Approved achievement: 120%');
  $this->actingAs($expert)->post(route('feedbacks.store'),['department_id'=>$dept->id,'year'=>2026,'reporting_period'=>'week:july:2','plan_quality_score'=>5,'report_quality_score'=>8,'submission_score'=>7])->assertRedirect();
  $this->assertDatabaseHas('edr_feedbacks',['department_id'=>$dept->id,'reporting_period'=>'week:july:2','performance_percent'=>120,'performance_score'=>80]);
 }
}
