<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        $systemAdmin = Role::where('name', 'system_admin')->first();
        $hrManager = Role::where('name', 'hr_manager')->first();
        $deptHead = Role::where('name', 'dept_head')->first();
        $supervisor = Role::where('name', 'supervisor')->first();
        $employee = Role::where('name', 'employee')->first();
        $auditor = Role::where('name', 'auditor')->first();

        $users = [
            [
                'name' => 'System Administrator',
                'email' => 'admin@iprpms.gov.et',
                'password' => 'Admin@2026',
                'role' => $systemAdmin,
                'department_id' => Department::where('code', 'DO')->value('id'),
            ],
            [
                'name' => 'HR Manager',
                'email' => 'hr@iprpms.gov.et',
                'password' => 'HR@2026',
                'role' => $hrManager,
                'department_id' => Department::where('code', 'HR')->value('id'),
            ],
            [
                'name' => 'Department Head',
                'email' => 'depthead@iprpms.gov.et',
                'password' => 'Dept@2026',
                'role' => $deptHead,
                'department_id' => Department::where('code', 'PLN')->value('id'),
            ],
            [
                'name' => 'Supervisor',
                'email' => 'supervisor@iprpms.gov.et',
                'password' => 'Super@2026',
                'role' => $supervisor,
                'department_id' => Department::where('code', 'PLN')->value('id'),
            ],
            [
                'name' => 'Employee',
                'email' => 'employee@iprpms.gov.et',
                'password' => 'Emp@2026',
                'role' => $employee,
                'department_id' => Department::where('code', 'PLN')->value('id'),
            ],
            [
                'name' => 'System Auditor',
                'email' => 'auditor@iprpms.gov.et',
                'password' => 'Auditor@2026',
                'role' => $auditor,
                'department_id' => Department::where('code', 'AUD')->value('id'),
            ],
        ];

        foreach ($users as $userData) {
            if (!$userData['role']) {
                $this->command?->error("Role for {$userData['email']} not found. Did you run RolesAndPermissionsSeeder?");
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'department_id' => $userData['department_id'],
                    'is_active' => true,
                ]
            );

            $user->roles()->sync([$userData['role']->id]);
        }

        $this->command?->info('Login with: admin@iprpms.gov.et / Admin@2026');
    }
}
