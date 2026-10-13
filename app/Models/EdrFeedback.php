<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EdrFeedback extends Model {
 protected $table='edr_feedbacks';
 protected $fillable=['department_id','year','reporting_period','strengths','weaknesses','next_focus_areas','plan_quality_score','report_quality_score','submission_score','performance_percent','performance_score','status','prepared_by','directorate_reviewed_by','directorate_reviewed_at','head_approved_by','head_approved_at'];
 protected $casts=['year'=>'integer','plan_quality_score'=>'decimal:2','report_quality_score'=>'decimal:2','submission_score'=>'decimal:2','performance_percent'=>'decimal:2','performance_score'=>'decimal:2','directorate_reviewed_at'=>'datetime','head_approved_at'=>'datetime'];
 public function department(){return $this->belongsTo(Department::class);} public function preparer(){return $this->belongsTo(User::class,'prepared_by');}
 public function getTotalScoreAttribute(): float { return round((float)$this->plan_quality_score+(float)$this->report_quality_score+(float)$this->submission_score+(float)($this->performance_score??0),2); }
}
