<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportOperation extends Model
{
    protected $fillable = [
        'import_id',
        'wagon_id', 'yard_location', 'requires_escort',
        'transfer_date',
        'storage_location', 'get_pass_obtained',
        'lashing_required', 'lashing_completed', 'special_handling_notes',
        'train_id', 'loading_time',
        'operation_approved_at', 'approved_by',
    ];

    protected $casts = [
        'transfer_date'        => 'datetime',
        'loading_time'         => 'datetime',
        'operation_approved_at'=> 'datetime',
        'requires_escort'      => 'boolean',
        'get_pass_obtained'    => 'boolean',
        'lashing_required'     => 'boolean',
        'lashing_completed'    => 'boolean',
    ];

    public function import()   { return $this->belongsTo(Import::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
