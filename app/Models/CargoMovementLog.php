<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CargoMovementLog extends Model
{
    protected $fillable = [
        'cargo_consignment_id',
        'status',
        'recorded_at',
        'recorded_by_user_id',
        'location',
        'remarks'
    ];

    protected $casts = [
        'recorded_at' => 'datetime'
    ];

    public function consignment()
    {
        return $this->belongsTo(CargoConsignment::class, 'cargo_consignment_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
