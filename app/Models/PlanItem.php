<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Plan;
use App\Models\StrategicObjective;

class PlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'strategic_objective_id',
        'annual_target',
        'july_target',
        'august_target',
        'september_target',
        'october_target',
        'november_target',
        'december_target',
        'january_target',
        'february_target',
        'march_target',
        'april_target',
        'may_target',
        'june_target',
        'q1_target',
        'q2_target',
        'q3_target',
        'q4_target',
        'weight',
        'baseline_value',
        'baseline_year',
        'unit',
        'performers',
        'experts',
    ];

    protected $casts = [
        'annual_target' => 'decimal:2',
        'july_target' => 'decimal:2',
        'august_target' => 'decimal:2',
        'september_target' => 'decimal:2',
        // ... add others if needed, but decimal cast handles general formatting
        'weight' => 'decimal:2',
        'performers' => 'array',
        'experts' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function strategicObjective()
    {
        return $this->belongsTo(StrategicObjective::class);
    }

    public function effectiveReportingFrequency(): string
    {
        return $this->strategicObjective?->effectiveReportingFrequency($this->plan?->department)
            ?? $this->plan?->department?->reporting_type ?? 'monthly';
    }

    public function scopeForReportingFrequency($query, string $frequency)
    {
        return $query->where(function ($query) use ($frequency) {
            $query->whereHas('strategicObjective', fn ($objective) => $objective->where('reporting_frequency', $frequency))
                ->orWhere(function ($inherited) use ($frequency) {
                    $inherited->whereHas('strategicObjective', fn ($objective) => $objective->whereNull('reporting_frequency'))
                        ->whereHas('plan.department', fn ($department) => $department->where('reporting_type', $frequency));
                });
        });
    }

    /**
     * Helper to get the actual value for a given period based on department reporting type.
     */
    public function getActualValueForPeriod($period)
    {
        $department = $this->plan->department ?? null;
        $isAggregated = in_array($this->effectiveReportingFrequency(), ['weekly', 'daily'], true);
        $normalizedPeriod = strtolower((string) $period);
        $quarterMonths = [
            'q1' => ['july', 'august', 'september'],
            'q2' => ['october', 'november', 'december'],
            'q3' => ['january', 'february', 'march'],
            'q4' => ['april', 'may', 'june'],
        ];

        if ($normalizedPeriod === 'annual') {
            if ($isAggregated) {
                $months = collect($quarterMonths)->flatten();
                if ($this->strategicObjective?->target_distribution === 'checkpoint') {
                    $lastPlannedMonth = $months->filter(fn ($month) => !is_null($this->{$month . '_target'}))->last();
                    return $lastPlannedMonth ? $this->aggregatedMonthlyActual($lastPlannedMonth) : 0;
                }
                return $months->sum(fn ($month) => $this->aggregatedMonthlyActual($month));
            }
            // Try explicit 'annual' record first, otherwise sum all periodic records
            $annualPerf = $this->performances->first(fn($p) => strcasecmp($p->period, 'annual') === 0);
            if ($annualPerf) return $annualPerf->actual_value;

            return collect($quarterMonths)->flatten()
                ->sum(fn ($month) => $this->monthlyPeriodicActual($month));
        }

        if ($isAggregated) {
            if (isset($quarterMonths[$normalizedPeriod])) {
                $months = collect($quarterMonths[$normalizedPeriod]);
                if ($this->strategicObjective?->target_distribution === 'checkpoint') {
                    $lastPlannedMonth = $months->filter(fn ($month) => !is_null($this->{$month . '_target'}))->last();
                    return $lastPlannedMonth ? $this->aggregatedMonthlyActual($lastPlannedMonth) : 0;
                }
                return $months->sum(fn ($month) => $this->aggregatedMonthlyActual($month));
            }

            return $this->aggregatedMonthlyActual($normalizedPeriod);
        }

        if (isset($quarterMonths[$normalizedPeriod])) {
            return collect($quarterMonths[$normalizedPeriod])
                ->sum(fn ($month) => $this->monthlyPeriodicActual($month));
        }

        return $this->monthlyPeriodicActual($normalizedPeriod);
    }

    /**
     * Return the plan target for a month, quarter, or year.
     *
     * Null means the period is not planned. An explicit zero remains a valid
     * planned target. Older records may not have stored calculated quarter
     * targets, so sum/checkpoint plans are safely reconstructed from months.
     */
    public function getPlannedTargetForPeriod($period): ?float
    {
        $period = strtolower((string) $period);
        $quarterMonths = [
            'q1' => ['july', 'august', 'september'],
            'q2' => ['october', 'november', 'december'],
            'q3' => ['january', 'february', 'march'],
            'q4' => ['april', 'may', 'june'],
        ];

        if (isset($quarterMonths[$period])) {
            $explicit = $this->{$period . '_target'};
            if (!is_null($explicit)) return (float) $explicit;

            $mode = $this->strategicObjective?->target_distribution ?? 'checkpoint';
            if ($mode === 'manual') return null;

            $planned = collect($quarterMonths[$period])
                ->map(fn ($month) => $this->{$month . '_target'})
                ->filter(fn ($target) => !is_null($target));

            if ($planned->isEmpty()) return null;

            return $mode === 'sum'
                ? (float) $planned->sum()
                : (float) $planned->last();
        }

        if ($period === 'annual') {
            if (!is_null($this->annual_target)) return (float) $this->annual_target;

            $mode = $this->strategicObjective?->target_distribution ?? 'checkpoint';
            $months = collect($quarterMonths)->flatten()
                ->map(fn ($month) => $this->{$month . '_target'})
                ->filter(fn ($target) => !is_null($target));

            if ($months->isEmpty()) return null;

            return $mode === 'sum'
                ? (float) $months->sum()
                : (float) $months->last();
        }

        if (!array_key_exists($period, array_fill_keys(collect($quarterMonths)->flatten()->all(), true))) {
            return null;
        }

        $target = $this->{$period . '_target'};
        return is_null($target) ? null : (float) $target;
    }

    public function hasPerformanceForPeriod($period): bool
    {
        $department = $this->plan->department ?? null;
        $isAggregated = in_array($this->effectiveReportingFrequency(), ['weekly', 'daily'], true);
        $period = strtolower((string) $period);
        $quarters = [
            'q1' => ['july', 'august', 'september'], 'q2' => ['october', 'november', 'december'],
            'q3' => ['january', 'february', 'march'], 'q4' => ['april', 'may', 'june'],
        ];

        if ($isAggregated) {
            if ($period === 'annual') return $this->weeklyPerformances->isNotEmpty();
            if (isset($quarters[$period])) return $this->weeklyPerformances->whereIn('month', $quarters[$period])->isNotEmpty();
            return $this->weeklyPerformances->where('month', $period)->isNotEmpty();
        }

        if ($period === 'annual') {
            return $this->performances->contains(fn ($entry) => strtolower((string) $entry->period) === 'annual')
                || collect($quarters)->flatten()->contains(fn ($month) => $this->hasMonthlyPeriodicEntry($month));
        }
        if (isset($quarters[$period])) {
            return collect($quarters[$period])->contains(fn ($month) => $this->hasMonthlyPeriodicEntry($month));
        }
        return $this->hasMonthlyPeriodicEntry($period);
    }

    private function monthlyPeriodicActual(string $month): float
    {
        $yearKey = ($this->plan->year ?? '') . '-' . strtolower($month);
        $entry = $this->performances->first(fn ($performance) => strtolower((string) $performance->period) === $yearKey)
            ?? $this->performances->first(fn ($performance) => strtolower((string) $performance->period) === strtolower($month));

        return $entry ? (float) $entry->actual_value : 0.0;
    }

    private function aggregatedMonthlyActual(string $month): float
    {
        $entries = $this->weeklyPerformances
            ->where('month', strtolower($month))
            ->where('workflow_status', 'approved_by_head_office')
            ->sortBy('week_number');

        if ($entries->isEmpty()) return 0.0;

        if ($this->strategicObjective?->target_distribution === 'checkpoint') {
            return (float) ($entries->last()?->actual_value ?? 0);
        }

        if ($this->strategicObjective?->weekly_plan_mode === 'repeat') {
            $averageAchievement = (float) $entries->avg(function ($entry) {
                if (!is_null($entry->percentage)) return (float) $entry->percentage;
                $plan = (float) $entry->planned_value;
                return $this->strategicObjective
                    ? $this->strategicObjective->calculateWeeklyAchievement((float) $entry->actual_value, $plan)
                    : ($plan != 0 ? ((float) $entry->actual_value / $plan) * 100 : 0);
            });
            $monthlyTarget = (float) ($this->{strtolower($month) . '_target'} ?? 0);
            return ($averageAchievement / 100) * $monthlyTarget;
        }

        return (float) $entries->sum('actual_value');
    }

    private function hasMonthlyPeriodicEntry(string $month): bool
    {
        $yearKey = ($this->plan->year ?? '') . '-' . strtolower($month);
        return $this->performances->contains(fn ($entry) => in_array(
            strtolower((string) $entry->period),
            [$yearKey, strtolower($month)],
            true
        ));
    }

    public function performances()
    {
        return $this->hasMany(\App\Models\PeriodicPerformance::class);
    }

    public function weeklyPerformances()
    {
        return $this->hasMany(WeeklyPerformance::class);
    }

    public function assignments()
    {
        return $this->hasMany(PlanItemAssignment::class);
    }

    public function scopeApprovedForDepartment($query, int $departmentId)
    {
        return $query->whereHas('assignments', function ($assignmentQuery) use ($departmentId) {
            $assignmentQuery->where('performer_id', $departmentId)
                ->where('status', 'approved_for_experts');
        });
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
