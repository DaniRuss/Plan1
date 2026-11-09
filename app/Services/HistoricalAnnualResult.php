<?php

namespace App\Services;

use App\Models\PlanItem;

/** Approved annual results; an unfinished year is never treated as a final result. */
class HistoricalAnnualResult
{
    private const MONTHS = ['july','august','september','october','november','december','january','february','march','april','may','june'];

    public function forItem(PlanItem $item): array
    {
        $year = $item->plan->year;
        $target = $item->getPlannedTargetForPeriod('annual');
        $annual = $item->performances->first(fn ($p) => in_array(strtolower($p->period), ['annual', $year.'-annual'], true));
        if ($annual && $annual->actual_value !== null) {
            return ['target'=>$target, 'actual'=>(float)$annual->actual_value, 'complete'=>true, 'completed'=>1, 'expected'=>1];
        }
        $planned = collect(self::MONTHS)->filter(fn ($month) => $item->{$month.'_target'} !== null)->values();
        $checkpoint = $item->strategicObjective?->target_distribution === 'checkpoint';
        $scheduledTarget = $checkpoint ? ($planned->isEmpty() ? null : (float)$item->{$planned->last().'_target'}) : (float)$planned->sum(fn ($m) => (float)$item->{$m.'_target'});
        $scheduleComplete = $planned->isNotEmpty() && $target !== null && abs((float)$target - (float)$scheduledTarget) < .0001;
        $values = collect();
        $completeMonths = 0;
        foreach (self::MONTHS as $month) {
            $complete = false;
            if (in_array($item->effectiveReportingFrequency(), ['weekly','daily'], true)) {
                $weeks = $item->weeklyPerformances->filter(fn ($p) => strtolower($p->month) === $month && $p->actual_value !== null)->sortBy('week_number');
                if ($weeks->isNotEmpty()) {
                    $expectedWeeks = array_keys(CalendarHelper::getMonthWeeks($month, $year));
                    $complete = collect($expectedWeeks)->diff($weeks->pluck('week_number')->map(fn ($n) => (int)$n))->isEmpty();
                    if ($checkpoint) $actual = (float)$weeks->last()->actual_value;
                    elseif ($item->strategicObjective?->weekly_plan_mode === 'repeat') {
                        $actual = (float)$weeks->avg(fn ($p) => $p->percentage !== null ? (float)$p->percentage : app(EdrPerformanceCalculator::class)->rawAchievement((float)$p->actual_value, (float)$p->planned_value, $item->strategicObjective?->goal_direction ?? 'increase')) / 100 * (float)$item->{$month.'_target'};
                    } else $actual = (float)$weeks->sum('actual_value');
                    $values->put($month, $actual);
                }
            } else {
                $entry = $item->performances->firstWhere('period', $year.'-'.$month) ?? $item->performances->firstWhere('period', $month);
                if ($entry && $entry->actual_value !== null) {
                    $values->put($month, (float)$entry->actual_value);
                    $complete = true;
                }
            }
            if ($planned->contains($month) && $complete) $completeMonths++;
        }
        return [
            'target'=>$target, 'actual'=>$values->isEmpty() ? null : ($checkpoint ? (float)$values->last() : (float)$values->sum()),
            'complete'=>$scheduleComplete && $completeMonths === $planned->count(),
            'completed'=>$completeMonths, 'expected'=>$scheduleComplete ? $planned->count() : max(12, $planned->count()),
        ];
    }
}
