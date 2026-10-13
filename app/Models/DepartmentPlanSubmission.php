<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentPlanSubmission extends Model
{
    protected $fillable = ['department_id', 'submitted_by', 'year', 'title', 'notes', 'file_path', 'file_name'];
    public function department() { return $this->belongsTo(Department::class); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
}
