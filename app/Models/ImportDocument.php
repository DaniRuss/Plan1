<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportDocument extends Model
{
    protected $fillable = [
        'import_id', 'document_type', 'reference_number',
        'file_path', 'status', 'notes', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = ['uploaded_at' => 'datetime'];

    const TYPE_LABELS = [
        'BL'             => 'Bill of Lading',
        'WAYBILL'        => 'Truck Waybill',
        'INVOICE'        => 'Commercial Invoice',
        'PACKING_LIST'   => 'Packing List',
        'DO'             => 'Delivery Order (D/O)',
        'DECLARATION_4'  => 'Declaration No. 4',
        'DJ_T1'          => 'Djibouti T1 Transit',
        'ET_T1'          => 'Ethiopian T1 Transit',
        'GET_PASS'       => 'Get Pass',
        'CUSTOMS_APPROVAL'=> 'ECD Customs Approval',
    ];

    public function import()     { return $this->belongsTo(Import::class); }
    public function uploader()   { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->document_type] ?? $this->document_type;
    }
}
