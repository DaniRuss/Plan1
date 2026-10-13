<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EdrAnnex extends Model
{
    use HasFactory;

    protected $fillable = [
        'edr_report_id',
        'file_path',
        'file_name',
        'caption',
    ];

    public function report()
    {
        return $this->belongsTo(EdrReport::class, 'edr_report_id');
    }
}
