<?php

namespace Tests\Feature;

use App\Models\{Department,EdrFeedback,EdrPlanDocument,EdrReport,Plan,PlanItem,Role,StrategicObjective,User};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdrEditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_moves_expert_to_directorate_to_head_with_stage_editing(): void
    {
        [$department,$expert,$director,$head]=$this->users();
        $report=EdrReport::create(['department_id'=>$department->id,'reporting_period'=>'annual','year'=>2026,'status'=>'draft','submitted_by'=>$expert->id]);
        $this->actingAs($expert)->post(route('reports.submit',$report))->assertRedirect();
        $this->assertSame('submitted_to_plan_report_directorate',$report->fresh()->status);
        $this->actingAs($director)->put(route('reports.update',$report),['executive_summary'=>'Director edited'])->assertRedirect();
        $this->actingAs($director)->post(route('reports.forward-head',$report))->assertRedirect();
        $this->actingAs($head)->put(route('reports.update',$report),['executive_summary'=>'Head edited'])->assertRedirect();
        $this->actingAs($head)->post(route('reports.approve',$report))->assertRedirect();
        $this->assertDatabaseHas('edr_reports',['id'=>$report->id,'status'=>'approved','executive_summary'=>'Head edited','reviewed_by'=>$director->id,'approved_by'=>$head->id]);
    }

    public function test_plan_narration_moves_through_the_same_three_stages(): void
    {
        [, $expert,$director,$head]=$this->users();
        $payload=['year'=>2026,'introduction'=>'Expert draft'];
        $this->actingAs($expert)->put(route('objectives.plan-narrative.update'),$payload+['workflow_action'=>'submit_to_directorate'])->assertRedirect();
        $this->actingAs($director)->put(route('objectives.plan-narrative.update'),['year'=>2026,'introduction'=>'Director edit','workflow_action'=>'send_to_head'])->assertRedirect();
        $this->actingAs($head)->put(route('objectives.plan-narrative.update'),['year'=>2026,'introduction'=>'Head edit','workflow_action'=>'finalize'])->assertRedirect();
        $this->assertDatabaseHas('edr_plan_documents',['year'=>2026,'status'=>'approved','introduction'=>'Head edit','directorate_reviewed_by'=>$director->id,'head_approved_by'=>$head->id]);
    }

    public function test_reviewers_cannot_open_drafts_before_their_workflow_stage(): void
    {
        [$department,$expert,$director,$head]=$this->users();
        $report=EdrReport::create(['department_id'=>$department->id,'reporting_period'=>'annual','year'=>2026,'status'=>'draft','submitted_by'=>$expert->id,'executive_summary'=>'PRIVATE REPORT DRAFT']);
        $feedback=EdrFeedback::create(['department_id'=>$department->id,'reporting_period'=>'annual','year'=>2026,'status'=>'draft','prepared_by'=>$expert->id,'strengths'=>'PRIVATE FEEDBACK DRAFT']);

        $this->actingAs($director)->get(route('reports.show',$report))->assertForbidden();
        $this->actingAs($head)->get(route('reports.show',$report))->assertForbidden();
        $this->actingAs($director)->get(route('feedbacks.show',$feedback))->assertForbidden();
        $this->actingAs($head)->get(route('feedbacks.show',$feedback))->assertForbidden();
        $this->actingAs($director)->get(route('reports.index'))->assertDontSee('PRIVATE REPORT DRAFT');
        $this->actingAs($director)->get(route('feedbacks.index'))->assertDontSee('PRIVATE FEEDBACK DRAFT');
    }

    public function test_department_report_export_is_scoped_and_logistics_are_admin_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $own=Department::create(['name'=>'Own Department','code'=>'OWN','reporting_type'=>'monthly','is_active'=>true]);
        $other=Department::create(['name'=>'Other Department','code'=>'OTH','reporting_type'=>'monthly','is_active'=>true]);
        $user=User::factory()->create(['department_id'=>$own->id]);
        $user->roles()->sync([Role::where('name','dept_head')->value('id')]);
        $report=EdrReport::create(['department_id'=>$other->id,'reporting_period'=>'annual','year'=>2026,'status'=>'approved']);

        $this->actingAs($user)->get(route('reports.pdf',$report))->assertForbidden();
        $this->actingAs($user)->get(route('trains.index'))->assertForbidden();
        $this->actingAs($user)->get(route('imports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('cargo.index'))->assertForbidden();
    }

    public function test_performance_writes_are_department_and_plan_scoped(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $own=Department::create(['name'=>'Entry Department','code'=>'ENT','reporting_type'=>'monthly','is_active'=>true]);
        $other=Department::create(['name'=>'Protected Department','code'=>'PRO','reporting_type'=>'monthly','is_active'=>true]);
        $user=User::factory()->create(['department_id'=>$own->id]);
        $user->roles()->sync([Role::where('name','department_expert')->value('id')]);
        $ownPlan=Plan::create(['title'=>'Own','type'=>'annual','year'=>2026,'department_id'=>$own->id,'status'=>'approved','created_by'=>$user->id]);
        $otherPlan=Plan::create(['title'=>'Other','type'=>'annual','year'=>2026,'department_id'=>$other->id,'status'=>'approved','created_by'=>$user->id]);
        $objective=StrategicObjective::create(['code'=>'SEC.1','description'=>'Protected KPI','department_id'=>$other->id,'unit'=>'Number','weight'=>1,'status'=>'approved']);
        $foreignItem=PlanItem::create(['plan_id'=>$otherPlan->id,'strategic_objective_id'=>$objective->id,'july_target'=>10]);

        $this->actingAs($user)->put(route('performance.update',[$otherPlan,'july']),['performances'=>[$foreignItem->id=>9]])->assertForbidden();
        $this->actingAs($user)->put(route('performance.update',[$ownPlan,'july']),['performances'=>[$foreignItem->id=>9]])->assertForbidden();
    }

    private function users(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department=Department::create(['name'=>'Plan and Report','code'=>'PR','reporting_type'=>'monthly','is_active'=>true]);
        $make=function(string $role)use($department){$u=User::factory()->create(['department_id'=>$department->id]);$u->roles()->sync([Role::where('name',$role)->value('id')]);return $u;};
        return [$department,$make('plan_report_department_expert'),$make('plan_report_directorate'),$make('plan_report_head_office')];
    }
}
