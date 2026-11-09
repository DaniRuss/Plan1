<?php

namespace App\Services;

use App\Models\PlanItem;
use Illuminate\Support\Collection;

/** Read-only official evidence. An absent entry is never an actual zero. */
class ReportPeriodEvidence
{
    public const MONTHS = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];
    private const OFFICIAL = ['approved_by_head_office', 'reviewed_no_correction_required'];
    private array $calendarWeeks = [];

    public function forItem(PlanItem $item, string $period): array
    {
        $period = strtolower($period);
        $parts = explode(':', $period);
        if ($parts[0] === 'week') {
            $month = $parts[1];
            $week = (int) $parts[2];
            $entries = $this->weeks($item, $month)->where('week_number', $week);
            $entry = $entries->first();
            $monthlyTarget = $item->getPlannedTargetForPeriod($month);
            $target = $monthlyTarget === null ? null : ($entry?->planned_value !== null
                ? (float) $entry->planned_value
                : $item->strategicObjective->weeklyPlanFromMonthlyTarget($monthlyTarget, count($this->weekNumbers($item, $month))));
            $official = $entries->first(fn ($record) => $this->official($record));
            return $this->result($target, $official ? (float) $official->actual_value : null, 1, $official ? 1 : 0, $entries->contains(fn ($entry) => $entry->actual_value !== null));
        }

        $period = $parts[0] === 'month' ? $parts[1] : $period;
        $target = $item->getPlannedTargetForPeriod($period);
        if ($target === null) return $this->result(null, null, 0, 0, false);
        $operational = in_array($item->effectiveReportingFrequency(), ['weekly', 'daily'], true);
        $directPending = false;
        if (!$operational || !in_array($period, self::MONTHS, true)) {
            $entries = $this->periodic($item, $period);
            $official = $entries->first(fn ($record) => $this->official($record));
            $directPending = $entries->isNotEmpty() && !$official;
            if ($official || in_array($period, self::MONTHS, true)) {
                return $this->result($target, $official ? (float) $official->actual_value : null, 1, $official ? 1 : 0, $entries->isNotEmpty());
            }
        }
        if (in_array($period, self::MONTHS, true)) return $this->month($item, $period, $target);

        $periods = $period === 'annual' ? ['q1', 'q2', 'q3', 'q4'] : array_slice(self::MONTHS, ((int) substr($period, 1) - 1) * 3, 3);
        $scheduled = collect($periods)->filter(fn ($part) => $item->getPlannedTargetForPeriod($part) !== null);
        $evidence = $scheduled->map(fn ($part) => $this->forItem($item, $part));
        $values = $evidence->pluck('actual')->filter(fn ($value) => $value !== null);
        $checkpoint = $item->strategicObjective?->target_distribution === 'checkpoint';
        $expected = (int) $evidence->sum('expected');
        $completed = (int) $evidence->sum('completed');
        $result = $this->result($target, $values->isEmpty() ? null : (float) ($checkpoint ? $values->last() : $values->sum()),
            $expected ?: count($periods), $completed, $directPending || $evidence->contains('pending', true));
        if ($period === 'annual') {
            $scheduledTarget = $checkpoint ? $evidence->pluck('target')->last() : $evidence->sum('target');
            if ($scheduledTarget === null || abs($target - (float) $scheduledTarget) > 0.0001) {
                // A partly scheduled year must not look final just because every existing slot was filled.
                $result['expected'] = max(4, $result['expected'] + 1);
                $result['complete'] = false;
                $result['coverage'] = round($completed / $result['expected'] * 100, 4);
            }
        }
        return $result;
    }

    private function month(PlanItem $item, string $month, float $target): array
    {
        $validWeeks = $this->weekNumbers($item, $month);
        $entries = $this->weeks($item, $month)->whereIn('week_number', $validWeeks)->filter(fn ($entry) => $entry->actual_value !== null);
        $official = $entries->filter(fn ($record) => $this->official($record))->unique('week_number')->sortBy('week_number');
        $actual = null;
        if ($official->isNotEmpty()) {
            if ($item->strategicObjective?->target_distribution === 'checkpoint') {
                $actual = (float) $official->last()->actual_value;
            } elseif ($item->strategicObjective?->weekly_plan_mode === 'repeat') {
                $actual = $official->avg(fn ($entry) => $entry->percentage !== null ? (float) $entry->percentage
                    : $item->strategicObjective->calculateWeeklyAchievement((float) $entry->actual_value, (float) $entry->planned_value)) / 100 * $target;
            } else {
                $actual = (float) $official->sum('actual_value');
            }
        }
        return $this->result($target, $actual, count($validWeeks), $official->count(), $entries->isNotEmpty());
    }

    private function weeks(PlanItem $item, string $month): Collection
    {
        return $item->weeklyPerformances->filter(fn ($entry) => (int) $entry->year === (int) $item->plan->year
            && strtolower((string) $entry->month) === $month);
    }

    private function weekNumbers(PlanItem $item, string $month): array
    {
        $key = $item->plan->year.'-'.$month;
        return $this->calendarWeeks[$key] ??= array_keys(CalendarHelper::getMonthWeeks($month, $item->plan->year));
    }

    private function periodic(PlanItem $item, string $period): Collection
    {
        $keys = [strtolower($item->plan->year.'-'.$period), $period];
        return $item->performances->filter(fn ($entry) => $entry->actual_value !== null
            && in_array(strtolower((string) $entry->period), $keys, true))
            ->sortBy(fn ($entry) => array_search(strtolower((string) $entry->period), $keys, true));
    }

    private function official($entry): bool
    {
        return $entry->actual_value !== null && in_array($entry->workflow_status, self::OFFICIAL, true);
    }

    private function result(?float $target, ?float $actual, int $expected, int $completed, bool $hasEntries): array
    {
        $planned = $target !== null;
        $expected = $planned ? $expected : 0;
        $completed = $planned ? $completed : 0;
        return ['target' => $target, 'actual' => $planned ? $actual : null, 'planned' => $planned,
            'reported' => $planned && $actual !== null, 'complete' => $planned && $expected > 0 && $completed === $expected,
            'pending' => $planned && $hasEntries && $completed < $expected,
            'expected' => $expected, 'completed' => $completed,
            'coverage' => $expected > 0 ? round(min(100, $completed / $expected * 100), 4) : 0.0];
    }
}
