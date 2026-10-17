<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PlanItemAssignment;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectiveAssignmentApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_objective_moves_from_plan_report_expert_through_directorate_to_head(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $planning = Department::create(['name' => 'Planning', 'code' => 'PLN', 'reporting_type' => 'monthly']);
        $performer = Department::create(['name' => 'Operation', 'code' => 'OPS', 'reporting_type' => 'monthly']);
        $expert = $this->user($planning, 'plan_report_department_expert');
        $directorate = $this->user($planning, 'plan_report_directorate');
        $head = $this->user($planning, 'plan_report_head_office');

        $objective = StrategicObjective::create([
            'code' => '7', 'description' => 'Improve operations', 'weight' => 10,
            'unit' => 'Percentage (%)', 'department_id' => $planning->id,
            'performers' => [$performer->id], 'lead_department_id' => $performer->id, 'goal_direction' => 'increase',
            'target_year' => 2019, 'annual_target' => 100, 'status' => 'draft',
            'is_active' => true,
        ]);

        $this->actingAs($expert)->post(route('objectives.submit', $objective))->assertRedirect();
        $this->assertSame('submitted_to_plan_report_directorate', $objective->fresh()->status);

        $this->actingAs($directorate)->post(route('objectives.directorate-forward', $objective))->assertRedirect();
        $this->assertSame('submitted', $objective->fresh()->status);

        $this->actingAs($head)->post(route('objectives.approve', $objective))->assertRedirect();
        $this->assertSame('approved', $objective->fresh()->status);
    }

    public function test_expert_recall_requires_approval_from_the_current_objective_reviewer(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $planning = Department::create(['name' => 'Planning', 'code' => 'PLN']);
        $expert = $this->user($planning, 'plan_report_department_expert');
        $directorate = $this->user($planning, 'plan_report_directorate');
        $head = $this->user($planning, 'plan_report_head_office');

        $root = StrategicObjective::create([
            'code' => '8', 'description' => 'Root plan', 'weight' => 10, 'unit' => 'Number',
            'department_id' => $planning->id, 'performers' => [$planning->id],
            'goal_direction' => 'increase', 'target_year' => 2019,
            'status' => 'submitted_to_plan_report_directorate', 'is_active' => true,
        ]);
        $child = StrategicObjective::create([
            'code' => '8.1', 'description' => 'Editable sub-objective', 'weight' => 5, 'unit' => 'Number',
            'department_id' => $planning->id, 'parent_id' => $root->id,
            'performers' => [$planning->id], 'goal_direction' => 'increase', 'target_year' => 2019,
            'status' => 'submitted_to_plan_report_directorate', 'is_active' => true,
        ]);

        $this->actingAs($expert)->get(route('objectives.edit', $child))->assertForbidden();
        $this->actingAs($expert)->post(route('objectives.recall-request', $child), [
            'recall_reason' => 'Correct the monthly targets',
        ])->assertRedirect();
        $this->assertSame('recall_requested_from_plan_report_directorate', $root->fresh()->status);
        $this->assertSame('recall_requested_from_plan_report_directorate', $child->fresh()->status);
        $this->actingAs($head)->post(route('objectives.recall-approve', $root))->assertForbidden();
        $this->actingAs($directorate)->post(route('objectives.recall-approve', $root))->assertRedirect();
        $this->assertSame('returned', $root->fresh()->status);
        $this->assertSame('returned', $child->fresh()->status);
        $this->actingAs($expert)->get(route('objectives.edit', $child))->assertOk();

        $root->update(['status' => 'submitted']);
        $child->update(['status' => 'submitted']);
        $this->actingAs($expert)->post(route('objectives.recall-request', $root), [
            'recall_reason' => 'Correct performer assignment before final approval',
        ])->assertRedirect();
        $this->assertSame('recall_requested_from_head_office', $root->fresh()->status);
        $this->actingAs($directorate)->post(route('objectives.recall-approve', $root))->assertForbidden();
        $this->actingAs($head)->post(route('objectives.recall-approve', $root))->assertRedirect();
        $this->assertSame('returned', $root->fresh()->status);
        $this->assertSame('returned', $child->fresh()->status);
    }

    public function test_chief_directorate_expert_workflow_and_supplementary_objective(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $planning = Department::create(['name' => 'Planning', 'code' => 'PLN', 'reporting_type' => 'monthly']);
        $performer = Department::create(['name' => 'Security', 'code' => 'SEC', 'reporting_type' => 'monthly']);

        $headOffice = $this->user($planning, 'plan_report_head_office');
        $planReportExpert = $this->user($planning, 'plan_report_department_expert');
        $chief = $this->user($performer, 'department_chief');
        $directorate = $this->user($performer, 'department_directorate');
        $otherDirectorate = $this->user($performer, 'department_directorate');
        $expertOne = $this->user($performer, 'department_expert');
        $expertTwo = $this->user($performer, 'department_expert');

        $base = [
            'weight' => 1, 'unit' => 'Percentage (%)', 'department_id' => $planning->id,
            'performers' => [$performer->id], 'goal_direction' => 'increase',
            'target_year' => 2019, 'annual_target' => 100, 'july_target' => 10,
            'status' => 'submitted', 'is_active' => true,
        ];
        $root = StrategicObjective::create(array_merge($base, ['code' => '14', 'description' => 'Infrastructure availability']));
        $child = StrategicObjective::create(array_merge($base, [
            'code' => '14.1', 'description' => 'Track quality', 'parent_id' => $root->id,
        ]));

        $this->actingAs($headOffice)->post(route('objectives.approve', $root))->assertRedirect();
        $rootAssignment = $this->assignment($root, $performer);
        $childAssignment = $this->assignment($child, $performer);
        $this->assertSame('pending_chief_approval', $childAssignment->status);

        $this->actingAs($expertOne)->get(route('assignments.index'))->assertDontSee('Track quality');
        $this->actingAs($expertOne)->get(route('objectives.index'))->assertDontSee('Track quality');

        $this->actingAs($chief)->post(route('assignments.chief-approve', $childAssignment), [
            'directorate_user_id' => $directorate->id,
        ])->assertRedirect();
        $this->assertSame('pending_directorate_approval', $childAssignment->fresh()->status);

        $this->actingAs($otherDirectorate)
            ->post(route('assignments.directorate-approve', $childAssignment))->assertForbidden();
        $this->actingAs($directorate)
            ->post(route('assignments.directorate-approve', $childAssignment))->assertRedirect();

        foreach ([$expertOne, $expertTwo] as $expert) {
            $this->actingAs($expert)->get(route('assignments.index'))->assertSee('Track quality');
            $this->actingAs($expert)->get(route('objectives.index'))
                ->assertSee('Infrastructure availability')->assertSee('Track quality');
        }

        $this->actingAs($chief)->post(route('assignments.supplementary.store', $rootAssignment), [
            'description' => 'Additional security inspection', 'unit' => 'Number',
            'goal_direction' => 'increase', 'annual_target' => 12,
            'target_distribution' => 'sum', 'july_target' => 1,
        ])->assertRedirect(route('assignments.index'));

        $supplementary = StrategicObjective::where('description', 'Additional security inspection')->firstOrFail();
        $this->assertTrue($supplementary->is_supplementary);
        $this->assertFalse($supplementary->include_in_parent_rollup);
        $this->assertTrue($supplementary->exclude_from_overall_score);
        $this->assertSame(0.0, (float) $supplementary->weight);
        $this->assertFalse($root->rollupChildren()->get()->contains($supplementary));

        $supplementaryAssignment = $this->assignment($supplementary, $performer);
        $this->actingAs($chief)->post(route('assignments.chief-approve', $supplementaryAssignment), [
            'directorate_user_id' => $directorate->id,
        ])->assertRedirect();
        $this->actingAs($directorate)->post(route('assignments.directorate-approve', $supplementaryAssignment))->assertRedirect();

        $this->actingAs($expertOne)->get(route('assignments.index'))
            ->assertSee('Additional security inspection')->assertSee('Department Added');
        $this->actingAs($headOffice)->get(route('assignments.index'))->assertSee('Additional security inspection');
        $this->actingAs($planReportExpert)->get(route('assignments.index'))->assertSee('Additional security inspection');
    }

    public function test_return_paths_move_back_one_step(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $planning = Department::create(['name' => 'Planning', 'code' => 'PLN']);
        $performer = Department::create(['name' => 'Commercial', 'code' => 'COM']);
        $headOffice = $this->user($planning, 'plan_report_head_office');
        $chief = $this->user($performer, 'department_chief');
        $directorate = $this->user($performer, 'department_directorate');
        $objective = StrategicObjective::create([
            'code' => '1', 'description' => 'Cargo objective', 'weight' => 1, 'unit' => 'Number',
            'department_id' => $planning->id, 'performers' => [$performer->id], 'goal_direction' => 'increase',
            'target_year' => 2019, 'annual_target' => 10, 'status' => 'submitted',
        ]);
        $this->actingAs($headOffice)->post(route('objectives.approve', $objective));
        $assignment = $this->assignment($objective, $performer);

        $this->actingAs($chief)->post(route('assignments.chief-return', $assignment), ['reason' => 'Target needs correction']);
        $this->assertSame('returned_to_head_office', $assignment->fresh()->status);
        $this->actingAs($headOffice)->post(route('assignments.head-office-resend', $assignment))->assertRedirect();
        $this->assertSame('pending_chief_approval', $assignment->fresh()->status);

        $this->actingAs($chief)->post(route('assignments.chief-approve', $assignment), ['directorate_user_id' => $directorate->id]);
        $this->actingAs($directorate)->post(route('assignments.directorate-return', $assignment), ['reason' => 'Reassign reviewer']);
        $this->assertSame('returned_to_chief', $assignment->fresh()->status);
    }

    public function test_chief_assigns_each_subobjective_independently_to_different_directorates(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $otherDepartment = Department::create(['name' => 'Other', 'code' => 'OTH']);
        $chief = $this->user($department, 'department_chief');
        $directorate = $this->user($department, 'department_directorate');
        $base = [
            'weight' => 1, 'unit' => 'Number', 'department_id' => $department->id,
            'performers' => [$department->id, $otherDepartment->id], 'goal_direction' => 'increase',
            'target_year' => 2019, 'annual_target' => 10, 'status' => 'approved', 'is_active' => true,
        ];
        $root = StrategicObjective::create(array_merge($base, ['code' => '2', 'description' => 'Main objective']));
        $tenth = StrategicObjective::create(array_merge($base, ['code' => '2.10', 'description' => 'Tenth child', 'parent_id' => $root->id]));
        $second = StrategicObjective::create(array_merge($base, ['code' => '2.2', 'description' => 'Second child', 'parent_id' => $root->id]));
        $nested = StrategicObjective::create(array_merge($base, ['code' => '2.2.1', 'description' => 'Nested child', 'parent_id' => $second->id]));
        app(\App\Services\ObjectivePublisher::class)->publishTree($root, $chief);
        $rootAssignment = $this->assignment($root, $department);
        $secondAssignment = $this->assignment($second, $department);
        $secondAssignment->update(['status' => 'returned_to_chief']);
        $previousPlan = $rootAssignment->planItem->plan->replicate();
        $previousPlan->year = 2018;
        $previousPlan->save();
        $previousItem = $secondAssignment->planItem->replicate();
        $previousItem->plan_id = $previousPlan->id;
        $previousItem->save();
        $previousAssignment = PlanItemAssignment::create([
            'plan_item_id' => $previousItem->id, 'performer_id' => $department->id,
            'status' => 'pending_chief_approval',
        ]);

        $this->actingAs($chief)->get(route('assignments.index'))->assertOk()
            ->assertSeeInOrder(['Main objective', 'Second child', 'Nested child', 'Tenth child'])
            ->assertSee(route('assignments.chief-approve', $secondAssignment), false);
        $this->actingAs($chief)->post(route('assignments.chief-approve', $rootAssignment), [
            'directorate_user_id' => $directorate->id, 'note' => 'Approved together',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('pending_directorate_approval',$rootAssignment->fresh()->status);
        $this->assertSame('returned_to_chief',$secondAssignment->fresh()->status);
        foreach ([$nested,$tenth] as $objective) $this->assertSame('pending_chief_approval',$this->assignment($objective,$department)->status);
        $otherDirectorate=$this->user($department,'department_directorate');
        $this->post(route('assignments.chief-approve',$secondAssignment),['directorate_user_id'=>$directorate->id])->assertRedirect()->assertSessionHasNoErrors();
        $tenthAssignment=$this->assignment($tenth,$department);
        $this->post(route('assignments.chief-approve',$tenthAssignment),['directorate_user_id'=>$otherDirectorate->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($directorate->id,$secondAssignment->fresh()->assigned_directorate_user_id);
        $this->assertEquals($otherDirectorate->id,$tenthAssignment->fresh()->assigned_directorate_user_id);
        $this->assertSame('pending_chief_approval',$this->assignment($nested,$department)->status);
        foreach ([$root,$second,$nested,$tenth] as $objective) $this->assertSame('pending_chief_approval',$this->assignment($objective,$otherDepartment)->status);
        $this->actingAs($directorate)->post(route('assignments.directorate-approve',$tenthAssignment))->assertForbidden();
        $this->actingAs($otherDirectorate)->get(route('assignments.index'))->assertOk()->assertSee('Tenth child')->assertDontSee('Second child');
        $this->assertSame('pending_chief_approval', $previousAssignment->fresh()->status);
    }

    private function user(Department $department, string $role): User
    {
        $user = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $user->roles()->sync([Role::where('name', $role)->value('id')]);
        return $user;
    }

    private function assignment(StrategicObjective $objective, Department $department): PlanItemAssignment
    {
        return PlanItemAssignment::where('performer_id', $department->id)
            ->whereHas('planItem', fn ($q) => $q->where('strategic_objective_id', $objective->id))
            ->firstOrFail();
    }
}
