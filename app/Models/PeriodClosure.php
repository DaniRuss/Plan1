<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'department_id',
        'certified_by',
        'certified_at',
        'notes',
    ];

    protected $casts = [
        'certified_at' => 'datetime',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function certifiedBy()
    {
        return $this->belongsTo(User::class, 'certified_by');
    }
}
