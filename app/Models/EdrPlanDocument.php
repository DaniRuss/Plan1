<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EdrPlanDocument extends Model
{
    protected $fillable = [
        'year', 'introduction', 'past_performance', 'strategic_issues',
        'implementation_strategies', 'monitoring_evaluation',
        'challenges_solutions', 'prepared_by', 'status',
        'directorate_reviewed_by', 'directorate_reviewed_at',
        'head_approved_by', 'head_approved_at',
        'word_file_path', 'word_file_name',
    ];

    protected $casts = ['year' => 'integer', 'directorate_reviewed_at' => 'datetime', 'head_approved_at' => 'datetime'];
    public function preparer() { return $this->belongsTo(User::class, 'prepared_by'); }
}
