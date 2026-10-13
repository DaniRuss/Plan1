<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\StrategicObjective;
use App\Models\Department;
use App\Services\CalendarHelper;
use App\Services\EdrPerformanceCalculator;
use App\Services\PerformanceLevel;
use App\Services\PeriodWeightNormalizer;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MasterPerformanceExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class MasterReportController extends Controller
{
    public function __construct(
        private readonly EdrPerformanceCalculator $performanceCalculator,
        private readonly PerformanceLevel $performanceLevel,
        private readonly PeriodWeightNormalizer $weightNormalizer,
    ) {}

    /**
     * Show the Master Performance Report grid.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isSystemAdmin();
        $canSelectDepartment = $this->canViewAllDepartments($user);
        $years = CalendarHelper::getAppYears();
        $year = (int) $request->get('year', now()->year);
        abort_unless(in_array($year, $years, true), 404);
        $deptId = $this->authorizedDepartmentId($request);

        $departments = $canSelectDepartment ? Department::orderBy('name')->get() : null;
        $activeDept = null;
        
        if ($deptId && $deptId !== 'all') {
            $activeDept = Department::find($deptId);
        } elseif ($deptId === 'all') {
            $activeDept = (object)['name' => 'All Departments', 'id' => 'all'];
        }

        $gridData = [];
        $months = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];
        $quarters = ['q1', 'q2', 'q3', 'q4'];
        $periodGroups = $this->periodGroups();

        if ($deptId) {
            $gridData = $this->buildGridData($year, $deptId);
        }

        $summary = $this->reportSummary($gridData);

        return view('reports.master', compact('year', 'deptId', 'departments', 'activeDept', 'gridData', 'years', 'months', 'quarters', 'periodGroups', 'isAdmin', 'canSelectDepartment', 'summary'));
    }

    /**
     * Build the hierarchical grid data for the plan.
     */
    private function buildGridData($year, $deptId)
    {
        $objectives = StrategicObjective::with(['children', 'children.children'])
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        $plansQuery = Plan::where('year', $year)->where('status', 'approved');
        
        if ($deptId !== 'all') {
            $plansQuery->where('department_id', $deptId);
        }

        $plans = $plansQuery->with(['items.strategicObjective', 'items.performances', 'items.weeklyPerformances', 'department'])->get();
        $periodWeights = [];
        foreach (array_merge(['july','august','september','october','november','december','january','february','march','april','may','june'], ['q1','q2','q3','q4']) as $period) {
            foreach ($plans as $plan) {
                foreach ($this->weightNormalizer->forPeriod($plan->items, $period) as $itemId => $effectiveWeight) {
                    $periodWeights[$period][(int) $itemId] = (float) $effectiveWeight;
                }
            }
        }
        
        // Keep the original assignment list for hierarchy visibility, then use
        // only the lead department's item as the authoritative company value.
        $assignedItems = $plans->pluck('items')->flatten();
        $assignedObjectiveIds = $assignedItems->pluck('strategic_objective_id')->unique();
        $allPlanItems = $assignedItems->groupBy('strategic_objective_id');
        if ($deptId === 'all') {
            $allPlanItems = $allPlanItems->map(function (Collection $items) {
                $objective = $items->first()?->strategicObjective;
                $leadId = (int) ($objective?->lead_department_id ?? 0);
                return $leadId
                    ? $items->filter(fn ($item) => (int) $item->plan->department_id === $leadId)->values()
                    : $items;
            });
        }

        // Include only objectives assigned through the selected approved plan(s).
        // Ancestors are retained solely as hierarchy/context for an assigned child.
        $includedObjectiveIds = $assignedObjectiveIds->map(fn ($id) => (int) $id)->flip();
        foreach (StrategicObjective::whereIn('id', $assignedObjectiveIds)->get() as $assignedObjective) {
            $ancestor = $assignedObjective->parent;
            while ($ancestor) {
                $includedObjectiveIds->put((int) $ancestor->id, true);
                $ancestor = $ancestor->parent;
            }
        }

        $grid = [];
        foreach ($objectives as $topObj) {
            $topData = $this->formatObjectiveRow($topObj, $allPlanItems, $periodWeights);
            if ($includedObjectiveIds->has((int) $topObj->id)) {
                $grid[] = $topData;
                foreach ($topObj->children as $child) {
                    /** @var StrategicObjective $child */
                    $childData = $this->formatObjectiveRow($child, $allPlanItems, $periodWeights);
                    if ($includedObjectiveIds->has((int) $child->id)) {
                        $grid[] = $childData;
                        foreach ($child->children as $grandchild) {
                            /** @var StrategicObjective $grandchild */
                            $gcData = $this->formatObjectiveRow($grandchild, $allPlanItems, $periodWeights);
                            if ($includedObjectiveIds->has((int) $grandchild->id)) {
                                $grid[] = $gcData;
                            }
                        }
                    }
                }
            }
        }

        return $this->applyHierarchyRollups($grid);
    }

    /**
     * Format a single objective row with all its targets and actuals (possibly aggregated).
     */
    private function formatObjectiveRow(StrategicObjective $obj, $allPlanItems, array $periodWeights)
    {
        $items = $allPlanItems->get($obj->id) ?? collect();
        $months = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];
        $quarters = ['q1', 'q2', 'q3', 'q4'];

        // Aggregate data if multiple items exist
        $weight = 0;
        $annualTarget = 0;
        $annualPlanned = false;
        $baseline = 0;
        $performers = [];
        $monthlyData = [];
        foreach ($months as $m) $monthlyData[$m] = $this->emptyPeriodCell();
        $quarterlyData = [];
        foreach ($quarters as $q) $quarterlyData[$q] = $this->emptyPeriodCell();

        foreach ($items as $item) {
            $weight += ($item->weight > 0) ? $item->weight : ($obj->weight ?? 0);
            if (!is_null($item->annual_target)) {
                $annualPlanned = true;
                $annualTarget += (float) $item->annual_target;
            }
            $baseline += $item->baseline_value ?? 0;
            $performers[] = $item->plan->department->name;

            foreach ($months as $m) {
                $targetCol = $m . '_target';
                if (!is_null($item->$targetCol)) {
                    $monthlyData[$m]['planned'] = true;
                    $monthlyData[$m]['target'] += (float) $item->$targetCol;
                    $monthlyData[$m]['effective_weight'] += (float) ($periodWeights[$m][$item->id] ?? 0);
                }
                if ($this->hasOfficialPerformance($item, $m)) {
                    $monthlyData[$m]['actual'] += $this->officialActualForPeriod($item, $m);
                    $monthlyData[$m]['reported'] = true;
                }
            }
            foreach ($quarters as $q) {
                $targetCol = $q . '_target';
                if (!is_null($item->$targetCol)) {
                    $quarterlyData[$q]['planned'] = true;
                    $quarterlyData[$q]['target'] += (float) $item->$targetCol;
                    $quarterlyData[$q]['effective_weight'] += (float) ($periodWeights[$q][$item->id] ?? 0);
                }
                $quarterReported = collect([
                    'q1' => ['july', 'august', 'september'], 'q2' => ['october', 'november', 'december'],
                    'q3' => ['january', 'february', 'march'], 'q4' => ['april', 'may', 'june'],
                ][$q])->contains(fn ($month) => $this->hasOfficialPerformance($item, $month));
                if ($quarterReported) {
                    $quarterlyData[$q]['actual'] += $this->officialActualForPeriod($item, $q);
                    $quarterlyData[$q]['reported'] = true;
                }
            }
        }

        foreach ($months as $m) $monthlyData[$m] = $this->finalizePeriodCell($monthlyData[$m], $obj);
        foreach ($quarters as $q) $quarterlyData[$q] = $this->finalizePeriodCell($quarterlyData[$q], $obj);

        $resolvedBaseline = $this->resolveBaseline($obj, $items, $allPlanItems);

        $row = [
            'id' => $obj->id,
            'parent_id' => $obj->parent_id,
            'code' => $obj->code,
            'description' => $obj->description,
            'unit' => $obj->unit ?? '-',
            'weight' => $weight ?: ($obj->weight ?? 0),
            'baseline' => $resolvedBaseline,
            'annual_target' => $annualPlanned ? $annualTarget : null,
            'performers' => !empty($performers) ? implode(', ', array_unique($performers)) : '-',
            'months' => $monthlyData,
            'quarters' => $quarterlyData,
            'has_data' => $items->isNotEmpty(),
            'is_root' => $obj->parent_id === null,
            'is_child' => $obj->parent_id !== null && $obj->children()->exists(),
            'is_leaf' => !$obj->children()->exists(),
            'is_reference' => $obj->is_reference_only
        ];

        // Format values for display if no data
        if ($items->isEmpty()) {
            foreach ($months as $m) $row['months'][$m] = $this->emptyPeriodCell();
            foreach ($quarters as $q) $row['quarters'][$q] = $this->emptyPeriodCell();
        }

        return $row;
    }

    private function emptyPeriodCell(): array
    {
        return [
            'target' => 0.0, 'actual' => 0.0, 'planned' => false, 'reported' => false,
            'effective_weight' => 0.0, 'raw_achievement' => null, 'scorecard_achievement' => null,
            'status' => 'Not Planned (N/A)', 'level' => $this->performanceLevel->for(null),
            'is_rollup' => false, 'coverage' => 0.0,
        ];
    }

    private function finalizePeriodCell(array $cell, StrategicObjective $objective): array
    {
        if (!$cell['planned']) {
            return $cell;
        }

        if (!$cell['reported']) {
            $cell['status'] = 'Not Reported';
            $cell['level'] = $this->performanceLevel->for(null);
            return $cell;
        }

        $raw = $this->performanceCalculator->rawAchievement(
            (float) $cell['actual'],
            (float) $cell['target'],
            $objective->goal_direction ?? 'increase'
        );
        $cell['raw_achievement'] = round($raw, 2);
        $cell['scorecard_achievement'] = round(min(100, $raw), 2);
        $cell['status'] = $this->performanceCalculator->performanceStatus($raw);
        $cell['level'] = $this->performanceLevel->for($cell['scorecard_achievement']);
        $cell['coverage'] = 100.0;

        return $cell;
    }

    private function applyHierarchyRollups(array $grid): array
    {
        $rows = collect($grid)->keyBy('id');
        $children = collect($grid)->groupBy('parent_id');
        $periodSets = ['months' => ['july','august','september','october','november','december','january','february','march','april','may','june'], 'quarters' => ['q1','q2','q3','q4']];

        $rollup = function (int $rowId) use (&$rollup, $rows, $children, $periodSets): array {
            $row = $rows->get($rowId);
            $directChildren = $children->get($rowId, collect());
            if ($directChildren->isEmpty()) return $row;

            $rolledChildren = $directChildren->map(fn ($child) => $rollup((int) $child['id']));
            foreach ($periodSets as $bucket => $periods) {
                foreach ($periods as $period) {
                    $cells = $rolledChildren->pluck("{$bucket}.{$period}")->filter(fn ($cell) => $cell['planned'] ?? false);
                    if ($cells->isEmpty()) {
                        $row[$bucket][$period] = $this->emptyPeriodCell();
                        continue;
                    }

                    $reported = $cells->filter(fn ($cell) => $cell['reported'] ?? false);
                    $plannedWeight = (float) $cells->sum(fn ($cell) => max(0, (float) ($cell['effective_weight'] ?? 0)));
                    $reportedWeight = (float) $reported->sum(fn ($cell) => max(0, (float) ($cell['effective_weight'] ?? 0)));
                    $fallbackWeight = $reported->count() > 0 ? (float) $reported->count() : 0.0;
                    $denominator = $reportedWeight > 0 ? $reportedWeight : $fallbackWeight;
                    $raw = $denominator > 0 ? $reported->sum(function ($cell) use ($reportedWeight) {
                        $weight = $reportedWeight > 0 ? max(0, (float) ($cell['effective_weight'] ?? 0)) : 1.0;
                        return ((float) ($cell['raw_achievement'] ?? 0)) * $weight;
                    }) / $denominator : null;
                    $scorecard = $denominator > 0 ? $reported->sum(function ($cell) use ($reportedWeight) {
                        $weight = $reportedWeight > 0 ? max(0, (float) ($cell['effective_weight'] ?? 0)) : 1.0;
                        return ((float) ($cell['scorecard_achievement'] ?? 0)) * $weight;
                    }) / $denominator : null;
                    $coverage = $plannedWeight > 0
                        ? ($reportedWeight / $plannedWeight) * 100
                        : ($cells->count() > 0 ? ($reported->count() / $cells->count()) * 100 : 0);

                    $row[$bucket][$period] = [
                        'target' => 'Roll-up', 'actual' => 'Roll-up', 'planned' => true,
                        'reported' => $reported->isNotEmpty(), 'effective_weight' => $plannedWeight,
                        'raw_achievement' => is_null($raw) ? null : round($raw, 2),
                        'scorecard_achievement' => is_null($scorecard) ? null : round(min(100, $scorecard), 2),
                        'status' => is_null($raw) ? 'Not Reported' : $this->performanceCalculator->performanceStatus($raw),
                        'level' => $this->performanceLevel->for($scorecard), 'is_rollup' => true,
                        'coverage' => round(min(100, $coverage), 1),
                    ];
                }
            }

            return $row;
        };

        $calculated = [];
        foreach ($grid as $row) $calculated[(int) $row['id']] = $rollup((int) $row['id']);
        return array_map(fn ($row) => $calculated[(int) $row['id']], $grid);
    }

    /** Official reports include only records that completed Head Office approval. */
    private function officialActualForPeriod(PlanItem $item, string $period): float
    {
        $reportingType = $item->effectiveReportingFrequency();
        if (in_array($reportingType, ['weekly', 'daily'], true)) {
            return (float) $item->getActualValueForPeriod($period);
        }

        $quarterMonths = [
            'q1' => ['july', 'august', 'september'], 'q2' => ['october', 'november', 'december'],
            'q3' => ['january', 'february', 'march'], 'q4' => ['april', 'may', 'june'],
        ];
        $period = strtolower($period);
        $approved = $item->performances->filter(fn ($entry) =>
            in_array($entry->workflow_status, ['reviewed_no_correction_required', 'approved_by_head_office'], true)
        );
        $monthlyValue = function (string $month) use ($approved, $item): float {
            $keys = [strtolower($month), strtolower(($item->plan->year ?? '') . '-' . $month)];
            $entry = $approved->first(fn ($record) => in_array(strtolower((string) $record->period), $keys, true));
            return (float) ($entry?->actual_value ?? 0);
        };

        if (isset($quarterMonths[$period])) {
            if ($item->strategicObjective?->target_distribution === 'checkpoint') {
                $lastPlannedMonth = collect($quarterMonths[$period])
                    ->filter(fn ($month) => !is_null($item->{$month . '_target'}))->last();
                return $lastPlannedMonth ? $monthlyValue($lastPlannedMonth) : 0.0;
            }
            return (float) collect($quarterMonths[$period])->sum($monthlyValue);
        }
        return $monthlyValue($period);
    }

    private function hasOfficialPerformance(PlanItem $item, string $month): bool
    {
        $month = strtolower($month);
        $reportingType = $item->effectiveReportingFrequency();
        if (in_array($reportingType, ['weekly', 'daily'], true)) {
            return $item->weeklyPerformances->contains(fn ($entry) =>
                strtolower((string) $entry->month) === $month
                && in_array($entry->workflow_status, ['reviewed_no_correction_required', 'approved_by_head_office'], true)
            );
        }

        $keys = [$month, strtolower(($item->plan->year ?? '') . '-' . $month)];
        return $item->performances->contains(fn ($entry) =>
            in_array($entry->workflow_status, ['reviewed_no_correction_required', 'approved_by_head_office'], true)
            && in_array(strtolower((string) $entry->period), $keys, true)
        );
    }

    private function reportSummary(array $gridData): array
    {
        $dataRows = collect($gridData)->where('has_data', true);
        $scoredRows = $dataRows->where('is_leaf', true)->where('is_reference', false);
        $plannedCells = 0;
        $reportedCells = 0;
        foreach ($scoredRows as $row) {
            foreach ($row['months'] as $period) {
                if ($period['planned'] ?? false) {
                    $plannedCells++;
                    if ($period['reported'] ?? false) $reportedCells++;
                }
            }
        }

        return [
            'objectives' => $dataRows->count(),
            'performers' => $dataRows->pluck('performers')->flatMap(fn ($names) => explode(', ', $names))->filter(fn ($name) => $name !== '-')->unique()->count(),
            'planned_periods' => $plannedCells,
            'reported_periods' => $reportedCells,
            'coverage' => $plannedCells > 0 ? round(($reportedCells / $plannedCells) * 100, 1) : 0,
        ];
    }

    /**
     * Prefer an assigned plan baseline, then the objective's own baseline. A
     * calculated parent with neither derives its baseline from assigned direct
     * children using the configured summary rule.
     */
    private function resolveBaseline(StrategicObjective $objective, $items, $allPlanItems)
    {
        $itemBaselines = $items->pluck('baseline_value')->filter(fn ($value) => $value !== null);
        if ($itemBaselines->isNotEmpty()) {
            return (float) $itemBaselines->sum();
        }

        if ($objective->baseline_value !== null) {
            return $objective->baseline_value;
        }

        if (!$objective->children()->exists() || $objective->summary_type === 'manual_override') {
            return '-';
        }

        $childBaselines = $objective->children->map(function ($child) use ($allPlanItems) {
            $childItems = $allPlanItems->get($child->id) ?? collect();
            if ($childItems->isEmpty()) return null;

            $planBaselines = $childItems->pluck('baseline_value')->filter(fn ($value) => $value !== null);
            $value = $planBaselines->isNotEmpty()
                ? (float) $planBaselines->sum()
                : ($child->baseline_value !== null ? (float) $child->baseline_value : null);

            return $value === null ? null : ['value' => $value, 'weight' => (float) $child->weight];
        })->filter();

        if ($childBaselines->isEmpty()) return '-';

        return match ($objective->summary_type) {
            'average' => round((float) $childBaselines->avg('value'), 4),
            'weighted' => $childBaselines->sum('weight') > 0
                ? round((float) ($childBaselines->sum(fn ($child) => $child['value'] * $child['weight']) / $childBaselines->sum('weight')), 4)
                : '-',
            default => round((float) $childBaselines->sum('value'), 4),
        };
    }

    /**
     * Export to Excel.
     */
    public function exportExcel(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        abort_unless(in_array($year, CalendarHelper::getAppYears(), true), 404);
        $deptId = $this->authorizedDepartmentId($request);
        abort_if(!$deptId, 422, 'A department is required for this report.');
        $gridData = $this->buildGridData($year, $deptId);
        $deptName = $deptId === 'all' ? 'All Departments' : Department::findOrFail($deptId)->name;
        
        $fileName = "Master_Performance_Report_{$deptName}_{$year}.xlsx";

        return Excel::download(new MasterPerformanceExport($gridData, $year, $deptName), $fileName);
    }

    /**
     * Export to PDF.
     */
    public function exportPdf(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        abort_unless(in_array($year, CalendarHelper::getAppYears(), true), 404);
        $deptId = $this->authorizedDepartmentId($request);
        abort_if(!$deptId, 422, 'A department is required for this report.');
        $gridData = $this->buildGridData($year, $deptId);
        $deptName = $deptId === 'all' ? 'All Departments' : Department::findOrFail($deptId)->name;
        
        $months = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];
        $quarters = ['q1', 'q2', 'q3', 'q4'];

        $pdf = Pdf::loadView('reports.master_pdf', [
            'deptName' => $deptName,
            'gridData' => $gridData,
            'year' => $year,
            'months' => $months,
            'quarters' => $quarters,
            'periodGroups' => $this->periodGroups(),
        ])->setPaper('a4', 'landscape');
            
        return $pdf->download("Master_Performance_Report_{$deptName}_{$year}.pdf");
    }

    private function canViewAllDepartments($user): bool
    {
        if ($user->hasOrganizationWidePerformanceAccess()) return true;

        // Department-scoped expert/directorate/chief accounts assigned to the
        // central Plan and Report unit also require organization-wide reports.
        $code = strtoupper((string) $user->department?->code);
        $name = strtolower((string) $user->department?->name);

        return in_array($code, ['PLN', 'PRD'], true)
            || str_contains($name, 'plan and report')
            || str_contains($name, 'planning');
    }

    private function periodGroups(): array
    {
        return [
            ['months' => ['july', 'august', 'september'], 'quarter' => 'q1'],
            ['months' => ['october', 'november', 'december'], 'quarter' => 'q2'],
            ['months' => ['january', 'february', 'march'], 'quarter' => 'q3'],
            ['months' => ['april', 'may', 'june'], 'quarter' => 'q4'],
        ];
    }

    private function authorizedDepartmentId(Request $request)
    {
        $user = auth()->user();
        if (!$this->canViewAllDepartments($user)) {
            return $user->department_id;
        }

        $deptId = $request->get('department_id', 'all');
        if ($deptId === 'all') return 'all';

        abort_unless(Department::whereKey($deptId)->exists(), 404);
        return (int) $deptId;
    }
}
