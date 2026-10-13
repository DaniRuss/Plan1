<?php

namespace Tests\Feature;

use App\Http\Controllers\EdrReportController;
use App\Models\Department;
use App\Models\PeriodicPerformance;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Models\WeeklyPerformance;
use App\Services\ReportCompleteness;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdrReportPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_creation_offers_monthly_and_weekly_periods_and_calculates_them(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create([
            'name' => 'Operation', 'code' => 'OPS', 'reporting_type' => 'weekly', 'is_active' => true,
        ]);
        $admin = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $admin->roles()->sync([Role::where('name', 'system_admin')->value('id')]);

        $objective = StrategicObjective::create([
            'code' => '1', 'description' => 'Operational KPI', 'weight' => 10, 'unit' => 'Number',
            'department_id' => $department->id, 'performers' => [$department->id],
            'goal_direction' => 'increase', 'status' => 'approved', 'is_active' => true,
        ]);
        $plan = Plan::create([
            'title' => 'Operation Plan', 'type' => 'annual', 'year' => 2019,
            'department_id' => $department->id, 'status' => 'approved', 'created_by' => $admin->id,
        ]);
        $item = PlanItem::create([
            'plan_id' => $plan->id, 'strategic_objective_id' => $objective->id,
            'annual_target' => 120, 'july_target' => 12, 'weight' => 10,
            'unit' => 'number', 'performers' => [$department->id],
        ]);
        PeriodicPerformance::create([
            'plan_item_id' => $item->id, 'period' => '2019-july',
            'actual_value' => 9, 'status' => 'approved',
        ]);
        WeeklyPerformance::create([
            'plan_item_id' => $item->id, 'year' => 2019, 'month' => 'july', 'week_number' => 2,
            'planned_value' => 3, 'actual_value' => 2, 'percentage' => 66.67,
            'status' => 'submitted', 'workflow_status' => 'approved_by_head_office',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('reports.create'))
            ->assertOk()
            ->assertSee('All Departments')
            ->assertSee('value="all"', false)
            ->assertSee('Monthly Reports')
            ->assertSee('value="month:july"', false)
            ->assertSee('Weekly Reports')
            ->assertSee('value="week:july:2"', false);

        $controller = app(EdrReportController::class);
        $monthly = $controller->calculateComparisonData($department->id, 2019, 'month:july');
        $weekly = $controller->calculateComparisonData($department->id, 2019, 'week:july:2');

        $this->assertSame(12.0, $monthly[0]['quarter_plan']);
        $this->assertSame(2.0, $monthly[0]['quarter_perf']);
        $this->assertSame(3.0, $weekly[0]['quarter_plan']);
        $this->assertSame(2.0, $weekly[0]['quarter_perf']);

        $allDepartments = $controller->calculateAllDepartmentComparisonData(2019, 'month:july');
        $this->assertCount(1, $allDepartments);
        $this->assertSame('Operation', $allDepartments[0]['reporting_department']);

        $this->actingAs($admin)->get(route('reports.preview', [
            'department_id' => 'all',
            'reporting_period' => 'month:july',
            'year' => 2019,
        ]))->assertOk()->assertSee('Operational KPI');

        $this->actingAs($admin)->get(route('reports.preview', [
            'department_id' => $department->id,
            'reporting_period' => 'Q1',
            'year' => 2019,
        ]))->assertOk()->assertSee('Operational KPI');

        $response = $this->actingAs($admin)->post(route('reports.store'), [
            'department_id' => 'all',
            'reporting_period' => 'month:july',
            'year' => 2019,
            'executive_summary' => 'Collected company report',
        ]);
        $report = \App\Models\EdrReport::latest('id')->firstOrFail();
        $response->assertRedirect(route('reports.show', $report));
        $this->assertNull($report->department_id);
        $this->actingAs($admin)->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('All Departments');
    }

    public function test_collected_report_counts_a_shared_kpi_once_using_the_lead_department_result(): void
    {
        $lead = Department::create([
            'name' => 'Operations', 'code' => 'OPS', 'reporting_type' => 'monthly', 'is_active' => true,
        ]);
        $contributor = Department::create([
            'name' => 'Maintenance', 'code' => 'MNT', 'reporting_type' => 'monthly', 'is_active' => true,
        ]);
        $user = User::factory()->create(['department_id' => $lead->id]);
        $objective = StrategicObjective::create([
            'code' => 'K1', 'kpi_code' => 'K1', 'description' => 'Shared company KPI',
            'weight' => 10, 'unit' => 'Percentage (%)', 'department_id' => $lead->id,
            'lead_department_id' => $lead->id, 'company_consolidation_method' => 'lead_department',
            'performers' => [$lead->id, $contributor->id], 'goal_direction' => 'increase',
            'status' => 'approved', 'is_active' => true,
        ]);

        foreach ([[$lead, 80], [$contributor, 20]] as [$department, $actual]) {
            $plan = Plan::create([
                'title' => $department->name . ' Plan', 'type' => 'annual', 'year' => 2019,
                'department_id' => $department->id, 'status' => 'approved', 'created_by' => $user->id,
            ]);
            $item = PlanItem::create([
                'plan_id' => $plan->id, 'strategic_objective_id' => $objective->id,
                'annual_target' => 100, 'july_target' => 100, 'weight' => 10,
                'unit' => 'percent', 'performers' => [$lead->id, $contributor->id],
            ]);
            PeriodicPerformance::create([
                'plan_item_id' => $item->id, 'period' => '2019-july',
                'actual_value' => $actual, 'status' => 'approved',
            ]);
        }

        $rows = app(EdrReportController::class)
            ->calculateAllDepartmentComparisonData(2019, 'month:july');

        $this->assertCount(1, $rows);
        $this->assertSame('Operations', $rows[0]['reporting_department']);
        $this->assertSame(80.0, $rows[0]['quarter_perf']);
        $this->assertSame(2, $rows[0]['contributor_count']);
        $this->assertEqualsCanonicalizing(['Operations', 'Maintenance'], $rows[0]['contributing_departments']);
        $this->assertSame(8.0, $rows[0]['weighted_score']);
    }

    public function test_monthly_completeness_is_provisional_and_aggregates_only_approved_weeks(): void
    {
        $department = Department::create([
            'name' => 'Weekly Operations', 'code' => 'WOP', 'reporting_type' => 'weekly', 'is_active' => true,
        ]);
        $user = User::factory()->create(['department_id' => $department->id]);
        $objective = StrategicObjective::create([
            'code' => '1', 'description' => 'Weekly KPI', 'weight' => 10, 'unit' => 'Number',
            'department_id' => $department->id, 'lead_department_id' => $department->id,
            'performers' => [$department->id], 'goal_direction' => 'increase',
            'target_distribution' => 'sum', 'weekly_plan_mode' => 'distribute',
            'status' => 'approved', 'is_active' => true,
        ]);
        $plan = Plan::create([
            'title' => 'Weekly Plan', 'type' => 'annual', 'year' => 2019,
            'department_id' => $department->id, 'status' => 'approved', 'created_by' => $user->id,
        ]);
        $item = PlanItem::create([
            'plan_id' => $plan->id, 'strategic_objective_id' => $objective->id,
            'annual_target' => 50, 'july_target' => 50, 'weight' => 10,
            'unit' => 'number', 'performers' => [$department->id],
        ]);

        foreach ([1, 2, 3, 4] as $week) {
            WeeklyPerformance::create([
                'plan_item_id' => $item->id, 'year' => 2019, 'month' => 'july', 'week_number' => $week,
                'planned_value' => 10, 'actual_value' => 10, 'percentage' => 100,
                'status' => 'submitted',
                'workflow_status' => $week <= 3 ? 'approved_by_head_office' : 'submitted_to_head_office',
                'submitted_by' => $user->id, 'submitted_at' => now(), 'created_by' => $user->id,
            ]);
        }

        $loadedPlan = Plan::with(['department', 'items.strategicObjective.children', 'items.weeklyPerformances'])
            ->findOrFail($plan->id);
        $completeness = app(ReportCompleteness::class)->forPlan($loadedPlan, 'month:july');

        $this->assertSame(4, $completeness['expected']);
        $this->assertSame(4, $completeness['submitted']);
        $this->assertSame(3, $completeness['approved']);
        $this->assertSame(75.0, $completeness['reporting_coverage']);
        $this->assertSame(100.0, $completeness['planning_coverage']);
        $this->assertSame('Provisional', $completeness['status']);
        $this->assertSame(30.0, $loadedPlan->items->first()->getActualValueForPeriod('july'));
    }
}
