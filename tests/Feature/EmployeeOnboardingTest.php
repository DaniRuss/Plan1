<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'system_admin']);
        Role::create(['name' => 'employee']);
    }

    public function test_employee_number_is_generated_on_save_when_browser_does_not_supply_it(): void
    {
        $department = Department::create(['name' => 'Operation', 'code' => 'OPS']);
        $admin = User::factory()->create(['department_id' => $department->id]);
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());

        $this->actingAs($admin)->post(route('employees.store'), [
            'first_name' => 'Mulu', 'last_name' => 'Hassen', 'email' => 'mulu@example.test',
            'department_id' => $department->id, 'employment_status' => 'active',
        ])->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EDR/OPS/001', 'department_id' => $department->id,
        ]);

        $employee = Employee::where('email', 'mulu@example.test')->firstOrFail();
        $account = User::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('mulu@example.test', $account->email);
        $this->assertTrue(Hash::check('EDR@123456', $account->password));
        $this->assertTrue($account->must_change_password);
        $this->assertTrue($account->hasRole('employee'));
    }

    public function test_created_user_account_inherits_employee_department(): void
    {
        $department = Department::create(['name' => 'Operation', 'code' => 'OPS']);
        $admin = User::factory()->create(['department_id' => $department->id]);
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $employee = Employee::create([
            'employee_number' => 'EDR/OPS/001', 'first_name' => 'Mulu', 'last_name' => 'Hassen',
            'email' => 'mulu@example.test', 'department_id' => $department->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('employees.create-account', $employee));

        $this->assertDatabaseHas('users', [
            'employee_id' => $employee->id, 'department_id' => $department->id,
        ]);
    }

    public function test_new_employee_must_change_temporary_password_before_accessing_system(): void
    {
        $department = Department::create(['name' => 'Commercial', 'code' => 'COM']);
        $admin = User::factory()->create(['department_id' => $department->id]);
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());

        $this->actingAs($admin)->post(route('employees.store'), [
            'first_name' => 'Sara', 'last_name' => 'Bekele', 'email' => 'sara@example.test',
            'department_id' => $department->id, 'employment_status' => 'active',
        ])->assertRedirect(route('employees.index'));

        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => 'sara@example.test', 'password' => 'EDR@123456',
        ])->assertRedirect(route('password.first.edit'));

        $this->get(route('dashboard'))->assertRedirect(route('password.first.edit'));
        $this->get(route('password.first.edit'))->assertOk();

        $this->put(route('password.first.update'), [
            'current_password' => 'EDR@123456',
            'password' => 'NewSecure@789',
            'password_confirmation' => 'NewSecure@789',
        ])->assertRedirect(route('dashboard'));

        $account = User::where('email', 'sara@example.test')->firstOrFail();
        $this->assertFalse($account->must_change_password);
        $this->assertTrue(Hash::check('NewSecure@789', $account->password));
        $this->get(route('dashboard'))->assertOk();
    }
}
