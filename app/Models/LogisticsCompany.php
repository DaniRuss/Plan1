<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsCompany extends Model
{
    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address'
    ];

    public function consignments()
    {
        return $this->hasMany(CargoConsignment::class);
    }
}
