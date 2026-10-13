<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Import extends Model
{
    // ── Status Constants ──────────────────────────────────────────────────
    const S_DOCUMENT_SUBMITTED  = 'DOCUMENT_SUBMITTED';
    const S_ECD_VALIDATED       = 'ECD_VALIDATED';
    const S_RELEASE_DOC         = 'RELEASE_DOC_RECEIVED';
    const S_WAGON_ASSIGNED      = 'WAGON_ASSIGNED';
    const S_AT_RAILWAY_YARD     = 'AT_RAILWAY_YARD';
    const S_TRANSIT_DOC_READY   = 'TRANSIT_DOC_READY';
    const S_OPERATION_APPROVED  = 'OPERATION_APPROVED';
    const S_LOADED_ON_TRAIN     = 'LOADED_ON_TRAIN';
    const S_DISPATCHED          = 'DISPATCHED';

    const STAGE_ORDER = [
        'DOCUMENT_SUBMITTED'  => 1,
        'ECD_VALIDATED'       => 2,
        'RELEASE_DOC_RECEIVED'=> 3,
        'WAGON_ASSIGNED'      => 4,
        'AT_RAILWAY_YARD'     => 5,
        'TRANSIT_DOC_READY'   => 6,
        'OPERATION_APPROVED'  => 7,
        'LOADED_ON_TRAIN'     => 8,
        'DISPATCHED'          => 9,
    ];

    const STAGE_LABELS = [
        'DOCUMENT_SUBMITTED'   => 'Step 1: Documents Submitted',
        'ECD_VALIDATED'        => 'Step 2: ECD Validated',
        'RELEASE_DOC_RECEIVED' => 'Step 3: Release Doc Received',
        'WAGON_ASSIGNED'       => 'Step 4: Wagon Assigned',
        'AT_RAILWAY_YARD'      => 'Step 5: At Railway Yard',
        'TRANSIT_DOC_READY'    => 'Step 6: T1 Docs Ready',
        'OPERATION_APPROVED'   => 'Step 7: Operation Approved',
        'LOADED_ON_TRAIN'      => 'Step 8: Loaded on Train',
        'DISPATCHED'           => 'Step 9: Dispatched',
    ];

    const STAGE_ICONS = [
        'DOCUMENT_SUBMITTED'   => 'fa-file-alt',
        'ECD_VALIDATED'        => 'fa-stamp',
        'RELEASE_DOC_RECEIVED' => 'fa-file-signature',
        'WAGON_ASSIGNED'       => 'fa-pallet',
        'AT_RAILWAY_YARD'      => 'fa-warehouse',
        'TRANSIT_DOC_READY'    => 'fa-passport',
        'OPERATION_APPROVED'   => 'fa-check-double',
        'LOADED_ON_TRAIN'      => 'fa-train',
        'DISPATCHED'           => 'fa-flag-checkered',
    ];

    const CARGO_TYPE_LABELS = [
        'CNTR'     => 'Container',
        'FZ'       => 'Free Zone',
        'MACHINERY'=> 'Machinery (GMGR)',
    ];

    const CARGO_TYPE_ICONS = [
        'CNTR'     => 'fa-box',
        'FZ'       => 'fa-industry',
        'MACHINERY'=> 'fa-cogs',
    ];

    // Short code for display in badges
    const CARGO_TYPE_CODES = [
        'CNTR'     => 'CNTR',
        'FZ'       => 'FZ',
        'MACHINERY'=> 'GMGR',
    ];

    const STATUS_COLORS = [
        'DOCUMENT_SUBMITTED'   => 'info',
        'ECD_VALIDATED'        => 'primary',
        'RELEASE_DOC_RECEIVED' => 'indigo',
        'WAGON_ASSIGNED'       => 'orange',
        'AT_RAILWAY_YARD'      => 'purple',
        'TRANSIT_DOC_READY'    => 'teal',
        'OPERATION_APPROVED'   => 'success',
        'LOADED_ON_TRAIN'      => 'dark',
        'DISPATCHED'           => 'success',
    ];

    protected $fillable = [
        'job_id', 'cargo_type', 'status',
        'shipper_name', 'consignee_name', 'freight_forwarder',
        'description', 'weight', 'container_count',
        'bl_number', 'vessel_name', 'port_of_origin',
        'assigned_user_id',
    ];

    // ── Relationships ─────────────────────────────────────────────────────
    public function documents()   { return $this->hasMany(ImportDocument::class); }
    public function payments()    { return $this->hasMany(ImportPayment::class); }
    public function operation()   { return $this->hasOne(ImportOperation::class); }
    public function logs()        { return $this->hasMany(ImportLog::class)->orderByDesc('created_at'); }
    public function assignedUser(){ return $this->belongsTo(User::class, 'assigned_user_id'); }

    // ── Stage Helpers ──────────────────────────────────────────────────────
    public function getStageNumberAttribute(): int
    {
        return self::STAGE_ORDER[$this->status] ?? 1;
    }

    public function getProgressPercentageAttribute(): int
    {
        $requiredDocs = ['INVOICE', 'CUSTOMS_APPROVAL', 'DJ_T1', 'ET_T1'];
        if ($this->cargo_type === 'FZ') {
            $requiredDocs = array_merge($requiredDocs, ['WAYBILL', 'DECLARATION_4', 'GET_PASS']);
        } else {
            $requiredDocs = array_merge($requiredDocs, ['BL', 'DO']);
        }

        $completedDocs = $this->documents->whereIn('document_type', $requiredDocs)->whereIn('status', ['uploaded', 'verified'])->count();
        $totalDocs = count($requiredDocs);

        $completedStages = $this->stage_number;
        $totalStages = 9;

        $totalPayments = 1; 
        $completedPayments = $this->payments->where('payment_type', 'port_fee')->where('status', 'paid')->count() > 0 ? 1 : 0;
        
        if ($this->cargo_type === 'MACHINERY') {
            $totalPayments += 1; // Lashing
            if ($this->payments->where('payment_type', 'lashing_fee')->where('status', 'paid')->count() > 0) $completedPayments++;
        }

        $totalItems = $totalDocs + $totalStages + $totalPayments;
        $completedItems = $completedDocs + $completedStages + $completedPayments;

        return (int) round(($completedItems / $totalItems) * 100);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STAGE_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    public function getCargoTypeLabelAttribute(): string
    {
        return self::CARGO_TYPE_LABELS[$this->cargo_type] ?? $this->cargo_type;
    }

    public function getCargoTypeCodeAttribute(): string
    {
        return self::CARGO_TYPE_CODES[$this->cargo_type] ?? $this->cargo_type;
    }

    public function isStageComplete(string $status): bool
    {
        return $this->stage_number > (self::STAGE_ORDER[$status] ?? 0);
    }

    public function isStageActive(string $status): bool
    {
        return $this->status === $status;
    }

    public function getNextStageNameAttribute(): string
    {
        $order = array_flip(self::STAGE_ORDER);
        $nextNum = $this->stage_number + 1;
        if (isset($order[$nextNum])) {
            $key = $order[$nextNum];
            return str_replace('Step ' . $nextNum . ': ', '', self::STAGE_LABELS[$key] ?? '');
        }
        return 'Completion';
    }

    // ── Core Status Engine ─────────────────────────────────────────────────
    public function advanceTo(string $status, ?string $notes = null): void
    {
        $old = $this->status;
        $this->update(['status' => $status]);
        $this->logs()->create([
            'status'  => $status,
            'action'  => 'Advanced: ' . (self::STAGE_LABELS[$old] ?? $old) . ' → ' . (self::STAGE_LABELS[$status] ?? $status),
            'notes'   => $notes,
            'user_id' => auth()->id(),
        ]);
    }

    // ── Document Helpers ───────────────────────────────────────────────────
    public function hasDocument(string $type): bool
    {
        return $this->documents
            ->where('document_type', $type)
            ->whereIn('status', ['uploaded', 'verified'])
            ->isNotEmpty();
    }

    public function getRequiredDocumentsForStage(): array
    {
        $byStatus = [
            'DOCUMENT_SUBMITTED'   => ['INVOICE', 'PACKING_LIST'],
            'ECD_VALIDATED'        => ['CUSTOMS_APPROVAL'],
            'RELEASE_DOC_RECEIVED' => ($this->cargo_type === 'FZ') ? ['DECLARATION_4'] : ['DO'],
            'TRANSIT_DOC_READY'    => ['DJ_T1', 'ET_T1'],
        ];
        return $byStatus[$this->status] ?? [];
    }

    // ── Missing Alerts ─────────────────────────────────────────────────────
    public function getMissingAlertsAttribute(): array
    {
        $alerts = [];
        $status = $this->status;

        // Block Stage 1 -> 2
        if ($status === self::S_DOCUMENT_SUBMITTED) {
            if (!$this->hasDocument('INVOICE')) $alerts[] = ['text' => 'Commercial Invoice', 'type' => 'doc', 'key' => 'INVOICE'];
            if ($this->cargo_type === 'FZ' && !$this->hasDocument('WAYBILL')) $alerts[] = ['text' => 'Truck Waybill', 'type' => 'doc', 'key' => 'WAYBILL'];
            if ($this->cargo_type !== 'FZ' && !$this->hasDocument('BL')) $alerts[] = ['text' => 'Bill of Lading', 'type' => 'doc', 'key' => 'BL'];
        }
        
        // Block Stage 3 -> 4 (Wagon Assignment requires Port Fee)
        if ($status === self::S_RELEASE_DOC) {
            if ($this->payments->where('payment_type', 'port_fee')->where('status', 'paid')->isEmpty()) {
                $alerts[] = ['text' => 'Port Fee Payment', 'type' => 'pay', 'key' => 'port_fee'];
            }
        }
        
        // Block Stage 6 -> 7 (Machinery Lashing enforced at controller, but good to show)
        if ($status === self::S_TRANSIT_DOC_READY && $this->cargo_type === 'MACHINERY') {
             if (!$this->operation || !$this->operation->lashing_completed) {
                 $alerts[] = ['text' => 'Lashing Completion', 'type' => 'none', 'key' => ''];
             }
        }

        return $alerts;
    }

    // ── Static Generator ──────────────────────────────────────────────────
    public static function generateJobId(): string
    {
        do {
            $id = 'EDR-IMP-' . strtoupper(Str::random(6));
        } while (self::where('job_id', $id)->exists());
        return $id;
    }
}
