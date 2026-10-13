<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class StrategicObjective extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'kpi_code',
        'description',
        'weight',
        'unit',
        'baseline_value',
        'baseline_year',
        'department_id',
        'lead_department_id',
        'company_consolidation_method',
        'parent_id',
        'owner_id',
        'performers',
        'annual_target',
        'target_distribution',
        'weekly_plan_mode',
        'july_target', 'august_target', 'september_target', 'q1_target',
        'october_target', 'november_target', 'december_target', 'q2_target',
        'january_target', 'february_target', 'march_target', 'q3_target',
        'april_target', 'may_target', 'june_target', 'q4_target',
        'target_year', // Added this line based on the provided "Code Edit"
        'is_active',
        'status',
        'revision_number',
        'last_edited_by',
        'last_edited_at',
        'last_edited_stage',
        'last_change_reason',
        'rejection_comment',
        'recall_requested_by',
        'recall_requested_at',
        'recall_reason',
        'recall_from_status',
        'is_reference_only',
        'is_supplementary',
        'exclude_from_overall_score',
        'is_calculated_weight',
        'template_type',
        'data_source',
        'summary_type',
        'goal_direction',
        'include_in_parent_rollup',
        'reporting_frequency', 'pending_reporting_frequency', 'frequency_change_reason', 'frequency_requested_by',
    ];

    const DIRECTION_INCREASE = 'increase';
    const DIRECTION_DECREASE = 'decrease';

    public function weeklyPlanFromMonthlyTarget(float $monthlyTarget, int $numberOfWeeks): float
    {
        if ($this->weekly_plan_mode === 'repeat') {
            return $monthlyTarget;
        }

        return $numberOfWeeks > 0 ? $monthlyTarget / $numberOfWeeks : 0;
    }

    protected $casts = [
        'weight' => 'decimal:2',
        'baseline_value' => 'decimal:2',
        'annual_target' => 'decimal:2',
        'july_target' => 'decimal:4', 'august_target' => 'decimal:4', 'september_target' => 'decimal:4',
        'q1_target' => 'decimal:4', 'october_target' => 'decimal:4', 'november_target' => 'decimal:4',
        'december_target' => 'decimal:4', 'q2_target' => 'decimal:4', 'january_target' => 'decimal:4',
        'february_target' => 'decimal:4', 'march_target' => 'decimal:4', 'q3_target' => 'decimal:4',
        'april_target' => 'decimal:4', 'may_target' => 'decimal:4', 'june_target' => 'decimal:4',
        'q4_target' => 'decimal:4',
        'is_active' => 'boolean',
        'is_reference_only' => 'boolean',
        'is_supplementary' => 'boolean',
        'exclude_from_overall_score' => 'boolean',
        'is_calculated_weight' => 'boolean',
        'performers' => 'array',
        'template_type' => 'string',
        'summary_type' => 'string',
        'goal_direction' => 'string',
        'include_in_parent_rollup' => 'boolean',
        'recall_requested_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'revision_number' => 'integer',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function leadDepartment()
    {
        return $this->belongsTo(Department::class, 'lead_department_id');
    }

    public function parent()
    {
        return $this->belongsTo(StrategicObjective::class, 'parent_id');
    }

    public function recallRequester()
    {
        return $this->belongsTo(User::class, 'recall_requested_by');
    }

    public function lastEditor()
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function children()
    {
        return $this->hasMany(StrategicObjective::class, 'parent_id')
            ->orderByRaw('LENGTH(code)')
            ->orderBy('code');
    }

    public function rollupChildren()
    {
        return $this->children()->where('include_in_parent_rollup', true);
    }

    public function effectiveReportingFrequency(?Department $department = null): string
    {
        return $this->reporting_frequency ?: ($department ?? $this->department)?->reporting_type ?: 'monthly';
    }

    public function isKpiForDepartment(?int $departmentId): bool
    {
        if (!$departmentId || blank($this->kpi_code)) {
            return false;
        }

        return in_array($departmentId, array_map('intval', $this->performers ?? []), true);
    }

    /**
     * Sort hierarchical numeric codes naturally (1, 2, 10 and
     * 1.1, 1.2, 1.10) instead of using plain lexical ordering.
     */
    public function scopeOrderByNaturalCode($query)
    {
        return $query->orderByRaw('LENGTH(code)')->orderBy('code');
    }

    public function planItems()
    {
        return $this->hasMany(PlanItem::class);
    }

    // --- Weight Logic Helpers ---

    /**
     * Get the total weight currently allocated to sub-objectives.
     */
    public function getAllocatedWeight(): float
    {
        return (float) $this->children()->sum('weight');
    }

    /**
     * Get the remaining weight available for allocation.
     * Logic: Parent Weight - Sum(Children Weights)
     */
    public function getRemainingWeight(): float
    {
        return (float) $this->weight - $this->getAllocatedWeight();
    }

    /**
     * Check if the objective's weight is fully distributed to its children.
     */
    public function isWeightFullyAllocated(): bool
    {
        // Allow a tiny float rounding buffer (e.g. 0.0001)
        return abs($this->getRemainingWeight()) < 0.001;
    }

    /**
     * Check if we can allocate X amount of weight to a child.
     */
    public function canAllocateWeight(float $weightToAdd, ?float $ignoreChildId = null): bool
    {
        $currentAllocated = $this->children();
        if ($ignoreChildId) {
            $currentAllocated->where('id', '!=', $ignoreChildId);
        }
        $currentAllocatedSum = $currentAllocated->sum('weight');

        $remaining = (float) $this->weight - $currentAllocatedSum;

        // Use a small epsilon for float comparison safety
        return ($weightToAdd <= $remaining + 0.001);
    }

    /**
     * Check if the unit of measurement is time-based.
     */
    public function isTemporal(): bool
    {
        $temporalUnits = ['hrs', 'min', 'days', 'time', 'hours', 'minutes', 'seconds'];
        return in_array(strtolower($this->unit), $temporalUnits);
    }

    /**
     * Format a decimal value into a temporal string (HH:MM).
     */
    public function formatTemporal($value): string
    {
        if (!$value || $value == 0)
            return '00:00';

        $hours = floor($value);
        $minutes = round(($value - $hours) * 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Parse a temporal string (HH:MM) into a decimal value.
     */
    public static function parseTemporal($string): float
    {
        if (!$string)
            return 0;

        $parts = explode(':', $string);
        if (count($parts) == 2) {
            return (float) $parts[0] + ($parts[1] / 60);
        }

        return (float) $string;
    }
    /**
     * Parse the unit string to find a scale factor (e.g., "(1000)").
     * Returns the factor if found, otherwise 1.
     */
    public function getScaleFactorAttribute(): float
    {
        if (preg_match('/\(([\d,]+)\)/', $this->unit, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }

        // Handle common variations
        if (stripos($this->unit, '1000') !== false || stripos($this->unit, '000') !== false) {
            return 1000.0;
        }

        return 1.0;
    }

    /**
     * Traverse up the hierarchy to find the top-level (root) objective.
     */
    public function getRoot(): StrategicObjective
    {
        $current = $this;
        while ($current->parent_id) {
            $parent = $current->parent()->first();
            if (!$parent) break;
            $current = $parent;
        }
        return $current;
    }

    public function getHierarchyLevel(): int
    {
        $level = 1;
        $current = $this;
        while ($current->parent_id && $level < 10) {
            $current = $current->parent()->first();
            if (!$current) break;
            $level++;
        }
        return $level;
    }

    public function syncCalculatedWeight(): void
    {
        if ($this->is_calculated_weight) {
            $this->weight = $this->children()->sum('weight');
            $this->saveQuietly();
        }
    }

    /**
     * Calculate progress percentage (0-100) based on direction and baseline.
     */
    public function calculateProgress(float $actual, float $planned, ?float $baseline = null): float
    {
        // Distance units (including Kilometer) are quantitative measurements.
        // They intentionally use this target-versus-actual calculation, with the
        // configured goal direction and baseline, rather than percentage validation.
        $baseline = $baseline ?? $this->baseline_value ?? 0;
        $direction = $this->goal_direction ?? self::DIRECTION_INCREASE;

        if ($direction === self::DIRECTION_DECREASE) {
            if ($baseline <= $planned) {
                return $actual <= $planned ? 100 : ($actual > 0 ? round(min(100, ($planned / $actual) * 100), 2) : 0);
            }
            $denominator = $baseline - $planned;
            $progress = ($baseline - $actual) / $denominator;
        } else {
            if ($planned <= $baseline) {
                return $actual >= $planned ? 100 : ($planned > 0 ? round(min(100, ($actual / $planned) * 100), 2) : 0);
            }
            $denominator = $planned - $baseline;
            $progress = ($actual - $baseline) / $denominator;
        }

        return (float) round(max(0, $progress * 100), 2);
    }

    /**
     * Weekly reporting shows raw operational attainment and is intentionally
     * not capped. Weight and the 100% ceiling are applied only in monthly and
     * higher-level scoring.
     */
    public function calculateWeeklyAchievement(float $actual, float $planned): float
    {
        if (($this->goal_direction ?? self::DIRECTION_INCREASE) === self::DIRECTION_DECREASE) {
            return $actual <= 0 ? 100.0 : (float) round(max(0, ($planned / $actual) * 100), 2);
        }

        if ($planned == 0.0) {
            return $actual >= 0 ? 100.0 : 0.0;
        }

        return (float) round(max(0, ($actual / $planned) * 100), 2);
    }

    public function scopeVisibleToUser($query, User $user)
    {
        if (
            $user->isSystemAdmin()
            || $user->isHRManager()
            || $user->hasPermission('manage_objectives')
        ) {
            return $query;
        }

        if ($user->isPlanReportHeadOffice() || $user->isHeadOffice()) {
            return $query->whereIn('status', ['submitted', 'recall_requested_from_head_office', 'approved', 'returned']);
        }

        if ($user->isPlanReportDirectorate()) {
            return $query->whereIn('status', ['submitted_to_plan_report_directorate', 'recall_requested_from_plan_report_directorate', 'submitted', 'approved', 'returned']);
        }

        if (
            ($user->isDeptHead() || $user->isDepartmentChief() || $user->isDepartmentDirectorate())
            && $user->department_id
        ) {
            return $query->whereIn('status', ['approved', 'submitted'])->where(function ($scopedQuery) use ($user) {
                $scopedQuery
                    ->where('department_id', $user->department_id)
                    ->orWhereJsonContains('performers', (int) $user->department_id);
            });
        }

        if ($user->hasPermission('view_department_objectives_only') && $user->department_id) {
            $approvedAssignment = function ($assignmentQuery) use ($user) {
                $assignmentQuery->where('performer_id', (int) $user->department_id)
                    ->where('status', 'approved_for_experts');
            };

            return $query->where('status', 'approved')
                ->where(function ($visible) use ($approvedAssignment) {
                    $visible->whereHas('planItems.assignments', $approvedAssignment)
                        ->orWhereHas('children.planItems.assignments', $approvedAssignment)
                        ->orWhereHas('children.children.planItems.assignments', $approvedAssignment);
                });
        }

        if ($user->department_id) {
            return $query->whereIn('status', ['approved', 'submitted'])->where('department_id', $user->department_id);
        }

        return $query->whereRaw('1 = 0');
    }
}
