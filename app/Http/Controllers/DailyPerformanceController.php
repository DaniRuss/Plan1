<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\DailyPerformance;
use App\Models\WeeklyPerformance;
use App\Models\User;
use App\Services\FidelityCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyPerformanceController extends Controller
{
    public function recalculateSharedPeriod(\App\Models\ReportingSubmission $submission, int $userId): void
    {
        $this->performParentRollups($submission->department_id,$submission->year,$submission->month,$submission->week,$submission->status,$userId,$submission->plan_id);
    }
    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isSystemAdmin();
        $canSelectDepartment = $this->canSelectDepartment($user);
        $userDept = $user->department;
        
        $latestPlanYear = Plan::where('status', 'approved')->max('year') ?? date('Y');
        $year = (int) $request->get('year', $latestPlanYear);
        $deptId = $request->get('department_id', null);

        if (!$canSelectDepartment) {
            $deptId = auth()->user()->department_id;
            $dept = $userDept;
        } else {
            $dept = $deptId ? \App\Models\Department::find($deptId) : null;
        }

        if ($dept && !$dept->hasReportingFrequency('daily')) {
            return redirect()->route('weekly.dashboard')
                ->with('info', 'The selected department does not use Daily reporting.');
        }

        $dailyDepartments = $canSelectDepartment
            ? \App\Models\Department::withReportingFrequency('daily')->orderBy('name')->get() 
            : null;

        $months = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];
        $weeksByMonth = [];
        foreach ($months as $m) {
            $weeksByMonth[$m] = \App\Services\CalendarHelper::getMonthWeeks($m, $year);
        }

        $query = WeeklyPerformance::where('year', $year);
        if ($deptId) {
            $query->whereIn('plan_item_id', $this->accessiblePlanItemsQuery((int) $deptId)->select('plan_items.id'));
        } elseif ($canSelectDepartment) {
            // Keep submissions separated by their sending department.
            $query->whereRaw('1 = 0');
        }

        $progress = $query->select(
                'month',
                'week_number',
                DB::raw('count(distinct plan_item_id) as item_count'),
                DB::raw("COALESCE(MAX(workflow_status), 'draft') as workflow_status")
            )
            ->groupBy('month', 'week_number')
            ->get()
            ->groupBy('month');

        $years = \App\Services\CalendarHelper::getAppYears();

        return view('daily.dashboard', compact('year', 'months', 'progress', 'years', 'weeksByMonth', 'dept', 'deptId', 'dailyDepartments', 'isAdmin', 'canSelectDepartment'));
    }

    public function entry(Request $request, $year, $month, $week)
    {
        $user = auth()->user();
        $isAdmin = $user->isSystemAdmin();
        $canSelectDepartment = $this->canSelectDepartment($user);
        $userDept = $user->department;
        $deptId = $request->get('department_id', null);

        if (!$canSelectDepartment) {
            $deptId = auth()->user()->department_id;
            $dept = $userDept;
        } else {
            $dept = $deptId ? Department::find($deptId) : null;
        }

        if (!$dept || !$dept->hasReportingFrequency('daily')) {
             return redirect()->route('daily.dashboard')->with('error', 'Invalid department for daily entry.');
        }

        $plan = Plan::where('department_id', $dept->id)
            ->where('year', $year)
            ->where('status', 'approved')
            ->first();

        // Fetch items:
        // 1. Items from the department's own approved plan
        // 2. Items assigned to this department from parent plans AND accepted
        // 3. Items assigned directly to the current expert user
        $items = $this->accessiblePlanItemsQuery($dept->id)
            ->with(['strategicObjective', 'plan'])
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('daily.dashboard')->with('error', 'No approved objectives or accepted assignments found for this period.');
        }
        $weeksInMonth = \App\Services\CalendarHelper::getMonthWeeks($month, $year);
        $weekInfo = $weeksInMonth[$week];
        $numWeeks = count($weeksInMonth);
        
        $dailyData = DailyPerformance::where('year', $year)
            ->where('month', $month)
            ->where('week_number', $week)
            ->whereIn('plan_item_id', $items->pluck('id'))
            ->get()
            ->groupBy('plan_item_id');

        $weeklyEntries = WeeklyPerformance::where('year', $year)
            ->where('month', $month)
            ->where('week_number', $week)
            ->whereIn('plan_item_id', $items->pluck('id'))
            ->with(['submittedBy', 'directorateReviewer', 'chiefReviewer', 'planReportReviewer', 'headOfficeReviewer'])
            ->get()
            ->keyBy('plan_item_id');

        $workflowEntry = $weeklyEntries->first();
        if (!$this->canUserViewPeriod($user, $workflowEntry?->workflow_status, $weeklyEntries->isNotEmpty())) {
            return redirect()->route('daily.dashboard', ['year' => $year, 'department_id' => $dept?->id])
                ->with('error', 'This daily period is not currently assigned to your role.');
        }

        $workflowState = $this->buildWorkflowState($workflowEntry);
        $canEditWorkflow = $this->canUserEditPeriod($user, $workflowState['key']);
        $workflowActions = $this->availableWorkflowActions($user, $workflowState['key']);

        return view('daily.entry', compact(
            'plan',
            'items',
            'year',
            'month',
            'week',
            'weekInfo',
            'numWeeks',
            'dept',
            'dailyData',
            'weeklyEntries',
            'workflowState',
            'canEditWorkflow',
            'workflowActions'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|string',
            'week' => 'required|integer',
            'department_id' => 'required|exists:departments,id',
            'actuals' => 'required|array',
            'weekly_plans' => 'nullable|array',
        ]);

        $year = $validated['year'];
        $month = $validated['month'];
        $week = $validated['week'];
        $deptId = $validated['department_id'];

        $user = auth()->user();
        $dept = Department::findOrFail($deptId);
        $accessibleItemIds = $this->accessiblePlanItemsQuery($dept->id)->pluck('plan_items.id');
        $existingWeeklyEntries = WeeklyPerformance::where('year', $year)
            ->where('month', $month)
            ->where('week_number', $week)
            ->whereIn('plan_item_id', $accessibleItemIds)
            ->get();

        $currentWorkflowStatus = $existingWeeklyEntries->first()->workflow_status ?? 'draft';

        if (!$this->canUserEditPeriod($user, $currentWorkflowStatus)) {
            return back()->with('error', 'This daily period is currently locked for your role. Use the workflow actions before editing again.');
        }

        DB::transaction(function () use ($validated, $year, $month, $week, $deptId, $currentWorkflowStatus) {
            foreach ($validated['actuals'] as $itemId => $days) {
                $planItem = PlanItem::find($itemId);
                if (!$planItem) continue;
                $monthTargetColumn = strtolower($month) . '_target';
                if (is_null($planItem->{$monthTargetColumn})) continue;

                foreach ($days as $day => $value) {
                    if ($value === null || $value === '') continue;
                    
                    DailyPerformance::updateOrCreate(
                        [
                            'plan_item_id' => $itemId,
                            'year' => $year,
                            'month' => $month,
                            'week_number' => $week,
                            'day_of_week' => $day,
                        ],
                        [
                            'actual_value' => $value,
                            'created_by' => auth()->id(),
                        ]
                    );
                }

                // Aggregate to WeeklyPerformance
                // Fetch the manual weekly plan from the request, otherwise default to monthly/numWeeks logic
                $weeksInMonth = \App\Services\CalendarHelper::getMonthWeeks($month, $year);
                $numWeeks = count($weeksInMonth);
                $monthlyTarget = (float)($planItem->{$month . '_target'} ?? 0);
                $defaultWeeklyTarget = $planItem->strategicObjective
                    ? $planItem->strategicObjective->weeklyPlanFromMonthlyTarget($monthlyTarget, $numWeeks)
                    : ($numWeeks > 0 ? $monthlyTarget / $numWeeks : 0);
                
                $existingPlan = WeeklyPerformance::where(['plan_item_id'=>$itemId,'year'=>$year,'month'=>$month,'week_number'=>$week])->value('planned_value');
                $planValue = $existingPlan !== null ? (float)$existingPlan : $defaultWeeklyTarget;

                // Recalculate full weekly total from database to be safe
                $fullWeeklyTotal = DailyPerformance::where('plan_item_id', $itemId)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->where('week_number', $week)
                    ->sum('actual_value');

                $percentage = 0;
                if ($planItem->strategicObjective) {
                    $percentage = $planItem->strategicObjective->calculateWeeklyAchievement((float) $fullWeeklyTotal, (float) $planValue);
                }

                WeeklyPerformance::updateOrCreate(
                    [
                        'plan_item_id' => $itemId,
                        'year' => $year,
                        'month' => $month,
                        'week_number' => $week,
                    ],
                    [
                        'planned_value' => $planValue,
                        'actual_value' => $fullWeeklyTotal,
                        'percentage' => $percentage,
                        'status' => $this->legacyStatusForWorkflow($currentWorkflowStatus),
                        'workflow_status' => $currentWorkflowStatus,
                        'created_by' => auth()->id(),
                    ]
                );
            }

            $this->performParentRollups(
                $deptId,
                $year,
                $month,
                $week,
                $currentWorkflowStatus,
                auth()->id()
            );
        });

        return redirect()->route('daily.entry', [$year, $month, $week, 'department_id' => $deptId])
            ->with('success', 'Daily data saved and weekly totals updated successfully.');
    }

    /**
     * Recalculate computed parent rows after their children's daily totals have
     * been written to WeeklyPerformance. Manual-override parents retain their
     * own independently entered value.
     */
    private function performParentRollups(
        int $deptId,
        int $year,
        string $month,
        int $week,
        string $workflowStatus,
        ?int $createdBy,
        ?int $sharedPlanId = null
    ): void {
        $accessibleItems = $this->accessiblePlanItemsQuery($deptId)
            ->when($sharedPlanId !== null,fn ($query) => $query->where('plan_id',$sharedPlanId))
            ->with('strategicObjective')
            ->get();

        $parents = $accessibleItems
            ->pluck('strategicObjective')
            ->filter(fn ($objective) => $objective
                && $objective->children()->exists()
                && $objective->summary_type
                && $objective->summary_type !== 'manual_override')
            ->unique('id')
            ->sortByDesc(fn ($objective) => $objective->getHierarchyLevel());

        foreach ($parents as $parent) {
            foreach ($accessibleItems->where('strategic_objective_id', $parent->id) as $parentPlanItem) {
                $childObjectiveIds = $parent->rollupChildren()->pluck('id');
                $childPlanItemIds = $accessibleItems
                    ->where('plan_id', $parentPlanItem->plan_id)
                    ->whereIn('strategic_objective_id', $childObjectiveIds)
                    ->pluck('id');
                $childItems = $accessibleItems->whereIn('id', $childPlanItemIds);

                $childrenEntries = WeeklyPerformance::with('planItem')
                    ->whereIn('plan_item_id', $childPlanItemIds)
                    ->where('year', $year)
                    ->where('month', strtolower($month))
                    ->where('week_number', $week)
                    ->get()
                    ->keyBy('planItem.strategic_objective_id');

                if ($childrenEntries->isEmpty()) continue;

                if ($parent->summary_type === 'weighted') {
                    $planned = 100.0;
                    $actual = (float) ($childrenEntries->avg('percentage') ?? 0);
                } else {
                    $actual = FidelityCalculator::rollup($parent, $childrenEntries, $parent->summary_type);
                    $planned = (float) $childrenEntries->sum('planned_value');
                }
                $percentage = $parent->calculateWeeklyAchievement((float) $actual, (float) $planned);

                WeeklyPerformance::updateOrCreate(
                    [
                        'plan_item_id' => $parentPlanItem->id,
                        'year' => $year,
                        'month' => strtolower($month),
                        'week_number' => $week,
                    ],
                    [
                        'planned_value' => $planned,
                        'actual_value' => $actual,
                        'percentage' => $percentage,
                        'status' => $this->legacyStatusForWorkflow($workflowStatus),
                        'workflow_status' => $workflowStatus,
                        'created_by' => $createdBy,
                    ]
                );

                // Keep the daily grid consistent with the weekly roll-up. This
                // also lets a higher-level parent aggregate a calculated child.
                $childDailyEntries = DailyPerformance::whereIn('plan_item_id', $childPlanItemIds)
                    ->where('year', $year)
                    ->where('month', strtolower($month))
                    ->where('week_number', $week)
                    ->get()
                    ->groupBy('day_of_week');

                foreach ($childDailyEntries as $day => $dayEntries) {
                    $dailyActual = match ($parent->summary_type) {
                        'average' => (float) ($dayEntries->avg('actual_value') ?: 0),
                        'weighted' => $this->calculateWeightedDailyRollup(
                            $parent,
                            $dayEntries,
                            $childrenEntries
                        ),
                        default => (float) $dayEntries->sum('actual_value'),
                    };

                    DailyPerformance::updateOrCreate(
                        [
                            'plan_item_id' => $parentPlanItem->id,
                            'year' => $year,
                            'month' => strtolower($month),
                            'week_number' => $week,
                            'day_of_week' => $day,
                        ],
                        [
                            'actual_value' => $dailyActual,
                            'created_by' => $createdBy,
                        ]
                    );
                }
            }
        }
    }

    private function calculateWeightedDailyRollup($parent, $dayEntries, $childrenWeeklyEntries): float
    {
        $achievements = collect();

        foreach ($parent->children->where('include_in_parent_rollup', true) as $child) {
            $weeklyEntry = $childrenWeeklyEntries->get($child->id);
            if (!$weeklyEntry) continue;

            $dailyEntry = $dayEntries->firstWhere('plan_item_id', $weeklyEntry->plan_item_id);
            $dailyActual = (float) ($dailyEntry?->actual_value ?? 0);
            $daysReported = DailyPerformance::where('plan_item_id', $weeklyEntry->plan_item_id)
                ->where('year', $weeklyEntry->year)
                ->where('month', $weeklyEntry->month)
                ->where('week_number', $weeklyEntry->week_number)
                ->count();
            $dailyPlan = $daysReported > 0
                ? (float) $weeklyEntry->planned_value / $daysReported
                : 0;
            $achievement = $child->calculateWeeklyAchievement((float) $dailyActual, (float) $dailyPlan);
            $achievements->push($achievement);
        }

        return (float) ($achievements->avg() ?? 0);
    }

    private function canSelectDepartment($user): bool
    {
        return $user->isSystemAdmin()
            || $user->hasPermission('view_all_performance')
            || $user->hasPermission('review_office_performance')
            || $user->hasPermission('final_approve_office_performance')
            || $user->hasPermission('view_final_approved_records');
    }

    private function buildWorkflowState(?WeeklyPerformance $entry): array
    {
        $status = $entry?->workflow_status ?? 'draft';

        return match ($status) {
            'submitted_to_directorate' => [
                'key' => $status,
                'label' => 'Submitted to Directorate',
                'badge' => 'info',
                'note' => $entry?->workflow_note,
                'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
            'returned_to_expert' => [
                'key' => $status,
                'label' => 'Returned to Expert',
                'badge' => 'warning',
                'note' => $entry?->workflow_note,
                'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at,
                'reviewed_by' => $entry?->directorateReviewer?->name,
                'reviewed_at' => $entry?->directorate_reviewed_at,
            ],
            'submitted_to_chief' => [
                'key' => $status,
                'label' => 'Sent to Chief',
                'badge' => 'primary',
                'note' => $entry?->workflow_note,
                'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at,
                'reviewed_by' => $entry?->directorateReviewer?->name,
                'reviewed_at' => $entry?->directorate_reviewed_at,
            ],
            'returned_to_directorate' => [
                'key' => $status,
                'label' => 'Returned to Directorate',
                'badge' => 'warning',
                'note' => $entry?->workflow_note,
                'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at,
                'reviewed_by' => $entry?->chiefReviewer?->name,
                'reviewed_at' => $entry?->chief_reviewed_at,
            ],
            'submitted_to_plan_report_department' => [
                'key' => $status,
                'label' => 'Sent to Plan & Report Department',
                'badge' => 'primary',
                'note' => $entry?->workflow_note,
                'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at,
                'reviewed_by' => $entry?->chiefReviewer?->name,
                'reviewed_at' => $entry?->chief_reviewed_at,
            ],
            'returned_to_department_chief' => [
                'key' => $status,
                'label' => 'Returned to Department Chief',
                'badge' => 'warning',
                'note' => $entry?->workflow_note,
                'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at,
                'reviewed_by' => $entry?->headOfficeReviewer?->name,
                'reviewed_at' => $entry?->head_office_reviewed_at,
            ],
            'submitted_to_head_office' => [
                'key' => $status,
                'label' => 'Sent to Head Office',
                'badge' => 'primary',
                'note' => $entry?->workflow_note,
                'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at,
                'reviewed_by' => $entry?->planReportReviewer?->name,
                'reviewed_at' => $entry?->plan_report_reviewed_at,
            ],
            'returned_to_plan_report_department' => [
                'key' => $status,
                'label' => 'Returned to Plan & Report Department',
                'badge' => 'warning',
                'note' => $entry?->workflow_note,
                'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at,
                'reviewed_by' => $entry?->headOfficeReviewer?->name,
                'reviewed_at' => $entry?->head_office_reviewed_at,
            ],
            'reviewed_no_correction_required' => [
                'key' => $status, 'label' => 'Reviewed — No Correction Required', 'badge' => 'success',
                'note' => $entry?->workflow_note, 'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at, 'reviewed_by' => $entry?->planReportReviewer?->name,
                'reviewed_at' => $entry?->plan_report_reviewed_at,
            ],
            'approved_by_head_office' => [
                'key' => $status,
                'label' => 'Final Approved by Head Office',
                'badge' => 'success',
                'note' => $entry?->workflow_note,
                'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at,
                'reviewed_by' => $entry?->headOfficeReviewer?->name,
                'reviewed_at' => $entry?->head_office_reviewed_at,
            ],
            default => [
                'key' => 'draft',
                'label' => 'Draft',
                'badge' => 'secondary',
                'note' => $entry?->workflow_note,
                'submitted_by' => null,
                'submitted_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        };
    }

    private function canUserEditPeriod($user, string $workflowStatus): bool
    {
        if ($user->isSystemAdmin()) {
            return true;
        }

        if ($user->isDepartmentExpert()) {
            return in_array($workflowStatus, ['draft', 'returned_to_expert'], true);
        }

        if ($user->isDepartmentDirectorate()) {
            return in_array($workflowStatus, ['submitted_to_directorate', 'returned_to_directorate'], true);
        }

        if ($user->isDepartmentChief()) {
            return in_array($workflowStatus, ['submitted_to_chief', 'returned_to_department_chief'], true);
        }

        if ($user->isPlanReportDepartmentExpert()) {
            return in_array($workflowStatus, ['submitted_to_plan_report_department', 'returned_to_plan_report_department'], true);
        }

        if ($user->isPlanReportHeadOffice() || $user->isHeadOffice()) {
            return false;
        }

        return true;
    }

    private function canUserViewPeriod($user, ?string $workflowStatus, bool $hasEntries): bool
    {
        // Department scoping is already enforced before this check. Keep the
        // complete period visible as history after it is handed to the next
        // reviewer; editability and workflow buttons remain role/status based.
        return true;
    }

    private function visibleWorkflowStatusesFor($user): ?array
    {
        if ($user->isSystemAdmin()) {
            return null;
        }

        if ($user->isDepartmentExpert()) return ['draft', 'returned_to_expert'];
        if ($user->isDepartmentDirectorate()) return ['submitted_to_directorate', 'returned_to_directorate'];
        if ($user->isDepartmentChief()) return ['submitted_to_chief', 'returned_to_department_chief'];
        if ($user->isPlanReportDepartmentExpert()) return ['submitted_to_plan_report_department', 'reviewed_no_correction_required'];
        if ($user->isPlanReportDirectorate()) return ['submitted_to_plan_report_department', 'submitted_to_plan_report_directorate'];
        if ($user->isPlanReportHeadOffice() || $user->isHeadOffice()) return ['submitted_to_plan_report_department', 'submitted_to_plan_report_directorate', 'submitted_to_head_office', 'reviewed_no_correction_required'];

        return null;
    }

    private function applyWorkflowVisibility($query, $user)
    {
        $visibleStatuses = $this->visibleWorkflowStatusesFor($user);

        if ($visibleStatuses === null) {
            return $query;
        }

        return $query->whereHas('planItem.weeklyPerformances', function ($weeklyQuery) use ($visibleStatuses) {
            $weeklyQuery->where(function ($statusQuery) use ($visibleStatuses) {
                $statusQuery->whereIn('workflow_status', $visibleStatuses);

                if (in_array('draft', $visibleStatuses, true)) {
                    $statusQuery->orWhereNull('workflow_status');
                }
            });
        });
    }

    private function availableWorkflowActions($user, string $workflowStatus): array
    {
        return [
            'submit_to_directorate' => ($user->isDepartmentExpert() || $user->isSystemAdmin()) && in_array($workflowStatus, ['draft', 'returned_to_expert'], true),
            'return_to_expert' => ($user->isDepartmentDirectorate() || $user->isSystemAdmin()) && $workflowStatus === 'submitted_to_directorate',
            'submit_to_chief' => ($user->isDepartmentDirectorate() || $user->isSystemAdmin()) && in_array($workflowStatus, ['submitted_to_directorate', 'returned_to_directorate'], true),
            'return_to_directorate' => ($user->isDepartmentChief() || $user->isSystemAdmin()) && $workflowStatus === 'submitted_to_chief',
            'approve_by_chief' => ($user->isDepartmentChief() || $user->isSystemAdmin()) && in_array($workflowStatus, ['submitted_to_chief','returned_to_department_chief'], true),
            'mark_reviewed_no_correction' => ($user->isPlanReportDepartmentExpert() || $user->isSystemAdmin()) && $workflowStatus === 'submitted_to_plan_report_department',
            'return_to_department_chief' => ($user->isPlanReportHeadOffice() || $user->isSystemAdmin()) && $workflowStatus === 'submitted_to_head_office',
            'submit_to_plan_report_directorate' => ($user->isPlanReportDepartmentExpert() || $user->isSystemAdmin()) && $workflowStatus === 'submitted_to_plan_report_department',
            'submit_to_head_office' => ($user->isPlanReportDirectorate() || $user->isSystemAdmin()) && $workflowStatus === 'submitted_to_plan_report_directorate',
            'return_to_plan_report_department' => ($user->isPlanReportDirectorate() || $user->isSystemAdmin()) && $workflowStatus === 'submitted_to_plan_report_directorate',
            'return_to_plan_report_directorate' => ($user->isPlanReportHeadOffice() || $user->isSystemAdmin()) && $workflowStatus === 'submitted_to_head_office',
            'approve_by_head_office' => ($user->isPlanReportHeadOffice() || $user->isSystemAdmin()) && $workflowStatus === 'submitted_to_head_office',
        ];
    }

    private function legacyStatusForWorkflow(string $workflowStatus): string
    {
        return $workflowStatus === 'draft' ? 'draft' : 'submitted';
    }

    private function allWorkflowStatuses(): array
    {
        return [
            'draft',
            'submitted_to_directorate',
            'returned_to_expert',
            'submitted_to_chief',
            'returned_to_directorate',
            'submitted_to_plan_report_department',
            'reviewed_no_correction_required',
            'submitted_to_plan_report_directorate',
            'returned_to_department_chief',
            'submitted_to_head_office',
            'returned_to_plan_report_department',
            'approved_by_head_office',
        ];
    }

    private function accessiblePlanItemsQuery(int $deptId)
    {
        return PlanItem::query()->forReportingFrequency('daily')
            ->whereHas('plan', function ($query) {
                $query->where('status', 'approved');
            })
            ->approvedForDepartment($deptId);
    }
}
