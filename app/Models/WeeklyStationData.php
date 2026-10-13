<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyStationData extends Model
{
    protected $fillable = [
        'weekly_performance_id',
        'station_name',
        'planned_value',
        'actual_value',
    ];

    protected $casts = [
        'planned_value' => 'decimal:12',
        'actual_value' => 'decimal:12',
    ];

    public function weeklyPerformance()
    {
        return $this->belongsTo(WeeklyPerformance::class);
    }
}
