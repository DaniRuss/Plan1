<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Collection;

class ReportCompleteness
{
    private const OFFICIAL = ['approved_by_head_office'];

    public function forPlan(Plan $plan, string $period): array
    {
        $period = strtolower($period);
        $parts = explode(':', $period);
        $months = $this->monthsForPeriod($period);
        $isWeeklyPeriod = ($parts[0] ?? null) === 'week';
        $itemObjectiveIds = $plan->items->pluck('strategic_objective_id');
        $reportableItems = $plan->items->filter(fn ($item) => $item->strategicObjective
            && ($item->strategicObjective->summary_type === 'manual_override'
                || !$item->strategicObjective->children->contains(fn ($child) => $itemObjectiveIds->contains($child->id))));
        $operationalItems = $reportableItems->filter(fn ($item) => in_array($item->effectiveReportingFrequency(), ['weekly', 'daily'], true));
        $monthlyItems = $reportableItems->filter(fn ($item) => $item->effectiveReportingFrequency() === 'monthly');
        $operational = $operationalItems->isNotEmpty();

        $planningCoverage = $this->planningCoverage($plan, $isWeeklyPeriod ? ($parts[1] ?? '') : $period);

        if (!$operational) {
            if ($isWeeklyPeriod) return $this->result(0, 0, 0, 0, 0, 0, (int) $plan->department_id);
            return $this->periodicCompleteness($plan, $period, $planningCoverage);
        }

        $expectedKeys = collect();
        foreach ($months as $month) {
            if ($isWeeklyPeriod) {
                $expectedKeys->push(strtolower($month) . ':' . (int) ($parts[2] ?? 0));
                continue;
            }
            foreach (array_keys(CalendarHelper::getMonthWeeks($month, (int) $plan->year)) as $week) {
                $expectedKeys->push(strtolower($month) . ':' . $week);
            }
        }

        $entries = $operationalItems->flatMap->weeklyPerformances
            ->filter(fn ($entry) => (int) $entry->year === (int) $plan->year)
            ->filter(fn ($entry) => $expectedKeys->contains(strtolower((string) $entry->month) . ':' . (int) $entry->week_number));

        $entries = $entries->map(function ($entry) {
            $entry->_frequency_key = strtolower((string) $entry->month) . ':' . (int) $entry->week_number;
            return $entry;
        });
        if (!$isWeeklyPeriod && $monthlyItems->isNotEmpty()) {
            foreach ($months as $month) {
                $expectedKeys->push('month:' . $month);
                $keys = [$month, strtolower($plan->year . '-' . $month)];
                $monthlyEntries = $monthlyItems->flatMap->performances->filter(fn ($entry) => in_array(strtolower((string) $entry->period), $keys, true));
                foreach ($monthlyEntries as $entry) {
                    $entry->_frequency_key = 'month:' . $month;
                    $entries->push($entry);
                }
            }
        }
        return $this->summarize($expectedKeys, $entries, $planningCoverage, (int) $plan->department_id, fn ($entry) => $entry->_frequency_key);
    }

    public function combine(Collection $completeness): array
    {
        $unique = $completeness->filter()->unique(fn ($item) => $item['department_id'] ?? null);
        $expected = (int) $unique->sum('expected');
        $submitted = (int) $unique->sum('submitted');
        $approved = (int) $unique->sum('approved');
        $planningDenominator = (float) $unique->sum('planning_weight_total');
        $planningNumerator = (float) $unique->sum('planning_weight_planned');

        return $this->result(
            $expected,
            $submitted,
            $approved,
            $planningDenominator > 0 ? ($planningNumerator / $planningDenominator) * 100 : 0,
            $planningNumerator,
            $planningDenominator,
            null
        );
    }

