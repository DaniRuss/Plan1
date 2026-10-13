<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EdrPlanDocument;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdrPlanDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_word_narration_can_be_uploaded_downloaded_and_replaced(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$expert] = $this->approvedPlan();
        $upload = fn ($name) => \Illuminate\Http\UploadedFile::fake()->create($name, 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->actingAs($expert)->put(route('objectives.plan-narrative.update'), [
            'year' => 2026, 'word_file' => $upload('Narration.docx'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $document = EdrPlanDocument::where('year', 2026)->firstOrFail();
        $oldPath = $document->word_file_path;
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($oldPath);
        $this->get(route('objectives.plan-narrative.attachment', ['year' => 2026]))
            ->assertOk()->assertDownload('Narration.docx');
        $this->put(route('objectives.plan-narrative.update'), ['year' => 2026, 'word_file' => $upload('Revised.docx')])
            ->assertSessionHasNoErrors()->assertRedirect();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($oldPath);
        $this->assertSame('Revised.docx', $document->fresh()->word_file_name);
        $this->put(route('objectives.plan-narrative.update'), [
            'year' => 2026, 'word_file' => \Illuminate\Http\UploadedFile::fake()->create('Bad.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('word_file');
        $this->actingAs(User::factory()->create())->get(route('objectives.plan-narrative.attachment', ['year' => 2026]))->assertForbidden();
    }

    public function test_plan_report_expert_can_prepare_one_narration_for_all_approved_plans(): void
    {
        [$expert, $plan] = $this->approvedPlan();
        $this->actingAs($expert)->get(route('objectives.plan-narrative.edit', ['year' => 2026]))
            ->assertOk()->assertSee('One narration covers all approved departmental plans');
        $this->actingAs($expert)->put(route('objectives.plan-narrative.update'), [
            'year' => 2026,
            'introduction' => 'Official introduction', 'past_performance' => 'Past performance',
            'strategic_issues' => 'Strategic issues', 'implementation_strategies' => 'Implementation strategy',
            'monitoring_evaluation' => 'Monitoring approach', 'challenges_solutions' => 'Challenges and solutions',
        ])->assertRedirect(route('objectives.plan-narrative.edit', ['year' => 2026]));
        $this->assertDatabaseHas('edr_plan_documents', ['year' => 2026, 'introduction' => 'Official introduction', 'prepared_by' => $expert->id]);
    }

    public function test_approved_plan_and_single_objective_are_downloadable(): void
    {
        [$expert, $plan, $objective] = $this->approvedPlan();
        EdrPlanDocument::create(['year' => 2026, 'introduction' => 'Introduction', 'prepared_by' => $expert->id]);
        $this->actingAs($expert)->get(route('plans.download', $plan))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($expert)->get(route('plans.objectives.download', [$plan, $objective]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($expert)->get(route('objectives.download-approved'))->assertOk()->assertHeader('content-type', 'application/pdf');

        $plan->load(['department','items.strategicObjective']);
        $this->view('edr_plans.organization_approved_plans_pdf', [
            'plans'=>collect([$plan]), 'narrative'=>null, 'year'=>2026,
            'departmentNames'=>Department::pluck('name','id'),
        ])->assertSee('APPROVED ORGANIZATION EDR PLANS')
          ->assertSee('Approved railway objective')
          ->assertDontSee('1. Introduction');
    }

    public function test_section_four_is_generated_from_approved_objectives_and_activities(): void
    {
        [$expert,$plan,$objective]=$this->approvedPlan();
        $plan->load(['department','items.strategicObjective']);
        $departmentNames=Department::pluck('name','id');

        $this->view('edr_plans.organization_plan_pdf',[
            'plans'=>collect([$plan]),'narrative'=>null,'year'=>2026,'departmentNames'=>$departmentNames,
        ])->assertSee('4. Objectives and Detailed Activities')->assertSee('Approved railway objective')->assertSee('Plan and Report');
    }

    public function test_preview_action_saves_current_narration_before_downloading(): void
    {
        [$expert] = $this->approvedPlan();

        $this->actingAs($expert)->put(route('objectives.plan-narrative.update'), [
            'year' => 2026,
            'introduction' => 'Text entered immediately before preview',
            'past_performance' => 'Saved past performance',
            'strategic_issues' => 'Saved strategic issues',
            'implementation_strategies' => 'Saved implementation',
            'monitoring_evaluation' => 'Saved monitoring',
            'challenges_solutions' => 'Saved challenges',
            'workflow_action' => 'save_and_preview',
        ])->assertRedirect(route('objectives.download-approved', ['year' => 2026, 'preview' => 1]));

        $this->assertDatabaseHas('edr_plan_documents', [
            'year' => 2026,
            'introduction' => 'Text entered immediately before preview',
        ]);

        $this->actingAs($expert)->get(route('objectives.download-approved', ['year'=>2026,'preview'=>1]))
            ->assertOk()
            ->assertHeader('content-type','application/pdf')
            ->assertHeader('content-disposition','inline; filename=Organization_EDR_Plan_2026_Narration_Preview.pdf');

        $plan = Plan::with(['department','items.strategicObjective'])->where('year', 2026)->firstOrFail();
        $this->view('edr_plans.organization_plan_pdf', [
            'plans'=>collect([$plan]), 'narrative'=>EdrPlanDocument::where('year',2026)->first(),
            'year'=>2026, 'departmentNames'=>Department::pluck('name','id'),
        ])->assertSee('Approved Action Plan Schedule (Plan Only)')
          ->assertSee('Annual Plan')
          ->assertDontSee('<th>Actual</th>', false);
    }

    private function approvedPlan(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Plan and Report', 'code' => 'PRD', 'reporting_type' => 'monthly', 'is_active' => true]);
        $expert = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $expert->roles()->sync([Role::where('name', 'plan_report_department_expert')->value('id')]);
        $objective = StrategicObjective::create(['code' => '1', 'description' => 'Approved railway objective', 'weight' => 10, 'unit' => 'Number', 'department_id' => $department->id, 'lead_department_id' => $department->id, 'performers' => [$department->id], 'annual_target' => 12, 'status' => 'approved', 'is_active' => true]);
        $plan = Plan::create(['title' => 'Annual EDR Plan', 'type' => 'annual', 'year' => 2026, 'department_id' => $department->id, 'status' => 'approved', 'created_by' => $expert->id]);
        PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, 'annual_target' => 12, 'july_target' => 1, 'weight' => 10, 'unit' => 'number', 'performers' => [$department->id]]);
        return [$expert, $plan, $objective];
    }
}
