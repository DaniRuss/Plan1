<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\WeeklyPerformance;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Services\FidelityCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WeeklyPerformanceController extends Controller
{
    /**
     * Show the Weekly Performance Dashboard (Navigation Hub).
     */
    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isSystemAdmin();
        $canSelectDepartment = $isAdmin
            || $user->hasPermission('view_all_performance')
            || $user->hasPermission('review_office_performance')
            || $user->hasPermission('final_approve_office_performance')
            || $user->hasPermission('view_final_approved_records');
        $userDept = $user->department;
        
        // Define default year based on the latest approved plan
        $latestPlanYear = Plan::where('status', 'approved')->max('year') ?? date('Y');
        $year = (int) $request->get('year', $latestPlanYear);

        $deptId = $request->get('department_id', null);

        // If not admin, force their own department
        if (!$canSelectDepartment) {
            $deptId = auth()->user()->department_id;
            $dept = $userDept;
        } else {
            // Admin selected a department or defaults to null
            $dept = $deptId ? \App\Models\Department::find($deptId) : null;
        }

        // Block monthly-reporting departments from the weekly workbench
        if ($dept && !$dept->hasReportingFrequency('weekly')) {
            return redirect()->route('monthly.dashboard')
                ->with('info', 'The selected department uses Monthly reporting. Please use the Monthly Aggregator to view/submit their monthly report.');
        }

        // For admins, fetch all weekly departments for the dropdown
        $weeklyDepartments = $canSelectDepartment
            ? \App\Models\Department::withReportingFrequency('weekly')->orderBy('name')->get() 
            : null;

        $months = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];

        // Pre-compute the 4 week date ranges for every month
        $weeksByMonth = [];
        foreach ($months as $m) {
            $weeksByMonth[$m] = \App\Services\CalendarHelper::getMonthWeeks($m, $year);
        }

        // Fetch existing entries to show progress on the grid
        $query = WeeklyPerformance::where('year', $year);
        if ($deptId) {
            $query->whereIn('plan_item_id', $this->accessiblePlanItemsQuery((int) $deptId, (int) $year)->select('plan_items.id'));
        } elseif ($canSelectDepartment) {
            // Reviewers must choose the sender's department. Showing every
            // department in one calendar merges unrelated submissions.
            $query->whereRaw('1 = 0');
        }

        $progress = $query->select(
                'month',
                'week_number',
                DB::raw('count(*) as count'),
                DB::raw("COALESCE(MAX(workflow_status), 'draft') as workflow_status"),
                DB::raw('MAX(submitted_at) as submitted_at')
            )
            ->groupBy('month', 'week_number')
            ->get()
            ->groupBy('month');

        $now = now();
        $timeliness = ['missing' => 0, 'overdue' => 0, 'late' => 0, 'awaiting' => 0, 'approved' => 0];
        $currentPeriod = null;
        if ($deptId) {
            $weeklyLeafItems = $this->accessiblePlanItemsQuery((int) $deptId, (int) $year)
                ->with('strategicObjective.children')->get()->filter(fn ($item) =>
                    $item->strategicObjective->children->isEmpty() || $item->strategicObjective->summary_type === 'manual_override');
            foreach ($weeksByMonth as $monthName => $monthWeeks) {
                if (!$weeklyLeafItems->contains(fn ($item) => !is_null($item->{strtolower($monthName) . '_target'}))) continue;
                $monthData = collect(data_get($progress, $monthName, []));
                foreach ($monthWeeks as $number => $period) {
                    $row = $monthData->firstWhere('week_number', $number);
                    $dueAt = \Illuminate\Support\Carbon::parse($period['due_at']);
                    $startAt = \Illuminate\Support\Carbon::parse($period['start_full']);
                    $endAt = \Illuminate\Support\Carbon::parse($period['end_full'])->endOfDay();
                    if ($now->between($startAt, $endAt)) {
                        $currentPeriod = ['month' => $monthName, 'week' => $number, 'label' => $period['label']];
                    }
                    if (!$row && $now->gte($startAt)) $timeliness['missing']++;
                    if (!$row && $now->gt($dueAt)) $timeliness['overdue']++;
                    if ($row?->submitted_at && \Illuminate\Support\Carbon::parse($row->submitted_at)->gt($dueAt)) $timeliness['late']++;
                    if ($row && $row->workflow_status !== 'approved_by_head_office') $timeliness['awaiting']++;
                    if ($row?->workflow_status === 'approved_by_head_office') $timeliness['approved']++;
                }
            }
        }

        $years = \App\Services\CalendarHelper::getAppYears();

        return view('weekly.dashboard', compact('year', 'months', 'progress', 'years', 'weeksByMonth', 'dept', 'deptId', 'weeklyDepartments', 'isAdmin', 'canSelectDepartment', 'timeliness', 'currentPeriod', 'now'));
    }

    /**
     * Show the Weekly Performance Entry Workbench.
     */
    public function entry(Request $request, $year, $month, $week)
    {
        $user = auth()->user();
        $isAdmin = $user->isSystemAdmin();
        $canSelectDepartment = $isAdmin
            || $user->hasPermission('review_office_performance')
            || $user->hasPermission('final_approve_office_performance');
        $userDept = auth()->user()->department;
        $deptId = $request->get('department_id', null);

        // Get the specific weeks for this month/year
        $weeks = \App\Services\CalendarHelper::getMonthWeeks($month, $year);
        $weekCount = count($weeks);

        // Enforce valid week range
        if ($week < 1 || $week > $weekCount) {
            return redirect()->route('weekly.dashboard')
                ->with('error', "Invalid week number. " . ucfirst($month) . " $year only has $weekCount weeks.");
        }

        // If not admin, force their own department
        if (!$canSelectDepartment) {
            $deptId = auth()->user()->department_id;
            $dept = $userDept;
        } else {
            $dept = $deptId ? \App\Models\Department::find($deptId) : null;
        }

        // Admin must select a department before entering data
        if ($canSelectDepartment && !$deptId) {
            return redirect()->route('weekly.dashboard', ['year' => $year])
                ->with('error', 'Please select a department from the dashboard before entering data.');
        }

        // Block monthly-reporting departments from weekly entry
        if ($dept && !$dept->hasReportingFrequency('weekly')) {
            return redirect()->route('monthly.dashboard')
                ->with('info', 'The selected department uses Monthly reporting. Please use the Monthly Aggregator.');
        }

        // Get the specific date range for this week and month
        $weeks = \App\Services\CalendarHelper::getMonthWeeks($month, $year);
        $weekInfo = $weeks[$week] ?? null;

        if (now()->lt(\Illuminate\Support\Carbon::parse($weekInfo['start_full'])->startOfDay())) {
            return redirect()->route('weekly.dashboard', ['year' => $year, 'department_id' => $deptId])
                ->with('error', 'Future reporting weeks cannot accept performance before their start date.');
        }

        // Map month name to PlanItem column
        $monthColumn = strtolower($month) . '_target';

        // Find the active approved plan for the department. 
        // We order by updated_at to ensure that the plan the user most recently 
        // worked on in the planning page is picked if multiples exist.
        $plan = Plan::where('year', $year)
            ->where('department_id', $deptId)
            ->where('status', 'approved')
            ->orderByDesc('updated_at')
            ->first();

        if (!$plan) {
            return redirect()->route('weekly.dashboard', ['year' => $year, 'department_id' => $deptId])
                ->with('error', "No approved department plan found for Year $year.");
        }

        // Get PlanItems for this plan (now optional, handled in blade)
        $planItems = $this->accessiblePlanItemsQuery((int) $deptId, (int) $year)
            ->with('strategicObjective')
            ->get()
            ->keyBy('strategic_objective_id');

        // Fetch hierarchical objectives associated with THIS specific department
        $approvedObjectiveIds = $planItems->keys()->map(fn ($id) => (int) $id)->all();
        $rootIds = StrategicObjective::whereIn('id', $approvedObjectiveIds)
            ->get()
            ->map(fn (StrategicObjective $objective) => $objective->parent_id ? $objective->getRoot()->id : $objective->id)
            ->unique()
            ->values();

        $objectives = StrategicObjective::whereNull('parent_id')
            ->whereIn('id', $rootIds)
            ->where('status', 'approved')
            ->with(['children' => function($q) {
                $q->where('status', 'approved')
                    ->with(['children' => fn ($children) => $children->where('status', 'approved')->orderByNaturalCode()])
                    ->orderByNaturalCode();
            }])
            ->orderByNaturalCode()
            ->get();

        // A root is displayed only as hierarchy context. Every reportable row
        // beneath it must belong to this department's approved plan/assignment.
        $objectives = $this->pruneObjectiveTree($objectives, array_flip($approvedObjectiveIds));

        // Fetch existing entries for the specified period
        $periodEntries = $this->periodEntriesQuery((int) $deptId, (int) $year, strtolower($month), (int) $week, 'weekly')
            ->with(['stationData', 'submittedBy', 'directorateReviewer', 'chiefReviewer', 'planReportReviewer', 'headOfficeReviewer'])
            ->get();

        if (!$this->canUserViewPeriod($user, $periodEntries->first()?->workflow_status, $periodEntries->isNotEmpty())) {
            return redirect()->route('weekly.dashboard', ['year' => $year, 'department_id' => $deptId])
                ->with('error', 'This weekly period is not currently assigned to your role.');
        }

        $entries = $periodEntries->keyBy('plan_item_id');

        $workflowState = $this->buildWorkflowState($periodEntries->first());
        $canEditWorkflow = $this->canUserEditPeriod($user, $workflowState['key']);
        $workflowActions = $this->availableWorkflowActions($user, $workflowState['key']);

        // Fetch sums of existing plan entries for this month (across all weeks) to prevent over-planning
        $monthlyPlanSums = $this->periodEntriesQuery((int) $deptId, (int) $year, strtolower($month), null, 'weekly')
            ->where('month', strtolower($month))
            ->selectRaw('plan_item_id, SUM(planned_value) as total_planned')
            ->groupBy('plan_item_id')
            ->pluck('total_planned', 'plan_item_id');

        // Fetch performance remarks for this period
        $performanceRemarks = \App\Models\PerformanceRemark::where([
            'department_id' => $deptId,
            'year' => $year,
            'month' => strtolower($month),
            'week_number' => $week,
        ])->get();
        $attachments = \App\Models\PerformanceAttachment::with('uploader')->where([
            'department_id' => $deptId, 'year' => $year, 'month' => strtolower($month),
            'week_number' => $week, 'frequency' => 'weekly',
        ])->latest()->get();

        return view('weekly.entry', compact(
            'year',
            'month',
            'week',
            'objectives',
            'planItems',
            'entries',
            'monthColumn',
            'weekInfo',
            'deptId',
            'dept',
            'isAdmin',
            'canSelectDepartment',
            'monthlyPlanSums',
            'performanceRemarks',
            'workflowState',
            'canEditWorkflow',
            'workflowActions',
            'attachments'
        ));
    }

    /**
     * Save weekly performance entries.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|string',
            'week' => 'required|integer',
            'department_id' => 'required|exists:departments,id',
            'entries' => 'required|array',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
        ]);

        $deptId = $validated['department_id'];
        $user = auth()->user();
        $month = strtolower($validated['month']);
        $weekInfo = \App\Services\CalendarHelper::getMonthWeeks($month, (int) $validated['year'])[(int) $validated['week']] ?? null;
        if (!$weekInfo) {
            return back()->with('error', 'The selected reporting week is invalid.');
        }
        if (now()->lt(\Illuminate\Support\Carbon::parse($weekInfo['start_full'])->startOfDay())) {
            return back()->with('error', 'Future reporting weeks cannot accept performance before their start date.');
        }

        $existingEntries = $this->periodEntriesQuery(
            (int) $deptId,
            (int) $validated['year'],
            strtolower($validated['month']),
            (int) $validated['week'],
            'weekly'
        )->get();

        $currentWorkflowStatus = $existingEntries->first()->workflow_status ?? 'draft';

        if (!$this->canUserEditPeriod($user, $currentWorkflowStatus)) {
            return back()->with('error', 'This period is currently locked for your role. Please use the review workflow actions instead.');
        }

        // Period lock check
        $isLocked = \App\Models\PeriodClosure::where('year', $validated['year'])
            ->where('month', strtolower($validated['month']))
            ->where('department_id', $deptId)
            ->exists();

        if ($isLocked) {
            return back()->with('error', 'This month has been certified and locked. Data entry is no longer permitted.');
        }

        // Performance may only be recorded against the approved plan selected
        // for this department and year. Never create plan items from request IDs.
        $plan = Plan::where('year', (int)$validated['year'])
            ->where('department_id', (int)$deptId)
            ->where('status', 'approved')
            ->orderByDesc('updated_at')
            ->first();

        if (!$plan) {
            return back()->with('error', 'Performance cannot be saved because no approved department plan exists for the selected year.');
        }

        $allowedPlanItems = $this->accessiblePlanItemsQuery((int) $deptId, (int) $validated['year'])
            ->where('plan_id', $plan->id)
            ->with('strategicObjective')
            ->get()
            ->keyBy('strategic_objective_id');

        DB::beginTransaction();
        try {
            foreach ($request->input('entries') as $objectiveId => $data) {
                if (!isset($data['actual']) && !isset($data['qt']))
                    continue;

                $planItem = $allowedPlanItems->get((int) $objectiveId);
                if (!$planItem) {
                    continue;
                }
                
                $planItemId = $planItem->id;
                if (!$planItem->strategicObjective)
                    continue;
                $monthTargetColumn = strtolower($validated['month']) . '_target';
                if (is_null($planItem->{$monthTargetColumn})) {
                    continue;
                }

                // Parent numbers are derived after the leaf entries are saved.
                if ($planItem->strategicObjective->children()->exists()
                    && $planItem->strategicObjective->summary_type !== 'manual_override') {
                    $existing = WeeklyPerformance::where('plan_item_id', $planItem->id)
                        ->where('year', $validated['year'])->where('month', $month)
                        ->where('week_number', $validated['week'])->first();
                    if ($existing) {
                        $existing->update(['notes' => $data['notes'] ?? $existing->notes]);
                    }
                    continue;
                }

                $actual_input = $data['actual'] ?? null;
                $metadata = $data['metadata'] ?? [];

                // 1. Calculate Actual based on Template
                if ($planItem->strategicObjective->template_type === 'machinery') {
                    $qty = (float) ($metadata['qty'] ?? 0);
                    $time = (float) ($metadata['time'] ?? 10);
                    $days = (float) ($metadata['days'] ?? 7);
                    $actual = FidelityCalculator::calculateMachineryPlan($qty, $time, $days);
                } elseif ($planItem->strategicObjective->template_type === 'truck_haulage') {
                    $count = (float) ($metadata['count'] ?? 0);
                    $cycle = (float) ($metadata['cycle'] ?? 0);
                    $tone = (float) ($metadata['tone'] ?? 40);
                    $actual = FidelityCalculator::calculateTruckPlan($count, $cycle, $tone);
                } elseif ($planItem->strategicObjective->isTemporal() && is_string($actual_input)) {
                    $actual = FidelityCalculator::parseComplexTemporal($actual_input);
                } else {
                    $rawActual = (float) $actual_input;
                    $actual = FidelityCalculator::scaleRevenueValue($rawActual, $planItem->strategicObjective->unit);
                }

                $planValue = (float) ($data['plan_value'] ?? 0);
                if ($planItem->strategicObjective->weekly_plan_mode === 'repeat') {
                    $planValue = (float) ($planItem->{$monthTargetColumn} ?? 0);
                }
                $percentage = $planItem->strategicObjective->calculateWeeklyAchievement($actual, $planValue);

                // Manual Override Logic (e.g. RoRo 100%)
                if ($planItem->strategicObjective->summary_type === 'manual_override' && $actual > 0) {
                    $percentage = 100;
                }

                $weekly = WeeklyPerformance::updateOrCreate(
                    [
                        'plan_item_id' => $planItemId,
                        'year' => (int) $validated['year'],
                        'month' => $month,
                        'week_number' => (int) $validated['week'],
                    ],
                    [
                        'period_start' => $weekInfo['start_full'],
                        'period_end' => $weekInfo['end_full'],
                        'due_at' => $weekInfo['due_at'],
                        'planned_value' => $planValue,
                        'actual_value' => $actual,
                        'metadata' => $metadata,
                        'percentage' => $percentage,
                        'status' => $this->legacyStatusForWorkflow($currentWorkflowStatus),
                        'workflow_status' => $currentWorkflowStatus,
                        'created_by' => auth()->id(),
                        'notes' => $data['notes'] ?? null,
                    ]
                );

                // Handle Station Matrix Data if present
                if (isset($data['stations'])) {
                    foreach ($data['stations'] as $stationName => $stationActual) {
                        $weekly->stationData()->updateOrCreate(
                            ['station_name' => $stationName],
                            [
                                'planned_value' => 0,
                                'actual_value' => (float) $stationActual,
                            ]
                        );
                    }
                }
            }

            // Save General Performance Remarks
            if ($request->has('remarks')) {
                // Delete existing ones for this specific week/month/year/dept to refresh
                \App\Models\PerformanceRemark::where([
                    'department_id' => $deptId,
                    'year' => $request->year,
                    'month' => strtolower($request->month),
                    'week_number' => $request->week,
                ])->delete();

                foreach ($request->remarks as $remarkData) {
                    if (!empty($remarkData['text'])) {
                        \App\Models\PerformanceRemark::create([
                            'department_id' => $deptId,
                            'year' => $request->year,
                            'month' => strtolower($request->month),
                            'week_number' => $request->week,
                            'remark_type' => $remarkData['type'] ?? 'general',
                            'remark_text' => $remarkData['text'],
                        ]);
                    }
                }
            }

            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store("performance_evidence/{$deptId}/{$validated['year']}/weekly/" . strtolower($validated['month']) . "/week-{$validated['week']}", 'local');
                \App\Models\PerformanceAttachment::create([
                    'department_id' => $deptId,
                    'year' => $validated['year'],
                    'month' => strtolower($validated['month']),
                    'week_number' => $validated['week'],
                    'frequency' => 'weekly',
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $user->id,
                ]);
            }

            // 2. Post-Save Rollups (Parent Summaries)
            $this->performParentRollups(
                $request->year,
                $request->month,
                $request->week,
                $currentWorkflowStatus,
                auth()->id(),
                $plan->id
            );

            DB::commit();
            return back()->with('success', 'Weekly performance saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'Error saving weekly performance: ' . $e->getMessage());
        }
    }

    /**
     * Perform hierarchical rollups for main objectives.
     */
    private function performParentRollups($year, $month, $week, string $workflowStatus = 'draft', ?int $createdBy = null, ?int $planId = null)
    {
        // 1. Fetch parents with children that have entries
        $parents = StrategicObjective::whereHas('children')
            ->where(fn ($query) => $query
                ->whereHas('planItems', fn ($items) => $items->where('plan_id', $planId))
                ->orWhereHas('children.planItems', fn ($items) => $items->where('plan_id', $planId))
                ->orWhereHas('children.children.planItems', fn ($items) => $items->where('plan_id', $planId)))
            ->get()
            ->sortByDesc(fn ($parent) => $parent->getHierarchyLevel());

        foreach ($parents as $parent) {
            if ($parent->summary_type === 'manual_override') {
                continue;
            }

            $childObjectiveIds = $parent->rollupChildren()->pluck('id');
            $childPlanItemIds = PlanItem::where('plan_id', $planId)
                ->forReportingFrequency('weekly')
                ->whereIn('strategic_objective_id', $childObjectiveIds)->pluck('id');

            // Get entries specifically for this period, keyed by objective ID
            $childrenEntries = WeeklyPerformance::whereIn('plan_item_id', $childPlanItemIds)
                ->where('year', $year)
                ->where('month', strtolower($month))
                ->where('week_number', $week)
                ->get()
                ->keyBy('planItem.strategic_objective_id');

            if ($childrenEntries->isEmpty()) {
                continue; // No child entries this week, so nothing to rollup
            }

            // 2. Perform math based on summary_type
            if ($parent->summary_type === 'weighted') {
                // Weekly reporting is operational attainment only. Weights are
                // applied later when the month/quarter is scored.
                $planned = 100.0;
                $actual = (float) ($childrenEntries->avg('percentage') ?? 0);
            } else {
                $actual = FidelityCalculator::rollup($parent, $childrenEntries, $parent->summary_type);
                $planned = $parent->summary_type === 'average'
                    ? (float) $childrenEntries->avg('planned_value')
                    : (float) $childrenEntries->sum('planned_value');
            }

            $percentage = $parent->calculateWeeklyAchievement($actual, $planned);

            // Find or create PlanItem for the parent if it doesn't exist (needed for performance tracking)
            $parentPlanItem = PlanItem::firstOrCreate([
                'plan_id' => $childrenEntries->first()->planItem->plan_id,
                'strategic_objective_id' => $parent->id,
            ], [
                'annual_target' => 0, // Placeholder
            ]);

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
        }
    }

    /**
     * Show the read-only Weekly Performance Report (like the images provided).
     */
    public function report(Request $request, $year, $month, $week)
    {
        $user = auth()->user();
        $isAdmin = $user->isSystemAdmin();
        $canSelectDepartment = $isAdmin
            || $user->hasPermission('view_all_performance')
            || $user->hasPermission('review_office_performance')
            || $user->hasPermission('final_approve_office_performance')
            || $user->hasPermission('view_final_approved_records');
        $deptId  = $request->get('department_id', null);

        if (!$canSelectDepartment) {
            $deptId = auth()->user()->department_id;
        }

        $dept = $deptId ? \App\Models\Department::find($deptId) : null;

        if ($canSelectDepartment && !$deptId) {
            return redirect()->route('weekly.dashboard')
                ->with('error', 'Please select a department from the dashboard to view its report.');
        }

        $plan = Plan::where('year', $year)
            ->where('department_id', $deptId)
            ->where('status', 'approved')
            ->first();

        if (!$plan) {
            return redirect()->route('weekly.dashboard', ['year' => $year, 'department_id' => $deptId])
                ->with('error', "No approved plan found for Year $year for the selected department.");
        }

        // Fetch existing entries for this period
        $periodEntries = $this->periodEntriesQuery((int) $deptId, (int) $year, strtolower($month), (int) $week)
            ->with(['planItem', 'stationData', 'submittedBy', 'directorateReviewer', 'chiefReviewer', 'planReportReviewer', 'headOfficeReviewer'])
            ->get();

        if (!$this->canUserViewPeriod($user, $periodEntries->first()?->workflow_status, $periodEntries->isNotEmpty())) {
            return redirect()->route('weekly.dashboard', ['year' => $year, 'department_id' => $deptId])
                ->with('error', 'This weekly period is not currently assigned to your role.');
        }

        $entries = $periodEntries->keyBy('plan_item_id');
        $workflowState = $this->buildWorkflowState($periodEntries->first());
        $workflowActions = $this->availableWorkflowActions($user, $workflowState['key']);

        if ($user->hasPermission('view_final_approved_records') && !in_array($workflowState['key'], ['reviewed_no_correction_required','approved_by_head_office'], true)) {
            abort(403, 'Head office can only view final approved performance reports.');
        }

        // Load hierarchical objectives
        $planItems = $plan->items()->forReportingFrequency($this->operationalFrequency((int) $deptId))->get()->keyBy('strategic_objective_id');
        $planItemObjectiveIds = $planItems->keys()->toArray();

        $objectives = StrategicObjective::with(['children', 'children.children'])
            ->whereNull('parent_id')
            ->orderByNaturalCode()
            ->get()
            ->filter(function ($topObj) use ($planItemObjectiveIds) {
                if (in_array($topObj->id, $planItemObjectiveIds)) return true;
                foreach ($topObj->children as $child) {
                    if (in_array($child->id, $planItemObjectiveIds)) return true;
                    foreach ($child->children as $gc) {
                        if (in_array($gc->id, $planItemObjectiveIds)) return true;
                    }
                }
                return false;
            });

        $objectives = $this->pruneObjectiveTree($objectives, array_flip($planItemObjectiveIds));
        $monthColumn = strtolower($month) . '_target';

        // Build flat report rows for the view
        $reportRows = [];
        $sectionCounter = 0;

        foreach ($objectives as $topObj) {
            $sectionCounter++;
            $topItem   = $planItems->get($topObj->id);
            $topEntry  = $topItem ? ($entries->get($topItem->id) ?? null) : null;

            $reportRows[] = [
                'sno'            => $sectionCounter . '.',
                'level'          => 0,
                'label'          => $topObj->description,
                'weight'         => $topObj->weight,
                'baseline'       => $topObj->baseline_value,
                'unit'           => $topObj->unit ?? '',
                'is_reference'   => (bool) $topObj->is_reference_only,
                'is_parent'      => true,
                'plan'           => $topEntry ? $topEntry->planned_value : null,
                'actual'         => $topEntry ? $topEntry->actual_value  : null,
                'percentage'     => $topEntry
                    ? ($topObj->summary_type === 'manual_override'
                        ? (float) $topEntry->percentage
                        : $topObj->calculateWeeklyAchievement(
                            (float) $topEntry->actual_value,
                            (float) $topEntry->planned_value
                        ))
                    : null,
            ];

            $childCounter = 0;
            foreach ($topObj->children as $child) {
                if (!$planItems->has($child->id) && $child->children->isEmpty()) continue;
                // Check child or any grandchild in plan
                $inPlan = $planItems->has($child->id) ||
                          $child->children->contains(fn($gc) => $planItems->has($gc->id));
                if (!$inPlan) continue;

                $childCounter++;
                $childItem  = $planItems->get($child->id);
                $childEntry = $childItem ? ($entries->get($childItem->id) ?? null) : null;

                $stationRows = [];
                if ($childEntry && $childEntry->stationData->isNotEmpty()) {
                    foreach ($childEntry->stationData as $sd) {
                        $stationRows[] = [
                            'sno'          => '*' . $sectionCounter . '.' . $childCounter . '.*',
                            'level'        => 2,
                            'label'        => $sd->station_name,
                            'unit'         => $topObj->unit ?? '',
                            'is_reference' => true,
                            'is_parent'    => false,
                            'plan'         => null,
                            'actual'       => $sd->actual_value,
                            'percentage'   => null,
                        ];
                    }
                }

                $reportRows[] = [
                    'sno'          => $sectionCounter . '.' . $childCounter . '.',
                    'level'        => 1,
                    'label'        => $child->description,
                    'weight'       => $childItem ? ($childItem->weight ?: $child->weight) : $child->weight,
                    'baseline'     => $child->baseline_value,
                    'unit'         => $child->unit ?? ($topObj->unit ?? ''),
                    'is_reference' => (bool) $child->is_reference_only,
                    'is_parent'    => false,
                    'plan'         => $childEntry ? $childEntry->planned_value : null,
                    'actual'       => $childEntry ? $childEntry->actual_value  : null,
                    'percentage'   => $childEntry
                        ? ($child->summary_type === 'manual_override'
                            ? (float) $childEntry->percentage
                            : $child->calculateWeeklyAchievement(
                                (float) $childEntry->actual_value,
                                (float) $childEntry->planned_value
                            ))
                        : null,
                ];

                // Append station sub-rows
                foreach ($stationRows as $sr) {
                    $reportRows[] = $sr;
                }

                // Grandchildren
                $gcCounter = 0;
                foreach ($child->children as $gc) {
                    if (!$planItems->has($gc->id)) continue;
                    $gcCounter++;
                    $gcItem  = $planItems->get($gc->id);
                    $gcEntry = $gcItem ? ($entries->get($gcItem->id) ?? null) : null;

                    $reportRows[] = [
                        'sno'          => $sectionCounter . '.' . $childCounter . '.' . $gcCounter,
                        'level'        => 2,
                        'label'        => $gc->description,
                        'weight'       => $gcItem ? ($gcItem->weight ?: $gc->weight) : $gc->weight,
                        'baseline'     => $gc->baseline_value,
                        'unit'         => $gc->unit ?? ($child->unit ?? ''),
                        'is_reference' => (bool) $gc->is_reference_only,
                        'is_parent'    => false,
                        'plan'         => $gcEntry ? $gcEntry->planned_value : null,
                        'actual'       => $gcEntry ? $gcEntry->actual_value  : null,
                        'percentage'   => $gcEntry
                            ? ($gc->summary_type === 'manual_override'
                                ? (float) $gcEntry->percentage
                                : $gc->calculateWeeklyAchievement(
                                    (float) $gcEntry->actual_value,
                                    (float) $gcEntry->planned_value
                                ))
                            : null,
                    ];
                }
            }
        }

        $weekLabel = ucfirst($month) . ' W' . $week . ' ' . $year;
        $attachments = \App\Models\PerformanceAttachment::with('uploader')->where([
            'department_id' => $deptId, 'year' => $year, 'month' => strtolower($month),
            'week_number' => $week, 'frequency' => 'weekly',
        ])->latest()->get();

        $reportFrequency = $this->operationalFrequency((int) $deptId);
        return view('weekly.report', compact(
            'year', 'month', 'week', 'dept', 'deptId',
            'isAdmin', 'plan', 'reportRows', 'weekLabel', 'workflowState', 'workflowActions', 'canSelectDepartment', 'attachments', 'reportFrequency'
        ));
    }

    /**
     * Export the weekly performance report as Excel.
     */
    public function exportWeekly(Request $request, $year, $month, $week)
    {
        $deptId = $request->get('department_id', auth()->user()->department_id);
        $dept   = \App\Models\Department::find($deptId);

        // Reuse report logic
        $reportRequest = new \Illuminate\Http\Request();
        $reportRequest->merge(['department_id' => $deptId]);
        $response = $this->report($reportRequest, $year, $month, $week);

        // If it's a redirect (error), pass it through
        if ($response instanceof \Illuminate\Http\RedirectResponse) {
            return $response;
        }

        // Extract the view data
        $viewData = $response->getData();
        $reportRows = $viewData['reportRows'];
        $weekLabel  = $viewData['weekLabel'];

        // Build simple CSV export since Maatwebsite export class is not defined for weekly yet
        $filename = "Weekly_Report_{$dept->name}_{$month}_W{$week}_{$year}.csv";
        $headers  = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$filename\""];

        $callback = function () use ($reportRows, $weekLabel) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['S.No', 'Activities', 'Weight', 'Unit', 'Baseline', $weekLabel . ' Plan', $weekLabel . ' Performance', '% Achievement']);
            foreach ($reportRows as $row) {
                $indent = str_repeat('    ', $row['level']);
                fputcsv($f, [
                    $row['sno'],
                    $indent . $row['label'],
                    $row['weight'],
                    $row['unit'],
                    $row['baseline'],
                    $row['is_reference'] ? '' : number_format((float)($row['plan'] ?? 0), 3),
                    number_format((float)($row['actual'] ?? 0), 3),
                    $row['is_reference'] ? '' : ($row['percentage'] !== null ? round($row['percentage'], 1) . '%' : ''),
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function periodEntriesQuery(int $deptId, int $year, string $month, ?int $week = null, ?string $frequency = null)
    {
        return WeeklyPerformance::query()
            ->where('year', $year)
            ->where('month', strtolower($month))
            ->when($week !== null, fn($query) => $query->where('week_number', $week))
            ->whereIn('plan_item_id', $this->accessiblePlanItemsQuery($deptId, $year, $frequency ?? $this->operationalFrequency($deptId))->select('plan_items.id'));
    }

    private function operationalFrequency(int $deptId): string
    {
        $frequency = request()->input('frequency');
        if (in_array($frequency, ['daily', 'weekly'], true)) return $frequency;
        return \App\Models\Department::find($deptId)?->isDaily() ? 'daily' : 'weekly';
    }

    private function applyWorkflowVisibility($query, $user)
    {
        $visibleStatuses = $this->visibleWorkflowStatusesFor($user);

        if ($visibleStatuses === null) {
            return $query;
        }

        return $query->where(function ($workflowQuery) use ($visibleStatuses) {
            $workflowQuery->whereIn('workflow_status', $visibleStatuses);

            if (in_array('draft', $visibleStatuses, true)) {
                $workflowQuery->orWhereNull('workflow_status');
            }
        });
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
            'submitted_to_plan_report_directorate' => [
                'key' => 'submitted_to_plan_report_directorate', 'label' => 'Sent to Plan & Report Directorate', 'badge' => 'primary',
                'note' => $entry?->workflow_note, 'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at, 'reviewed_by' => $entry?->planReportReviewer?->name,
                'reviewed_at' => $entry?->plan_report_reviewed_at,
            ],
            'returned_to_plan_report_directorate' => [
                'key' => 'returned_to_plan_report_directorate', 'label' => 'Returned to Plan & Report Directorate', 'badge' => 'warning',
                'note' => $entry?->workflow_note, 'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at, 'reviewed_by' => $entry?->headOfficeReviewer?->name,
                'reviewed_at' => $entry?->head_office_reviewed_at,
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
        // Department-scoping is enforced before this method is called. Once a
        // user may access that department, its periods remain visible as a
        // permanent calendar/history. canUserEditPeriod() and
        // availableWorkflowActions() still restrict editing and approvals to
        // the role currently responsible for the workflow status.
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

    private function accessiblePlanItemsQuery(int $deptId, ?int $year = null, string $frequency = 'weekly')
    {
        return PlanItem::query()->forReportingFrequency($frequency)
            ->whereHas('plan', function ($query) use ($deptId, $year) {
                $query->where('status', 'approved')
                    ->where('department_id', $deptId)
                    ->when($year !== null, fn ($planQuery) => $planQuery->where('year', $year));
            })
            ->whereHas('strategicObjective', fn ($query) => $query->where('status', 'approved'))
            ->approvedForDepartment($deptId);
    }

    private function pruneObjectiveTree($objectives, array $allowedObjectiveIds)
    {
        return $objectives->filter(function (StrategicObjective $objective) use ($allowedObjectiveIds) {
            $hadChildren = $objective->children->isNotEmpty();
            $objective->setRelation('children', $this->pruneObjectiveTree($objective->children, $allowedObjectiveIds));

            if ($hadChildren && $objective->children->isEmpty() && $objective->summary_type !== 'manual_override') return false;

            return isset($allowedObjectiveIds[$objective->id]) || $objective->children->isNotEmpty();
        })->values();
    }

    private function ensureWorkflowAllowed(bool $condition, string $message): void
    {
        abort_unless($condition, Response::HTTP_FORBIDDEN, $message);
    }

    private function ensureSubmissionWindowOpen(): void
    {
        $start = \App\Models\Setting::get('performance_submission_window_start');
        $end = \App\Models\Setting::get('performance_submission_window_end');

        if (!$start || !$end) {
            return;
        }

        $now = now();
        $startAt = \Illuminate\Support\Carbon::parse($start);
        $endAt = \Illuminate\Support\Carbon::parse($end);

        abort_unless(
            $now->between($startAt, $endAt),
            Response::HTTP_FORBIDDEN,
            "Department chiefs can only forward performance between {$startAt->format('M d, Y H:i')} and {$endAt->format('M d, Y H:i')}."
        );
    }

    public function transition(Request $request, $year, $month, $week)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'action' => 'required|string|in:submit_to_directorate,return_to_expert,submit_to_chief,return_to_directorate,approve_by_chief,mark_reviewed_no_correction,return_to_department_chief,submit_to_plan_report_directorate,return_to_plan_report_department,submit_to_head_office,return_to_plan_report_directorate,approve_by_head_office',
            'workflow_note' => 'nullable|string|max:2000',
        ]);

        $entries = $this->periodEntriesQuery(
            (int) $validated['department_id'],
            (int) $year,
            strtolower($month),
            (int) $week
        )->get();

        if ($entries->isEmpty()) {
            return back()->with('error', 'No weekly performance entries were found for this period.');
        }

        $user = auth()->user();
        $currentStatus = $entries->first()->workflow_status ?? 'draft';
        $now = now();
        $action = $validated['action'];
        $updates = [
            'workflow_note' => $validated['workflow_note'] ?? null,
        ];

        switch ($action) {
            case 'submit_to_directorate':
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentExpert() || $user->isSystemAdmin()) && in_array($currentStatus, ['draft', 'returned_to_expert'], true),
                    'Only department experts can submit draft performance to directorate.'
                );
                $updates += [
                    'workflow_status' => 'submitted_to_directorate',
                    'status' => 'submitted',
                    'submitted_by' => $user->id,
                    'submitted_at' => $now,
                ];
                $success = 'Performance submitted to directorate for review.';
                break;

            case 'return_to_expert':
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentDirectorate() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_directorate',
                    'Only department directorates can return submitted performance to experts.'
                );
                $updates += [
                    'workflow_status' => 'returned_to_expert',
                    'status' => 'draft',
                    'directorate_reviewed_by' => $user->id,
                    'directorate_reviewed_at' => $now,
                ];
                $success = 'Performance returned to expert for correction.';
                break;

            case 'submit_to_chief':
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentDirectorate() || $user->isSystemAdmin()) && in_array($currentStatus, ['submitted_to_directorate', 'returned_to_directorate'], true),
                    'Only department directorates can review and forward performance to chief.'
                );
                $updates += [
                    'workflow_status' => 'submitted_to_chief',
                    'status' => 'submitted',
                    'directorate_reviewed_by' => $user->id,
                    'directorate_reviewed_at' => $now,
                ];
                $success = 'Performance reviewed and sent to chief.';
                break;

            case 'return_to_directorate':
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentChief() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_chief',
                    'Only department chiefs can return performance to directorate.'
                );
                $updates += [
                    'workflow_status' => 'returned_to_directorate',
                    'status' => 'draft',
                    'chief_reviewed_by' => $user->id,
                    'chief_reviewed_at' => $now,
                ];
                $success = 'Performance returned to directorate for correction.';
                break;

            case 'approve_by_chief':
                // A missed deadline never closes the original reporting week.
                // Timeliness is measured separately from the approval workflow.
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentChief() || $user->isSystemAdmin()) && in_array($currentStatus, ['submitted_to_chief','returned_to_department_chief'], true),
                    'Only department chiefs can approve reviewed performance.'
                );
                $updates += [
                    'workflow_status' => 'submitted_to_plan_report_department',
                    'status' => 'submitted',
                    'chief_reviewed_by' => $user->id,
                    'chief_reviewed_at' => $now,
                ];
                $success = $currentStatus === 'returned_to_department_chief' ? 'Corrected performance sent back to the Plan and Report Expert.' : 'Performance approved by chief and sent to the plan and report department.';
                break;

            case 'return_to_department_chief':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportHeadOffice() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_head_office',
                    'Only the Plan and Report Head can return an escalated correction to the department chief.'
                );
                $this->ensureWorkflowAllowed(filled($validated['workflow_note'] ?? null), 'A correction comment is required.');
                $updates += [
                    'workflow_status' => 'returned_to_department_chief',
                    'status' => 'draft',
                    'head_office_reviewed_by' => $user->id,
                    'head_office_reviewed_at' => $now,
                    'approved_at' => $now,
                ];
                $success = 'Performance returned to department chief for correction.';
                break;

            case 'mark_reviewed_no_correction':
                $this->ensureWorkflowAllowed(($user->isPlanReportDepartmentExpert() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_plan_report_department', 'Only the Plan and Report Expert can complete this review.');
                $updates += ['workflow_status' => 'submitted_to_plan_report_directorate', 'status' => 'submitted', 'plan_report_reviewed_by' => $user->id, 'plan_report_reviewed_at' => $now];
                $success = 'Performance confirmed with no correction required and sent to the Plan and Report Directorate.';
                break;

            case 'submit_to_plan_report_directorate':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportDepartmentExpert() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_plan_report_department',
                    'Only plan and report department experts can forward performance to the plan and report directorate.'
                );
                $this->ensureWorkflowAllowed(filled($validated['workflow_note'] ?? null), 'A correction comment is required before escalation.');
                $updates += [
                    'workflow_status' => 'submitted_to_plan_report_directorate',
                    'status' => 'submitted',
                    'plan_report_reviewed_by' => $user->id,
                    'plan_report_reviewed_at' => $now,
                ];
                $success = 'Correction request sent to the Plan and Report Directorate.';
                break;

            case 'return_to_plan_report_department':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportDirectorate() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_plan_report_directorate',
                    'Only the plan and report directorate can return performance to the plan and report expert.'
                );
                $updates += [
                    'workflow_status' => 'returned_to_plan_report_department',
                    'status' => 'draft',
                ];
                $success = 'Performance returned to the plan and report expert for correction.';
                break;

            case 'submit_to_head_office':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportDirectorate() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_plan_report_directorate',
                    'Only the plan and report directorate can forward performance to the head of plan and report.'
                );
                $updates += ['workflow_status' => 'submitted_to_head_office', 'status' => 'submitted'];
                $success = 'Performance accepted by the Plan and Report Directorate and sent to Head Office.';
                break;

            case 'return_to_plan_report_directorate':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportHeadOffice() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_head_office',
                    'Only the head of plan and report can return performance to the directorate.'
                );
                $updates += ['workflow_status' => 'returned_to_plan_report_directorate', 'status' => 'draft', 'head_office_reviewed_by' => $user->id, 'head_office_reviewed_at' => $now];
                $success = 'Performance returned to the plan and report directorate for correction.';
                break;

            case 'approve_by_head_office':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportHeadOffice() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_head_office',
                    'Only plan and report head office can grant final approval.'
                );
                $updates += [
                    'workflow_status' => 'approved_by_head_office',
                    'status' => 'submitted',
                    'head_office_reviewed_by' => $user->id,
                    'head_office_reviewed_at' => $now,
                ];
                $success = 'Performance received final head office approval.';
                break;
        }

        $this->periodEntriesQuery(
            (int) $validated['department_id'],
            (int) $year,
            strtolower($month),
            (int) $week
        )->update($updates);

        app(\App\Services\WorkflowNotificationService::class)->performanceTransition(
            $action,
            (int) $validated['department_id'],
            'Weekly ' . ucfirst(strtolower($month)) . " Week {$week}, {$year}",
            route('weekly.report', ['year' => $year, 'month' => strtolower($month), 'week' => $week, 'department_id' => $validated['department_id']]),
            $user
        );

        // Every successful workflow action transfers ownership of the period
        // to the next role. Redirecting back to the entry page made the former
        // owner immediately fail the role-visibility check and see a misleading
        // "not assigned" error even though the handoff succeeded.
        $department = Department::find((int) $validated['department_id']);
        $dashboardRoute = $this->operationalFrequency((int) $validated['department_id']) === 'daily'
            ? 'daily.dashboard'
            : 'weekly.dashboard';

        return redirect()->route($dashboardRoute, [
            'year' => (int) $year,
            'department_id' => (int) $validated['department_id'],
        ])->with('success', $success);
    }

}
