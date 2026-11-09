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

    public function test_planning_follows_included_descendants_but_not_manual_or_excluded_branches(): void
    {
        $parent=$this->item(10,3,null);
        $parent->strategic_objective_id=1000;
        $child=$this->item(11,1.5,null);
        $child->strategic_objective_id=1001;
        $child->strategicObjective->parent_id=1000;
        $grandchild=$this->item(12,1.5,0);
        $grandchild->strategic_objective_id=1002;
        $grandchild->strategicObjective->parent_id=1001;
        $items=collect([$parent,$child,$grandchild]);
        $resolver=new PeriodWeightNormalizer();
        $map=$resolver->planningForPeriod($items,'july');
        $this->assertTrue($map[10]);
        $this->assertTrue($map[11]);
        $this->assertTrue($map[12]);
        $grandchild->strategicObjective->include_in_parent_rollup=false;
        $this->assertFalse($resolver->planningForPeriod($items,'july')[10]);
        $grandchild->strategicObjective->include_in_parent_rollup=true;
        $parent->strategicObjective->summary_type='manual_override';
        $this->assertFalse($resolver->planningForPeriod($items,'july')[10]);
        $parent->strategicObjective->summary_type='sum';
        $grandchild->strategicObjective->is_reference_only=true;
        $this->assertFalse($resolver->planningForPeriod($items,'july')[10]);
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
