<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportPayment extends Model
{
    protected $fillable = [
        'import_id', 'payment_type', 'amount',
        'status', 'receipt_number', 'paid_at', 'recorded_by',
    ];

    protected $casts = ['paid_at' => 'datetime'];

    const TYPE_LABELS = [
        'port_fee'     => 'Port Fee',
        'escort_fee'   => 'Escort Fee',
        'transfer_fee' => 'Transfer Fee',
        'lashing_fee'  => 'Lashing Fee',
        'loading_fee'  => 'Loading Fee',
        'storage_fee'  => 'Storage Fee',
    ];

    public function import()   { return $this->belongsTo(Import::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->payment_type] ?? $this->payment_type;
    }

    public function markPaid(string $receiptNumber, float $amount): void
    {
        $this->update([
            'status'         => 'paid',
            'receipt_number' => $receiptNumber,
            'amount'         => $amount,
            'paid_at'        => now(),
            'recorded_by'    => auth()->id(),
        ]);
    }
}
