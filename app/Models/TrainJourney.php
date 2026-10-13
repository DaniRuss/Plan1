<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainJourney extends Model
{
    protected $fillable = [
        'train_number',
        'locomotive_number',
        'driver_name',
        'origin_station',
        'destination_station',
        'departure_time',
        'arrival_time',
        'status'
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime'
    ];

    public function consignments()
    {
        return $this->hasMany(CargoConsignment::class);
    }
}
