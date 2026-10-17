<?php

namespace App\Services;

use App\Models\{User, PlanItem, WeeklyPerformance, PeriodicPerformance, DailyPerformance};

class DashboardWorkspace
{
    public const FINAL_STATES = ['reviewed_no_correction_required', 'approved_by_head_office'];

    public function organizationWide(User $user): bool
    {
        return $user->hasOrganizationWidePerformanceAccess() || $user->isHRManager() || $user->hasRole('auditor');
    }

    public function items(User $user, ?int $year = null, ?array $departments = null)
    {
        $query = PlanItem::whereHas('plan', function ($plan) use ($user, $year, $departments) {
            $plan->where('status', 'approved')->when($year !== null, fn ($q) => $q->where('year', $year));
            if ($departments !== null) $plan->whereIn('department_id', $departments);
            elseif (!$this->organizationWide($user)) $plan->whereIn('department_id', array_filter([$user->department_id ?? $user->employee?->department_id]));
        });
        if ($user->isDepartmentExpert() && !$this->organizationWide($user)) {
            $employeeId = $user->employee_id;
            // Department workflow accounts share unassigned items. Explicit expert
            // assignments remain personal and must not leak to another expert.
            if (!\App\Models\ReportingTeam::where('department_id',$user->department_id)->exists()) $query->where(function ($q) use ($employeeId) {
                $q->whereNull('experts')->orWhereJsonLength('experts', 0);
                if ($employeeId) $q->orWhereJsonContains('experts', (int) $employeeId);
            });
            $departmentId = $user->department_id ?? $user->employee?->department_id;
            $query->where(function ($q) use ($departmentId) {
                $q->whereDoesntHave('assignments')->orWhereHas('assignments', fn ($assignment) => $assignment->where('performer_id', $departmentId)->where('status', 'approved_for_experts'));
            });
        }
        if (!$user->hasPermission('view_performance') && !$this->organizationWide($user)) $query->whereRaw('1 = 0');
        return $query;
    }

    public function isOfficial($entry): bool
    {
        return in_array($entry->workflow_status, self::FINAL_STATES, true)
            || (in_array($entry->workflow_status, [null, '', 'approved'], true) && $entry->status === 'approved');
    }

    public function state($entry): string
    {
        if ($this->isOfficial($entry)) return 'approved_by_head_office';
        return $entry->workflow_status ?: ($entry->status ?: 'draft');
    }

    public function snapshot(User $user, ?int $requestedYear = null): array
    {
        $years = $this->items($user)->join('plans', 'plans.id', '=', 'plan_items.plan_id')
            ->distinct()->orderByDesc('plans.year')->pluck('plans.year')->map(fn ($year) => (int) $year)->values();
        $year = $years->contains($requestedYear) ? $requestedYear : $years->first();
        $pendingItems = 0;
        if ($user->isDepartmentExpert() && !$this->organizationWide($user) && $user->department_id) {
            $pending = PlanItem::whereHas('plan', fn ($plan) => $plan->where('status', 'approved')->where('department_id', $user->department_id))
                ->whereHas('assignments', fn ($assignment) => $assignment->where('performer_id', $user->department_id)->where('status', '!=', 'approved_for_experts'))
                ->whereDoesntHave('assignments', fn ($assignment) => $assignment->where('performer_id', $user->department_id)->where('status', 'approved_for_experts'));
            if (!$year) $year = (clone $pending)->join('plans', 'plans.id', '=', 'plan_items.plan_id')->max('plans.year');
            $pendingItems = $pending->when($year, fn ($q) => $q->whereHas('plan', fn ($plan) => $plan->where('year', $year)))->count();
        }
        $items = $this->items($user, $year)->with(['plan', 'strategicObjective'])->get();
        $ids = $items->pluck('id');
        $weekly = WeeklyPerformance::whereIn('plan_item_id', $ids)->when($year, fn ($q) => $q->where('year', $year))->get();
        $periodic = PeriodicPerformance::whereIn('plan_item_id', $ids)->get();
        $daily = DailyPerformance::whereIn('plan_item_id', $ids)->when($year, fn ($q) => $q->where('year', $year))->count();
        $entries = $weekly->concat($periodic);
        if ($user->isHeadOffice() && !$user->isSystemAdmin() && !$user->isPlanReportHeadOffice()) {
            $entries = $entries->filter(fn ($entry) => $this->isOfficial($entry));
        }
        $states = $entries->countBy(fn ($entry) => $this->state($entry));
        $official = $entries->filter(fn ($entry) => $this->isOfficial($entry))->count();
        $queueStates = match (true) {
            $user->isDepartmentDirectorate() => ['submitted_to_directorate', 'returned_to_directorate'],
            $user->isDepartmentChief() => ['submitted_to_chief', 'returned_to_department_chief'],
            $user->isPlanReportDepartmentExpert() => ['submitted_to_plan_report_department', 'returned_to_plan_report_department'],
            $user->isPlanReportDirectorate() => ['submitted_to_plan_report_directorate', 'returned_to_plan_report_directorate'],
            $user->isPlanReportHeadOffice(), $user->isHeadOffice() => ['submitted_to_head_office'],
            default => ['draft', 'pending', 'returned_to_expert'],
        };
        $queue = $entries->filter(fn ($entry) => in_array($this->state($entry), $queueStates, true))->count();
        $months = ['july','august','september','october','november','december','january','february','march','april','may','june'];
        $points = collect();
        foreach ($entries as $entry) {
            $item = $items->firstWhere('id', $entry->plan_item_id);
            if (!$item?->strategicObjective) continue;
            $period = $entry instanceof WeeklyPerformance ? strtolower($entry->month) : preg_replace('/^\d{4}-/', '', strtolower($entry->period));
            $target = $entry instanceof WeeklyPerformance ? $entry->planned_value : $item->getPlannedTargetForPeriod($period);
            if ($target === null) continue;
            $achievement = app(EdrPerformanceCalculator::class)->achievement((float) $entry->actual_value, (float) $target, $item->strategicObjective->goal_direction ?? 'increase');
            $points->push(['month' => $period, 'achievement' => $achievement]);
        }
        $trend = collect($months)->map(fn ($month) => $points->where('month', $month)->isEmpty() ? null : round((float) $points->where('month', $month)->avg('achievement'), 1))->all();
        return [
            'year' => $year, 'years' => $years, 'items' => $items->count(), 'pendingItems' => $pendingItems, 'reported' => $entries->count(), 'daily' => $daily,
            'official' => $official, 'queue' => $queue,
            'barChart' => ['title' => 'Reported Monthly Achievement'.($year ? " — $year" : ''), 'hasData' => $points->whereIn('month', $months)->isNotEmpty(), 'labels' => array_map('ucfirst', $months), 'datasets' => [['label' => 'Reported achievement (%) — includes work awaiting review', 'data' => $trend, 'color' => '#07988a']]],
            'doughnutChart' => ['title' => 'Performance Review Workflow'.($year ? " — $year" : ''), 'hasData' => $states->isNotEmpty(), 'labels' => $states->keys()->map(fn ($state) => ucwords(str_replace('_', ' ', $state)))->all(), 'data' => $states->values()->all()],
        ];
    }
}
