<?php

namespace App\Services;

use App\Models\PlanItem;
use App\Models\WeeklyPerformance;
use Illuminate\Support\Str;

class FidelityCalculator
{
    /**
     * Calculate a machinery plan value based on metadata.
     * Formula: Qty * Time * Days (Default 10hrs * 7days)
     */
    public static function calculateMachineryPlan($qty, $time = 10, $days = 7)
    {
        return $qty * $time * $days;
    }

    /**
     * Calculate truck haulage plan value.
     * Formula: Count * Cycle * Tone
     */
    public static function calculateTruckPlan($count, $cycle, $tone = 40)
    {
        return $count * $cycle * $tone;
    }

    /**
     * Parse temporal durations with Days support.
     * Example: "1 day, 15:02" -> 39.033 hours
     */
    public static function parseComplexTemporal($string)
    {
        if (empty($string))
            return 0;

        $totalHours = 0;

        // Extract days
        if (preg_match('/(\d+)\s*day/i', $string, $matches)) {
            $totalHours += ($matches[1] * 24);
        }

        // Extract HH:MM
        if (preg_match('/(\d{1,2}):(\d{2})/', $string, $matches)) {
            $totalHours += $matches[1] + ($matches[2] / 60);
        } elseif (preg_match('/^(\d+(\.\d+)?)$/', $string, $matches)) {
            // Raw decimal
            $totalHours += (float) $matches[1];
        }

        return $totalHours;
    }

    /**
     * Format decimal hours to "X days, HH:MM" or "HH:MM".
     */
    public static function formatComplexTemporal($hours)
    {
        if (!$hours || $hours == 0)
            return '00:00';

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        $h = floor($remainingHours);
        $m = round(($remainingHours - $h) * 60);

        $timeStr = sprintf('%02d:%02d', $h, $m);

        if ($days > 0) {
            return "{$days} " . Str::plural('day', $days) . ", {$timeStr}";
        }

        return $timeStr;
    }

    /**
     * Handle Hybrid Revenue Scaling.
     * If unit is "Million" but value is > 10000, it's likely raw "000s".
     */
    public static function scaleRevenueValue($value, $unit)
    {
        if (Str::contains(strtolower($unit), 'million')) {
            // Case: Blueprint Section 10 shows "3,783,050" under "Birr (Million)"
            // Logic: If the value is suspicious (very high), we treat it as raw Birr for storage but keep the MILLION flag for display.
            if ($value > 10000) {
                return $value / 1000000;
            }
        }
        return $value;
    }

    /**
     * Rollup child performances to a parent.
     * Type: sum, average, weighted (default)
     */
    public static function rollup($parent, $childrenEntries, $type = 'sum', array $effectiveWeights = [])
    {
        switch ($type) {
            case 'sum':
                return $childrenEntries->sum('actual_value');
            case 'average':
                return $childrenEntries->avg('actual_value') ?: 0;
            case 'weighted':
                $totalScore = 0;
                // Get ALL children to account for missing data as 0%
                $children = $parent->children->where('include_in_parent_rollup', true);
                foreach ($children as $child) {
                    $weight = array_key_exists($child->id, $effectiveWeights)
                        ? $effectiveWeights[$child->id]
                        : (float) $child->weight;
                    if (is_null($weight)) continue;
                    $entry = $childrenEntries->get($child->id); // Using keyBy('strategic_objective_id') from controller
                    if ($entry) {
                        $perf = (float) $entry->percentage;
                        // Manual overrides (e.g. RoRo 100%)
                        if ($child->summary_type === 'manual_override' && $entry->actual_value > 0) {
                            $perf = 100;
                        }
                        $itemScore = ($perf / 100) * $weight;
                        $totalScore += min($itemScore, $weight);
                    }
                    // Missing children implicitly add 0 to totalScore
                }
                return $totalScore;
            default:
                return $childrenEntries->sum('actual_value');
        }
    }
}
