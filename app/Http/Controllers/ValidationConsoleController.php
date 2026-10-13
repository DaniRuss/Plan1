<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Department;
use App\Models\WeeklyPerformance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidationConsoleController extends Controller
{
    /**
     * Show the Data Validation & Quality Console.
     */
    public function index(Request $request)
    {
        $year = $request->get('year', 2026);
        $month = $request->get('month', 'october');

        $months = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];

        $alerts = $this->scanForQualityIssues($year, $month);
        $stats = $this->getQualityStats($year, $month);

        return view('quality.console', compact('year', 'month', 'months', 'alerts', 'stats'));
    }

    /**
     * Scan the database for missing data or statistical outliers.
     */
    private function scanForQualityIssues($year, $month)
    {
        $alerts = [];

        // 1. Check for Missing Weeks (Departments with approved plans but 0 entries for specific weeks)
        $plans = Plan::where('year', $year)->where('status', 'approved')->with('department')->get();
        foreach ($plans as $plan) {
            for ($w = 1; $w <= 4; $w++) { // Check first 4 weeks as minimal requirement
                $count = WeeklyPerformance::where('year', $year)
                    ->where('month', $month)
                    ->where('week_number', $w)
                    ->whereHas('planItem', function ($q) use ($plan) {
                        $q->where('plan_id', $plan->id);
                    })->count();

                if ($count == 0) {
                    $alerts[] = [
                        'type' => 'critical',
                        'category' => 'Missing Data',
                        'message' => "Department [{$plan->department->name}] has NO data for Week $w of " . ucfirst($month),
                        'dept' => $plan->department->name,
                        'resolve_url' => route('weekly.entry', [$year, $month, $w])
                    ];
                }
            }
        }

        // 2. Check for Outliers (Performance > 200% of weekly plan)
        $outliers = WeeklyPerformance::where('year', $year)
            ->where('month', $month)
            ->where('percentage', '>', 200)
            ->with(['planItem.strategicObjective', 'planItem.plan.department'])
            ->get();

        foreach ($outliers as $outlier) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'Statistical Outlier',
                'message' => "Extremely high performance ({$outlier->percentage}%) detected for activity [{$outlier->planItem->strategicObjective->description}] in [{$outlier->planItem->plan->department->name}]",
                'dept' => $outlier->planItem->plan->department->name,
                'resolve_url' => route('weekly.entry', [$year, $month, $outlier->week_number])
            ];
        }

        return $alerts;
    }

    private function getQualityStats($year, $month)
    {
        return [
            'total_entries' => WeeklyPerformance::where('year', $year)->where('month', $month)->count(),
            'completion_rate' => 85, // Dummy for UI design
            'health_score' => 92, // Dummy for UI design
        ];
    }
}
