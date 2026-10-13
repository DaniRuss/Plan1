<?php

namespace Tests\Feature;

use App\Models\{Department, Employee, Plan, PlanItem, Role, StrategicObjective, User, WeeklyPerformance, PeriodicPerformance};
use App\Services\DashboardWorkspace;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(Department $department, string $role): User
    {
        $user = User::factory()->create(['department_id' => $department->id, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        return $user;
    }

    private function item(Department $department, User $creator, int $year = 2026): PlanItem
    {
        $objective = StrategicObjective::create(['department_id' => $department->id, 'code' => '1', 'description' => $department->name.' KPI', 'unit' => 'Tons', 'weight' => 100, 'status' => 'approved']);
        $plan = Plan::create(['department_id' => $department->id, 'title' => 'Plan', 'type' => 'annual', 'year' => $year, 'status' => 'approved', 'created_by' => $creator->id]);
        return PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, 'july_target' => 100]);
    }

    public function test_expert_without_employee_sees_real_department_records_and_not_other_departments(): void
    {
        $own = Department::create(['name' => 'Operations', 'code' => 'OP']);
        $other = Department::create(['name' => 'Other', 'code' => 'OT']);
        $user = $this->user($own, 'department_expert');
        $item = $this->item($own, $user, 2025);
        $foreign = $this->item($other, $user);
        WeeklyPerformance::create(['plan_item_id' => $item->id, 'year' => 2025, 'month' => 'july', 'week_number' => 1, 'planned_value' => 100, 'actual_value' => 40, 'percentage' => 40, 'workflow_status' => 'submitted_to_plan_report_department', 'status' => 'submitted']);
        WeeklyPerformance::create(['plan_item_id' => $foreign->id, 'year' => 2026, 'month' => 'july', 'week_number' => 1, 'planned_value' => 100, 'actual_value' => 90, 'percentage' => 90, 'workflow_status' => 'approved_by_head_office', 'status' => 'approved']);
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $stats = collect($response->viewData('stats'))->pluck('value', 'label');
        $this->assertEquals(1, $stats['Department Plan Items']);
        $this->assertEquals(1, $stats['Reported Records']);
        $this->assertEquals(0, $stats['Reviewed / Approved Records']);
        $this->assertSame(2025, $response->viewData('workspaceYear'));
        $this->assertSame(40.0, $response->viewData('barChart')['datasets'][0]['data'][0]);
        $this->assertCount(12, $response->viewData('barChart')['datasets'][0]['data']);
        $this->assertNull($response->viewData('barChart')['datasets'][0]['data'][1]);
        $this->assertFalse($response->viewData('performanceAnalytics')['hasData']);
    }

    public function test_periodic_year_prefix_and_legacy_approval_are_supported_without_fake_values(): void
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OP']);
        $user = $this->user($department, 'department_expert');
        $item = $this->item($department, $user);
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-july', 'actual_value' => 75, 'status' => 'approved', 'workflow_status' => null]);
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertSame(75.0, $response->viewData('barChart')['datasets'][0]['data'][0]);
        $this->assertSame(75.0, $response->viewData('performanceAnalytics')['average']);
        $this->assertEquals(1, $response->viewData('performanceAnalytics')['officialRecords']);
        $filtered = $this->get(route('dashboard', ['performance_period' => 'august']))->assertOk()->viewData('performanceAnalytics');
        $this->assertEquals(0, $filtered['submittedRecords']);
        $this->assertEquals(0, $filtered['officialRecords']);
    }

    public function test_review_queue_counts_only_records_at_the_users_review_stage(): void
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OP']);
        $user = $this->user($department, 'plan_report_department_expert');
        $item = $this->item($department, $user);
        foreach (['submitted_to_plan_report_department', 'submitted_to_head_office'] as $index => $state) {
            WeeklyPerformance::create(['plan_item_id' => $item->id, 'year' => 2026, 'month' => 'july', 'week_number' => $index + 1, 'planned_value' => 100, 'actual_value' => 50, 'percentage' => 50, 'workflow_status' => $state, 'status' => 'submitted']);
        }
        $snapshot = app(DashboardWorkspace::class)->snapshot($user);
        $this->assertSame(2, $snapshot['reported']);
        $this->assertSame(1, $snapshot['queue']);
        $this->assertSame(0, $snapshot['official']);
    }

    public function test_all_role_dashboards_render_and_unassigned_users_do_not_inherit_root_departments(): void
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OP']);
        foreach (Role::all() as $role) {
            $user = $this->user($department, $role->name);
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $this->assertFalse($response->viewData('doughnutChart')['hasData']);
            $this->assertSame([], $response->viewData('doughnutChart')['data']);
        }
        $user = $this->user($department, 'department_chief');
        $this->item($department, $user);
        $user->update(['department_id' => null]);
        $snapshot = app(DashboardWorkspace::class)->snapshot($user);
        $this->assertSame(0, $snapshot['items']);
        $this->assertNull($snapshot['year']);
    }

    public function test_personal_assignments_and_final_only_access_are_respected(): void
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OP']);
        $expert = $this->user($department, 'department_expert');
        $item = $this->item($department, $expert);
        $item->update(['experts' => [999]]);
        $this->assertSame(0, app(DashboardWorkspace::class)->snapshot($expert)['items']);
        $item->update(['experts' => null]);
        WeeklyPerformance::create(['plan_item_id' => $item->id, 'year' => 2026, 'month' => 'july', 'week_number' => 1, 'planned_value' => 100, 'actual_value' => 75, 'percentage' => 75, 'workflow_status' => 'submitted_to_plan_report_department', 'status' => 'submitted']);
        $office = $this->user($department, 'head_office');
        $snapshot = app(DashboardWorkspace::class)->snapshot($office);
        $this->assertSame(0, $snapshot['reported']);
        $this->assertFalse($snapshot['barChart']['hasData']);
        $analytics = $this->actingAs($office)->get(route('dashboard'))->assertOk()->viewData('performanceAnalytics');
        $this->assertSame(0, $analytics['submittedRecords']);
    }
}
