<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EdrReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'reporting_period',
        'year',
        'executive_summary',
        'introduction',
        'challenges',
        'solutions',
        'non_planned_activities',
        'strengths',
        'weaknesses',
        'best_practices',
        'next_focus_areas',
        'summary_and_result',
        'status',
        'submitted_by',
        'reviewed_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function getDepartmentLabelAttribute(): string
    {
        return $this->department?->name ?? 'All Departments';
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function annexes()
    {
        return $this->hasMany(EdrAnnex::class);
    }

    public static function periodLabel(?string $period): string
    {
        $parts = explode(':', strtolower((string) $period));
        if (($parts[0] ?? null) === 'month' && isset($parts[1])) {
            return 'Monthly — ' . ucfirst($parts[1]);
        }
        if (($parts[0] ?? null) === 'week' && isset($parts[1], $parts[2])) {
            return 'Weekly — ' . ucfirst($parts[1]) . ' W' . (int) $parts[2];
        }
        return strtoupper((string) $period);
    }
}
