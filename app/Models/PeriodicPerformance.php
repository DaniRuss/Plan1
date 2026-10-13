<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodicPerformance extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_item_id',
        'period',
        'actual_value',
        'notes',
        'status',
        'workflow_status',
        'workflow_note',
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
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'actual_value' => 'decimal:2',
        'submitted_at' => 'datetime',
        'directorate_reviewed_at' => 'datetime',
        'chief_reviewed_at' => 'datetime',
        'plan_report_reviewed_at' => 'datetime',
        'head_office_reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function planItem()
    {
        return $this->belongsTo(\App\Models\PlanItem::class);
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'submitted_by');
    }

    public function directorateReviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'directorate_reviewed_by');
    }

    public function chiefReviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'chief_reviewed_by');
    }

    public function planReportReviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'plan_report_reviewed_by');
    }

    public function headOfficeReviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'head_office_reviewed_by');
    }
}
