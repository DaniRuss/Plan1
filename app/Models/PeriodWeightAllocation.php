<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodWeightAllocation extends Model
{
    protected $fillable = ['distribution_id','plan_item_id','original_weight','effective_weight'];
    protected $casts = ['original_weight'=>'decimal:4','effective_weight'=>'decimal:4'];
    public function distribution() { return $this->belongsTo(PeriodWeightDistribution::class, 'distribution_id'); }
    public function planItem() { return $this->belongsTo(PlanItem::class); }
}
