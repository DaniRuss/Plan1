<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalKpiMapping extends Model
{
    public const TYPES = ['direct','renamed','split','merged','unit_changed','definition_changed','not_comparable','retired'];
    public const STATUSES = ['draft','submitted','approved','rejected'];
    protected $fillable = [
        'historical_kpi_id','current_objective_id','mapping_type','comparable','confidence','expert_comment',
        'status','prepared_by','reviewed_by','reviewed_at','review_comment','version',
    ];
    protected $casts = ['comparable'=>'boolean','confidence'=>'decimal:2','reviewed_at'=>'datetime','version'=>'integer'];

    public function historicalKpi() { return $this->belongsTo(HistoricalKpi::class); }
    public function currentObjective() { return $this->belongsTo(StrategicObjective::class, 'current_objective_id'); }
    public function preparer() { return $this->belongsTo(User::class, 'prepared_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function scopeApprovedComparable($query) { return $query->where('status','approved')->where('comparable',true); }
}
