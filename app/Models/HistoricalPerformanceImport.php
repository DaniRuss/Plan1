<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalPerformanceImport extends Model
{
    protected $fillable = ['year', 'original_filename', 'source_hash', 'status', 'imported_by', 'notes'];
    protected $casts = ['year' => 'integer'];

    public function kpis() { return $this->hasMany(HistoricalKpi::class); }
    public function importer() { return $this->belongsTo(User::class, 'imported_by'); }
}
