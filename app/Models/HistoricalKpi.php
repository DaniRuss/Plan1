<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalKpi extends Model
{
    protected $fillable = [
        'historical_performance_import_id','source_row','code','kpi_code','original_code','parent_code','description','weight',
        'unit','normalized_unit','annual_target','annual_actual','reported_achievement','goal_direction',
        'direction_status','performers','is_main_objective','unit_status','version','updated_by',
    ];
    protected $casts = [
        'weight'=>'decimal:4','annual_target'=>'decimal:4','annual_actual'=>'decimal:4',
        'reported_achievement'=>'decimal:4','is_main_objective'=>'boolean','version'=>'integer',
    ];

    public function import() { return $this->belongsTo(HistoricalPerformanceImport::class, 'historical_performance_import_id'); }
    public function mappings() { return $this->hasMany(HistoricalKpiMapping::class); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}
