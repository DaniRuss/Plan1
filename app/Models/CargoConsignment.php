<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CargoConsignment extends Model
{
    protected $fillable = [
        'tracking_id',
        'consignment_number',
        'shipper_name',
        'consignee_name',
        'goods_type',
        'quantity',
        'weight',
        'volume',
        'container_id',
        'current_status',
        'origin_station',
        'destination_station',
        'train_journey_id',
        'logistics_company_id'
    ];

    public function trainJourney()
    {
        return $this->belongsTo(TrainJourney::class);
    }

    public function logisticsCompany()
    {
        return $this->belongsTo(LogisticsCompany::class);
    }

    public function movementLogs()
    {
        return $this->hasMany(CargoMovementLog::class)->orderBy('recorded_at', 'desc');
    }

    /**
     * Record a status change in the history log.
     */
    public function logStatus($status, $location = null, $remarks = null)
    {
        $this->update(['current_status' => $status]);
        
        return $this->movementLogs()->create([
            'status' => $status,
            'recorded_at' => now(),
            'recorded_by_user_id' => auth()->id(),
            'location' => $location,
            'remarks' => $remarks
        ]);
    }
}
