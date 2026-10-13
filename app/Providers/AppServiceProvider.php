<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Permission-based Gate Mapping
        // This allows middleware('can:permission_name') to work dynamically
        Gate::before(function ($user, $ability) {
            // System Admin has absolute override
            if ($user->hasRole('system_admin')) {
                return true;
            }

            // Checks if the user's roles have the specific permission assigned
            if (method_exists($user, 'hasPermission') && $user->hasPermission($ability)) {
                return true;
            }
        });

        // Role-based Gates for legacy/simple checks in blade
        Gate::define('system_admin', function ($user) {
            return $user->hasRole('system_admin');
        });

        Gate::define('hr_manager', function ($user) {
            return $user->hasRole('hr_manager');
        });

        Gate::define('dept_head', function ($user) {
            return $user->hasRole('dept_head');
        });

        Gate::define('auditor', function ($user) {
            return $user->hasRole('auditor');
        });

        Gate::define('department_expert', function ($user) {
            return $user->isDepartmentExpert();
        });

        Gate::define('department_directorate', function ($user) {
            return $user->isDepartmentDirectorate();
        });

        Gate::define('department_chief', function ($user) {
            return $user->isDepartmentChief();
        });

        Gate::define('head_office', function ($user) {
            return $user->isHeadOffice();
        });

        Gate::define('plan_report_head_office', function ($user) {
            return $user->isPlanReportHeadOffice();
        });

        Gate::define('plan_report_department_expert', function ($user) {
            return $user->isPlanReportDepartmentExpert();
        });

        Gate::define('plan_report_directorate', function ($user) {
            return $user->isPlanReportDirectorate();
        });

        Gate::define('view_daily_performance', fn ($user) => $user->canAccessReportingModule('daily'));
        Gate::define('view_weekly_performance', fn ($user) => $user->canAccessReportingModule('weekly'));
        Gate::define('view_monthly_performance', fn ($user) => $user->canAccessReportingModule('monthly'));
        Gate::define('view_weekly_operational_detail', fn ($user) => $user->canAccessWeeklyOperationalDetail());
        Gate::define('access_department_lookup', fn ($user) =>
            $user->hasPermission('manage_departments')
            || $user->hasPermission('manage_employees')
            || $user->hasPermission('manage_users')
            || $user->hasPermission('manage_objectives')
        );
    }
}
