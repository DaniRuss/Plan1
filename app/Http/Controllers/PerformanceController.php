<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\PeriodicPerformance;
use App\Services\ScoreCalculator;
use App\Services\FidelityCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;

class PerformanceController extends Controller
{
    protected $calculator;

    public function __construct(ScoreCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    // LIST DEPARTMENTS / PLANS to track
    public function index(Request $request)
    {
        $type = $request->get('type', 'monthly');

        $plans = Plan::with('department')
            ->where('status', 'approved')
            ->whereHas('department', function ($q) use ($type) {
                $q->where('reporting_type', $type);
            })
            ->when(!auth()->user()->isSystemAdmin() && !auth()->user()->hasPermission('view_all_performance'), function ($q) {
                $q->where('department_id', auth()->user()->department_id);
            })
            ->get();

        return view('performance.index', compact('plans', 'type'));
    }

    // ENTER ACTUALS FORM
    public function edit(Plan $plan, $period = 'july')
    {
        $this->authorizePlanEntry($plan);

        $plan->load(['department']);
        $user = auth()->user();
        $dept = $plan->department;

        // Fetch items:
        // 1. Items from the department's own approved plan
        // 2. Items assigned to this department from parent plans AND accepted
        // 3. Items assigned directly to the current expert user
        $items = PlanItem::with(['strategicObjective.children', 'plan'])
            ->where('plan_id', $plan->id)
            ->approvedForDepartment((int) $plan->department_id)
            ->get();
        $plan->setRelation('items', $items);

        $performances = PeriodicPerformance::whereIn('plan_item_id', $items->pluck('id'))
            ->where('period', $period)
            ->get()
            ->keyBy('plan_item_id');

        // Fetch performance remarks for this period (Monthly - week_number is null)
        $performanceRemarks = \App\Models\PerformanceRemark::where([
            'department_id' => $plan->department_id,
            'year' => $plan->year, // assuming plan year is the correct context here
            'month' => strtolower($period),
            'week_number' => null,
        ])->get();

        return view('performance.edit', compact('plan', 'period', 'performances', 'performanceRemarks'));
    }

    // SAVE ACTUALS
    public function update(Request $request, Plan $plan, $period)
    {
        $this->authorizePlanEntry($plan);

        $validated = $request->validate([
            'performances' => 'array',
            'performances.*' => 'nullable|numeric|min:0',
        ]);

        $submittedItemIds = collect(array_keys($validated['performances'] ?? []))->map(fn ($id) => (int) $id);
        $allowedItemIds = PlanItem::where('plan_id', $plan->id)
            ->approvedForDepartment((int) $plan->department_id)
            ->whereIn('id', $submittedItemIds)
            ->pluck('id');
        abort_unless($submittedItemIds->diff($allowedItemIds)->isEmpty(), 403, 'One or more performance items do not belong to this authorized plan.');

        DB::transaction(function () use ($validated, $plan, $period) {
            if (isset($validated['performances'])) {
                foreach ($validated['performances'] as $itemId => $actual) {
                    if ($actual !== null) {
                        PeriodicPerformance::updateOrCreate(
                            [
                                'plan_item_id' => $itemId,
                                'period' => $period
                            ],
                            [
                                'actual_value' => $actual,
                                'status' => 'approved' // Auto-approve for now based on previous impl
                            ]
                        );
                    }
                }
            }

            $this->performParentRollups($plan, $period);

            // Save General Performance Remarks (Monthly)
            if (request()->has('remarks')) {
                \App\Models\PerformanceRemark::where([
                    'department_id' => $plan->department_id,
                    'year' => $plan->year,
                    'month' => strtolower($period),
                    'week_number' => null,
                ])->delete();

                foreach (request()->remarks as $remarkData) {
                    if (!empty($remarkData['text'])) {
                        \App\Models\PerformanceRemark::create([
                            'department_id' => $plan->department_id,
                            'year' => $plan->year,
                            'month' => strtolower($period),
                            'week_number' => null,
                            'remark_type' => $remarkData['type'] ?? 'general',
                            'remark_text' => $remarkData['text'],
                        ]);
                    }
                }
            }
        });

        AuditLog::log("Updated performance data for {$period}", $plan);

        return back()->with('success', 'Performance data updated for ' . ucfirst($period));
    }

    private function authorizePlanEntry(Plan $plan): void
    {
        $user = auth()->user();
        abort_unless(
            $user->isSystemAdmin() || (int) $plan->department_id === (int) $user->department_id,
            403,
            'Unauthorized access to this department performance.'
        );
    }

    /**
     * Keep monthly reporting consistent with the daily and weekly workbenches:
     * main objectives configured for aggregation are derived from their
     * direct sub-objectives, starting at the deepest hierarchy level.
     */
    private function performParentRollups(Plan $plan, string $period): void
    {
        $items = $plan->items()->with(['strategicObjective.children'])->get();

        $parentItems = $items
            ->filter(fn (PlanItem $item) => $item->strategicObjective
                && $item->strategicObjective->children->isNotEmpty()
                && $item->strategicObjective->summary_type !== 'manual_override')
            ->sortByDesc(fn (PlanItem $item) => $item->strategicObjective->getHierarchyLevel());

        foreach ($parentItems as $parentItem) {
            $parent = $parentItem->strategicObjective;
            $childObjectiveIds = $parent->rollupChildren()->pluck('id');
            $childItems = $items->whereIn('strategic_objective_id', $childObjectiveIds);

            if ($childItems->isEmpty()) {
                continue;
            }

            $entries = PeriodicPerformance::whereIn('plan_item_id', $childItems->pluck('id'))
                ->where('period', $period)
                ->get();

            if ($entries->isEmpty()) {
                continue;
            }

            $childItemsById = $childItems->keyBy('id');
            $entriesByObjective = $entries->mapWithKeys(function (PeriodicPerformance $entry) use ($childItemsById, $period) {
                $childItem = $childItemsById->get($entry->plan_item_id);
                if (!$childItem || !$childItem->strategicObjective) {
                    return [];
                }

                $targetColumn = strtolower($period) . '_target';
                $target = (float) ($childItem->{$targetColumn} ?? 0);
                $raw = app(\App\Services\EdrPerformanceCalculator::class)->rawAchievement(
                    (float) $entry->actual_value,
                    $target,
                    $childItem->strategicObjective->goal_direction ?? 'increase'
                );
                $entry->percentage = min(100, $raw);

                if ($childItem->strategicObjective->summary_type === 'manual_override'
                    && (float) $entry->actual_value > 0) {
                    $entry->percentage = 100;
                }

                return [$childItem->strategic_objective_id => $entry];
            });

            $periodWeights = app(\App\Services\PeriodWeightNormalizer::class)->forPeriod($items, $period);
            $effectiveWeights = $childItems->mapWithKeys(fn (PlanItem $item) => [
                $item->strategic_objective_id => $periodWeights[$item->id] ?? null,
            ])->all();
            $actual = FidelityCalculator::rollup($parent, $entriesByObjective, $parent->summary_type, $effectiveWeights);

            PeriodicPerformance::updateOrCreate(
                ['plan_item_id' => $parentItem->id, 'period' => $period],
                ['actual_value' => $actual, 'status' => 'approved']
            );
        }
    }

    // SCOREBOARD REPORT
    public function report(Plan $plan, $period = 'july')
    {
        $plan->load(['items.strategicObjective', 'department', 'items.performances', 'items.weeklyPerformances']);
        $deptScore = $this->calculator->calculateDepartmentAverage($plan->department, $plan, $period);
        $calculator = $this->calculator;
        $effectiveWeights = app(\App\Services\PeriodWeightNormalizer::class)->forPeriod($plan->items, $period);

        return view('performance.report', compact('plan', 'period', 'deptScore', 'calculator', 'effectiveWeights'));
    }
}
