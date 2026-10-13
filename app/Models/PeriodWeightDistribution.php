<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodWeightDistribution extends Model
{
    protected $fillable = ['department_id','year','period','mode','status','assigned_by','assigned_at','directorate_reviewed_by','directorate_reviewed_at','approved_by','approved_at','change_reason'];
    protected $casts = ['year'=>'integer','assigned_at'=>'datetime','directorate_reviewed_at'=>'datetime','approved_at'=>'datetime'];
    public function allocations() { return $this->hasMany(PeriodWeightAllocation::class, 'distribution_id'); }
    public function department() { return $this->belongsTo(Department::class); }
}
