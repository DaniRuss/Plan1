<?php

namespace Tests\Unit;

use App\Models\StrategicObjective;
use App\Services\FidelityCalculator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use stdClass;

class FidelityCalculatorSelectedChildrenTest extends TestCase
{
    public function test_weighted_rollup_ignores_children_excluded_from_parent_calculation(): void
    {
        $parent = new StrategicObjective(['summary_type' => 'weighted']);
        $included = new StrategicObjective([
            'weight' => 40,
            'include_in_parent_rollup' => true,
        ]);
        $included->id = 11;
        $excluded = new StrategicObjective([
            'weight' => 60,
            'include_in_parent_rollup' => false,
        ]);
        $excluded->id = 12;
        $parent->setRelation('children', new EloquentCollection([$included, $excluded]));

        $includedEntry = new stdClass();
        $includedEntry->percentage = 50;
        $includedEntry->actual_value = 20;
        $excludedEntry = new stdClass();
        $excludedEntry->percentage = 100;
        $excludedEntry->actual_value = 60;

        $entries = new Collection([
            11 => $includedEntry,
            12 => $excludedEntry,
        ]);

        $this->assertSame(20.0, FidelityCalculator::rollup($parent, $entries, 'weighted'));
    }

    public function test_sum_and_average_use_only_the_entries_selected_by_the_caller(): void
    {
        $entries = new Collection([
            (object) ['actual_value' => 10],
            (object) ['actual_value' => 30],
        ]);

        $this->assertSame(40, FidelityCalculator::rollup(new StrategicObjective(), $entries, 'sum'));
        $this->assertSame(20, FidelityCalculator::rollup(new StrategicObjective(), $entries, 'average'));
    }
}
