<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaskReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'employee_id',
        'reporting_period',
        'progress_percentage',
        'narrative',
        'challenges',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_comments',
    ];

    public function task()
    {
        return $this->belongsTo(\App\Models\Task::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Models\Employee::class, 'reviewed_by');
    }
}
