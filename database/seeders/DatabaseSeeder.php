<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Digital Office', 'code' => 'DO', 'objective_weight' => 15.0, 'is_active' => true],
            ['name' => 'ICT', 'code' => 'ICT', 'objective_weight' => 7.5, 'is_active' => true],
            ['name' => 'Operation', 'code' => 'OP', 'objective_weight' => 12.0, 'is_active' => true],
            ['name' => 'Global Logistics', 'code' => 'GL', 'objective_weight' => 5.0, 'is_active' => true],
            ['name' => 'B&M', 'code' => 'BM', 'objective_weight' => 5.0, 'is_active' => true],
            ['name' => 'S&E', 'code' => 'SE', 'objective_weight' => 5.0, 'is_active' => true],
            ['name' => 'Passenger', 'code' => 'PSG', 'objective_weight' => 5.0, 'is_active' => true],
            ['name' => 'Infrastructure', 'code' => 'INF', 'objective_weight' => 5.0, 'is_active' => true],
            ['name' => 'Engineering', 'code' => 'ENG', 'objective_weight' => 8.0, 'is_active' => true],
            ['name' => 'Security', 'code' => 'SEC', 'objective_weight' => 5.0, 'is_active' => true],
            ['name' => 'Research', 'code' => 'RES', 'objective_weight' => 8.0, 'is_active' => true],
            ['name' => 'Rolling Stock', 'code' => 'RS', 'objective_weight' => 4.0, 'is_active' => true],
            ['name' => 'Finance', 'code' => 'FIN', 'objective_weight' => 3.0, 'is_active' => true],
            ['name' => 'Procurement', 'code' => 'PRO', 'objective_weight' => 1.5, 'is_active' => true],
            ['name' => 'Auditing', 'code' => 'AUD', 'objective_weight' => 5.0, 'is_active' => true],
            ['name' => 'Marketing', 'code' => 'MKT', 'objective_weight' => 5.0, 'is_active' => true],
            ['name' => 'H.R', 'code' => 'HR', 'objective_weight' => 5.0, 'is_active' => true],
            ['name' => 'Planning', 'code' => 'PLN', 'objective_weight' => 15.0, 'is_active' => true],
            ['name' => 'Legal', 'code' => 'LEG', 'objective_weight' => 8.0, 'is_active' => true],
            ['name' => 'Ethics', 'code' => 'ETH', 'objective_weight' => 0, 'is_active' => true],
            ['name' => 'Communication', 'code' => 'COM', 'objective_weight' => 0, 'is_active' => true],
            ['name' => 'OCC', 'code' => 'OCC', 'objective_weight' => 0, 'is_active' => true],
            ['name' => 'Workshop', 'code' => 'WS', 'objective_weight' => 0, 'is_active' => true],
            ['name' => 'Capacity Building', 'code' => 'CB', 'objective_weight' => 0, 'is_active' => true],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                $department
            );
        }

        $this->call([
            RolesAndPermissionsSeeder::class,
            DefaultUsersSeeder::class,
            WorkflowUsersSeeder::class,
        ]);
    }
}
