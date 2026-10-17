<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Services\EdrPerformanceCalculator;

class ScorecardController extends Controller
{
    public function __construct(private readonly EdrPerformanceCalculator $performanceCalculator) {}

    /**
     * Show the Department Scorecard Leaderboard.
     */
    public function leaderboard(Request $request)
    {
        $filters = $request->validate(['year' => 'nullable|integer|min:1900|max:9999', 'month' => 'nullable|in:july,august,september,october,november,december,january,february,march,april,may,june']);
        $user = $request->user();
        $departmentQuery = Department::where('is_active', true)->when(!$user->hasOrganizationWidePerformanceAccess(), fn ($q) => $q->whereIn('id', array_filter([$user->department_id ?? $user->employee?->department_id])));
        $departmentIds = (clone $departmentQuery)->pluck('id');
        $years = Plan::whereIn('department_id', $departmentIds)->where('status', 'approved')->distinct()->orderByDesc('year')->pluck('year');
        $latestPlanYear = Plan::whereIn('department_id', $departmentIds)->where('status', 'approved')->max('year') ?? date('Y');
        $year = (int) ($filters['year'] ?? $latestPlanYear);
        
        // Default to July if no month specified (start of fiscal year)
        $month = $filters['month'] ?? 'july';

        $months = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];

        $prevMonth = $this->getPreviousMonth($month, $months);

        $departments = $departmentQuery->orderBy('name')->get();
        $leaderboard = [];

        foreach ($departments as $dept) {
            $currentScore = $this->calculateDepartmentScore($dept->id, $year, $month);
            $previousScore = $this->calculateDepartmentScore($dept->id, $prevMonth ? $year : $year - 1, $prevMonth ?? 'june');

            $trend = 'unavailable';
            if ($currentScore !== null && $previousScore !== null) $trend = 'stable';
            if ($currentScore !== null && $previousScore !== null && $currentScore > $previousScore)
                $trend = 'up';
            elseif ($currentScore !== null && $previousScore !== null && $currentScore < $previousScore)
                $trend = 'down';

            // Find plan to get objective count
            $plan = Plan::where('department_id', $dept->id)->where('year', $year)->where('status', 'approved')->orderByDesc('id')->first();
            $objectiveCount = $plan ? $plan->items()->count() : 0;

            $leaderboard[] = [
                'department' => $dept,
                'score' => $currentScore,
                'previous_score' => $previousScore,
                'trend' => $trend,
                'objective_count' => $objectiveCount,
                'reviewed_count' => $this->scoreDetails[$dept->id.'-'.$year.'-'.$month]['reviewed'] ?? 0,
                'coverage' => $this->scoreDetails[$dept->id.'-'.$year.'-'.$month]['coverage'] ?? 0,
                'state' => $this->scoreDetails[$dept->id.'-'.$year.'-'.$month]['state'] ?? 'No approved plan',
                'is_top' => false // Will be set after sorting
            ];
        }

        // Sort by score descending
        usort($leaderboard, fn($a, $b) => ($a['score'] === null) <=> ($b['score'] === null) ?: ($b['score'] <=> $a['score']) ?: strcasecmp($a['department']->name, $b['department']->name));

        $lastScore = null;
        $rank = null;
        foreach ($leaderboard as $index => &$entry) {
            if ($entry['score'] === null) { $entry['rank'] = null; continue; }
            if ($lastScore === null || $lastScore !== $entry['score']) $rank = $index + 1;
            $entry['rank'] = $rank;
            $entry['is_top'] = $rank <= 3;
            $lastScore = $entry['score'];
        }
        unset($entry);

        return view('scorecard.leaderboard', compact('year', 'years', 'month', 'months', 'leaderboard'));
    }

    /**
     * Calculate a single department's weighted score for a given month.
     */
    private array $scoreDetails = [];

    private function calculateDepartmentScore($deptId, $year, $month)
    {
        $plan = Plan::where('department_id', $deptId)
            ->where('year', $year)
            ->where('status', 'approved')
            ->orderByDesc('id')->with(['items.plan.department', 'items.strategicObjective', 'items.performances', 'items.weeklyPerformances'])
            ->first();

        if (!$plan)
            return null;

        $totalWeightedScore = 0;
        $possibleWeight = 0;
        $plannedCount = 0;
        $reviewed = 0;
        $missingWeights = false;
        $effectiveWeights = app(\App\Services\PeriodWeightNormalizer::class)
            ->forPeriod($plan->items, $month);
        $parentObjectiveIds = $plan->items->pluck('strategicObjective.parent_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $notApplicable = app(\App\Services\SharedReporting::class)->notApplicableForMonth((int)$deptId,(int)$year,strtolower($month));

        foreach ($plan->items as $item) {
            $so = $item->strategicObjective;
            if (in_array((int)$item->id,$notApplicable,true)) continue;
            if (!$so || $so->is_reference_only || $so->exclude_from_overall_score || $parentObjectiveIds->contains((int) $so->id)) continue;

            $target = $item->getPlannedTargetForPeriod($month);
            if ($target === null) continue;
            $plannedCount++;
            $weight = $effectiveWeights[$item->id] ?? null;
            if ($weight === null) $missingWeights = true;
            $targetValue = (float) $target;

            $approvedWeekly = $item->weeklyPerformances->filter(fn ($entry) => (int) $entry->year === (int) $year && strtolower((string) $entry->month) === strtolower($month) && app(\App\Services\DashboardWorkspace::class)->isOfficial($entry));
            $periodKeys = [strtolower($month), strtolower($year . '-' . $month)];
            $approvedPeriodic = $item->performances->filter(fn ($entry) => in_array(strtolower((string) $entry->period), $periodKeys, true) && app(\App\Services\DashboardWorkspace::class)->isOfficial($entry));
            $aggregated = in_array($item->effectiveReportingFrequency(), ['weekly', 'daily'], true);
            if (($aggregated && $approvedWeekly->isEmpty()) || (!$aggregated && $approvedPeriodic->isEmpty())) continue;
            $reviewed++;
            $officialItem = clone $item;
            $officialItem->setRelation('weeklyPerformances', $approvedWeekly->map(function ($entry) { $copy = clone $entry; $copy->workflow_status = 'approved_by_head_office'; return $copy; })->values());
            $officialItem->setRelation('performances', $approvedPeriodic->values());
            $actualValue = (float) $officialItem->getActualValueForPeriod($month);
            $rawAchievement = $this->performanceCalculator->rawAchievement($actualValue, $targetValue, $so->goal_direction ?? 'increase');
            $perfRatio = min(1.0, max(0.0, $rawAchievement / 100));

            $totalWeightedScore += ($perfRatio * (float) $weight);
            $possibleWeight += (float) $weight;
        }

        $state = !$plannedCount ? 'No monthly targets' : ($missingWeights ? 'Period weights need approval' : (!$reviewed ? 'Awaiting reviewed results' : ($reviewed < $plannedCount ? 'Incomplete reviewed results' : ($possibleWeight <= 0 ? 'No scoring weight' : 'Ready to rank'))));
        $this->scoreDetails[$deptId.'-'.$year.'-'.$month] = ['reviewed' => $reviewed, 'coverage' => $plannedCount ? round($reviewed / $plannedCount * 100, 1) : 0, 'state' => $state];
        return $state === 'Ready to rank' ? round(min(100, $totalWeightedScore / $possibleWeight * 100), 2) : null;
    }

    private function getPreviousMonth($current, $months)
    {
        $index = array_search($current, $months);
        return ($index > 0) ? $months[$index - 1] : null;
    }
}
