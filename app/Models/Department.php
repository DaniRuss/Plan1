<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'objective_weight',
        'parent_id',
        'is_active',
        'reporting_type',
    ];

    protected $casts = [
        'objective_weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function isWeekly(): bool
    {
        return $this->reporting_type === 'weekly';
    }

    public function hasReportingFrequency(string $frequency): bool
    {
        return $this->reporting_type === $frequency || PlanItem::query()
            ->whereHas('plan', fn ($plan) => $plan->where('department_id', $this->id)->where('status', 'approved'))
            ->whereHas('strategicObjective', fn ($objective) => $objective->where('status', 'approved'))
            ->forReportingFrequency($frequency)->exists();
    }

    public function scopeWithReportingFrequency($query, string $frequency)
    {
        return $query->where(fn ($department) => $department->where('reporting_type', $frequency)
            ->orWhereHas('plans', fn ($plan) => $plan->where('status', 'approved')
                ->whereHas('items', fn ($item) => $item->forReportingFrequency($frequency)
                    ->whereHas('strategicObjective', fn ($objective) => $objective->where('status', 'approved')))));
    }

    public function isMonthly(): bool
    {
        return $this->reporting_type === 'monthly';
    }

    public function isDaily(): bool
    {
        return $this->reporting_type === 'daily';
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function strategicObjectives(): HasMany
    {
        return $this->hasMany(StrategicObjective::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
