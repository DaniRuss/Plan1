<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\PlanItemAssignment;
use App\Models\PeriodicPerformance;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Models\WeeklyPerformance;
use App\Notifications\WorkflowNotification;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_workflow_roles_and_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'department_expert']);
        $this->assertDatabaseHas('roles', ['name' => 'department_directorate']);
        $this->assertDatabaseHas('roles', ['name' => 'department_chief']);
        $this->assertDatabaseHas('roles', ['name' => 'head_office']);
        $this->assertDatabaseHas('roles', ['name' => 'plan_report_head_office']);
        $this->assertDatabaseHas('roles', ['name' => 'plan_report_department_expert']);
        $this->assertDatabaseHas('roles', ['name' => 'plan_report_directorate']);

        $expert = User::where('email', 'planning.expert@iprpms.gov.et')->first();
        $placeholder = User::where('email', 'placeholder.chief@iprpms.gov.et')->first();

        $this->assertNotNull($expert);
        $this->assertTrue($expert->isDepartmentExpert());
        $this->assertTrue((bool) $expert->is_active);

        $this->assertNotNull($placeholder);
        $this->assertTrue($placeholder->isDepartmentChief());
        $this->assertFalse((bool) $placeholder->is_active);

        $headOffice = User::where('email', 'headoffice@iprpms.gov.et')->first();
        $planReportHeadOffice = User::where('email', 'planreport.headoffice@iprpms.gov.et')->first();
        $planReportDepartment = User::where('email', 'planreport.department@iprpms.gov.et')->first();

        $this->assertTrue($headOffice?->isHeadOffice());
        $this->assertTrue($planReportHeadOffice?->isPlanReportHeadOffice());
        $this->assertTrue($planReportDepartment?->isPlanReportDepartmentExpert());
    }

    public function test_admin_can_create_department_scoped_workflow_user(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $department = Department::create([
            'name' => 'Planning',
            'code' => 'PLN',
            'objective_weight' => 15,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::where('name', 'system_admin')->value('id')]);

        $roleId = Role::where('name', 'department_expert')->value('id');

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Test Expert',
            'email' => 'test.expert@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'department_id' => $department->id,
            'roles' => [$roleId],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'test.expert@example.com',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $user = User::where('email', 'test.expert@example.com')->firstOrFail();
        $this->assertTrue($user->isDepartmentExpert());
    }

    public function test_department_expert_only_sees_objectives_where_their_department_is_a_performer(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $planning = Department::create([
            'name' => 'Planning',
            'code' => 'PLN',
            'objective_weight' => 15,
            'is_active' => true,
        ]);

        $finance = Department::create([
            'name' => 'Finance',
            'code' => 'FIN',
            'objective_weight' => 10,
            'is_active' => true,
        ]);

        $planningObjective = StrategicObjective::create([
            'code' => '1',
            'kpi_code' => 'KPI-PLN-01',
            'description' => 'Planning objective visible to expert',
            'weight' => 10,
            'unit' => 'Count',
            'department_id' => $planning->id,
            'performers' => [$planning->id],
            'goal_direction' => 'increase',
            'status' => 'approved',
            'is_active' => true,
        ]);

        StrategicObjective::create([
            'code' => '2',
            'description' => 'Finance-only objective hidden from expert',
            'weight' => 8,
            'unit' => 'Count',
            'department_id' => $finance->id,
            'performers' => [$finance->id],
            'goal_direction' => 'increase',
            'is_active' => true,
        ]);

        $expert = User::factory()->create([
            'department_id' => $planning->id,
            'is_active' => true,
        ]);
        $expert->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

        $plan = Plan::create([
            'title' => 'Planning approved objectives', 'type' => 'annual', 'year' => 2019,
            'department_id' => $planning->id, 'status' => 'approved', 'created_by' => $expert->id,
        ]);
        $item = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $planningObjective->id]);
        PlanItemAssignment::create([
            'plan_item_id' => $item->id, 'performer_id' => $planning->id,
            'status' => 'approved_for_experts',
        ]);

        $response = $this->actingAs($expert)->get(route('objectives.index'));

        $response->assertOk();
        $response->assertSee('Planning objective visible to expert');
        $response->assertSee('Your KPI: KPI-PLN-01');
        $response->assertSee('objective-kpi-owned');
        $response->assertDontSee('Finance-only objective hidden from expert');

        $this->actingAs($expert)->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('Your KPI: KPI-PLN-01')
            ->assertSee('assigned-kpi');
    }

    public function test_legacy_system_admin_still_has_access_to_user_management(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $department = Department::create([
            'name' => 'Digital Office',
            'code' => 'DO',
            'objective_weight' => 15,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::where('name', 'system_admin')->value('id')]);

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('System Users');
    }

    public function test_workflow_users_can_move_weekly_performance_from_expert_to_head_office_approval(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $department = Department::create([
            'name' => 'Planning',
            'code' => 'PLN',
            'objective_weight' => 15,
            'is_active' => true,
        ]);

        $planReportOffice = Department::create([
            'name' => 'Plan and Report Office',
            'code' => 'PRO',
            'objective_weight' => 0,
            'is_active' => true,
        ]);

        $objective = StrategicObjective::create([
            'code' => '1',
            'description' => 'Planning workflow objective',
            'status' => 'approved',
            'weight' => 10,
            'unit' => 'Count',
            'department_id' => $department->id,
            'performers' => [$department->id],
            'goal_direction' => 'increase',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $admin->roles()->sync([Role::where('name', 'system_admin')->value('id')]);

        $plan = Plan::create([
            'title' => 'Planning Annual Plan',
            'type' => 'annual',
            'year' => 2026,
            'department_id' => $department->id,
            'status' => 'approved',
            'created_by' => $admin->id,
        ]);

        $planItem = PlanItem::create([
            'plan_id' => $plan->id,
            'strategic_objective_id' => $objective->id,
            'annual_target' => 100,
            'july_target' => 80,
            'weight' => 10,
            'unit' => 'number',
            'performers' => [$department->id],
        ]);
        $this->approveItemForExperts($planItem, $department);

        $expert = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $expert->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

        $directorate = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $directorate->roles()->sync([Role::where('name', 'department_directorate')->value('id')]);

        $chief = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $chief->roles()->sync([Role::where('name', 'department_chief')->value('id')]);

        $planReportDepartment = User::factory()->create(['department_id' => $planReportOffice->id, 'is_active' => true]);
        $planReportDepartment->roles()->sync([Role::where('name', 'plan_report_department_expert')->value('id')]);

        $planReportDirectorate = User::factory()->create(['department_id' => $planReportOffice->id, 'is_active' => true]);
        $planReportDirectorate->roles()->sync([Role::where('name', 'plan_report_directorate')->value('id')]);

        $planReportHeadOffice = User::factory()->create(['department_id' => $planReportOffice->id, 'is_active' => true]);
        $planReportHeadOffice->roles()->sync([Role::where('name', 'plan_report_head_office')->value('id')]);

        WeeklyPerformance::create([
            'plan_item_id' => $planItem->id,
            'year' => 2026,
            'month' => 'july',
            'week_number' => 1,
            'planned_value' => 20,
            'actual_value' => 18,
            'percentage' => 90,
            'status' => 'draft',
            'workflow_status' => 'draft',
            'created_by' => $expert->id,
        ]);

        $this->actingAs($expert)->post(route('weekly.workflow', ['year' => 2026, 'month' => 'july', 'week' => 1]), [
            'department_id' => $department->id,
            'action' => 'submit_to_directorate',
        ])->assertRedirect(route('weekly.dashboard', [
            'year' => 2026,
            'department_id' => $department->id,
        ]))->assertSessionHas('success', 'Performance submitted to directorate for review.')
          ->assertSessionMissing('error');

        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_directorate',
        ]);

        $this->actingAs($directorate)->post(route('weekly.workflow', ['year' => 2026, 'month' => 'july', 'week' => 1]), [
            'department_id' => $department->id,
            'action' => 'submit_to_chief',
        ])->assertRedirect();

        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_chief',
        ]);

        $this->actingAs($chief)->post(route('weekly.workflow', ['year' => 2026, 'month' => 'july', 'week' => 1]), [
            'department_id' => $department->id,
            'action' => 'approve_by_chief',
        ])->assertRedirect();

        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_plan_report_department',
        ]);

        $this->actingAs($planReportDepartment)->get(route('weekly.report', [
            'year' => 2026,
            'month' => 'july',
            'week' => 1,
            'department_id' => $department->id,
        ]))->assertOk()
          ->assertSee('Sent to Plan &amp; Report Department', false)
          ->assertSee('Reviewed — No Correction Required')
          ->assertSee('Request Correction Through Directorate');

        $this->actingAs($planReportDepartment)->post(route('weekly.workflow', ['year' => 2026, 'month' => 'july', 'week' => 1]), [
            'department_id' => $department->id,
            'action' => 'submit_to_plan_report_directorate',
            'workflow_note' => 'Incorrect supporting value',
        ])->assertRedirect();

        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_plan_report_directorate',
        ]);

        $this->actingAs($planReportDirectorate)->post(route('weekly.workflow', ['year' => 2026, 'month' => 'july', 'week' => 1]), [
            'department_id' => $department->id,
            'action' => 'submit_to_head_office',
            'workflow_note' => 'Directorate confirms correction request',
        ])->assertRedirect();

        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_head_office',
        ]);

        $this->actingAs($planReportHeadOffice)->get(route('weekly.entry', [
            'year' => 2026,
            'month' => 'july',
            'week' => 1,
            'department_id' => $department->id,
        ]))->assertOk()
          ->assertSee('Sent to Head Office')
          ->assertSee('View Report');

        $this->actingAs($planReportHeadOffice)->get(route('weekly.report', [
            'year' => 2026,
            'month' => 'july',
            'week' => 1,
            'department_id' => $department->id,
        ]))->assertOk()
          ->assertSee('Return to Department Chief for Correction');

        $this->actingAs($planReportHeadOffice)->post(route('weekly.workflow', ['year' => 2026, 'month' => 'july', 'week' => 1]), [
            'department_id' => $department->id,
            'action' => 'return_to_department_chief',
            'workflow_note' => 'Correct the reported value and resubmit',
        ])->assertRedirect();

        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'returned_to_department_chief',
        ]);

        $this->actingAs($chief)->get(route('weekly.entry', ['year'=>2026,'month'=>'july','week'=>1,'department_id'=>$department->id]))
            ->assertOk()->assertSee('Save Corrected Performance')->assertDontSee('This weekly period is read-only');
        $this->actingAs($chief)->post(route('weekly.store'), [
            'year'=>2026,'month'=>'july','week'=>1,'department_id'=>$department->id,
            'entries'=>[$objective->id=>['plan_value'=>20,'actual'=>19,'notes'=>'Corrected by chief']],
        ])->assertRedirect();
        $this->assertDatabaseHas('weekly_performances',['plan_item_id'=>$planItem->id,'actual_value'=>19,'workflow_status'=>'returned_to_department_chief']);
        $this->actingAs($chief)->post(route('weekly.workflow',['year'=>2026,'month'=>'july','week'=>1]),[
            'department_id'=>$department->id,'action'=>'approve_by_chief','workflow_note'=>'Correction completed',
        ])->assertRedirect()->assertSessionHas('success','Corrected performance sent back to the Plan and Report Expert.');
        $this->assertDatabaseHas('weekly_performances',['plan_item_id'=>$planItem->id,'workflow_status'=>'submitted_to_plan_report_department']);
    }

    public function test_workflow_users_can_move_monthly_performance_from_expert_to_head_office_approval(): void
    {
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $department = Department::create([
            'name' => 'Planning Monthly',
            'code' => 'PLM',
            'objective_weight' => 15,
            'reporting_type' => 'monthly',
            'is_active' => true,
        ]);

        $objective = StrategicObjective::create([
            'code' => '1',
            'description' => 'Monthly workflow objective',
            'status' => 'approved',
            'weight' => 10,
            'unit' => 'Count',
            'department_id' => $department->id,
            'performers' => [$department->id],
            'goal_direction' => 'increase',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $admin->roles()->sync([Role::where('name', 'system_admin')->value('id')]);

        $plan = Plan::create([
            'title' => 'Planning Monthly Plan',
            'type' => 'annual',
            'year' => 2026,
            'department_id' => $department->id,
            'status' => 'approved',
            'created_by' => $admin->id,
        ]);

        $planItem = PlanItem::create([
            'plan_id' => $plan->id,
            'strategic_objective_id' => $objective->id,
            'annual_target' => 120,
            'weight' => 10,
            'unit' => 'number',
            'performers' => [$department->id],
        ]);
        $this->approveItemForExperts($planItem, $department);

        $expert = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $expert->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

        $directorate = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $directorate->roles()->sync([Role::where('name', 'department_directorate')->value('id')]);

        $chief = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $chief->roles()->sync([Role::where('name', 'department_chief')->value('id')]);

        $planReportDepartment = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $planReportDepartment->roles()->sync([Role::where('name', 'plan_report_department_expert')->value('id')]);

        $planReportDirectorate = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $planReportDirectorate->roles()->sync([Role::where('name', 'plan_report_directorate')->value('id')]);

        $planReportHeadOffice = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $planReportHeadOffice->roles()->sync([Role::where('name', 'plan_report_head_office')->value('id')]);

        PeriodicPerformance::create([
            'plan_item_id' => $planItem->id,
            'period' => '2026-july',
            'actual_value' => 18,
            'notes' => 'Monthly draft',
            'status' => 'pending',
            'workflow_status' => 'draft',
        ]);

        $this->actingAs($expert)->post(route('monthly.workflow', ['year' => 2026, 'month' => 'july']), [
            'department_id' => $department->id,
            'action' => 'submit_to_directorate',
        ])->assertRedirect();

        $this->assertDatabaseHas('periodic_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_directorate',
        ]);

        $this->actingAs($directorate)->post(route('monthly.workflow', ['year' => 2026, 'month' => 'july']), [
            'department_id' => $department->id,
            'action' => 'submit_to_chief',
        ])->assertRedirect();

        $this->assertDatabaseHas('periodic_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_chief',
        ]);

        $this->actingAs($chief)->post(route('monthly.workflow', ['year' => 2026, 'month' => 'july']), [
            'department_id' => $department->id,
            'action' => 'approve_by_chief',
        ])->assertRedirect();

        $this->assertDatabaseHas('periodic_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_plan_report_department',
        ]);

        $this->actingAs($planReportDepartment)->post(route('monthly.workflow', ['year' => 2026, 'month' => 'july']), [
            'department_id' => $department->id,
            'action' => 'mark_reviewed_no_correction',
        ])->assertRedirect();

        $this->assertDatabaseHas('periodic_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_plan_report_directorate',
            'status' => 'pending',
        ]);
        Notification::assertSentTo($planReportDirectorate, WorkflowNotification::class, function ($notification) use ($planReportDirectorate) {
            $payload = $notification->toArray($planReportDirectorate);

            return $payload['title'] === 'Performance awaiting Directorate confirmation'
                && str_contains($payload['message'], 'reviewed with no correction required');
        });
        Notification::assertNotSentTo($planReportHeadOffice, WorkflowNotification::class);

        $this->actingAs($planReportDirectorate)->post(route('monthly.workflow', ['year' => 2026, 'month' => 'july']), [
            'department_id' => $department->id,
            'action' => 'submit_to_head_office',
        ])->assertRedirect();

        $this->assertDatabaseHas('periodic_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'submitted_to_head_office',
            'status' => 'pending',
        ]);

        $this->actingAs($planReportHeadOffice)->post(route('monthly.workflow', ['year' => 2026, 'month' => 'july']), [
            'department_id' => $department->id,
            'action' => 'approve_by_head_office',
        ])->assertRedirect();

        $this->assertDatabaseHas('periodic_performances', [
            'plan_item_id' => $planItem->id,
            'workflow_status' => 'approved_by_head_office',
            'status' => 'approved',
        ]);
    }

    public function test_workflow_periods_remain_visible_to_department_roles_but_only_current_owner_can_edit(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $department = Department::create([
            'name' => 'Planning',
            'code' => 'PLN2',
            'objective_weight' => 15,
            'is_active' => true,
        ]);

        $objective = StrategicObjective::create([
            'code' => '1',
            'description' => 'Visibility objective',
            'status' => 'approved',
            'weight' => 10,
            'unit' => 'Count',
            'department_id' => $department->id,
            'performers' => [$department->id],
            'goal_direction' => 'increase',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $admin->roles()->sync([Role::where('name', 'system_admin')->value('id')]);

        $plan = Plan::create([
            'title' => 'Visibility Plan',
            'type' => 'annual',
            'year' => 2026,
            'department_id' => $department->id,
            'status' => 'approved',
            'created_by' => $admin->id,
        ]);

        $planItem = PlanItem::create([
            'plan_id' => $plan->id,
            'strategic_objective_id' => $objective->id,
            'annual_target' => 100,
            'weight' => 10,
            'unit' => 'number',
            'performers' => [$department->id],
        ]);
        $this->approveItemForExperts($planItem, $department);

        $expert = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $expert->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

        $directorate = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $directorate->roles()->sync([Role::where('name', 'department_directorate')->value('id')]);

        WeeklyPerformance::create([
            'plan_item_id' => $planItem->id,
            'year' => 2026,
            'month' => 'july',
            'week_number' => 1,
            'planned_value' => 20,
            'actual_value' => 15,
            'percentage' => 75,
            'status' => 'draft',
            'workflow_status' => 'draft',
            'created_by' => $expert->id,
        ]);

        $this->actingAs($expert)
            ->get(route('weekly.entry', ['year' => 2026, 'month' => 'july', 'week' => 1, 'department_id' => $department->id]))
            ->assertOk();

        $this->actingAs($directorate)
            ->get(route('weekly.entry', ['year' => 2026, 'month' => 'july', 'week' => 1, 'department_id' => $department->id]))
            ->assertOk()
            ->assertSee('read-only for your current role');

        $this->actingAs($expert)->post(route('weekly.workflow', [
            'year' => 2026, 'month' => 'july', 'week' => 1,
        ]), [
            'department_id' => $department->id,
            'action' => 'submit_to_directorate',
        ])->assertRedirect();

        // Submission remains on the expert's calendar and opens read-only with
        // the latest workflow status instead of disappearing.
        $this->actingAs($expert)
            ->get(route('weekly.dashboard', ['year' => 2026, 'department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Directorate')
            ->assertSee('1/5 Weeks');

        $this->actingAs($expert)
            ->get(route('weekly.entry', ['year' => 2026, 'month' => 'july', 'week' => 1, 'department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Submitted to Directorate')
            ->assertSee('read-only for your current role');

        $this->actingAs($admin)
            ->get(route('performance.report', ['plan' => $plan->id, 'period' => 'july']))
            ->assertOk()
            ->assertSee('July')
            ->assertSee('Week 1')
            ->assertSee('Jun 27 - Jul 03, 2026')
            ->assertSee('Submitted to Directorate');
    }

    public function test_department_users_only_see_and_open_their_configured_reporting_module(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['daily', 'weekly', 'monthly'] as $reportingType) {
            $department = Department::create([
                'name' => ucfirst($reportingType) . ' Navigation',
                'code' => strtoupper(substr($reportingType, 0, 3)) . 'N',
                'reporting_type' => $reportingType,
                'is_active' => true,
            ]);
            $expert = User::factory()->create([
                'department_id' => $department->id,
                'is_active' => true,
            ]);
            $expert->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

            $expectedLabel = match ($reportingType) {
                'daily' => 'Daily Performance',
                'weekly' => 'Weekly Workbench',
                'monthly' => 'Monthly Aggregator',
            };
            $dashboardRoute = match ($reportingType) {
                'daily' => 'daily.dashboard',
                'weekly' => 'weekly.dashboard',
                'monthly' => 'monthly.dashboard',
            };

            $response = $this->actingAs($expert)->get(route($dashboardRoute));
            $response->assertOk()->assertSee($expectedLabel);

            foreach ([
                'daily' => ['daily.dashboard', 'Daily Performance'],
                'weekly' => ['weekly.dashboard', 'Weekly Workbench'],
                'monthly' => ['monthly.dashboard', 'Monthly Aggregator'],
            ] as $type => [$routeName, $label]) {
                if ($type === $reportingType) continue;

                $response->assertDontSee($label);
                $this->actingAs($expert)->get(route($routeName))->assertForbidden();
            }
        }
    }

    public function test_organization_wide_reviewer_sees_all_reporting_modules_but_regular_employee_sees_none(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create([
            'name' => 'Review Navigation', 'code' => 'RVN',
            'reporting_type' => 'weekly', 'is_active' => true,
        ]);

        $reviewer = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $reviewer->roles()->sync([Role::where('name', 'plan_report_head_office')->value('id')]);
        $this->actingAs($reviewer)->get(route('weekly.dashboard'))
            ->assertOk()
            ->assertSee('Daily Performance')
            ->assertSee('Weekly Workbench')
            ->assertSee('Monthly Aggregator')
            ->assertDontSee('Employees')
            ->assertDontSee('Users &amp; Roles', false)
            ->assertDontSee('LOGISTICS &amp; IMPORTS', false);

        $employee = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $employee->roles()->sync([Role::where('name', 'employee')->value('id')]);
        $this->actingAs($employee)->get(route('objectives.index'))
            ->assertOk()
            ->assertDontSee('Daily Performance')
            ->assertDontSee('Weekly Workbench')
            ->assertDontSee('Monthly Aggregator')
            ->assertDontSee('Performance</p>', false)
            ->assertSee('Tasks');
    }

    public function test_plan_report_department_expert_reviews_each_reporting_dashboard_by_sender_department(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $planning = Department::create([
            'name' => 'Plan and Report', 'code' => 'PRD',
            'reporting_type' => 'weekly', 'is_active' => true,
        ]);
        $dailySender = Department::create([
            'name' => 'Daily Sender', 'code' => 'DSN',
            'reporting_type' => 'daily', 'is_active' => true,
        ]);
        $weeklySender = Department::create([
            'name' => 'Weekly Sender', 'code' => 'WSN',
            'reporting_type' => 'weekly', 'is_active' => true,
        ]);
        $monthlySender = Department::create([
            'name' => 'Monthly Sender', 'code' => 'MSN',
            'reporting_type' => 'monthly', 'is_active' => true,
        ]);

        $reviewer = User::factory()->create([
            'department_id' => $planning->id,
            'is_active' => true,
        ]);
        $reviewer->roles()->sync([Role::where('name', 'plan_report_department_expert')->value('id')]);

        $this->actingAs($reviewer)->get(route('daily.dashboard'))
            ->assertOk()
            ->assertSee('Select a sender department')
            ->assertSee($dailySender->name);

        $this->actingAs($reviewer)->get(route('weekly.dashboard'))
            ->assertOk()
            ->assertSee('Select a sender department')
            ->assertSee($weeklySender->name);

        $this->actingAs($reviewer)->get(route('monthly.dashboard'))
            ->assertOk()
            ->assertSee('Select a sender department')
            ->assertSee($monthlySender->name);
    }

    public function test_daily_save_rolls_child_values_up_to_calculated_parent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $department = Department::create([
            'name' => 'Daily Rollup', 'code' => 'DRU',
            'reporting_type' => 'daily', 'is_active' => true,
        ]);
        $expert = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $expert->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

        $parent = StrategicObjective::create([
            'code' => '1', 'description' => 'Calculated parent', 'weight' => 20,
            'unit' => 'Count', 'department_id' => $department->id,
            'performers' => [$department->id], 'goal_direction' => 'increase',
            'summary_type' => 'sum', 'status' => 'approved', 'is_active' => true,
        ]);
        $childOne = StrategicObjective::create([
            'code' => '1.1', 'description' => 'First child', 'weight' => 10,
            'unit' => 'Count', 'department_id' => $department->id, 'parent_id' => $parent->id,
            'performers' => [$department->id], 'goal_direction' => 'increase',
            'status' => 'approved', 'is_active' => true,
        ]);
        $childTwo = StrategicObjective::create([
            'code' => '1.2', 'description' => 'Second child', 'weight' => 10,
            'unit' => 'Count', 'department_id' => $department->id, 'parent_id' => $parent->id,
            'performers' => [$department->id], 'goal_direction' => 'increase',
            'status' => 'approved', 'is_active' => true,
        ]);

        $plan = Plan::create([
            'title' => 'Daily rollup plan', 'type' => 'annual', 'year' => 2019,
            'department_id' => $department->id, 'status' => 'approved', 'created_by' => $expert->id,
        ]);
        $parentItem = PlanItem::create([
            'plan_id' => $plan->id, 'strategic_objective_id' => $parent->id,
            'annual_target' => 180, 'july_target' => 60, 'weight' => 20,
            'unit' => 'number', 'performers' => [$department->id],
        ]);
        $childOneItem = PlanItem::create([
            'plan_id' => $plan->id, 'strategic_objective_id' => $childOne->id,
            'annual_target' => 60, 'july_target' => 20, 'weight' => 10,
            'unit' => 'number', 'performers' => [$department->id],
        ]);
        $childTwoItem = PlanItem::create([
            'plan_id' => $plan->id, 'strategic_objective_id' => $childTwo->id,
            'annual_target' => 120, 'july_target' => 40, 'weight' => 10,
            'unit' => 'number', 'performers' => [$department->id],
        ]);
        foreach ([$parentItem, $childOneItem, $childTwoItem] as $item) {
            $this->approveItemForExperts($item, $department);
        }

        $this->actingAs($expert)->post(route('daily.store'), [
            'year' => 2019, 'month' => 'july', 'week' => 1,
            'department_id' => $department->id,
            'actuals' => [
                $childOneItem->id => ['monday' => 4, 'tuesday' => 1],
                $childTwoItem->id => ['monday' => 8, 'tuesday' => 2],
            ],
            'weekly_plans' => [
                $childOneItem->id => 5,
                $childTwoItem->id => 10,
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $parentItem->id,
            'year' => 2019, 'month' => 'july', 'week_number' => 1,
            'planned_value' => 15, 'actual_value' => 15, 'percentage' => 100,
        ]);

        $this->assertDatabaseHas('daily_performances', [
            'plan_item_id' => $parentItem->id,
            'year' => 2019, 'month' => 'july', 'week_number' => 1,
            'day_of_week' => 'monday', 'actual_value' => 12,
        ]);
        $this->assertDatabaseHas('daily_performances', [
            'plan_item_id' => $parentItem->id,
            'year' => 2019, 'month' => 'july', 'week_number' => 1,
            'day_of_week' => 'tuesday', 'actual_value' => 3,
        ]);

        $this->actingAs($expert)->get(route('daily.entry', [
            'year' => 2019, 'month' => 'july', 'week' => 1,
            'department_id' => $department->id,
        ]))->assertOk()->assertSee('15.00');

        $this->actingAs($expert)->post(route('weekly.workflow', [
            'year' => 2019, 'month' => 'july', 'week' => 1,
        ]), [
            'department_id' => $department->id,
            'action' => 'submit_to_directorate',
        ])->assertRedirect(route('daily.dashboard', [
            'year' => 2019,
            'department_id' => $department->id,
        ]))->assertSessionHas('success', 'Performance submitted to directorate for review.');

        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $parentItem->id,
            'workflow_status' => 'submitted_to_directorate',
        ]);

        $this->actingAs($expert)->get(route('daily.dashboard', [
            'year' => 2019,
            'department_id' => $department->id,
        ]))->assertOk()
          ->assertSee('Directorate')
          ->assertSee('Items');

        $this->actingAs($expert)->get(route('daily.entry', [
            'year' => 2019, 'month' => 'july', 'week' => 1,
            'department_id' => $department->id,
        ]))->assertOk()
          ->assertSee('Submitted to Directorate')
          ->assertSee('read-only for your current role');

        $this->actingAs($expert)->get(route('weekly.report', [
            'year' => 2019, 'month' => 'july', 'week' => 1,
            'department_id' => $department->id,
        ]))->assertOk()
          ->assertSee('Submitted to Directorate');
    }

    public function test_master_report_only_shows_objectives_assigned_in_the_department_plan(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $department = Department::create([
            'name' => 'Assigned Report Department', 'code' => 'ARD',
            'reporting_type' => 'weekly', 'is_active' => true,
        ]);
        $expert = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $expert->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

        $assigned = StrategicObjective::create([
            'code' => '1', 'description' => 'Assigned master objective', 'weight' => 10,
            'unit' => 'Count', 'department_id' => $department->id,
            'performers' => [$department->id], 'goal_direction' => 'increase',
            'summary_type' => 'sum', 'is_calculated_weight' => true,
            'status' => 'approved', 'is_active' => true,
        ]);
        $assignedChildOne = StrategicObjective::create([
            'code' => '1.1', 'description' => 'Assigned child one', 'weight' => 4,
            'unit' => 'Count', 'baseline_value' => 4, 'department_id' => $department->id,
            'parent_id' => $assigned->id, 'performers' => [$department->id],
            'goal_direction' => 'increase', 'status' => 'approved', 'is_active' => true,
        ]);
        $assignedChildTwo = StrategicObjective::create([
            'code' => '1.2', 'description' => 'Assigned child two', 'weight' => 6,
            'unit' => 'Count', 'baseline_value' => 6, 'department_id' => $department->id,
            'parent_id' => $assigned->id, 'performers' => [$department->id],
            'goal_direction' => 'increase', 'status' => 'approved', 'is_active' => true,
        ]);
        $unrelated = StrategicObjective::create([
            'code' => '2', 'description' => 'Unrelated organization objective', 'weight' => 10,
            'unit' => 'Count', 'department_id' => $department->id,
            'performers' => [], 'goal_direction' => 'increase',
            'status' => 'approved', 'is_active' => true,
        ]);
        StrategicObjective::create([
            'code' => '2.1', 'description' => 'Unrelated sub-objective', 'weight' => 10,
            'unit' => 'Count', 'department_id' => $department->id, 'parent_id' => $unrelated->id,
            'performers' => [], 'goal_direction' => 'increase',
            'status' => 'approved', 'is_active' => true,
        ]);

        $plan = Plan::create([
            'title' => 'Assigned-only plan', 'type' => 'annual', 'year' => 2019,
            'department_id' => $department->id, 'status' => 'approved', 'created_by' => $expert->id,
        ]);
        foreach ([$assignedChildOne, $assignedChildTwo] as $child) {
            PlanItem::create([
                'plan_id' => $plan->id, 'strategic_objective_id' => $child->id,
                'annual_target' => 50, 'weight' => $child->weight, 'unit' => 'number',
                'performers' => [$department->id],
            ]);
        }

        $response = $this->actingAs($expert)->get(route('reports.master', ['year' => 2019]))
            ->assertOk()
            ->assertSee('Assigned master objective')
            ->assertDontSee('Unrelated organization objective')
            ->assertDontSee('Unrelated sub-objective')
            ->assertDontSee('Show All Objectives');

        $parentRow = collect($response->viewData('gridData'))->firstWhere('id', $assigned->id);
        $this->assertSame(10.0, $parentRow['baseline']);
    }

    public function test_plan_report_workers_can_view_master_reports_for_all_departments(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $planning = Department::create([
            'name' => 'Planning', 'code' => 'PLN',
            'reporting_type' => 'weekly', 'is_active' => true,
        ]);
        $operations = Department::create([
            'name' => 'Operations', 'code' => 'OPS',
            'reporting_type' => 'weekly', 'is_active' => true,
        ]);
        $planningExpert = User::factory()->create(['department_id' => $planning->id, 'is_active' => true]);
        $planningExpert->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

        foreach ([
            [$planning, '1', 'Planning assigned objective'],
            [$operations, '2', 'Operations assigned objective'],
        ] as [$department, $code, $description]) {
            $objective = StrategicObjective::create([
                'code' => $code, 'description' => $description, 'weight' => 10,
                'unit' => 'Count', 'department_id' => $department->id,
                'performers' => [$department->id], 'goal_direction' => 'increase',
                'status' => 'approved', 'is_active' => true,
            ]);
            $plan = Plan::create([
                'title' => $department->name . ' plan', 'type' => 'annual', 'year' => 2019,
                'department_id' => $department->id, 'status' => 'approved',
                'created_by' => $planningExpert->id,
            ]);
            PlanItem::create([
                'plan_id' => $plan->id, 'strategic_objective_id' => $objective->id,
                'annual_target' => 100, 'weight' => 10, 'unit' => 'number',
                'performers' => [$department->id],
            ]);
        }

        $this->actingAs($planningExpert)->get(route('reports.master', ['year' => 2019]))
            ->assertOk()
            ->assertSee('All Departments')
            ->assertSee('Planning assigned objective')
            ->assertSee('Operations assigned objective')
            ->assertSee('Department')
            ->assertSeeInOrder([
                'JUL', 'AUG', 'SEP', 'Q1',
                'OCT', 'NOV', 'DEC', 'Q2',
                'JAN', 'FEB', 'MAR', 'Q3',
                'APR', 'MAY', 'JUN', 'Q4',
            ]);

        $this->actingAs($planningExpert)->get(route('reports.master', [
            'year' => 2019, 'department_id' => $operations->id,
        ]))->assertOk()
          ->assertSee('Operations assigned objective')
          ->assertDontSee('Planning assigned objective');
    }
    public function test_plan_report_reviewers_can_edit_only_at_their_submitted_stage_without_changing_ownership(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $ownerDepartment = Department::create(['name' => 'Operations', 'code' => 'OPS', 'is_active' => true]);
        $reviewDepartment = Department::create(['name' => 'Plan and Report', 'code' => 'PR', 'is_active' => true]);

        $directorate = User::factory()->create(['department_id' => $reviewDepartment->id]);
        $directorate->roles()->sync([Role::where('name', 'plan_report_directorate')->value('id')]);
        $head = User::factory()->create(['department_id' => $reviewDepartment->id]);
        $head->roles()->sync([Role::where('name', 'plan_report_head_office')->value('id')]);

        $directorateObjective = StrategicObjective::create([
            'code' => 'REV-1', 'description' => 'Directorate review objective', 'weight' => 10,
            'unit' => 'Count', 'department_id' => $ownerDepartment->id,
            'performers' => [$ownerDepartment->id], 'lead_department_id' => $ownerDepartment->id,
            'goal_direction' => 'increase', 'target_year' => 2019,
            'status' => 'submitted_to_plan_report_directorate', 'is_active' => true,
        ]);
        $headObjective = StrategicObjective::create([
            'code' => 'REV-2', 'description' => 'Head review objective', 'weight' => 10,
            'unit' => 'Count', 'department_id' => $ownerDepartment->id,
            'performers' => [$ownerDepartment->id], 'lead_department_id' => $ownerDepartment->id,
            'goal_direction' => 'increase', 'target_year' => 2019,
            'status' => 'submitted', 'is_active' => true,
        ]);

        $this->actingAs($directorate)->get(route('objectives.edit', $directorateObjective))->assertOk();
        $this->actingAs($directorate)->get(route('objectives.edit', $headObjective))->assertForbidden();
        $this->actingAs($head)->get(route('objectives.edit', $headObjective))->assertOk();
        $this->actingAs($head)->get(route('objectives.edit', $directorateObjective))->assertForbidden();

        $this->actingAs($directorate)->put(route('objectives.update', $directorateObjective), [
            'code' => 'REV-1', 'description' => 'Corrected during Directorate review', 'weight' => 10,
            'unit' => 'Count', 'target_year' => 2019, 'goal_direction' => 'increase',
            'performers' => [$ownerDepartment->id], 'lead_department_id' => $ownerDepartment->id,
            'review_change_reason' => 'Corrected the objective wording during Directorate review.',
        ])->assertRedirect(route('objectives.index'));

        $directorateObjective->refresh();
        $this->assertSame($ownerDepartment->id, $directorateObjective->department_id);
        $this->assertSame('submitted_to_plan_report_directorate', $directorateObjective->status);
        $this->assertSame('Corrected during Directorate review', $directorateObjective->description);
        $this->assertSame(1, $directorateObjective->revision_number);
        $this->assertSame($directorate->id, $directorateObjective->last_edited_by);
        $this->assertSame('Corrected the objective wording during Directorate review.', $directorateObjective->last_change_reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'strategic_objective_updated',
            'model_id' => $directorateObjective->id,
            'user_id' => $directorate->id,
        ]);
    }

    private function approveItemForExperts(PlanItem $item, Department $department): void
    {
        PlanItemAssignment::updateOrCreate(
            ['plan_item_id' => $item->id, 'performer_id' => $department->id],
            ['status' => 'approved_for_experts']
        );
    }
}
