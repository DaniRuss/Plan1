<?php

namespace Tests\Feature;

use App\Http\Controllers\WeeklyPerformanceController;
use App\Models\Department;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Models\WeeklyPerformance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyNestedRollupTest extends TestCase
{
    use RefreshDatabase;

    public function test_objective_tree_uses_numeric_order_at_each_level(): void
    {
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG']);
        $base = ['department_id' => $department->id, 'unit' => 'Tons', 'weight' => 1, 'status' => 'approved'];
        $roots = [];
        foreach (['18', '2', '1'] as $code) {
            $roots[$code] = StrategicObjective::create($base + ['code' => $code, 'description' => 'Objective '.$code]);
        }
        foreach (['1.10', '1.2', '1.1'] as $code) {
            StrategicObjective::create($base + ['code' => $code, 'description' => 'Child '.$code, 'parent_id' => $roots['1']->id]);
        }
        $child = StrategicObjective::where('code', '1.2')->firstOrFail();
        foreach (['1.2.10', '1.2.2', '1.2.1'] as $code) {
            StrategicObjective::create($base + ['code' => $code, 'description' => 'Grandchild '.$code, 'parent_id' => $child->id]);
        }
        $tree = StrategicObjective::whereNull('parent_id')->with(['children' => function ($query) {
            $query->where('status', 'approved')->with(['children' => fn ($children) => $children->where('status', 'approved')->orderByNaturalCode()])->orderByNaturalCode();
        }])->orderByNaturalCode()->get();
        $this->assertSame(['1', '2', '18'], $tree->pluck('code')->all());
        $this->assertSame(['1.1', '1.2', '1.10'], $tree->first()->children->pluck('code')->all());
        $this->assertSame(['1.2.1', '1.2.2', '1.2.10'], $tree->first()->children[1]->children->pluck('code')->all());
    }

    public function test_nested_totals_are_calculated_by_depth_and_isolated_to_the_plan(): void
    {
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG']);
        $user = User::factory()->create(['department_id' => $department->id]);
        $base = ['department_id' => $department->id, 'unit' => 'Tons', 'weight' => 1];
        // Create the subtotal first: database IDs must not determine roll-up order.
        $import = StrategicObjective::create($base + ['code' => '1.1', 'description' => 'Import container cargo', 'summary_type' => 'sum']);
        $main = StrategicObjective::create($base + ['code' => '1', 'description' => 'Transport cargo', 'summary_type' => 'sum']);
        $import->update(['parent_id' => $main->id]);
        $multimodal = StrategicObjective::create($base + ['code' => '1.1.1', 'description' => 'Multimodal', 'parent_id' => $import->id]);
        $unimodal = StrategicObjective::create($base + ['code' => '1.1.2', 'description' => 'Unimodal', 'parent_id' => $import->id]);
        $export = StrategicObjective::create($base + ['code' => '1.2', 'description' => 'Export', 'parent_id' => $main->id]);
        $plan = Plan::create(['title' => 'Cargo', 'type' => 'annual', 'year' => 2019, 'department_id' => $department->id, 'status' => 'approved', 'created_by' => $user->id]);
        $otherPlan = Plan::create(['title' => 'Other', 'type' => 'annual', 'year' => 2018, 'department_id' => $department->id, 'status' => 'approved', 'created_by' => $user->id]);
        $items = [];
        foreach ([$main, $import, $multimodal, $unimodal, $export] as $objective) {
            $items[$objective->id] = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id]);
        }
        $period = ['year' => 2019, 'month' => 'september', 'week_number' => 3];
        foreach ([[$multimodal, 36.75, 28.16], [$unimodal, 10.19, 7.24], [$export, 14.38, 12.28], [$import, 100, 999]] as [$objective, $planned, $actual]) {
            WeeklyPerformance::create($period + ['plan_item_id' => $items[$objective->id]->id, 'planned_value' => $planned, 'actual_value' => $actual, 'percentage' => 0]);
        }
        $foreignItem = PlanItem::create(['plan_id' => $otherPlan->id, 'strategic_objective_id' => $multimodal->id]);
        WeeklyPerformance::create($period + ['plan_item_id' => $foreignItem->id, 'planned_value' => 1000, 'actual_value' => 1000, 'percentage' => 100]);
        $method = new \ReflectionMethod(WeeklyPerformanceController::class, 'performParentRollups');
        $method->invoke(app(WeeklyPerformanceController::class), 2019, 'september', 3, 'draft', $user->id, $plan->id);
        $subtotal = WeeklyPerformance::where('plan_item_id', $items[$import->id]->id)->firstOrFail();
        $total = WeeklyPerformance::where('plan_item_id', $items[$main->id]->id)->firstOrFail();
        $this->assertEqualsWithDelta(35.40, $subtotal->actual_value, 0.001);
        $this->assertEqualsWithDelta(46.94, $subtotal->planned_value, 0.001);
        $this->assertEqualsWithDelta(47.68, $total->actual_value, 0.001);
        $this->assertEqualsWithDelta(61.32, $total->planned_value, 0.001);
        $this->assertEqualsWithDelta(35.40 / 46.94 * 100, $subtotal->percentage, 0.01);
    }
}
