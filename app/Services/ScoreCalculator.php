<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Department;
use App\Models\PlanItem;
use App\Models\PeriodicPerformance;

class ScoreCalculator
{
    /**
     * Calculate progress for a specific objective (0 - 100).
     */
    public function calculateObjectiveProgress(PlanItem $item, $period)
    {
        // 1. Core Logic check: Does this objective have tasks?
        if ($item->tasks()->exists()) {
            $avgCompletion = $item->tasks()
                ->where('status', '!=', 'cancelled')
                ->avg('completion_percentage');

            return round($avgCompletion ?? 0, 2);
        }

        return $this->calculateBaselineProgress($item, $period);
    }

    /**
     * Calculate the achievement shown in target-versus-actual reports.
     * Unlike objective progress, this intentionally ignores task completion
     * because the report columns explicitly compare the reported actual with
     * the selected period target.
     */
    public function calculateTargetAchievement(PlanItem $item, $period): float
    {
        return min(100, $this->calculateRawTargetAchievement($item, $period));
    }

    /**
     * Period achievement before the official scorecard ceiling is applied.
     * Weekly actuals are aggregated by PlanItem first, so no individual week
     * is capped before this calculation.
     */
    public function calculateRawTargetAchievement(PlanItem $item, $period): float
    {
        $actual = (float) $item->getActualValueForPeriod($period);
        $target = (float) ($this->getTargetForPeriod($item, $period) ?? 0);
        $direction = $item->strategicObjective?->goal_direction ?? 'increase';

        if ($direction === 'decrease') {
            return $actual <= 0 ? 100.0 : round(max(0, ($target / $actual) * 100), 2);
        }

        if ($target == 0.0) {
            return $actual >= 0 ? 100.0 : 0.0;
        }

        return round(max(0, ($actual / $target) * 100), 2);
    }

    public function performanceStatus(?float $rawAchievement): string
    {
        if (is_null($rawAchievement)) return 'Not Planned (N/A)';
        if ($rawAchievement > 100) return 'Overachieved';
        if ($rawAchievement == 100.0) return 'Achieved';
        return 'Below Target';
    }

    private function calculateBaselineProgress(PlanItem $item, $period): float
    {
        $so = $item->strategicObjective;
        $actual = $item->getActualValueForPeriod($period);
        $target = $this->getTargetForPeriod($item, $period);
        $baseline = $item->baseline_value ?? 0;
        $direction = $so->goal_direction ?? 'increase';

        if ($direction === 'decrease') {
            // Lower is Better
            if ($baseline <= $target) {
                // If baseline is already better or equal to target, just simple ratio if target > 0
                return $actual <= $target ? 100 : ($actual > 0 ? round(min(100, ($target / $actual) * 100), 2) : 0);
            }
            
            // Formula: (Baseline - Actual) / (Baseline - Target)
            $denominator = $baseline - $target;
            $progress = ($baseline - $actual) / $denominator;
        } else {
            // Higher is Better (Increase)
            if ($target <= $baseline) {
                // simple ratio if target > 0
                return $actual >= $target ? 100 : ($target > 0 ? round(min(100, ($actual / $target) * 100), 2) : 0);
            }
            
            // Formula: (Actual - Baseline) / (Target - Baseline)
            $denominator = $target - $baseline;
            $progress = ($actual - $baseline) / $denominator;
        }

        // Clamp between 0 and 1, then multiply by 100
        $clamped = max(0, min(1, $progress));

        return round($clamped * 100, 2);
    }

    /**
     * Calculate Department Aggregate Score for a period.
     * Returns the "Total Average" (0-100).
     */
    public function calculateDepartmentAverage(Department $department, Plan $plan, $period)
    {
        $items = $plan->items()
            ->with(['strategicObjective', 'performances', 'weeklyPerformances'])
            ->whereHas('strategicObjective', function ($q) {
                $q->where('is_reference_only', false)
                    ->where('exclude_from_overall_score', false);
            })
            ->get();

        // Assuming the plan belongs to a department, just take all items in the plan?
        // Wait, the Scoring System description implies a Company-Level Score aggregation 
        // across multiple departments.
        // A "Plan" in this system seems to be Per-Department (Annual Plan 2018 - Digital Office).
        // So `calculateDepartmentAverage` essentially calculates the score for ONE Plan.

        if ($items->isEmpty())
            return 0;

        $effectiveWeights = app(PeriodWeightNormalizer::class)->forPeriod($items, (string) $period);
        $parentObjectiveIds = $items->pluck('strategicObjective.parent_id')
            ->filter()->map(fn ($id) => (int) $id)->unique();

        $totalWeightedScore = 0;
        $totalPossibleWeight = 0;

        foreach ($items as $item) {
            if ($parentObjectiveIds->contains((int) $item->strategic_objective_id)) {
                continue;
            }

            $progress = $this->calculateObjectiveProgress($item, $period);
            
            $weight = $effectiveWeights[$item->id] ?? null;
            
            // If an item has no weight, it shouldn't contribute to the average.
            if (is_null($weight) || $weight <= 0) {
                continue;
            }

            // Percentage can be > 100, but weighted score shouldn't exceed weight
            $itemScore = ($progress / 100) * $weight;
            $cappedItemScore = min($itemScore, $weight);
            
            $totalWeightedScore += $cappedItemScore;
            $totalPossibleWeight += $weight;
        }

        return $totalPossibleWeight > 0 ? round(($totalWeightedScore / $totalPossibleWeight) * 100, 2) : 0;
    }

    /**
     * Helper to get target for a specific period (month or quarter).
     */
    public function getTargetForPeriod(PlanItem $item, $period): ?float
    {
        return $item->getPlannedTargetForPeriod($period);
    }
}
