<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\PeriodicPerformance;
use App\Models\WeeklyPerformance;
use App\Models\StrategicObjective;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use App\Services\FidelityCalculator;

class MonthlyAggregationController extends Controller
{
    public function recalculateSharedPeriod(\App\Models\ReportingSubmission $submission): void
    {
        $items = $submission->plan->items()->forReportingFrequency('monthly')->with('strategicObjective.children')->get();
        $this->performParentRollups($items,$submission->year.'-'.$submission->month,$submission->status);
    }
    protected $calculator;

    public function __construct(\App\Services\ScoreCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Show the Monthly Performance Dashboard.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isSystemAdmin();
        $canSelectDepartment = $this->canSelectDepartment($user);
        $userDept = $user->department;
        
        $latestPlanYear = Plan::where('status', 'approved')->max('year') ?? date('Y');
        $year = (int) $request->get('year', $latestPlanYear);

        $deptId = $request->get('department_id', null);

        // If not admin, force their own department
        if (!$canSelectDepartment) {
            $deptId = auth()->user()->department_id;
            $dept = $userDept;
        } else {
            $dept = $deptId ? \App\Models\Department::find($deptId) : null;
        }

        // Fetch departments for the admin dropdown (excluding weekly ones if we want to follow strict boundaries, or just all)
        $monthlyDepartments = $canSelectDepartment
            ? \App\Models\Department::withReportingFrequency('monthly')->orderBy('name')->get() 
            : null;

        $plan = null;
        $allMonths = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];
        $months = [];
        $workflowSummaries = [];
        $hasMonthlyData = [];

        if ($deptId) {
            $plan = Plan::where('year', $year)
                ->where('department_id', $deptId)
                ->where('status', 'approved')
                ->first();
                
            if ($plan) {
                $planItemIds = $this->accessiblePlanItemsQuery((int) $deptId, (int) $year)->pluck('plan_items.id');
                $months = $allMonths;

                foreach ($allMonths as $month) {
                    $periodEntries = $this->periodEntriesQuery($deptId, $year, $month)
                        ->with(['submittedBy', 'directorateReviewer', 'chiefReviewer', 'planReportReviewer', 'headOfficeReviewer'])
                        ->get();

                    $weeklyExists = WeeklyPerformance::whereIn('plan_item_id', $planItemIds)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->exists();

                    $hasMonthlyData[$month] = $periodEntries->isNotEmpty() || $weeklyExists;
                    $workflowSummaries[$month] = $this->buildWorkflowState($periodEntries->first());
                }
            }
        }

        $years = \App\Services\CalendarHelper::getAppYears();

