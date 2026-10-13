<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WorkflowUsersSeeder extends Seeder
{
    public function run(): void
    {
        $planningDepartmentId = Department::where('code', 'PLN')->value('id');
        $digitalOfficeDepartmentId = Department::where('code', 'DO')->value('id');

        $users = [
            [
                'name' => 'Planning Department Expert',
                'email' => 'planning.expert@iprpms.gov.et',
                'password' => 'Expert@2026',
                'role' => 'department_expert',
                'department_id' => $planningDepartmentId,
                'is_active' => true,
            ],
            [
                'name' => 'Planning Directorate Reviewer',
                'email' => 'planning.directorate@iprpms.gov.et',
                'password' => 'Directorate@2026',
                'role' => 'department_directorate',
                'department_id' => $planningDepartmentId,
                'is_active' => true,
            ],
            [
                'name' => 'Planning Department Chief',
                'email' => 'planning.chief@iprpms.gov.et',
                'password' => 'Chief@2026',
                'role' => 'department_chief',
                'department_id' => $planningDepartmentId,
                'is_active' => true,
            ],
            [
                'name' => 'Planning Expert Placeholder',
                'email' => 'placeholder.expert@iprpms.gov.et',
                'password' => 'Placeholder@2026',
                'role' => 'department_expert',
                'department_id' => $planningDepartmentId,
                'is_active' => false,
            ],
            [
                'name' => 'Planning Directorate Placeholder',
                'email' => 'placeholder.directorate@iprpms.gov.et',
                'password' => 'Placeholder@2026',
                'role' => 'department_directorate',
                'department_id' => $planningDepartmentId,
                'is_active' => false,
            ],
            [
                'name' => 'Planning Chief Placeholder',
                'email' => 'placeholder.chief@iprpms.gov.et',
                'password' => 'Placeholder@2026',
                'role' => 'department_chief',
                'department_id' => $planningDepartmentId,
                'is_active' => false,
            ],
            [
                'name' => 'Head Office Viewer',
                'email' => 'headoffice@iprpms.gov.et',
                'password' => 'HeadOffice@2026',
                'role' => 'head_office',
                'department_id' => $digitalOfficeDepartmentId ?: $planningDepartmentId,
                'is_active' => true,
            ],
            [
                'name' => 'Plan and Report Head Office',
                'email' => 'planreport.headoffice@iprpms.gov.et',
                'password' => 'PlanReportHO@2026',
                'role' => 'plan_report_head_office',
                'department_id' => $planningDepartmentId,
                'is_active' => true,
            ],
            [
                'name' => 'Plan and Report Department Expert',
                'email' => 'planreport.department@iprpms.gov.et',
                'password' => 'PlanReportDept@2026',
                'role' => 'plan_report_department_expert',
                'department_id' => $planningDepartmentId,
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            $role = Role::where('name', $userData['role'])->first();

            if (!$role) {
                $this->command?->error("Role {$userData['role']} is missing. Run RolesAndPermissionsSeeder first.");
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'department_id' => $userData['department_id'],
                    'is_active' => $userData['is_active'],
                ]
            );

            $user->roles()->sync([$role->id]);
        }
    }
}
