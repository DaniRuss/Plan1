<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Roles and permissions are reused many times by the shared navigation.
     * Keep the resolved relationships on this model for the current request so
     * every Blade authorization check does not issue another database query.
     */
    protected array $roleCheckCache = [];
    protected array $permissionCheckCache = [];

    public const ROLE_SYSTEM_ADMIN = 'system_admin';
    public const ROLE_HR_MANAGER = 'hr_manager';
    public const ROLE_DEPT_HEAD = 'dept_head';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_EMPLOYEE = 'employee';
    public const ROLE_AUDITOR = 'auditor';
    public const ROLE_DEPARTMENT_EXPERT = 'department_expert';
    public const ROLE_DEPARTMENT_DIRECTORATE = 'department_directorate';
    public const ROLE_DEPARTMENT_CHIEF = 'department_chief';
    public const ROLE_HEAD_OFFICE = 'head_office';
    public const ROLE_PLAN_REPORT_HEAD_OFFICE = 'plan_report_head_office';
    public const ROLE_PLAN_REPORT_DEPARTMENT_EXPERT = 'plan_report_department_expert';
    public const ROLE_PLAN_REPORT_DIRECTORATE = 'plan_report_directorate';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'password',
        'must_change_password',
        'department_id',
        'role', // Deprecated in favor of roles() relationship
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the department that the user belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // RBAC Relationships
    public function roles()
    {
        return $this->belongsToMany(\App\Models\Role::class, 'user_roles');
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function getRoleNames()
    {
        $this->loadMissing('roles');

        return $this->roles->pluck('name');
    }

    public function canCreatePerformanceReport(): bool
    {
        return $this->is_active && ($this->isSystemAdmin() || $this->isPlanReportDepartmentExpert()
            || $this->isPlanReportDirectorate() || $this->isPlanReportHeadOffice());
    }

    // Helper Methods
    public function hasRole($roleName)
    {
        return $this->roleCheckCache[$roleName] ??= (function () use ($roleName) {
            $this->loadMissing('roles');

            return $this->roles->contains('name', $roleName);
        })();
    }

    public function hasPermission($permissionName)
    {
        return $this->permissionCheckCache[$permissionName] ??= (function () use ($permissionName) {
            $this->loadMissing('roles.permissions');

            return $this->roles->contains(
                fn ($role) => $role->permissions->contains('name', $permissionName)
            );
        })();
    }

    /**
     * Check if user is a system administrator.
     */
    public function isSystemAdmin()
    {
        return $this->hasRole(self::ROLE_SYSTEM_ADMIN);
    }

    public function isHRManager()
    {
        return $this->hasRole(self::ROLE_HR_MANAGER);
    }

    public function isDeptHead()
    {
        return $this->hasRole(self::ROLE_DEPT_HEAD);
    }

    public function isDepartmentExpert(): bool
    {
        return $this->hasRole(self::ROLE_DEPARTMENT_EXPERT);
    }

    public function isDepartmentDirectorate(): bool
    {
        return $this->hasRole(self::ROLE_DEPARTMENT_DIRECTORATE);
    }

    public function isDepartmentChief(): bool
    {
        return $this->hasRole(self::ROLE_DEPARTMENT_CHIEF);
    }

    public function isHeadOffice(): bool
    {
        return $this->hasRole(self::ROLE_HEAD_OFFICE);
    }

    public function isPlanReportHeadOffice(): bool
    {
        return $this->hasRole(self::ROLE_PLAN_REPORT_HEAD_OFFICE);
    }

    public function isPlanReportDepartmentExpert(): bool
    {
        return $this->hasRole(self::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT);
    }

    public function isPlanReportDirectorate(): bool
    {
        return $this->hasRole(self::ROLE_PLAN_REPORT_DIRECTORATE);
    }

    public function canReceiveDepartmentPlans(): bool
    {
        return $this->isSystemAdmin() || $this->isPlanReportDepartmentExpert()
            || $this->isPlanReportDirectorate() || $this->isPlanReportHeadOffice()
            || ($this->department_id && static::where('department_id', $this->department_id)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                    self::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT, self::ROLE_PLAN_REPORT_DIRECTORATE,
                    self::ROLE_PLAN_REPORT_HEAD_OFFICE,
                ]))->exists());
    }

    public function canManageObjectiveFrequency(): bool
    {
        return $this->isSystemAdmin() || $this->isPlanReportDepartmentExpert()
            || $this->isPlanReportDirectorate() || $this->isPlanReportHeadOffice();
    }

    /**
     * Organization-wide reviewers may switch between every reporting
     * frequency. Department-scoped users use only their department's configured
     * reporting frequency.
     */
    public function hasOrganizationWidePerformanceAccess(): bool
    {
        return $this->isSystemAdmin()
            || $this->hasPermission('view_all_performance')
            || $this->hasPermission('review_office_performance')
            || $this->hasPermission('final_approve_office_performance')
            || $this->hasPermission('view_final_approved_records');
    }

    public function canAccessReportingModule(string $reportingType): bool
    {
        if (!$this->hasPermission('view_performance')) {
            return false;
        }

        if ($this->hasOrganizationWidePerformanceAccess()) {
            return true;
        }

        return $this->department && $this->department->hasReportingFrequency(strtolower($reportingType));
    }

    public function canAccessWeeklyOperationalDetail(): bool
    {
        if (!$this->hasPermission('view_performance')) {
            return false;
        }

        if ($this->hasOrganizationWidePerformanceAccess()) {
            return true;
        }

        return $this->department && ($this->department->hasReportingFrequency('daily')
            || $this->department->hasReportingFrequency('weekly'));
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include users from a specific department.
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeVisibleWithinDepartment($query, ?int $departmentId)
    {
        if (!$departmentId) {
            return $query;
        }

        return $query->where('department_id', $departmentId);
    }

    /**
     * Get the user's full role label.
     */
    public function getRoleNameAttribute(): string
    {
        $this->loadMissing('roles');
        $role = $this->roles->first();
        return $role ? $role->description : 'No Role Assigned';
    }
}
