<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReportingEntry extends Model
{
    protected $guarded = ['id'];
    protected $attributes = ['version'=>1,'state'=>'missing','evidence_required'=>false];
    protected $casts = ['evidence_required'=>'boolean','evidence_paths'=>'array'];
    public function submission() { return $this->belongsTo(ReportingSubmission::class, 'submission_id'); }
    public function section() { return $this->belongsTo(ReportingReviewSection::class, 'section_id'); }
    public function planItem() { return $this->belongsTo(PlanItem::class); }
    public function editor() { return $this->belongsTo(User::class, 'updated_by'); }
}
