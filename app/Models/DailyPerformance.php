<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyPerformance extends Model
{
    protected $fillable = [
        'plan_item_id',
        'year',
        'month',
        'week_number',
        'day_of_week',
        'actual_value',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'actual_value' => 'decimal:12',
    ];

    public function planItem()
    {
        return $this->belongsTo(PlanItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
