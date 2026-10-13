<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'department_id',
        'description',
        'start_date',
        'end_date',
        'total_budget',
        'currency',
        'planned_progress_desc',
        'performance_desc',
        'completion_percentage',
        'status',
        'year',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'completion_percentage' => 'decimal:2',
        'total_budget' => 'decimal:2',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function milestones()
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    /**
     * Calculate the weighted project completion from milestones.
     */
    public function totalCompletion(): float
    {
        $milestonesQuery = $this->milestones();
        
        if ($milestonesQuery->count() == 0) {
            return (float) $this->completion_percentage;
        }

        $totalWeight = $milestonesQuery->sum('weight');
        if ($totalWeight == 0) return 0;

        $weightedSum = $this->milestones()->get()->sum(function ($milestone) {
            return $milestone->completion_percentage * $milestone->weight;
        });

        return round($weightedSum / $totalWeight, 2);
    }
}
