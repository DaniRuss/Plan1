<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'plan_item_id',
        'title',
        'description',
        'assigned_to',
        'assigned_by',
        'start_date',
        'due_date',
        'status',
        'completion_percentage',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'completion_percentage' => 'decimal:2',
        'start_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function planItem()
    {
        return $this->belongsTo(PlanItem::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function assignedBy()
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
