<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'system_admin' => 'Full system access, settings, and final approvals',
            'hr_manager' => 'HR and employee management, and institutional performance monitoring',
            'dept_head' => 'Department lead, planning, and reporting',
            'supervisor' => 'Operational supervision and task management',
            'employee' => 'Regular employee, task execution and reporting',
            'auditor' => 'Compliance monitor, view-only access to audit and reports',
            'department_expert' => 'Department expert, submits assigned objective performance for review',
            'department_directorate' => 'Department directorate, reviews and forwards department performance',
            'department_chief' => 'Department chief, assigns performers and approves department submissions',
            'head_office' => 'Head office, views final approved plans and performance reports',
            'plan_report_head_office' => 'Plan and report head office, performs final review, approval, and submission window control',
            'plan_report_department_expert' => 'Plan and report department expert, manages office plans/objectives and reviews department performance before head office approval',
            'plan_report_directorate' => 'Plan and report directorate, reviews objectives and performance forwarded by plan and report experts before head approval',
        ];

        foreach ($roles as $name => $description) {
            Role::updateOrCreate(['name' => $name], ['description' => $description]);
        }

        $permissions = [
            ['name' => 'view_employees', 'module' => 'employees', 'description' => 'View employee list and profiles'],
            ['name' => 'manage_employees', 'module' => 'employees', 'description' => 'Create, edit, and delete employees'],
            ['name' => 'import_employees', 'module' => 'employees', 'description' => 'Bulk import employees from Excel'],
            ['name' => 'create_employee_accounts', 'module' => 'employees', 'description' => 'Generate user accounts for employees'],
            ['name' => 'view_departments', 'module' => 'departments', 'description' => 'View department structures'],
            ['name' => 'manage_departments', 'module' => 'departments', 'description' => 'Create and modify departments'],
            ['name' => 'view_objectives', 'module' => 'objectives', 'description' => 'View strategic objectives'],
            ['name' => 'manage_objectives', 'module' => 'objectives', 'description' => 'Create and edit objectives and weights'],
            ['name' => 'import_objectives', 'module' => 'objectives', 'description' => 'Bulk import objectives from Excel'],
            ['name' => 'view_department_objectives_only', 'module' => 'objectives', 'description' => 'View only department objectives where the user department is a performer'],
            ['name' => 'assign_objective_performers', 'module' => 'objectives', 'description' => 'Assign performers to department objectives'],
            ['name' => 'view_plans', 'module' => 'plans', 'description' => 'View institutional/departmental plans'],
            ['name' => 'create_plan', 'module' => 'plans', 'description' => 'Draft departmental plans'],
            ['name' => 'edit_plan', 'module' => 'plans', 'description' => 'Modify existing plans'],
            ['name' => 'submit_plan', 'module' => 'plans', 'description' => 'Submit plans for official approval'],
            ['name' => 'approve_plan', 'module' => 'plans', 'description' => 'Approve or reject submitted plans'],
            ['name' => 'view_performance', 'module' => 'performance', 'description' => 'View performance actuals board'],
            ['name' => 'enter_performance', 'module' => 'performance', 'description' => 'Enter/Update actual performance values'],
            ['name' => 'approve_performance', 'module' => 'performance', 'description' => 'Approve periodic performance entries'],
            ['name' => 'submit_objective_performance', 'module' => 'performance', 'description' => 'Submit department objective performance for review'],
            ['name' => 'review_objective_performance', 'module' => 'performance', 'description' => 'Review and edit objective performance submitted by experts'],
            ['name' => 'approve_objective_performance', 'module' => 'performance', 'description' => 'Approve and forward department objective performance'],
            ['name' => 'review_office_performance', 'module' => 'performance', 'description' => 'Review department performance after chief submission'],
            ['name' => 'final_approve_office_performance', 'module' => 'performance', 'description' => 'Final approval for office-level performance submissions'],
            ['name' => 'view_reports', 'module' => 'reports', 'description' => 'View performance reports list'],
            ['name' => 'manage_reports', 'module' => 'reports', 'description' => 'Create and edit EDR narrative reports'],
            ['name' => 'submit_report', 'module' => 'reports', 'description' => 'Submit EDR reports for review'],
            ['name' => 'approve_report', 'module' => 'reports', 'description' => 'Approve finalized EDR reports'],
            ['name' => 'export_reports', 'module' => 'reports', 'description' => 'Export EDR reports to PDF/Excel/CSV'],
            ['name' => 'view_all_reports', 'module' => 'reports', 'description' => 'Access reports from any department'],
            ['name' => 'view_final_approved_records', 'module' => 'reports', 'description' => 'View only final approved organization plans and reports'],
            ['name' => 'view_employee_performance', 'module' => 'reports', 'description' => 'View individual employee task scorecards'],
            ['name' => 'export_employee_performance', 'module' => 'reports', 'description' => 'Export employee scorecards'],
            ['name' => 'view_tasks', 'module' => 'tasks', 'description' => 'View all tasks in scope'],
            ['name' => 'assign_task', 'module' => 'tasks', 'description' => 'Delegate tasks to employees'],
            ['name' => 'manage_own_tasks', 'module' => 'tasks', 'description' => 'Update progress on assigned tasks'],
            ['name' => 'submit_task_report', 'module' => 'tasks', 'description' => 'Submit formal task reports'],
            ['name' => 'approve_task_report', 'module' => 'tasks', 'description' => 'Verify and approve task completion'],
            ['name' => 'view_all_performance', 'module' => 'analytics', 'description' => 'View company-wide performance dashboard'],
            ['name' => 'view_dept_performance', 'module' => 'analytics', 'description' => 'View department-wide performance dashboard'],
            ['name' => 'view_audit_logs', 'module' => 'system', 'description' => 'Access system-wide activity audit logs'],
            ['name' => 'manage_settings', 'module' => 'system', 'description' => 'Manage institutional settings and branding'],
            ['name' => 'manage_users', 'module' => 'system', 'description' => 'Manage system user accounts'],
            ['name' => 'assign_roles', 'module' => 'system', 'description' => 'Manage role assignments'],
            ['name' => 'manage_submission_intervals', 'module' => 'system', 'description' => 'Set the allowed submission interval for department chiefs'],
            ['name' => 'view_all_plans', 'module' => 'plans', 'description' => 'View plans from all departments'],
            ['name' => 'view_feedbacks', 'module' => 'feedbacks', 'description' => 'View department performance feedback'],
            ['name' => 'manage_feedbacks', 'module' => 'feedbacks', 'description' => 'Prepare and finalize department performance feedback'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        $allPermissionIds = Permission::query()->pluck('id')->all();

        $rolePermissions = [
            'system_admin' => $allPermissionIds,
            'hr_manager' => $this->permissionIds([
                'view_employees',
                'manage_employees',
                'import_employees',
                'create_employee_accounts',
                'view_departments',
                'view_objectives',
                'view_plans',
                'view_performance',
                'view_reports',
                'export_reports',
                'view_all_reports',
                'view_employee_performance',
                'export_employee_performance',
                'view_tasks',
                'view_all_performance',
                'view_dept_performance',
                'view_audit_logs',
            ]),
            'dept_head' => $this->permissionIds([
                'view_employees',
                'view_departments',
                'view_objectives',
                'view_plans',
                'create_plan',
                'edit_plan',
                'submit_plan',
                'view_performance',
                'enter_performance',
                'view_reports',
                'manage_reports',
                'submit_report',
                'export_reports',
                'view_employee_performance',
                'export_employee_performance',
                'view_tasks',
                'assign_task',
                'approve_task_report',
                'view_dept_performance',
            ]),
            'supervisor' => $this->permissionIds([
                'view_employees',
                'view_objectives',
                'view_plans',
                'view_performance',
                'view_tasks',
                'assign_task',
                'manage_own_tasks',
                'submit_task_report',
                'approve_task_report',
            ]),
            'employee' => $this->permissionIds([
                'view_objectives',
                'view_plans',
                'view_tasks',
                'manage_own_tasks',
                'submit_task_report',
            ]),
            'auditor' => $this->permissionIds([
                'view_employees',
                'view_departments',
                'view_objectives',
                'view_plans',
                'view_performance',
                'view_reports',
                'view_all_reports',
                'export_reports',
                'view_employee_performance',
                'view_tasks',
                'view_all_performance',
                'view_dept_performance',
                'view_audit_logs',
            ]),
            'department_expert' => $this->permissionIds([
                'view_objectives',
                'view_department_objectives_only',
                'view_plans',
                'view_performance',
                'enter_performance',
                'submit_objective_performance',
                'view_feedbacks',
            ]),
            'department_directorate' => $this->permissionIds([
                'view_objectives',
                'view_plans',
                'view_performance',
                'enter_performance',
                'review_objective_performance',
                'approve_objective_performance',
                'view_feedbacks',
            ]),
            'department_chief' => $this->permissionIds([
                'view_objectives',
                'view_plans',
                'view_performance',
                'enter_performance',
                'assign_objective_performers',
                'approve_objective_performance',
                'view_dept_performance',
                'view_feedbacks',
            ]),
            'head_office' => $this->permissionIds([
                'view_plans',
                'view_reports',
                'view_performance',
                'view_all_reports',
                'view_all_performance',
                'view_all_plans',
                'view_final_approved_records',
                'view_feedbacks',
            ]),
            'plan_report_head_office' => $this->permissionIds([
                'view_objectives',
                'view_plans',
                'approve_plan',
                'view_reports',
                'approve_report',
                'export_reports',
                'view_all_reports',
                'view_performance',
                'view_all_performance',
                'view_all_plans',
                'review_office_performance',
                'final_approve_office_performance',
                'manage_settings',
                'manage_submission_intervals',
                'view_feedbacks',
            ]),
            'plan_report_department_expert' => $this->permissionIds([
                'view_objectives',
                'manage_objectives',
                'import_objectives',
                'view_plans',
                'create_plan',
                'edit_plan',
                'submit_plan',
                'view_reports',
                'manage_reports',
                'submit_report',
                'view_performance',
                'enter_performance',
                'review_office_performance',
                'approve_objective_performance',
                'view_all_plans',
                'view_feedbacks',
                'manage_feedbacks',
            ]),
            'plan_report_directorate' => $this->permissionIds([
                'view_objectives',
                'view_plans',
                'view_reports',
                'view_all_reports',
                'view_performance',
                'view_all_performance',
                'view_all_plans',
                'review_office_performance',
                'approve_objective_performance',
                'view_feedbacks',
            ]),
        ];

        foreach ($rolePermissions as $roleName => $permissionIds) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                $role->permissions()->sync($permissionIds);
            }
        }
    }

    private function permissionIds(array $permissionNames): array
    {
        return Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();
    }
}
