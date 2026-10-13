<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\StrategicObjective;
use App\Models\WeeklyPerformance;
use App\Services\ScoreCalculator;
use PHPUnit\Framework\TestCase;

class WeeklyToMonthlyAggregationTest extends TestCase
{
    public function test_flow_targets_sum_weekly_actuals(): void
    {
        $item = $this->item('sum', 'distribute', 10, [
            $this->week(1, 2, 1, 50), $this->week(2, 2, 2, 100),
        ]);

        $this->assertSame(3.0, $item->getActualValueForPeriod('july'));
    }

    public function test_repeated_targets_average_weekly_achievement_before_monthly_weighting(): void
    {
        $item = $this->item('sum', 'repeat', 100, [
            $this->week(1, 100, 125, 125),
            $this->week(2, 100, 80, 80),
            $this->week(3, 100, 90, 90),
            $this->week(4, 100, 105, 105),
        ]);

        // Raw weekly percentages average to 100%. Capping 125 and 105 first
        // would incorrectly produce 92.5%.
        $this->assertSame(100.0, $item->getActualValueForPeriod('july'));
        $calculator = new ScoreCalculator();
        $this->assertSame(100.0, $calculator->calculateRawTargetAchievement($item, 'july'));
        $this->assertSame(100.0, $calculator->calculateTargetAchievement($item, 'july'));
    }

    public function test_checkpoint_targets_use_the_final_reported_week(): void
    {
        $item = $this->item('checkpoint', 'repeat', 90, [
            $this->week(1, 90, 80, 88.89), $this->week(2, 90, 87, 96.67),
        ]);

        $this->assertSame(87.0, $item->getActualValueForPeriod('july'));
    }

    private function item(string $distribution, string $weeklyMode, float $target, array $weeks): PlanItem
    {
        $department = new Department(['reporting_type' => 'weekly']);
        $plan = new Plan(['year' => 2019]);
        $plan->setRelation('department', $department);
        $objective = new StrategicObjective([
            'target_distribution' => $distribution,
            'weekly_plan_mode' => $weeklyMode,
        ]);
        $item = new PlanItem(['july_target' => $target]);
        $item->setRelation('plan', $plan);
        $item->setRelation('strategicObjective', $objective);
        $item->setRelation('weeklyPerformances', collect($weeks));
        return $item;
    }

    private function week(int $week, float $plan, float $actual, float $percentage): WeeklyPerformance
    {
        return new WeeklyPerformance([
            'month' => 'july', 'week_number' => $week,
            'planned_value' => $plan, 'actual_value' => $actual, 'percentage' => $percentage,
            'workflow_status' => 'approved_by_head_office',
        ]);
    }
}
