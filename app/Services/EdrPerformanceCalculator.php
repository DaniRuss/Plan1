<?php

namespace App\Services;

use App\Models\PlanItem;
use Illuminate\Support\Collection;

class EdrPerformanceCalculator
{
    public function __construct(private readonly PerformanceLevel $performanceLevel)
    {
    }

    /**
     * Apply the hierarchy-aware calculation used by the collected EDR workbook.
     * Leaf score = capped achievement factor x effective period weight.
     * Main-objective score = sum of its direct sub-objectives' scores.
     */
    public function apply(Collection $items, array $rows): array
    {
        $rowsByItem = collect($rows)->keyBy('_item_id');
        $includedIds = $rowsByItem->keys()->map(fn ($id) => (int) $id)->all();
        $children = [];

        foreach ($items as $item) {
            if (!$rowsByItem->has($item->id)) {
                continue;
            }

            $parentItem = $this->includedParentItem($item, $items, $includedIds);
            if ($parentItem) {
                $children[$parentItem->id][] = $item->id;
            }
        }

        $calculated = [];
        $visiting = [];
        $calculate = function (int $itemId) use (&$calculate, &$calculated, &$visiting, $rowsByItem, $children): array {
            if (isset($calculated[$itemId])) {
                return $calculated[$itemId];
            }
            if (isset($visiting[$itemId])) {
                return ['score' => 0.0, 'raw_score' => 0.0, 'weight' => 0.0];
            }

            $visiting[$itemId] = true;
            $row = $rowsByItem->get($itemId);
            $childIds = $children[$itemId] ?? [];

            if ($childIds !== []) {
                $childResults = collect($childIds)->map(fn ($childId) => $calculate((int) $childId));
                $score = (float) $childResults->sum('score');
                $rawScore = (float) $childResults->sum('raw_score');
                $possibleWeight = (float) $childResults->sum('weight');
            } else {
                $possibleWeight = $row['is_planned'] ? max(0.0, (float) $row['weight']) : 0.0;
                $rawFactor = $row['is_planned']
                    ? max(0.0, (float) ($row['raw_achievement'] ?? $row['quarter_comp']) / 100)
                    : 0.0;
                $rawScore = $rawFactor * $possibleWeight;
                $score = min(1.0, $rawFactor) * $possibleWeight;
            }

            unset($visiting[$itemId]);
            return $calculated[$itemId] = ['score' => $score, 'raw_score' => $rawScore, 'weight' => $possibleWeight];
        };

        $result = [];
        foreach ($rows as $row) {
            $itemId = (int) $row['_item_id'];
            $score = $calculate($itemId);
            $parentItemId = null;
            foreach ($children as $candidateParentId => $childIds) {
                if (in_array($itemId, $childIds, true)) {
                    $parentItemId = (int) $candidateParentId;
                    break;
                }
            }
            $row['_parent_item_id'] = $parentItemId;
            $row['has_children'] = !empty($children[$itemId]);
            if ($row['has_children']) {
                $row['is_planned'] = $score['weight'] > 0;
                $rawAchievement = $score['weight'] > 0 ? ($score['raw_score'] / $score['weight']) * 100 : null;
                $scorecardAchievement = $score['weight'] > 0 ? ($score['score'] / $score['weight']) * 100 : null;
                $row['raw_achievement'] = is_null($rawAchievement) ? null : round($rawAchievement, 2);
                $row['scorecard_achievement'] = is_null($scorecardAchievement) ? null : round($scorecardAchievement, 2);
                $row['quarter_comp'] = $row['scorecard_achievement'] ?? 0;
                $row['performance_status'] = $this->performanceStatus($rawAchievement);
            } elseif (!$row['is_planned']) {
                $row['raw_achievement'] = null;
                $row['scorecard_achievement'] = null;
                $row['performance_status'] = $this->performanceStatus(null);
            }
            $row['score_factor'] = $score['weight'] > 0 ? min(1, $score['score'] / $score['weight']) : 0;
            $row['weighted_score'] = round($score['score'], 4);
            $row['score_weight'] = round($score['weight'], 4);
            $row['performance_level'] = $this->performanceLevel->for(
                $row['is_planned'] ? (float) ($row['scorecard_achievement'] ?? $row['quarter_comp']) : null
            );
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Attainment formula used in the reference EDR workbook. The baseline is
     * descriptive; the performance column is compared directly with plan.
     */
    public function achievement(float $actual, float $plan, string $direction = 'increase'): float
    {
        return min(100, $this->rawAchievement($actual, $plan, $direction));
    }

    public function rawAchievement(float $actual, float $plan, string $direction = 'increase'): float
    {
        if ($direction === 'decrease') {
            return $actual > 0 ? round(max(0, ($plan / $actual) * 100), 2) : 100.0;
        }

        if ($plan == 0.0) {
            return $actual >= 0 ? 100.0 : 0.0;
        }

        return round(max(0, ($actual / $plan) * 100), 2);
    }

    public function performanceStatus(?float $rawAchievement): string
    {
        if (is_null($rawAchievement)) return 'Not Planned (N/A)';
        if ($rawAchievement > 100) return 'Overachieved';
        if ($rawAchievement == 100.0) return 'Achieved';
        return 'Below Target';
    }

    public function summary(array $rows): array
    {
        $collection = collect($rows);
        $rootRows = $collection->filter(fn ($row) => empty($row['_parent_item_id']));
        $totalScore = (float) $rootRows->sum('weighted_score');
        $totalWeight = (float) $rootRows->sum('score_weight');
        $achievement = $totalWeight > 0 ? ($totalScore / $totalWeight) * 100 : null;

        return [
            'weighted_score' => round($totalScore, 4),
            'total_weight' => round($totalWeight, 4),
            'achievement' => is_null($achievement) ? null : round($achievement, 2),
            'performance_level' => $this->performanceLevel->for($achievement),
        ];
    }

    private function includedParentItem(PlanItem $item, Collection $items, array $includedIds): ?PlanItem
    {
        $parentObjectiveId = $item->strategicObjective?->parent_id;
        if (!$parentObjectiveId) {
            return null;
        }

        return $items->first(fn (PlanItem $candidate) =>
            in_array((int) $candidate->id, $includedIds, true)
            && (int) $candidate->strategic_objective_id === (int) $parentObjectiveId
        );
    }
}
