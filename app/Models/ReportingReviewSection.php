<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReportingReviewSection extends Model
{
    protected $guarded = ['id'];
    protected $attributes = ['version'=>1,'status'=>'draft'];
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function submission() { return $this->belongsTo(ReportingSubmission::class, 'submission_id'); }
    public function entries() { return $this->hasMany(ReportingEntry::class, 'section_id'); }
}
