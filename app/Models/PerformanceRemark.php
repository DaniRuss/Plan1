<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceRemark extends Model
{
    protected $fillable = [
        'department_id',
        'year',
        'month',
        'week_number',
        'remark_type',
        'remark_text',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
