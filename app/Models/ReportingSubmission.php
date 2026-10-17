<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReportingSubmission extends Model
{
    protected $guarded = ['id'];
    protected $attributes = ['version'=>1,'status'=>'draft'];
    protected $casts = ['department_snapshot'=>'array','department_approved_at'=>'datetime','submitted_at'=>'datetime','approved_at'=>'datetime'];
    public function department() { return $this->belongsTo(Department::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
    public function entries() { return $this->hasMany(ReportingEntry::class, 'submission_id'); }
    public function sections() { return $this->hasMany(ReportingReviewSection::class, 'submission_id'); }
}
