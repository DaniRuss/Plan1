<?php

namespace App\Http\Controllers;

use App\Models\EdrReport;
use App\Models\Department;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\EdrAnnex;
use App\Models\AuditLog;
use App\Models\PeriodicPerformance;
use App\Services\ScoreCalculator;
use App\Services\EdrPerformanceCalculator;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\Request;
use App\Exports\ReportPerformanceExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class EdrReportController extends Controller
{
    protected $scoreCalculator;
    protected $edrCalculator;

    public function __construct(ScoreCalculator $scoreCalculator, EdrPerformanceCalculator $edrCalculator, private WorkflowNotificationService $notifications)
    {
        $this->scoreCalculator = $scoreCalculator;
        $this->edrCalculator = $edrCalculator;
    }

    public function index()
    {
        $user = auth()->user();
        $query = EdrReport::with(['department', 'submittedBy'])
            ->orderBy('year', 'desc')
            ->orderBy('reporting_period', 'desc');

        $this->scopeReportsForUser($query, $user);

        $reports = $query->paginate(15);

        // Attach achievement scores for index view
        $reports->getCollection()->transform(function ($report) {
            $data = $this->calculateReportData($report->department_id, $report->year, $report->reporting_period);
            $report->achievement = $this->edrCalculator->summary($data)['achievement'];
            return $report;
        });

        return view('reports.index', compact('reports'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isPlanReportDepartmentExpert() || auth()->user()->isSystemAdmin(), 403);
        $departments = Department::where('is_active', true)
            ->whereHas('plans', fn ($query) => $query->where('status', 'approved'))
            ->orderBy('name')->get();
        $years = Plan::where('status', 'approved')->distinct()->orderByDesc('year')->pluck('year')->map(fn ($year) => (int) $year)->all();
        if ($years === []) $years = \App\Services\CalendarHelper::getAppYears();
        $months = ['july','august','september','october','november','december','january','february','march','april','may','june'];
        $weekCounts = [];
        foreach ($years as $year) {
            foreach ($months as $month) {
                $weekCounts[$year][$month] = count(\App\Services\CalendarHelper::getMonthWeeks($month, (int) $year));
            }
        }
        return view('reports.create', compact('departments', 'years', 'months', 'weekCounts'));
    }

    public function preview(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isSystemAdmin() || $user->isPlanReportDepartmentExpert() || $user->isPlanReportDirectorate() || $user->isPlanReportHeadOffice(), 403);
        $validated = $request->validate([
            'department_id' => ['required', function ($attribute, $value, $fail) {
                if ($value !== 'all' && !Department::whereKey($value)->exists()) $fail('The selected department is invalid.');
            }],
            'year' => 'required|integer',
            'reporting_period' => ['required', 'string', 'regex:/^(Q[1-4]|Annual|month:[a-z]+|week:[a-z]+:[1-6])$/i'],
        ]);
        $deptId = $validated['department_id'];
        $year = (int) $validated['year'];
        $period = $validated['reporting_period'];
        $this->validateCalendarWeek($period, $year);

        $performanceData = $deptId === 'all'
            ? $this->calculateAllDepartmentComparisonData($year, $period)
            : $this->calculateComparisonData((int) $deptId, $year, $period);
        $html = view('reports._performance_table', compact('performanceData', 'period'))->render();

        return response()->json(['html' => $html]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isPlanReportDepartmentExpert() || auth()->user()->isSystemAdmin(), 403);
        $validated = $request->validate([
            'department_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if ($value !== 'all' && !Department::whereKey($value)->exists()) {
                        $fail('The selected department is invalid.');
                    }
                },
            ],
            'reporting_period' => ['required', 'string', 'regex:/^(Q[1-4]|Annual|month:[a-z]+|week:[a-z]+:[1-6])$/i'],
            'year' => 'required|integer',
            'executive_summary' => 'nullable|string',
            'introduction' => 'nullable|string',
            'challenges' => 'nullable|string',
            'solutions' => 'nullable|string',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'best_practices' => 'nullable|string',
            'next_focus_areas' => 'nullable|string',
            'summary_and_result' => 'nullable|string',
            'non_planned_activities' => 'nullable|string',
        ]);
        $this->validateCalendarWeek($validated['reporting_period'], (int) $validated['year']);

        $validated['department_id'] = $validated['department_id'] === 'all'
            ? null
            : (int) $validated['department_id'];

        $report = EdrReport::create($validated + [
            'submitted_by' => auth()->id(),
            'status' => 'draft'
        ]);

        if ($request->hasFile('annexes')) {
            foreach ($request->file('annexes') as $file) {
                $path = $file->store('report_annexes', 'public');
                EdrAnnex::create([
                    'edr_report_id' => $report->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        AuditLog::log('Report drafted', $report);

        return redirect()->route('reports.show', $report)->with('success', 'Report drafted successfully.');
    }

    public function show(EdrReport $report)
    {
        $this->authorizeReportView($report);

        $report->load(['department', 'submittedBy', 'annexes']);
        $period = $report->reporting_period;
        $performanceData = $this->calculateReportData($report->department_id, $report->year, $period);

        return view('reports.show', compact('report', 'performanceData', 'period'));
    }

    public function edit(EdrReport $report)
    {
        $this->authorizeReportEdit($report);
        $departments = Department::orderBy('name')->get();
        $years = range(now()->year, now()->year - 2);
        return view('reports.edit', compact('report', 'departments', 'years'));
    }

    public function update(Request $request, EdrReport $report)
    {
        $this->authorizeReportEdit($report);
        $validated = $request->validate([
            'executive_summary' => 'nullable|string',
            'introduction' => 'nullable|string',
            'challenges' => 'nullable|string',
            'solutions' => 'nullable|string',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'best_practices' => 'nullable|string',
            'next_focus_areas' => 'nullable|string',
            'summary_and_result' => 'nullable|string',
            'non_planned_activities' => 'nullable|string',
        ]);

        $report->update($validated);

        if ($request->hasFile('annexes')) {
            foreach ($request->file('annexes') as $file) {
                $path = $file->store('report_annexes', 'public');
                EdrAnnex::create([
                    'edr_report_id' => $report->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        AuditLog::log('Report updated', $report);

        return redirect()->route('reports.show', $report)->with('success', 'Report updated successfully.');
    }

    public function submit(EdrReport $report)
    {
        abort_unless(auth()->user()->isPlanReportDepartmentExpert() && $report->status === 'draft', 403);
        $report->update(['status' => 'submitted_to_plan_report_directorate']);
        $this->notifications->roles('plan_report_directorate', 'Report awaiting review', 'A performance report was sent for Directorate review.', route('reports.show', $report), 'info', null, auth()->user());
        AuditLog::log('Report submitted to Plan and Report Directorate', $report);
        return back()->with('success', 'Report sent to Plan and Report Directorate.');
    }

    public function forwardToHead(EdrReport $report)
    {
        abort_unless(auth()->user()->isPlanReportDirectorate() && $report->status === 'submitted_to_plan_report_directorate', 403);
        $report->update(['status' => 'submitted_to_plan_report_head', 'reviewed_by' => auth()->id()]);
        $this->notifications->roles('plan_report_head_office', 'Report awaiting approval', 'A performance report was reviewed and sent for final approval.', route('reports.show', $report), 'info', null, auth()->user());
        AuditLog::log('Report sent to Plan and Report Head', $report);
        return back()->with('success', 'Report sent to Plan and Report Head.');
    }

    public function approve(EdrReport $report)
    {
        abort_unless(auth()->user()->isPlanReportHeadOffice() && $report->status === 'submitted_to_plan_report_head', 403);
        $report->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        AuditLog::log('Report approved', $report);
        return back()->with('success', 'Report approved successfully.');
    }

    private function authorizeReportEdit(EdrReport $report): void
    {
        $user = auth()->user();
        if ($user->isSystemAdmin()) return;
        abort_unless(
            ($user->isPlanReportDepartmentExpert() && $report->status === 'draft') ||
            ($user->isPlanReportDirectorate() && $report->status === 'submitted_to_plan_report_directorate') ||
            ($user->isPlanReportHeadOffice() && $report->status === 'submitted_to_plan_report_head'), 403
        );
    }

    private function authorizeReportView(EdrReport $report): void
    {
        $query = EdrReport::query()->whereKey($report->id);
        $this->scopeReportsForUser($query, auth()->user());
        abort_unless($query->exists(), 403);
    }

    private function scopeReportsForUser($query, \App\Models\User $user): void
    {
        if ($user->isSystemAdmin() || $user->isPlanReportDepartmentExpert()) {
            return;
        }

        if ($user->isPlanReportDirectorate()) {
            $query->whereIn('status', ['submitted_to_plan_report_directorate', 'submitted_to_plan_report_head', 'approved']);
            return;
        }

        if ($user->isPlanReportHeadOffice()) {
            $query->whereIn('status', ['submitted_to_plan_report_head', 'approved']);
            return;
        }

        if ($user->hasPermission('view_final_approved_records')) {
            $query->where('status', 'approved');
            return;
        }

        if ($user->hasPermission('view_all_reports')) {
            return;
        }

        $query->whereNotNull('department_id')
            ->where('department_id', $user->department_id);
    }

    public function exportPdf(EdrReport $report)
    {
        $this->authorizeReportView($report);
        $period = $report->reporting_period;
        $performanceData = $this->calculateReportData($report->department_id, $report->year, $period);
        $pdf = Pdf::loadView('reports.pdf', compact('report', 'performanceData', 'period'))
            ->setPaper('a4', 'landscape');
        return $pdf->download("EDR_Performance_Report_{$report->id}.pdf");
    }

    public function exportExcel(EdrReport $report)
    {
        $this->authorizeReportView($report);
        $period = $report->reporting_period;
        $performanceData = $this->calculateReportData($report->department_id, $report->year, $period);
        $periodLabel = strtoupper($period) . ' ' . $report->year;
        return Excel::download(new ReportPerformanceExport($performanceData, $periodLabel), "Performance_Report_{$period}_{$report->year}.xlsx");
    }

    public function exportCsv(EdrReport $report)
    {
        $this->authorizeReportView($report);
        $period = $report->reporting_period;
        $performanceData = $this->calculateReportData($report->department_id, $report->year, $period);
        $periodLabel = strtoupper($period) . ' ' . $report->year;
        return Excel::download(new ReportPerformanceExport($performanceData, $periodLabel), "Performance_Report_{$period}_{$report->year}.csv", \Maatwebsite\Excel\Excel::CSV);
    }

    public function calculateComparisonData($deptId, $year, $reportingPeriod)
    {
        $plan = Plan::where('department_id', $deptId)
            ->where('year', $year)
            ->where('status', 'approved')
            ->with(['items.strategicObjective', 'items.performances', 'items.weeklyPerformances'])
            ->first();

        if (!$plan) return [];

        $results = [];
        $completeness = app(\App\Services\ReportCompleteness::class)->forPlan($plan, $reportingPeriod);
        $reportingPeriodLower = strtolower($reportingPeriod);
        $periodParts = explode(':', $reportingPeriodLower);
        $isMonthly = ($periodParts[0] ?? null) === 'month' && isset($periodParts[1]);
        $isWeekly = ($periodParts[0] ?? null) === 'week' && isset($periodParts[1], $periodParts[2]);
        $selectedMonth = ($isMonthly || $isWeekly) ? $periodParts[1] : null;
        $selectedWeek = $isWeekly ? (int) $periodParts[2] : null;
        $isAnnual = $reportingPeriodLower === 'annual';
        $quarters = ['q1', 'q2', 'q3', 'q4'];

        $periodIdx = array_search($reportingPeriodLower, $quarters);
        if ($periodIdx === false && !$isAnnual && !$isMonthly && !$isWeekly) $periodIdx = 0;
        $weightPeriod = ($isMonthly || $isWeekly) ? $selectedMonth : ($isAnnual ? 'annual' : $reportingPeriodLower);
        if ($isWeekly) {
            $plan->setRelation('items', $plan->items->filter(fn ($item) => in_array($item->effectiveReportingFrequency(), ['weekly', 'daily'], true))->values());
        }
        $effectiveWeights = app(\App\Services\PeriodWeightNormalizer::class)
            ->forPeriod($plan->items, $weightPeriod);
        $performerIds = $plan->items
            ->flatMap(fn ($item) => $item->strategicObjective?->performers ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $performerNames = Department::whereIn('id', $performerIds)->pluck('name', 'id');

        // Prev Year Lookup
        $prevYearPlan = Plan::where('department_id', $deptId)
            ->where('year', $year - 1)
            ->where('status', 'approved')
            ->with(['items.performances', 'items.weeklyPerformances'])
            ->first();

        foreach ($plan->items as $item) {
            $so = $item->strategicObjective;
            if (!$so || $so->is_reference_only) continue;
            $effectiveWeight = $effectiveWeights[$item->id] ?? null;

            // 1. Calculate Target
            if ($isAnnual) {
                $target = (float) ($item->annual_target ?? 0);
            } elseif ($isWeekly) {
                $weeklyEntry = $item->weeklyPerformances
                    ->first(fn ($entry) => (int) $entry->year === (int) $year
                        && strtolower((string) $entry->month) === $selectedMonth
                        && (int) $entry->week_number === $selectedWeek);
                $target = (float) ($weeklyEntry?->planned_value ?? 0);
            } elseif ($isMonthly) {
                $targetColumn = $selectedMonth . '_target';
                $target = (float) ($item->{$targetColumn} ?? 0);
            } else {
                $col = $reportingPeriodLower . '_target';
                $target = (float) ($item->$col ?? 0);
            }

            // 2. Fetch Actual Performance
            if ($isAnnual) {
                $actual = 0;
                foreach ($quarters as $q) {
                    $actual += (float) $item->getActualValueForPeriod($q);
                }
            } elseif ($isWeekly) {
                $weeklyEntry = $item->weeklyPerformances
                    ->first(fn ($entry) => (int) $entry->year === (int) $year
                        && strtolower((string) $entry->month) === $selectedMonth
                        && (int) $entry->week_number === $selectedWeek);
                $actual = (float) ($weeklyEntry?->actual_value ?? 0);
            } elseif ($isMonthly) {
                $actual = (float) $item->getActualValueForPeriod($selectedMonth);
            } else {
                $actual = (float) $item->getActualValueForPeriod($reportingPeriodLower);
            }

            // 3. Achievement % (Comp %)
            $rawAchievement = $isWeekly
                ? $so->calculateWeeklyAchievement($actual, $target)
                : $this->edrCalculator->rawAchievement($actual, $target, $so->goal_direction ?? 'increase');
            $scorecardAchievement = $isWeekly ? null : min(100, $rawAchievement);
            $achievement = $scorecardAchievement ?? $rawAchievement;
            // Although achievement can be > 100%, we often use it to calculate weighted scores in views.
            // Let's ensure consistency if we ever add a weighted score column there.

            // 4. Cumulative Data
            $cumTarget = 0;
            $cumActual = 0;
            if ($isMonthly || $isWeekly) {
                $cumTarget = $target;
                $cumActual = $actual;
            } elseif (!$isAnnual) {
                for ($i = 0; $i <= $periodIdx; $i++) {
                    $q = $quarters[$i];
                    $cumTarget += (float) ($item->{$q . '_target'} ?? 0);
                    $cumActual += (float) $item->getActualValueForPeriod($q);
                }
            } else {
                $cumTarget = $target;
                $cumActual = $actual;
            }
            $cumAchievement = $this->edrCalculator->achievement($cumActual, $cumTarget, $so->goal_direction ?? 'increase');

            // 5. Weight Fallback & Calculated Weight
            $weight = $effectiveWeight ?? 0;

            // 6. Prev Year Comparison
            $prevActual = 0;
            if ($prevYearPlan) {
                $prevItem = $prevYearPlan->items->where('strategic_objective_id', $item->strategic_objective_id)->first();
                if ($prevItem) {
                    if ($isAnnual) {
                        foreach ($quarters as $q) $prevActual += (float) $prevItem->getActualValueForPeriod($q);
                    } elseif ($isWeekly) {
                        $prevEntry = $prevItem->weeklyPerformances
                            ->first(fn ($entry) => (int) $entry->year === (int) ($year - 1)
                                && strtolower((string) $entry->month) === $selectedMonth
                                && (int) $entry->week_number === $selectedWeek);
                        $prevActual = (float) ($prevEntry?->actual_value ?? 0);
                    } elseif ($isMonthly) {
                        $prevActual = (float) $prevItem->getActualValueForPeriod($selectedMonth);
                    } else {
                        $prevActual = (float) $prevItem->getActualValueForPeriod($reportingPeriodLower);
                    }
                }
            }
            $prevComparison = $prevActual > 0 ? (($actual - $prevActual) / $prevActual) * 100 : 0;

            $results[] = [
                '_item_id' => $item->id,
                '_strategic_objective_id' => $so->id,
                '_reporting_department_id' => (int) $plan->department_id,
                '_lead_department_id' => $so->lead_department_id ? (int) $so->lead_department_id : null,
                'code' => $so->code,
                'hierarchy_level' => $so->getHierarchyLevel(),
                'objective' => $so->description,
                'performer' => collect($so->performers ?? [])
                    ->map(fn ($id) => $performerNames[(int) $id] ?? null)
                    ->filter()
                    ->join(', '),
                'reporting_department' => $plan->department?->name ?? 'Unknown Department',
                'weight' => $weight,
                'base_weight' => app(\App\Services\PeriodWeightNormalizer::class)->baseWeight($item),
                'is_planned' => !is_null($effectiveWeight),
                'measurement' => $so->unit ?? '%',
                'baseline' => $item->baseline_value ?? 0,
                'annual_target' => (float) ($item->annual_target ?? 0),
                'quarter_plan' => $target,
                'quarter_perf' => $actual,
                'quarter_comp' => round($achievement, 1),
                'raw_achievement' => round($rawAchievement, 1),
                'scorecard_achievement' => is_null($scorecardAchievement) ? null : round($scorecardAchievement, 1),
                'performance_status' => $this->edrCalculator->performanceStatus($rawAchievement),
                'cumulative_plan' => $cumTarget,
                'cumulative_perf' => $cumActual,
                'cumulative_comp' => round($cumAchievement, 1),
                'comparison' => round($prevComparison, 1),
                'report_completeness' => $completeness,
            ];
        }

        return $this->edrCalculator->apply($plan->items, $results);
    }

    public function calculateAllDepartmentComparisonData(int $year, string $reportingPeriod): array
    {
        $departmentIds = Department::query()
            ->where('is_active', true)
            ->whereHas('plans', fn ($query) => $query
                ->where('year', $year)
                ->where('status', 'approved'))
            ->orderBy('name')
            ->pluck('id');

        $departmentRows = $departmentIds
            ->flatMap(fn ($departmentId) => $this->calculateComparisonData(
                (int) $departmentId,
                $year,
                $reportingPeriod
            ))
            ->values();

        $companyCompleteness = app(\App\Services\ReportCompleteness::class)->combine(
            $departmentRows->pluck('report_completeness')
        );

        return $departmentRows
            ->groupBy('_strategic_objective_id')
            ->map(function ($rows) use ($companyCompleteness) {
                $leadDepartmentId = $rows->pluck('_lead_department_id')->filter()->first();
                $authoritative = $leadDepartmentId
                    ? $rows->first(fn ($row) => (int) $row['_reporting_department_id'] === (int) $leadDepartmentId)
                    : null;
                $authoritative ??= $rows->sortBy('_reporting_department_id')->first();

                $departments = $rows->pluck('reporting_department')->filter()->unique()->values();
                $authoritative['is_company_authoritative'] = true;
                $authoritative['contributing_departments'] = $departments->all();
                $authoritative['contributor_count'] = $departments->count();
                $authoritative['report_completeness'] = $companyCompleteness;

                return $authoritative;
            })
            ->sortBy(fn ($row) => sprintf('%08d-%s', (int) $row['_reporting_department_id'], $row['code']))
            ->values()
            ->all();
    }

    private function calculateReportData(?int $departmentId, int $year, string $reportingPeriod): array
    {
        return is_null($departmentId)
            ? $this->calculateAllDepartmentComparisonData($year, $reportingPeriod)
            : $this->calculateComparisonData($departmentId, $year, $reportingPeriod);
    }

    private function validateCalendarWeek(string $period, int $year): void
    {
        if (!preg_match('/^week:([a-z]+):([1-6])$/i', $period, $matches)) return;
        $weeks = \App\Services\CalendarHelper::getMonthWeeks(strtolower($matches[1]), $year);
        abort_unless(isset($weeks[(int) $matches[2]]), 422, 'That week does not exist in the selected month and fiscal year.');
    }

    private function getTargetForPeriod($item, $period)
    {
        $col = strtolower($period) . '_target';
        return $item->$col ?? 0;
    }

    public function kpiDashboard(Request $request)
    {
        $defaultYear = Plan::where('status', 'approved')->max('year') ?? now()->year;
        $year   = (int) $request->get('year', $defaultYear);
        $period = $request->get('period', 'q1'); // q1 / q2 / q3 / q4 / annual

        // Fetch all objectives tagged with KPI codes, eager load plan items & their performances
        $objectives = \App\Models\StrategicObjective::whereNotNull('kpi_code')
            ->with([
                'planItems' => function ($q) use ($year) {
                    $q->whereHas('plan', function ($p) use ($year) {
                        $p->where('year', $year)->where('status', 'approved');
                    })->with(['performances', 'weeklyPerformances', 'plan.department']);
                }
            ])
            ->orderBy('kpi_code')
            ->get();

        // Group by KPI Code (e.g. 'K1', 'K2', …)
        $kpiGroups = $objectives->groupBy('kpi_code');

        return view('reports.kpi_dashboard', compact('kpiGroups', 'year', 'period'));
    }
}
