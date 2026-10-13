<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyPerformance extends Model
{
    protected $fillable = [
        'plan_item_id',
        'year',
        'month',
        'week_number',
        'period_start',
        'period_end',
        'due_at',
        'planned_value',
        'actual_value',
        'metadata',
        'percentage',
        'notes',
        'status',
        'workflow_status',
        'workflow_note',
        'created_by',
        'submitted_by',
        'submitted_at',
        'directorate_reviewed_by',
        'directorate_reviewed_at',
        'chief_reviewed_by',
        'chief_reviewed_at',
        'plan_report_reviewed_by',
        'plan_report_reviewed_at',
        'head_office_reviewed_by',
        'head_office_reviewed_at',
        'approved_at',
    ];

    protected $casts = [
        'planned_value' => 'decimal:12',
        'actual_value' => 'decimal:12',
        'percentage' => 'decimal:12',
        'metadata' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'due_at' => 'datetime',
        'submitted_at' => 'datetime',
        'directorate_reviewed_at' => 'datetime',
        'chief_reviewed_at' => 'datetime',
        'plan_report_reviewed_at' => 'datetime',
        'head_office_reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function isLateSubmitted(): bool
    {
        return $this->submitted_at && $this->due_at && $this->submitted_at->gt($this->due_at);
    }

    public function planItem()
    {
        return $this->belongsTo(PlanItem::class);
    }

    public function stationData()
    {
        return $this->hasMany(WeeklyStationData::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function directorateReviewer()
    {
        return $this->belongsTo(User::class, 'directorate_reviewed_by');
    }

    public function chiefReviewer()
    {
        return $this->belongsTo(User::class, 'chief_reviewed_by');
    }

    public function planReportReviewer()
    {
        return $this->belongsTo(User::class, 'plan_report_reviewed_by');
    }

    public function headOfficeReviewer()
    {
        return $this->belongsTo(User::class, 'head_office_reviewed_by');
    }
}
