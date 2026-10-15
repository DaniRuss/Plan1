<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PeriodicPerformance;
use App\Models\PeriodWeightDistribution;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_report_uses_only_head_office_approved_periodic_results(): void
    {
        [$user, $department] = $this->reportUserAndDepartment('Monthly Office', 'MON');
        $objective = $this->objective($department, '1', 'Approval filtered KPI');
        $item = $this->planItem($user, $department, $objective, 2019);

        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2019-july', 'actual_value' => 90, 'workflow_status' => 'submitted_to_head_office']);
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2019-august', 'actual_value' => 0, 'workflow_status' => 'approved_by_head_office']);

        $response = $this->actingAs($user)->get(route('reports.master', ['year' => 2019]));
        $response->assertOk()->assertSee('Provisional report');
        $row = collect($response->viewData('gridData'))->firstWhere('id', $objective->id);

        $this->assertFalse($row['months']['july']['reported']);
        $this->assertSame(0.0, $row['months']['july']['actual']);
        $this->assertSame('Not Reported', $row['months']['july']['status']);
        $this->assertTrue($row['months']['august']['reported']);
        $this->assertSame(0.0, $row['months']['august']['actual']);
        $this->assertSame('Below Target', $row['months']['august']['status']);
        $this->assertSame(0.0, $row['months']['august']['scorecard_achievement']);
        $this->assertSame('Not Planned (N/A)', $row['months']['september']['status']);
    }

    public function test_all_departments_counts_a_shared_kpi_from_the_lead_department_once(): void
    {
        [$user, $lead] = $this->reportUserAndDepartment('Lead Office', 'LEAD');
        $contributor = Department::create(['name' => 'Contributor', 'code' => 'CON', 'reporting_type' => 'monthly', 'is_active' => true]);
        $objective = $this->objective($lead, '2', 'Shared KPI', [$lead->id, $contributor->id]);

        $leadItem = $this->planItem($user, $lead, $objective, 2019);
        $contributorItem = $this->planItem($user, $contributor, $objective, 2019);
        PeriodicPerformance::create(['plan_item_id' => $leadItem->id, 'period' => '2019-july', 'actual_value' => 80, 'workflow_status' => 'approved_by_head_office']);
        PeriodicPerformance::create(['plan_item_id' => $contributorItem->id, 'period' => '2019-july', 'actual_value' => 70, 'workflow_status' => 'approved_by_head_office']);

        $response = $this->actingAs($user)->get(route('reports.master', ['year' => 2019, 'department_id' => 'all']))->assertOk();
        $row = collect($response->viewData('gridData'))->firstWhere('id', $objective->id);

        $this->assertSame(80.0, $row['months']['july']['actual']);
        $this->assertSame(80.0, $row['months']['july']['raw_achievement']);
        $this->assertSame('Below Target', $row['months']['july']['status']);
        $this->assertSame(10.0, (float) $row['weight']);
        $this->assertSame('Lead Office', $row['performers']);
    }

    public function test_master_report_respects_lower_is_better_and_caps_official_score(): void
    {
        [$user, $department] = $this->reportUserAndDepartment('Safety Office', 'SAF');
        $objective = $this->objective($department, '3', 'Reduce incidents');
        $objective->update(['goal_direction' => 'decrease']);
        $item = $this->planItem($user, $department, $objective, 2019);
        $item->update(['july_target' => 10]);
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2019-july', 'actual_value' => 5, 'workflow_status' => 'approved_by_head_office']);

        $response = $this->actingAs($user)->get(route('reports.master', ['year' => 2019]))->assertOk();
        $cell = collect($response->viewData('gridData'))->firstWhere('id', $objective->id)['months']['july'];

        $this->assertSame(200.0, $cell['raw_achievement']);
        $this->assertSame(100.0, $cell['scorecard_achievement']);
        $this->assertSame('Overachieved', $cell['status']);
    }

    public function test_master_report_applies_approved_manual_period_weights_to_parent_rollup(): void
    {
        [$user, $department] = $this->reportUserAndDepartment('Weighted Office', 'WGT');
        $parent = $this->objective($department, '4', 'Weighted parent');
        $first = $this->objective($department, '4.1', 'High priority activity');
        $second = $this->objective($department, '4.2', 'Low priority activity');
        $unplanned = $this->objective($department, '4.3', 'Unplanned activity');
        foreach ([$first, $second, $unplanned] as $child) $child->update(['parent_id' => $parent->id]);

        $plan = Plan::create(['title' => 'Weighted plan', 'type' => 'annual', 'year' => 2019, 'department_id' => $department->id, 'status' => 'approved', 'created_by' => $user->id]);
        $firstItem = PlanItem::create(['plan_id'=>$plan->id, 'strategic_objective_id'=>$first->id, 'july_target'=>100, 'weight'=>0.5]);
        $secondItem = PlanItem::create(['plan_id'=>$plan->id, 'strategic_objective_id'=>$second->id, 'july_target'=>100, 'weight'=>0.3]);
        $unplannedItem = PlanItem::create(['plan_id'=>$plan->id, 'strategic_objective_id'=>$unplanned->id, 'july_target'=>null, 'weight'=>0.2]);
        PeriodicPerformance::create(['plan_item_id'=>$firstItem->id, 'period'=>'2019-july', 'actual_value'=>100, 'workflow_status'=>'approved_by_head_office']);
        PeriodicPerformance::create(['plan_item_id'=>$secondItem->id, 'period'=>'2019-july', 'actual_value'=>0, 'workflow_status'=>'approved_by_head_office']);

        $distribution = PeriodWeightDistribution::create(['department_id'=>$department->id, 'year'=>2019, 'period'=>'july', 'mode'=>'manual', 'status'=>'approved', 'assigned_by'=>$user->id, 'assigned_at'=>now(), 'approved_by'=>$user->id, 'approved_at'=>now()]);
        $distribution->allocations()->createMany([
            ['plan_item_id'=>$firstItem->id, 'original_weight'=>0.5, 'effective_weight'=>0.8],
            ['plan_item_id'=>$secondItem->id, 'original_weight'=>0.3, 'effective_weight'=>0.2],
            ['plan_item_id'=>$unplannedItem->id, 'original_weight'=>0.2, 'effective_weight'=>null],
        ]);

        $response = $this->actingAs($user)->get(route('reports.master', ['year'=>2019]))->assertOk()->assertSee('Eff. wt 0.8');
        $rows = collect($response->viewData('gridData'));
        $this->assertSame(0.8, $rows->firstWhere('id', $first->id)['months']['july']['effective_weight']);
        $this->assertSame(0.2, $rows->firstWhere('id', $second->id)['months']['july']['effective_weight']);
        $this->assertSame(80.0, $rows->firstWhere('id', $parent->id)['months']['july']['scorecard_achievement']);
    }

    private function reportUserAndDepartment(string $name, string $code): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => $name, 'code' => $code, 'reporting_type' => 'monthly', 'is_active' => true]);
        $user = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $user->roles()->sync([Role::where('name', 'system_admin')->value('id')]);
        return [$user, $department];
    }

    private function objective(Department $lead, string $code, string $description, ?array $performers = null): StrategicObjective
    {
        return StrategicObjective::create([
            'code' => $code, 'description' => $description, 'weight' => 10, 'unit' => 'Percentage (%)',
            'department_id' => $lead->id, 'lead_department_id' => $lead->id,
            'company_consolidation_method' => 'lead_department', 'performers' => $performers ?? [$lead->id],
            'goal_direction' => 'increase', 'status' => 'approved', 'is_active' => true,
        ]);
    }

    private function planItem(User $user, Department $department, StrategicObjective $objective, int $year): PlanItem
    {
        $plan = Plan::create(['title' => $department->name . ' plan', 'type' => 'annual', 'year' => $year, 'department_id' => $department->id, 'status' => 'approved', 'created_by' => $user->id]);
        return PlanItem::create([
            'plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, 'annual_target' => 100,
            'july_target' => 100, 'august_target' => 100, 'weight' => 10, 'unit' => 'percent',
            'performers' => [$department->id],
        ]);
    }
}
