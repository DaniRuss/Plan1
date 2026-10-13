<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'year',
        'quarter',
        'month',
        'week',
        'plan_date',
        'department_id',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'rejection_comment',
    ];

    protected $casts = [
        'year' => 'integer',
        'plan_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PlanItem::class);
    }

}
