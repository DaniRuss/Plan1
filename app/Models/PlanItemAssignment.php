<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanItemAssignment extends Model
{
    protected $fillable = [
        'plan_item_id',
        'performer_id',
        'status',
        'sent_by',
        'sent_at',
        'chief_reviewed_by',
        'chief_reviewed_at',
        'chief_note',
        'assigned_directorate_user_id',
        'directorate_reviewed_by',
        'directorate_reviewed_at',
        'directorate_note',
        'accepted_at',
        'rejection_reason',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'sent_at' => 'datetime',
        'chief_reviewed_at' => 'datetime',
        'directorate_reviewed_at' => 'datetime',
    ];

    public function planItem()
    {
        return $this->belongsTo(PlanItem::class);
    }

    public function performer()
    {
        return $this->belongsTo(Department::class, 'performer_id');
    }

    public function assignedDirectorate()
    {
        return $this->belongsTo(User::class, 'assigned_directorate_user_id');
    }

    public function chiefReviewer()
    {
        return $this->belongsTo(User::class, 'chief_reviewed_by');
    }

    public function directorateReviewer()
    {
        return $this->belongsTo(User::class, 'directorate_reviewed_by');
    }
}
