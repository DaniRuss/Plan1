<?php

namespace Tests\Unit;

use App\Models\PlanItem;
use App\Models\StrategicObjective;
use App\Services\PeriodWeightNormalizer;
use PHPUnit\Framework\TestCase;

class PeriodWeightNormalizerTest extends TestCase
{
    public function test_it_proportionally_moves_unplanned_weight_to_planned_siblings(): void
    {
        $items = collect([
            $this->item(1, 0.5, 10),
            $this->item(2, 0.3, null),
            $this->item(3, 0.1, 0),
            $this->item(4, 0.1, null),
        ]);

        $weights = (new PeriodWeightNormalizer())->proportionalForPeriod($items, 'july');

        $this->assertEqualsWithDelta(0.833333, $weights[1], 0.00001);
        $this->assertNull($weights[2]);
        $this->assertEqualsWithDelta(0.166667, $weights[3], 0.00001);
        $this->assertNull($weights[4]);
        $this->assertEqualsWithDelta(1.0, $weights[1] + $weights[3], 0.00001);
    }

    private function item(int $id, float $weight, mixed $target): PlanItem
    {
        $objective = new StrategicObjective([
            'parent_id' => 100,
            'weight' => $weight,
            'is_reference_only' => false,
            'exclude_from_overall_score' => false,
        ]);
        $item = new PlanItem(['weight' => $weight, 'july_target' => $target]);
        $item->id = $id;
        $item->setRelation('strategicObjective', $objective);
        return $item;
    }
}