    private function periodicCompleteness(Plan $plan, string $period, array $planning): array
    {
        $periodKeys = collect($this->monthsForPeriod($period))->map(fn ($month) => strtolower($plan->year . '-' . $month));
        if ($period === 'annual') $periodKeys->push('annual');
        if (preg_match('/^q[1-4]$/', $period)) $periodKeys->push($period);

        $entries = $plan->items->filter(fn ($item) => $item->effectiveReportingFrequency() === 'monthly')->flatMap->performances->filter(function ($entry) use ($periodKeys) {
            $entryPeriod = strtolower((string) $entry->period);
            return $periodKeys->contains($entryPeriod) || $periodKeys->contains(str_contains($entryPeriod, '-') ? $entryPeriod : $entryPeriod);
        });

        // A directly reporting department submits one official package for the selected period.
        $expectedKeys = collect([$period]);
        $workflowEntries = $entries->map(function ($entry) use ($period) {
            $entry->_coverage_key = $period;
            return $entry;
        });

        return $this->summarize($expectedKeys, $workflowEntries, $planning, (int) $plan->department_id, fn ($entry) => $entry->_coverage_key);
    }

    private function summarize(Collection $expectedKeys, Collection $entries, array $planning, int $departmentId, callable $key): array
    {
        $grouped = $entries->groupBy($key);
        $submitted = $expectedKeys->filter(fn ($periodKey) => ($grouped->get($periodKey) ?? collect())->contains(
            fn ($entry) => !empty($entry->submitted_at)
                || !in_array((string) ($entry->workflow_status ?? 'draft'), ['draft', 'returned_to_expert'], true)
        ))->count();
        $approved = $expectedKeys->filter(fn ($periodKey) => ($grouped->get($periodKey) ?? collect())->contains(
            fn ($entry) => in_array((string) $entry->workflow_status, self::OFFICIAL, true)
        ))->count();

        return $this->result(
            $expectedKeys->count(), $submitted, $approved,
            $planning['percentage'], $planning['planned'], $planning['total'],
            $departmentId
        );
    }

    private function result(int $expected, int $submitted, int $approved, float $planningCoverage, float $plannedWeight, float $totalWeight, ?int $departmentId): array
    {
        $reportingCoverage = $expected > 0 ? ($approved / $expected) * 100 : 0;
        return [
            'department_id' => $departmentId,
            'expected' => $expected,
            'submitted' => $submitted,
            'approved' => $approved,
            'planning_coverage' => round($planningCoverage, 1),
            'reporting_coverage' => round($reportingCoverage, 1),
            'planning_weight_planned' => $plannedWeight,
            'planning_weight_total' => $totalWeight,
            'status' => $expected > 0 && $approved === $expected ? 'Complete' : 'Provisional',
        ];
    }

    private function planningCoverage(Plan $plan, string $period): array
    {
        $period = str_starts_with($period, 'month:') ? substr($period, 6) : $period;
        $targetColumn = preg_match('/^q[1-4]$/', $period) ? $period . '_target'
            : ($period === 'annual' ? 'annual_target' : strtolower($period) . '_target');
        $objectiveIds = $plan->items->pluck('strategic_objective_id')->map(fn ($id) => (int) $id);
        $scorable = $plan->items->filter(function ($item) use ($objectiveIds) {
            $objective = $item->strategicObjective;
            if (!$objective || $objective->is_reference_only || $objective->exclude_from_overall_score) return false;
            return !$objective->children->contains(fn ($child) => $objectiveIds->contains((int) $child->id));
        });
        $total = (float) $scorable->sum(fn ($item) => app(PeriodWeightNormalizer::class)->baseWeight($item));
        $planned = (float) $scorable->filter(fn ($item) => !is_null($item->{$targetColumn} ?? null))
            ->sum(fn ($item) => app(PeriodWeightNormalizer::class)->baseWeight($item));

        return ['planned' => $planned, 'total' => $total, 'percentage' => $total > 0 ? ($planned / $total) * 100 : 0];
    }

    private function monthsForPeriod(string $period): array
    {
        $period = strtolower($period);
        $parts = explode(':', $period);
        if (in_array($parts[0] ?? '', ['month', 'week'], true)) return [$parts[1]];
        return match ($period) {
            'q1' => ['july', 'august', 'september'],
            'q2' => ['october', 'november', 'december'],
            'q3' => ['january', 'february', 'march'],
            'q4' => ['april', 'may', 'june'],
            'annual' => ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'],
            default => [$period],
        };
    }
}
