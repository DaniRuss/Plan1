<?php

namespace Tests\Unit;

use App\Models\PlanItem;
use App\Models\StrategicObjective;
use App\Services\EdrPerformanceCalculator;
use App\Services\PerformanceLevel;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class EdrPerformanceCalculatorTest extends TestCase
{
    public function test_reference_attainment_compares_performance_directly_with_plan(): void
    {
        $calculator = new EdrPerformanceCalculator(new PerformanceLevel());

        $this->assertSame(95.87, $calculator->achievement(181.31744, 189.12602739726));
        $this->assertSame(100.0, $calculator->achievement(153.08, 147));
        $this->assertSame(104.14, $calculator->rawAchievement(153.08, 147));
        $this->assertSame('Overachieved', $calculator->performanceStatus(104.14));
        $this->assertSame('Not Planned (N/A)', $calculator->performanceStatus(null));
        $this->assertSame(100.0, $calculator->rawAchievement(0, 0));
        $this->assertSame(85.59, $calculator->achievement(3.505, 3, 'decrease'));
        $this->assertSame(100.0, $calculator->achievement(0.15665, 0.25, 'decrease'));
    }

    public function test_leaf_scores_roll_up_once_without_double_counting_parent_rows(): void
    {
        $rootObjective = $this->objective(1, '1', null);
        $firstObjective = $this->objective(2, '1.1', 1);
        $secondObjective = $this->objective(3, '1.2', 1);

        $root = $this->item(10, 1, $rootObjective);
        $first = $this->item(11, 2, $firstObjective);
        $second = $this->item(12, 3, $secondObjective);
        $items = new Collection([$root, $first, $second]);

        $rows = [
            $this->row($root, 100, 10),
            $this->row($first, 80, 6),
            $this->row($second, 120, 4),
        ];

        $calculator = new EdrPerformanceCalculator(new PerformanceLevel());
        $calculated = $calculator->apply($items, $rows);
        $summary = $calculator->summary($calculated);

        // 80% x 6 + capped 100% x 4 = 8.8. The parent's own 10 is not added again.
        $this->assertSame(8.8, $summary['weighted_score']);
        $this->assertSame(88.0, $summary['achievement']);
        $this->assertSame(8.8, $calculated[0]['weighted_score']);
        $this->assertSame(96.0, $calculated[0]['raw_achievement']);
        $this->assertSame(88.0, $calculated[0]['scorecard_achievement']);
        $this->assertSame('Below Target', $calculated[0]['performance_status']);
        $this->assertTrue($calculated[0]['is_planned']);
        $this->assertSame('satisfactory', $summary['performance_level']['key']);
    }

    public function test_unplanned_rows_never_receive_an_achievement_status(): void
    {
        $objective = $this->objective(1, '1', null);
        $item = $this->item(10, 1, $objective);
        $row = $this->row($item, 100, 10);
        $row['is_planned'] = false;

        $calculated = (new EdrPerformanceCalculator(new PerformanceLevel()))
            ->apply(new Collection([$item]), [$row]);

        $this->assertNull($calculated[0]['raw_achievement']);
        $this->assertNull($calculated[0]['scorecard_achievement']);
        $this->assertSame('Not Planned (N/A)', $calculated[0]['performance_status']);
    }

    public function test_monthly_weighted_result_cannot_exceed_one_hundred_percent(): void
    {
        $objective = $this->objective(1, '1', null);
        $item = $this->item(10, 1, $objective);
        $calculator = new EdrPerformanceCalculator(new PerformanceLevel());

        $calculated = $calculator->apply(
            new Collection([$item]),
            [$this->row($item, 150, 10)]
        );
        $summary = $calculator->summary($calculated);

        $this->assertSame(10.0, $summary['weighted_score']);
        $this->assertSame(100.0, $summary['achievement']);
    }

    private function objective(int $id, string $code, ?int $parentId): StrategicObjective
    {
        $objective = new StrategicObjective(['code' => $code, 'description' => $code]);
        $objective->id = $id;
        $objective->parent_id = $parentId;
        return $objective;
    }

    private function item(int $id, int $objectiveId, StrategicObjective $objective): PlanItem
    {
        $item = new PlanItem(['strategic_objective_id' => $objectiveId]);
        $item->id = $id;
        $item->setRelation('strategicObjective', $objective);
        return $item;
    }

    private function row(PlanItem $item, float $achievement, float $weight): array
    {
        return [
            '_item_id' => $item->id,
            'is_planned' => true,
            'weight' => $weight,
            'quarter_comp' => $achievement,
        ];
    }
}
