<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'department_id',
        'job_title',
        'supervisor_id',
        'hire_date',
        'employment_status',
    ];

    protected $casts = [
        'hire_date' => 'date',
    ];

    // Relationships
    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    public function user()
    {
        return $this->hasOne(\App\Models\User::class, 'employee_id');
    }

    public function tasks()
    {
        return $this->hasMany(\App\Models\Task::class, 'assigned_to');
    }

    // Helper methods
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isActive()
    {
        return $this->employment_status === 'active';
    }
}
