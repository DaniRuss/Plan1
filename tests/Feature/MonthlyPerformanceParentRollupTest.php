<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PeriodicPerformance;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\PlanItemAssignment;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyPerformanceParentRollupTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_parent_is_read_only_and_recalculated_from_children(): void
    {
        $role = Role::create(['name' => 'system_admin']);
        $department = Department::create([
            'name' => 'Infrastructure',
            'code' => 'INF',
            'reporting_type' => 'monthly',
        ]);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->roles()->attach($role);

        $base = [
            'status' => 'approved',
            'department_id' => $department->id,
            'performers' => [$department->id],
            'unit' => 'Percentage (%)',
            'goal_direction' => 'increase',
        ];
        $parent = StrategicObjective::create(array_merge($base, [
            'code' => '14',
            'description' => 'Improve infrastructure availability from 92% to 96%',
            'weight' => 3,
            'summary_type' => 'sum',
        ]));
        $childOne = StrategicObjective::create(array_merge($base, [
            'code' => '14.1', 'description' => 'Track quality', 'weight' => 1,
            'parent_id' => $parent->id, 'goal_direction' => 'decrease', 'kpi_code' => 'K2',
        ]));
        $childTwo = StrategicObjective::create(array_merge($base, [
            'code' => '14.2', 'description' => 'OCS quality', 'weight' => 2,
            'parent_id' => $parent->id,
        ]));
        $excludedChild = StrategicObjective::create(array_merge($base, [
            'code' => '14.3', 'description' => 'Reported separately', 'weight' => 1,
            'parent_id' => $parent->id, 'include_in_parent_rollup' => false,
        ]));

        $plan = Plan::create([
            'title' => 'Infrastructure 2019', 'type' => 'annual', 'year' => 2019,
            'department_id' => $department->id, 'status' => 'approved', 'created_by' => $user->id,
        ]);
        $parentItem = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $parent->id]);
        $childOneItem = PlanItem::create([
            'plan_id' => $plan->id, 'strategic_objective_id' => $childOne->id, 'july_target' => 50,
        ]);
        $childTwoItem = PlanItem::create([
            'plan_id' => $plan->id, 'strategic_objective_id' => $childTwo->id, 'july_target' => 50,
        ]);
        $excludedChildItem = PlanItem::create([
            'plan_id' => $plan->id, 'strategic_objective_id' => $excludedChild->id, 'july_target' => 100,
        ]);
        foreach ([$parentItem, $childOneItem, $childTwoItem, $excludedChildItem] as $item) {
            PlanItemAssignment::create([
                'plan_item_id' => $item->id,
                'performer_id' => $department->id,
                'status' => 'approved_for_experts',
            ]);
        }

        $this->actingAs($user)->get(route('performance.edit', [$plan, 'july']))
            ->assertOk()
            ->assertSee('Calculated from sub-objectives');

        $this->actingAs($user)->put(route('performance.update', [$plan, 'july']), [
            'performances' => [
                $parentItem->id => 999,
                $childOneItem->id => 40,
                $childTwoItem->id => 50,
                $excludedChildItem->id => 100,
            ],
        ])->assertRedirect();

        $this->assertSame(90.0, (float) PeriodicPerformance::where([
            'plan_item_id' => $parentItem->id,
            'period' => 'july',
        ])->firstOrFail()->actual_value);

        $this->actingAs($user)->get(route('monthly.entry.form', [
            'year' => 2019,
            'month' => 'july',
            'department_id' => $department->id,
        ]))->assertOk()->assertSee('Calculated from sub-objectives');

        $this->actingAs($user)->post(route('monthly.entry.store', [
            'year' => 2019,
            'month' => 'july',
        ]), [
            'department_id' => $department->id,
            'entries' => [
                $parentItem->id => ['actual_value' => 999],
                $childOneItem->id => ['actual_value' => 20],
                $childTwoItem->id => ['actual_value' => 30],
                $excludedChildItem->id => ['actual_value' => 100],
            ],
        ])->assertRedirect(route('monthly.dashboard'));

        $this->assertSame(50.0, (float) PeriodicPerformance::where([
            'plan_item_id' => $parentItem->id,
            'period' => '2019-july',
        ])->firstOrFail()->actual_value);
        $this->assertSame(100.0, (float) PeriodicPerformance::where([
            'plan_item_id' => $excludedChildItem->id,
            'period' => '2019-july',
        ])->firstOrFail()->actual_value);

        $this->actingAs($user)->get(route('monthly.report', [
            'year' => 2019,
            'month' => 'july',
            'department_id' => $department->id,
        ]))->assertOk()
            ->assertSee('Weighted child roll-up')
            ->assertSee('Lower is better')
            ->assertSee('Higher is better')
            ->assertSee('Raw: 250.0%', false)
            ->assertSee('60.0%');

        $this->actingAs($user)->get(route('reports.kpi-dashboard', [
            'year' => 2019,
            'period' => 'q1',
        ]))->assertOk()
            ->assertSee('Track quality')
            ->assertSee('20 Percentage (%)', false)
            ->assertSee('250%');
    }
}