        return view('monthly.dashboard', compact('year', 'months', 'plan', 'years', 'deptId', 'dept', 'isAdmin', 'monthlyDepartments', 'canSelectDepartment', 'workflowSummaries', 'hasMonthlyData'));
    }

    /**
     * Show the detailed Monthly Report for a specific period.
     */
    public function report(Request $request, $year, $month)
    {
        $month = strtolower($month);
        $monthColumn = $month . '_target';

        $user = auth()->user();
        $isAdmin = $user->isSystemAdmin();
        $canSelectDepartment = $this->canSelectDepartment($user);
        $deptId = $request->get('department_id');

        // Find the active approved plan for the user's department or selected admin department
        $planQuery = Plan::where('year', $year)->where('status', 'approved');
        
        if (!$canSelectDepartment) {
            $planQuery->where('department_id', auth()->user()->department_id);
            $deptId = auth()->user()->department_id;
        } elseif ($deptId) {
            $planQuery->where('department_id', $deptId);
        }
        
        $plan = $planQuery->with(['items.strategicObjective', 'items.performances', 'items.weeklyPerformances'])->first();

        if (!$plan) {
            return redirect()->route('monthly.dashboard')->with('error', "No approved plan found for $year.");
        }

        // Fetch hierarchical objectives, starting with root parents
        $objectives = StrategicObjective::with(['children', 'children.children'])
            ->whereNull('parent_id')
            ->orderByNaturalCode()
            ->get();

        // Get PlanItems for this plan
        $planItems = $plan->items->keyBy('strategic_objective_id');

        // Filter objectives so we only show top-level objectives that have SOME child/grandchild present in this plan
        $planItemObjectiveIds = $planItems->keys()->toArray();
        
        $objectives = $objectives->filter(function ($topObj) use ($planItemObjectiveIds) {
            if (in_array($topObj->id, $planItemObjectiveIds)) return true;
            foreach ($topObj->children as $child) {
                if (in_array($child->id, $planItemObjectiveIds)) return true;
                foreach ($child->children as $grandchild) {
                    if (in_array($grandchild->id, $planItemObjectiveIds)) return true;
                }
            }
            return false;
        });

        // Check if month is certified
        $closure = \App\Models\PeriodClosure::where('year', $year)
            ->where('month', $month)
            ->where('department_id', $plan->department_id)
            ->first();

        $calculator = $this->calculator;
        $effectiveWeights = app(\App\Services\PeriodWeightNormalizer::class)
            ->forPeriod($plan->items, $month);
        $objectiveRollups = $objectives->mapWithKeys(function ($objective) use ($planItems, $effectiveWeights, $calculator, $month) {
            $descendants = $objective->children->flatMap(fn ($child) => collect([$child])->merge($child->children));
            $descendantIds = $descendants->pluck('id')->map(fn ($id) => (int) $id);
            $leafItems = $descendants->filter(function ($candidate) use ($planItems, $descendantIds) {
                if ($candidate->is_reference_only || $candidate->exclude_from_overall_score) return false;
                if (!$planItems->has($candidate->id)) return false;
                return !$candidate->children->contains(fn ($child) => $descendantIds->contains((int) $child->id) && $planItems->has($child->id));
            })->map(fn ($candidate) => $planItems->get($candidate->id));

            $planned = $leafItems->filter(fn ($item) => !is_null($effectiveWeights[$item->id] ?? null));
            $possibleWeight = (float) $planned->sum(fn ($item) => (float) $effectiveWeights[$item->id]);
            $weightedScore = (float) $planned->sum(function ($item) use ($effectiveWeights, $calculator, $month) {
                $achievement = $calculator->calculateTargetAchievement($item, $month);
                $weight = (float) $effectiveWeights[$item->id];
                return min(($achievement / 100) * $weight, $weight);
            });
            $rawScore = (float) $planned->sum(function ($item) use ($effectiveWeights, $calculator, $month) {
                return ($calculator->calculateRawTargetAchievement($item, $month) / 100) * (float) $effectiveWeights[$item->id];
            });
            $rawAchievement = $possibleWeight > 0 ? ($rawScore / $possibleWeight) * 100 : null;
            $achievement = $possibleWeight > 0 ? ($weightedScore / $possibleWeight) * 100 : null;

            return [$objective->id => [
                'weight' => $possibleWeight,
                'weighted_score' => $weightedScore,
                'raw_achievement' => $rawAchievement,
                'achievement' => $achievement,
                'status' => $calculator->performanceStatus($rawAchievement),
                'planned_count' => $planned->count(),
                'total_count' => $leafItems->count(),
            ]];
        });
        $missingWeightItems = $plan->items->filter(fn ($item) =>
            $item->getAttribute($monthColumn) !== null && is_null($effectiveWeights[$item->id] ?? null)
            && $item->strategicObjective && !$item->strategicObjective->is_reference_only
            && !$item->strategicObjective->exclude_from_overall_score
        );
        $weeks = \App\Services\CalendarHelper::getMonthWeeks($month, $year);
        $weekCount = count($weeks);
        $completeness = app(\App\Services\ReportCompleteness::class)->forPlan($plan, 'month:' . $month);

        $periodEntries = $this->periodEntriesQuery((int) $plan->department_id, (int) $year, $month)
            ->with(['submittedBy', 'directorateReviewer', 'chiefReviewer', 'planReportReviewer', 'headOfficeReviewer'])
            ->get();

        if (!$this->canUserViewPeriod($user, $periodEntries->first()?->workflow_status, $periodEntries->isNotEmpty())) {
            return redirect()->route('monthly.dashboard', ['year' => $year, 'department_id' => $plan->department_id])
                ->with('error', 'This monthly period is not currently assigned to your role.');
        }

        $workflowState = $this->buildWorkflowState($periodEntries->first());
        $workflowActions = $this->availableWorkflowActions($user, $workflowState['key']);
        $canEditWorkflow = $this->canUserEditPeriod($user, $workflowState['key']);

        $attachments = \App\Models\PerformanceAttachment::with('uploader')->where([
            'department_id' => $deptId, 'year' => $year, 'month' => strtolower($month),
            'week_number' => null, 'frequency' => 'monthly',
        ])->latest()->get();
        return view('monthly.report', compact('year', 'month', 'plan', 'objectives', 'planItems', 'monthColumn', 'closure', 'deptId', 'calculator', 'effectiveWeights', 'objectiveRollups', 'missingWeightItems', 'weekCount', 'completeness', 'workflowState', 'workflowActions', 'canEditWorkflow', 'canSelectDepartment', 'attachments'));
    }

    /**
     * Certify and Lock the Month.
     */
    public function certify(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'notes' => 'nullable|string',
        ]);

        abort_unless(auth()->user()->isSystemAdmin() || auth()->user()->isPlanReportHeadOffice(), 403,
            'Only Plan and Report Head Office can certify a monthly report.');

        \App\Models\PeriodClosure::updateOrCreate(
            [
                'year' => $validated['year'],
                'month' => strtolower($validated['month']),
                'department_id' => $validated['department_id'],
            ],
            [
                'certified_by' => auth()->id(),
                'certified_at' => now(),
                'notes' => $validated['notes'] ?? 'Certified via system.',
            ]
        );

        \App\Models\AuditLog::log('month_certified', null, "Certified month {$validated['month']} {$validated['year']} for department {$validated['department_id']}");

        return back()->with('success', 'Month has been successfully certified and locked.');
    }

    public function reopen(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer', 'month' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'reason' => 'required|string|min:5|max:1000',
        ]);
        abort_unless(auth()->user()->isSystemAdmin() || auth()->user()->isPlanReportHeadOffice(), 403,
            'Only Plan and Report Head Office can reopen a certified month.');

        $closure = \App\Models\PeriodClosure::where('year', $validated['year'])
            ->where('month', strtolower($validated['month']))
            ->where('department_id', $validated['department_id'])->firstOrFail();
        $closure->delete();
        \App\Models\AuditLog::log('month_reopened', null,
            "Reopened {$validated['month']} {$validated['year']} for department {$validated['department_id']}. Reason: {$validated['reason']}");

        return back()->with('success', 'The month was reopened. Approved late weekly reports may now recalculate the monthly result.');
    }

    /**
     * Export the Monthly Report to an Excel template.
     */
    public function export(Request $request, $year, $month, \App\Services\ExcelTemplateService $excelService)
    {
        $month = strtolower($month);
        $monthColumn = $month . '_target';

        $isAdmin = auth()->user()->isSystemAdmin();
        $deptId = $request->get('department_id');

        $planQuery = Plan::where('year', $year)->where('status', 'approved');
        
        if (!$isAdmin) {
            $planQuery->where('department_id', auth()->user()->department_id);
        } elseif ($deptId) {
            $planQuery->where('department_id', $deptId);
        }
        
        $plan = $planQuery->with(['items.strategicObjective', 'items.performances', 'items.weeklyPerformances'])->firstOrFail();

        // Pre-defined mapping for the "October Report" template
        // In a real scenario, this mapping could be stored in a database or config
        $mapping = [
            'B2' => strtoupper($month) . ' ' . $year . ' PERFORMANCE REPORT',
            'B3' => 'Department: ' . $plan->department->name,
            'C5' => now()->format('d/m/Y'),
        ];
        $exportEffectiveWeights = app(\App\Services\PeriodWeightNormalizer::class)
            ->forPeriod($plan->items, strtolower($month));

        // Map plan items to cells based on the template layout (matching the bureau format in image)
        // A: SN, B: Desc, C: Weight, D: Unit, E: Baseline, F: Annual Target, G: July...
        foreach ($plan->items as $index => $item) {
            $rowOffset = 10 + $index;
            $so = $item->strategicObjective;

            // Mapping (Column B: Description, C: Weight, D: Unit)
            $mapping['B' . $rowOffset] = ($so->is_reference_only ? '* ' : '') . $so->description;

            // Weight Logic (Gap 3 + Fallback)
            $weight = $exportEffectiveWeights[$item->id] ?? null;
            if ($so->is_calculated_weight) {
                $weight = $plan->items->filter(function($i) use ($so) {
                    return $i->strategicObjective && $i->strategicObjective->parent_id == $so->id;
                })->sum(function($i) use ($exportEffectiveWeights) {
                    return (float) ($exportEffectiveWeights[$i->id] ?? 0);
                });
            }
            $mapping['C' . $rowOffset] = is_null($weight) ? 'N/A' : $weight;
            $mapping['D' . $rowOffset] = $so->unit ?? '%';

            // Baseline & Annual Target
            $mapping['E' . $rowOffset] = $item->baseline_value;
            $mapping['F' . $rowOffset] = $item->q1_target + $item->q2_target + $item->q3_target + $item->q4_target;

            // Month Column Mapping Logic
            $monthCols = [
                'july' => 'G',
                'august' => 'H',
                'september' => 'I',
                'october' => 'K',
                'november' => 'L',
                'december' => 'M',
                'january' => 'O',
                'february' => 'P',
                'march' => 'Q',
                'april' => 'S',
                'may' => 'T',
                'june' => 'U'
            ];

            $targetCol = $monthCols[$month] ?? 'G';
            $mapping[$targetCol . $rowOffset] = $item->$monthColumn;

            $actualValue = $item->getActualValueForPeriod($month);
            if (!$so->is_reference_only) {
                // Assuming Actual is TargetCol + 1 (e.g. if Target is G, Actual is H)
                // This is common in the Operational sheets shown in images.
                $actualCol = chr(ord($targetCol) + 1);
                $mapping[$actualCol . $rowOffset] = $actualValue;
            }
        }

        try {
            $fileName = "Monthly_Report_{$plan->department->name}_{$month}_{$year}.xlsx";
            $reportPath = $excelService->generateReport('monthly_report_template.xlsx', $mapping, $fileName);

            return response()->download($reportPath)->deleteFileAfterSend();
        } catch (\Exception $e) {
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Show the manual monthly entry form for monthly-reporting departments.
     */
    public function entryForm(Request $request, $year, $month)
    {
        $month = strtolower($month);
        $user = auth()->user();
        $canSelectDepartment = $this->canSelectDepartment($user);
        $deptId = $request->get('department_id');

        if (!$canSelectDepartment) {
            $deptId = $user->department_id;
        } elseif (!$deptId && $user->department_id) {
            $deptId = $user->department_id;
        }

        $dept = $deptId ? Department::find($deptId) : $user->department;

        // Enforce: only monthly-reporting departments can use this form
        if (!$dept || !$dept->hasReportingFrequency('monthly')) {
            return redirect()->route('weekly.dashboard')
                ->with('info', 'Your department uses Weekly reporting. Please use the Weekly Workbench.');
        }

        $plan = Plan::where('year', $year)
            ->where('department_id', $dept->id)
            ->where('status', 'approved')
            ->first();

        if (!$plan) {
            return redirect()->route('monthly.dashboard')
                ->with('error', "No approved plan found for Year $year.");
        }

        $planItems = $this->accessiblePlanItemsQuery((int) $dept->id, (int) $year)->forReportingFrequency('monthly')
            ->with('strategicObjective.children')
            ->get();
        $monthColumn = $month . '_target';
        $calculatedParentTargets = $this->calculatedParentTargets($planItems, $monthColumn);

        // Pre-load any existing entries for the period
        $existing = \App\Models\PeriodicPerformance::where('period', $year . '-' . $month)
            ->whereIn('plan_item_id', $planItems->pluck('id'))
            ->with(['submittedBy', 'directorateReviewer', 'chiefReviewer', 'planReportReviewer', 'headOfficeReviewer'])
            ->get()
            ->keyBy('plan_item_id');

        $closure = \App\Models\PeriodClosure::where('year', $year)
            ->where('month', $month)
            ->where('department_id', $dept->id)
            ->first();

        $months = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];

        // Fetch performance remarks for this period (Monthly - week_number is null)
        $performanceRemarks = \App\Models\PerformanceRemark::where([
            'department_id' => $dept->id,
            'year' => $year,
            'month' => $month,
            'week_number' => null,
        ])->get();

        $workflowState = $this->buildWorkflowState($existing->first());

        if (!$this->canUserViewPeriod($user, $existing->first()?->workflow_status, $existing->isNotEmpty())) {
            return redirect()->route('monthly.dashboard', ['year' => $year, 'department_id' => $dept->id])
                ->with('error', 'This monthly period is not currently assigned to your role.');
        }

        $canEditWorkflow = $this->canUserEditPeriod($user, $workflowState['key']);
        $workflowActions = $this->availableWorkflowActions($user, $workflowState['key']);

        $attachments = \App\Models\PerformanceAttachment::with('uploader')->where([
            'department_id' => $dept->id, 'year' => $year, 'month' => strtolower($month),
            'week_number' => null, 'frequency' => 'monthly',
        ])->latest()->get();
        return view('monthly.entry_form', compact('year', 'month', 'plan', 'planItems', 'monthColumn', 'calculatedParentTargets', 'existing', 'closure', 'months', 'performanceRemarks', 'workflowState', 'canEditWorkflow', 'workflowActions', 'deptId', 'attachments'));
    }

    /**
     * Store the manual monthly performance entries.
     */
    public function entryStore(Request $request, $year, $month)
    {
        $request->validate([
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
        ]);
        $month = strtolower($month);
        $user = auth()->user();
        $canSelectDepartment = $this->canSelectDepartment($user);
        $deptId = $canSelectDepartment ? ($request->input('department_id') ?: $user->department_id) : $user->department_id;
        $dept = Department::find($deptId);

        if (!$dept || !$dept->hasReportingFrequency('monthly')) {
            abort(403, 'Only monthly-reporting departments can submit monthly entries.');
        }

        $plan = Plan::where('year', $year)
            ->where('department_id', $dept->id)
            ->where('status', 'approved')
            ->firstOrFail();

        $entries = $request->input('entries', []);
        $period = $year . '-' . $month;
        $existingEntries = $this->periodEntriesQuery((int) $dept->id, (int) $year, $month)->get();
        $currentWorkflowStatus = $existingEntries->first()->workflow_status ?? 'draft';

        if (!$this->canUserEditPeriod($user, $currentWorkflowStatus)) {
            return back()->with('error', 'This monthly period is currently locked for your role. Use the review workflow actions for the next step.');
        }

        $planItems = $this->accessiblePlanItemsQuery((int) $dept->id, (int) $year)->forReportingFrequency('monthly')
            ->with(['strategicObjective.children'])
            ->get();
        $allowedItems = $planItems->keyBy('id');

        foreach ($entries as $planItemId => $data) {
            $item = $allowedItems->get((int) $planItemId);
            if (!$item || ($item->strategicObjective->children->isNotEmpty()
                && $item->strategicObjective->summary_type !== 'manual_override')) {
                continue;
            }
            if (is_null($item->{strtolower($month) . '_target'})) {
                continue;
            }

            \App\Models\PeriodicPerformance::updateOrCreate(
                ['plan_item_id' => $planItemId, 'period' => $period],
                [
                    'actual_value' => $data['actual_value'] ?? null,
                    'notes'        => $data['notes'] ?? null,
                    'status'       => $this->legacyStatusForWorkflow($currentWorkflowStatus),
                    'workflow_status' => $currentWorkflowStatus,
                ]
            );
        }

        $this->performParentRollups($planItems, $period, $currentWorkflowStatus);

        // Save General Performance Remarks (Monthly)
        if ($request->has('remarks')) {
            \App\Models\PerformanceRemark::where([
                'department_id' => $dept->id,
                'year' => $year,
                'month' => $month,
                'week_number' => null,
            ])->delete();

            foreach ($request->remarks as $remarkData) {
                if (!empty($remarkData['text'])) {
                    \App\Models\PerformanceRemark::create([
                        'department_id' => $dept->id,
                        'year' => $year,
                        'month' => $month,
                        'week_number' => null,
                        'remark_type' => $remarkData['type'] ?? 'general',
                        'remark_text' => $remarkData['text'],
                    ]);
                }
            }
        }

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store("performance_evidence/{$dept->id}/{$year}/monthly/{$month}", 'local');
            \App\Models\PerformanceAttachment::create([
                'department_id' => $dept->id,
                'year' => $year,
                'month' => $month,
                'week_number' => null,
                'frequency' => 'monthly',
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);
        }

        \App\Models\AuditLog::log('monthly_entry_submitted', null, "Monthly entry for {$month} {$year} submitted by dept {$dept->id}");

        return redirect()->route('monthly.dashboard')
            ->with('success', "Monthly report for " . ucfirst($month) . " {$year} submitted successfully.");
    }

    private function performParentRollups($planItems, string $period, string $workflowStatus): void
    {
        $parentItems = $planItems
            ->filter(fn (PlanItem $item) => $item->strategicObjective
                && $item->strategicObjective->children->isNotEmpty()
                && $item->strategicObjective->summary_type !== 'manual_override')
            ->sortByDesc(fn (PlanItem $item) => $item->strategicObjective->getHierarchyLevel());

        foreach ($parentItems as $parentItem) {
            $parent = $parentItem->strategicObjective;
            $childItems = $planItems
                ->where('plan_id', $parentItem->plan_id)
                ->whereIn('strategic_objective_id', $parent->rollupChildren()->pluck('id'));

            $entries = PeriodicPerformance::whereIn('plan_item_id', $childItems->pluck('id'))
                ->where('period', $period)
                ->get();

            if ($entries->isEmpty()) {
                continue;
            }

            $month = str_contains($period, '-') ? substr($period, strpos($period, '-') + 1) : $period;
            $targetColumn = strtolower($month) . '_target';
            $childItemsById = $childItems->keyBy('id');
            $entriesByObjective = $entries->mapWithKeys(function (PeriodicPerformance $entry) use ($childItemsById, $targetColumn) {
                $childItem = $childItemsById->get($entry->plan_item_id);
                if (!$childItem || !$childItem->strategicObjective) {
                    return [];
                }

                $raw = app(\App\Services\EdrPerformanceCalculator::class)->rawAchievement(
                    (float) $entry->actual_value,
                    (float) ($childItem->{$targetColumn} ?? 0),
                    $childItem->strategicObjective->goal_direction ?? 'increase'
                );
                $entry->percentage = min(100, $raw);

                if ($childItem->strategicObjective->summary_type === 'manual_override'
                    && (float) $entry->actual_value > 0) {
                    $entry->percentage = 100;
                }

                return [$childItem->strategic_objective_id => $entry];
            });

            $periodWeightsByItem = app(\App\Services\PeriodWeightNormalizer::class)
                ->forPeriod($childItems, $month);
            $effectiveWeights = $childItems->mapWithKeys(fn (PlanItem $item) => [
                $item->strategic_objective_id => $periodWeightsByItem[$item->id] ?? null,
            ])->all();
            $actual = FidelityCalculator::rollup($parent, $entriesByObjective, $parent->summary_type, $effectiveWeights);

            PeriodicPerformance::updateOrCreate(
                ['plan_item_id' => $parentItem->id, 'period' => $period],
                [
                    'actual_value' => $actual,
                    'status' => $this->legacyStatusForWorkflow($workflowStatus),
                    'workflow_status' => $workflowStatus,
                ]
            );
        }
    }

    private function calculatedParentTargets($planItems, string $monthColumn): array
    {
        $targets = [];

        foreach ($planItems->sortByDesc(fn (PlanItem $item) => $item->strategicObjective?->getHierarchyLevel() ?? 0) as $parentItem) {
            $parent = $parentItem->strategicObjective;
            if (!$parent || $parent->children->isEmpty() || $parent->summary_type === 'manual_override') {
                continue;
            }

            $childItems = $planItems
                ->where('plan_id', $parentItem->plan_id)
                ->whereIn('strategic_objective_id', $parent->rollupChildren()->pluck('id'));

            $childTargets = $childItems->map(fn (PlanItem $item) => $targets[$item->id] ?? $item->{$monthColumn});
            $targets[$parentItem->id] = match ($parent->summary_type) {
                'weighted' => (float) ($parent->rollupChildren()->sum('weight') ?: $parent->weight ?: 100),
                'average' => (float) ($childTargets->avg() ?? 0),
                default => (float) $childTargets->sum(),
            };
        }

        return $targets;
    }

    public function transition(Request $request, $year, $month)
    {
        $month = strtolower($month);
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'action' => 'required|string|in:submit_to_directorate,return_to_expert,submit_to_chief,return_to_directorate,approve_by_chief,mark_reviewed_no_correction,return_to_department_chief,submit_to_plan_report_directorate,return_to_plan_report_department,submit_to_head_office,return_to_plan_report_directorate,approve_by_head_office',
            'workflow_note' => 'nullable|string|max:2000',
        ]);

        $entries = $this->periodEntriesQuery((int) $validated['department_id'], (int) $year, $month)->get();

        if ($entries->isEmpty()) {
            return back()->with('error', 'No monthly performance entries were found for this period.');
        }

        $user = auth()->user();
        $currentStatus = $entries->first()->workflow_status ?? 'draft';
        $now = now();
        $updates = [
            'workflow_note' => $validated['workflow_note'] ?? null,
        ];

        if ($validated['action'] === 'submit_to_directorate') {
            $planForWeights = Plan::with(['items.strategicObjective'])->where([
                'department_id'=>$validated['department_id'], 'year'=>(int)$year, 'status'=>'approved',
            ])->latest('updated_at')->first();
            if ($planForWeights) {
                $resolved = app(\App\Services\PeriodWeightNormalizer::class)->forPeriod($planForWeights->items, $month);
                $missing = $planForWeights->items->filter(fn($item)=>$item->getAttribute($month.'_target') !== null
                    && $item->strategicObjective && !$item->strategicObjective->is_reference_only
                    && !$item->strategicObjective->exclude_from_overall_score
                    && is_null($resolved[$item->id] ?? null));
                if ($missing->isNotEmpty()) {
                    return back()->with('error', 'Weight Distribution Required. The approved manual period weights must be completed before monthly performance can be submitted.');
                }
            }
        }

        switch ($validated['action']) {
            case 'submit_to_directorate':
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentExpert() || $user->isSystemAdmin()) && in_array($currentStatus, ['draft', 'returned_to_expert'], true),
                    'Only department experts can submit draft monthly performance to directorate.'
                );
                $updates += [
                    'workflow_status' => 'submitted_to_directorate',
                    'status' => 'pending',
                    'submitted_by' => $user->id,
                    'submitted_at' => $now,
                ];
                $success = 'Monthly performance submitted to directorate for review.';
                break;
            case 'return_to_expert':
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentDirectorate() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_directorate',
                    'Only department directorates can return submitted monthly performance to experts.'
                );
                $updates += [
                    'workflow_status' => 'returned_to_expert',
                    'status' => 'rejected',
                    'directorate_reviewed_by' => $user->id,
                    'directorate_reviewed_at' => $now,
                ];
                $success = 'Monthly performance returned to expert for correction.';
                break;
            case 'submit_to_chief':
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentDirectorate() || $user->isSystemAdmin()) && in_array($currentStatus, ['submitted_to_directorate', 'returned_to_directorate'], true),
                    'Only department directorates can review and forward monthly performance to chief.'
                );
                $updates += [
                    'workflow_status' => 'submitted_to_chief',
                    'status' => 'pending',
                    'directorate_reviewed_by' => $user->id,
                    'directorate_reviewed_at' => $now,
                ];
                $success = 'Monthly performance reviewed and sent to chief.';
                break;
            case 'return_to_directorate':
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentChief() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_chief',
                    'Only department chiefs can return monthly performance to directorate.'
                );
                $updates += [
                    'workflow_status' => 'returned_to_directorate',
                    'status' => 'rejected',
                    'chief_reviewed_by' => $user->id,
                    'chief_reviewed_at' => $now,
                ];
                $success = 'Monthly performance returned to directorate for correction.';
                break;
            case 'approve_by_chief':
                $this->ensureSubmissionWindowOpen();
                $this->ensureWorkflowAllowed(
                    ($user->isDepartmentChief() || $user->isSystemAdmin()) && in_array($currentStatus, ['submitted_to_chief','returned_to_department_chief'], true),
                    'Only department chiefs can approve monthly performance.'
                );
                $updates += [
                    'workflow_status' => 'submitted_to_plan_report_department',
                    'status' => 'pending',
                    'chief_reviewed_by' => $user->id,
                    'chief_reviewed_at' => $now,
                ];
                $success = $currentStatus === 'returned_to_department_chief' ? 'Corrected monthly performance sent back to the Plan and Report Expert.' : 'Monthly performance approved by chief and sent to the plan and report department.';
                break;
            case 'return_to_department_chief':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportHeadOffice() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_head_office',
                    'Only the Plan and Report Head can return an escalated monthly correction to the department chief.'
                );
                $this->ensureWorkflowAllowed(filled($validated['workflow_note'] ?? null), 'A correction comment is required.');
                $updates += [
                    'workflow_status' => 'returned_to_department_chief',
                    'status' => 'rejected',
                    'head_office_reviewed_by' => $user->id,
                    'head_office_reviewed_at' => $now,
                ];
                $success = 'Monthly performance returned to department chief for correction.';
                break;
            case 'mark_reviewed_no_correction':
                $this->ensureWorkflowAllowed(($user->isPlanReportDepartmentExpert() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_plan_report_department', 'Only the Plan and Report Expert can complete this review.');
                $updates += ['workflow_status' => 'submitted_to_plan_report_directorate', 'status' => 'pending', 'plan_report_reviewed_by' => $user->id, 'plan_report_reviewed_at' => $now];
                $success = 'Monthly performance confirmed with no correction required and sent to the Plan and Report Directorate.';
                break;
            case 'submit_to_plan_report_directorate':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportDepartmentExpert() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_plan_report_department',
                    'Only plan and report department experts can forward monthly performance to the plan and report directorate.'
                );
                $this->ensureWorkflowAllowed(filled($validated['workflow_note'] ?? null), 'A correction comment is required before escalation.');
                $updates += [
                    'workflow_status' => 'submitted_to_plan_report_directorate',
                    'status' => 'pending',
                    'plan_report_reviewed_by' => $user->id,
                    'plan_report_reviewed_at' => $now,
                ];
                $success = 'Monthly correction request sent to the Plan and Report Directorate.';
                break;
            case 'return_to_plan_report_department':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportDirectorate() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_plan_report_directorate',
                    'Only the plan and report directorate can return monthly performance to the plan and report expert.'
                );
                $updates += [
                    'workflow_status' => 'returned_to_plan_report_department',
                    'status' => 'rejected',
                ];
                $success = 'Monthly performance returned to the plan and report expert for correction.';
                break;
            case 'submit_to_head_office':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportDirectorate() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_plan_report_directorate',
                    'Only the plan and report directorate can forward monthly performance to the head of plan and report.'
                );
                $updates += ['workflow_status' => 'submitted_to_head_office', 'status' => 'pending'];
                $success = 'Monthly performance accepted by the Plan and Report Directorate and sent to Head Office.';
                break;
            case 'return_to_plan_report_directorate':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportHeadOffice() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_head_office',
                    'Only the head of plan and report can return monthly performance to the directorate.'
                );
                $updates += ['workflow_status' => 'returned_to_plan_report_directorate', 'status' => 'rejected', 'head_office_reviewed_by' => $user->id, 'head_office_reviewed_at' => $now];
                $success = 'Monthly performance returned to the plan and report directorate for correction.';
                break;
            case 'approve_by_head_office':
                $this->ensureWorkflowAllowed(
                    ($user->isPlanReportHeadOffice() || $user->isSystemAdmin()) && $currentStatus === 'submitted_to_head_office',
                    'Only plan and report head office can grant final monthly approval.'
                );
                $updates += [
                    'workflow_status' => 'approved_by_head_office',
                    'status' => 'approved',
                    'head_office_reviewed_by' => $user->id,
                    'head_office_reviewed_at' => $now,
                    'approved_by' => $user->id,
                    'approved_at' => $now,
                ];
                $success = 'Monthly performance received final head office approval.';
                break;
        }

        $this->periodEntriesQuery((int) $validated['department_id'], (int) $year, $month)->update($updates);

        app(\App\Services\WorkflowNotificationService::class)->performanceTransition(
            $validated['action'],
            (int) $validated['department_id'],
            'Monthly ' . ucfirst($month) . ", {$year}",
            route('monthly.report', ['year' => $year, 'month' => $month, 'department_id' => $validated['department_id']]),
            $user
        );

        return redirect()->route('monthly.dashboard', [
            'year' => (int) $year,
            'department_id' => (int) $validated['department_id'],
        ])->with('success', $success);
    }

    private function periodEntriesQuery(int $deptId, int $year, string $month)
    {
        return PeriodicPerformance::query()
            ->where('period', $this->periodKey($year, $month))
            ->whereIn('plan_item_id', $this->accessiblePlanItemsQuery($deptId, $year)->forReportingFrequency('monthly')->select('plan_items.id'));
    }

    private function periodKey(int $year, string $month): string
    {
        return $year . '-' . strtolower($month);
    }

    private function canSelectDepartment($user): bool
    {
        return $user->isSystemAdmin()
            || $user->hasPermission('view_all_performance')
            || $user->hasPermission('review_office_performance')
            || $user->hasPermission('final_approve_office_performance')
            || $user->hasPermission('view_final_approved_records');
    }

    private function buildWorkflowState(?PeriodicPerformance $entry): array
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
                'key' => $status, 'label' => 'Sent to Plan & Report Directorate', 'badge' => 'primary',
                'note' => $entry?->workflow_note, 'submitted_by' => $entry?->submittedBy?->name,
                'submitted_at' => $entry?->submitted_at, 'reviewed_by' => $entry?->planReportReviewer?->name,
                'reviewed_at' => $entry?->plan_report_reviewed_at,
            ],
            'returned_to_plan_report_directorate' => [
                'key' => $status, 'label' => 'Returned to Plan & Report Directorate', 'badge' => 'warning',
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
        // Keep submitted records visible to their department as permanent
        // history. Editing and workflow buttons remain status/role restricted.
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
        return in_array($workflowStatus, ['reviewed_no_correction_required','approved_by_head_office'], true) ? 'approved' : 'pending';
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

    private function accessiblePlanItemsQuery(int $deptId, ?int $year = null)
    {
        return PlanItem::query()
            ->whereHas('plan', function ($query) use ($deptId, $year) {
                $query->where('status', 'approved')
                    ->where('department_id', $deptId)
                    ->when($year !== null, fn ($planQuery) => $planQuery->where('year', $year));
            })
            ->whereHas('strategicObjective', fn ($query) => $query->where('status', 'approved'))
            ->approvedForDepartment($deptId);
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
}
