<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Department;
use App\Models\WeeklyPerformance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\EdrPerformanceCalculator;

class ScorecardController extends Controller
{
    public function __construct(private readonly EdrPerformanceCalculator $performanceCalculator) {}

    /**
     * Show the Department Scorecard Leaderboard.
     */
    public function leaderboard(Request $request)
    {
        $latestPlanYear = Plan::where('status', 'approved')->max('year') ?? date('Y');
        $year = (int) $request->get('year', $latestPlanYear);
        
        // Default to July if no month specified (start of fiscal year)
        $month = $request->get('month', 'july'); 

        $months = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];

        $prevMonth = $this->getPreviousMonth($month, $months);

        $departments = Department::all();
        $leaderboard = [];

        foreach ($departments as $dept) {
            $currentScore = $this->calculateDepartmentScore($dept->id, $year, $month);
            $previousScore = $prevMonth ? $this->calculateDepartmentScore($dept->id, $year, $prevMonth) : 0;

            $trend = 'stable';
            if ($currentScore > $previousScore)
                $trend = 'up';
            elseif ($currentScore < $previousScore)
                $trend = 'down';

            // Find plan to get objective count
            $plan = Plan::where('department_id', $dept->id)->where('year', $year)->where('status', 'approved')->first();
            $objectiveCount = $plan ? $plan->items()->count() : 0;

            $leaderboard[] = [
                'department' => $dept,
                'score' => $currentScore,
                'previous_score' => $previousScore,
                'trend' => $trend,
                'objective_count' => $objectiveCount,
                'is_top' => false // Will be set after sorting
            ];
        }

        // Sort by score descending
        usort($leaderboard, fn($a, $b) => $b['score'] <=> $a['score']);

        // Mark top 3 for Podium
        foreach ($leaderboard as $index => &$entry) {
            if ($index < 3 && $entry['score'] > 0)
                $entry['is_top'] = true;
            $entry['rank'] = $index + 1;
        }

        return view('scorecard.leaderboard', compact('year', 'month', 'months', 'leaderboard'));
    }

    /**
     * Calculate a single department's weighted score for a given month.
     */
    private function calculateDepartmentScore($deptId, $year, $month)
    {
        $plan = Plan::where('department_id', $deptId)
            ->where('year', $year)
            ->where('status', 'approved')
            ->with(['items.strategicObjective', 'items.performances', 'items.weeklyPerformances'])
            ->first();

        if (!$plan)
            return 0;

        $monthColumn = strtolower($month) . '_target';
        $totalWeightedScore = 0;
        $effectiveWeights = app(\App\Services\PeriodWeightNormalizer::class)
            ->forPeriod($plan->items, $month);
        $objectiveIds = $plan->items->pluck('strategic_objective_id')->map(fn ($id) => (int) $id);
        $parentObjectiveIds = $plan->items->pluck('strategicObjective.parent_id')->filter()->map(fn ($id) => (int) $id)->unique();

        foreach ($plan->items as $item) {
            $so = $item->strategicObjective;
            if (!$so || $so->is_reference_only || $so->exclude_from_overall_score || $parentObjectiveIds->contains((int) $so->id)) continue;

            $weight = $effectiveWeights[$item->id] ?? null;
            if (is_null($weight)) continue;

            $targetValue = (float) ($item->$monthColumn ?? 0);

            $approvedStatuses = ['reviewed_no_correction_required', 'approved_by_head_office'];
            $approvedWeekly = $item->weeklyPerformances->filter(fn ($entry) => strtolower((string) $entry->month) === strtolower($month) && in_array($entry->workflow_status, $approvedStatuses, true));
            $periodKeys = [strtolower($month), strtolower($year . '-' . $month)];
            $approvedPeriodic = $item->performances->filter(fn ($entry) => in_array(strtolower((string) $entry->period), $periodKeys, true) && in_array($entry->workflow_status, $approvedStatuses, true));
            if ($approvedWeekly->isEmpty() && $approvedPeriodic->isEmpty()) continue;
            $officialItem = clone $item;
            $officialItem->setRelation('weeklyPerformances', $approvedWeekly->values());
            $officialItem->setRelation('performances', $approvedPeriodic->values());
            $actualValue = (float) $officialItem->getActualValueForPeriod($month);
            $rawAchievement = $this->performanceCalculator->rawAchievement($actualValue, $targetValue, $so->goal_direction ?? 'increase');
            $perfRatio = min(1.0, max(0.0, $rawAchievement / 100));

            $totalWeightedScore += ($perfRatio * $weight);
        }

        return round($totalWeightedScore, 2);
    }

    private function getPreviousMonth($current, $months)
    {
        $index = array_search($current, $months);
        return ($index > 0) ? $months[$index - 1] : null;
    }
}
