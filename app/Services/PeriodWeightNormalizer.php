<?php

namespace App\Services;

use App\Models\PlanItem;
use App\Models\PeriodWeightDistribution;
use App\Models\Setting;
use Illuminate\Support\Collection;

class PeriodWeightNormalizer
{
    /**
     * Return effective weights keyed by plan item ID. A null value means the
     * item is not planned/applicable in the requested period.
     */
    public function forPeriod(Collection $items, string $period): array
    {
        if (Setting::get('period_weight_distribution_mode', 'manual') === 'manual' && $this->usesManualMode($items, $period)) {
            return $this->manualForPeriod($items, $period);
        }

        return $this->proportionalForPeriod($items, $period);
    }

    private function usesManualMode(Collection $items, string $period): bool
    {
        $plan = $items->first()?->plan;
        if (!$plan) return true;
        $hasApprovedAllocation = PeriodWeightDistribution::where([
            'department_id'=>$plan->department_id, 'year'=>$plan->year,
            'period'=>strtolower($period), 'status'=>'approved',
        ])->exists();
        if ($hasApprovedAllocation) return true;
        return (int)$plan->year >= (int)Setting::get('manual_period_weight_effective_from_year', now()->year);
    }

    /** Preserve the original strategy for restricted configuration rollback. */
    public function proportionalForPeriod(Collection $items, string $period): array
    {
        $targetColumn = $this->targetColumn($period);
        $weights = [];

        $scorable = $items->filter(fn (PlanItem $item) => $item->strategicObjective
            && !$item->strategicObjective->is_reference_only
            && !$item->strategicObjective->exclude_from_overall_score);

        foreach ($scorable->groupBy(fn (PlanItem $item) => $item->strategicObjective->parent_id ?? 'root') as $siblings) {
            $baseWeights = $siblings->mapWithKeys(fn (PlanItem $item) => [
                $item->id => $this->baseWeight($item),
            ]);
            $groupWeight = (float) $baseWeights->sum();
            $planned = $siblings->filter(fn (PlanItem $item) => $this->isPlanned($item, $targetColumn));
            $plannedWeight = (float) $planned->sum(fn (PlanItem $item) => $baseWeights[$item->id]);

            foreach ($siblings as $item) {
                if (!$planned->contains('id', $item->id) || $plannedWeight <= 0) {
                    $weights[$item->id] = null;
                    continue;
                }

                $weights[$item->id] = ($baseWeights[$item->id] / $plannedWeight) * $groupWeight;
            }
        }

        return $weights;
    }

    public function manualForPeriod(Collection $items, string $period): array
    {
        $targetColumn = $this->targetColumn($period);
        $weights = [];
        $scorable = $items->filter(fn (PlanItem $item) => $item->strategicObjective
            && !$item->strategicObjective->is_reference_only
            && !$item->strategicObjective->exclude_from_overall_score);
        $plan = $items->first()?->plan;
        $distribution = $plan ? PeriodWeightDistribution::with('allocations')
            ->where(['department_id'=>$plan->department_id,'year'=>$plan->year,'period'=>strtolower($period),'status'=>'approved'])->first() : null;
        $approved = $distribution?->allocations?->keyBy('plan_item_id') ?? collect();

        // Approved allocations are historical records: do not reinterpret them after plan edits.
        if ($distribution) {
            foreach ($scorable as $item) {
                $value = $approved->get($item->id)?->effective_weight;
                $weights[$item->id] = $value === null ? null : (float) $value;
            }
            return $weights;
        }
        $planning = $this->planningForPeriod($plan ? $plan->items->merge($items)->unique('id') : $items, $period);
        foreach ($scorable->groupBy(fn (PlanItem $item) => $item->strategicObjective->parent_id ?? 'root') as $siblings) {
            $groupWeight = (float) $siblings->sum(fn (PlanItem $item) => $this->baseWeight($item));
            $planned = $siblings->filter(fn (PlanItem $item) => $planning[$item->id] ?? false);
            foreach ($siblings as $item) $weights[$item->id] = null;
            if ($planned->isEmpty()) continue;
            if ($planned->count() === $siblings->count()) {
                foreach ($planned as $item) $weights[$item->id] = $this->baseWeight($item);
                continue;
            }
            if ($planned->count() === 1) {
                $weights[$planned->first()->id] = $groupWeight;
                continue;
            }
            foreach ($planned as $item) {
                $allocation = $approved->get($item->id);
                $weights[$item->id] = $allocation && $allocation->effective_weight !== null
                    ? (float) $allocation->effective_weight : null;
            }
        }
        return $weights;
    }

    /** A calculated branch is applicable when any included descendant is planned. */
    public function planningForPeriod(Collection $items, string $period): array
    {
        $column = $this->targetColumn($period);
        $eligible = $items->filter(fn (PlanItem $item) => $item->strategicObjective
            && !$item->strategicObjective->is_reference_only
            && !$item->strategicObjective->exclude_from_overall_score);
        $children = $eligible->groupBy(fn ($item) => $item->strategicObjective->parent_id ?? 'root');
        $planned = []; $visiting = [];
        $resolve = function ($item) use (&$resolve, &$planned, &$visiting, $children, $column): bool {
            if (array_key_exists($item->id, $planned)) return $planned[$item->id];
            if (isset($visiting[$item->id])) return false;
            $visiting[$item->id] = true;
            $value = $item->getAttribute($column) !== null;
            if ($item->strategicObjective->summary_type !== 'manual_override') {
                foreach ($children->get($item->strategic_objective_id, collect()) as $child) {
                    if ($child->strategicObjective->include_in_parent_rollup !== false && $resolve($child)) $value = true;
                }
            }
            unset($visiting[$item->id]);
            return $planned[$item->id] = $value;
        };
        foreach ($eligible as $item) $resolve($item);
        return $planned;
    }

    public function isPlannedForPeriod(PlanItem $item, string $period): bool
    {
        return $this->isPlanned($item, $this->targetColumn($period));
    }

    public function baseWeight(PlanItem $item): float
    {
        return (float) ($item->weight > 0 ? $item->weight : ($item->strategicObjective?->weight ?? 0));
    }

    private function isPlanned(PlanItem $item, string $targetColumn): bool
    {
        // Null means not planned. Numeric zero remains an explicit plan and is
        // important for decreasing indicators such as incidents or defects.
        return $item->getAttribute($targetColumn) !== null;
    }

    private function targetColumn(string $period): string
    {
        $normalized = strtolower($period);
        return match ($normalized) {
            'annual' => 'annual_target',
            'q1', 'q2', 'q3', 'q4' => $normalized . '_target',
            default => $normalized . '_target',
        };
    }
}
