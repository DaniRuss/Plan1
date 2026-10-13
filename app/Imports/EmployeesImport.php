<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Department;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;

class EmployeesImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        // Find department by code
        $department = null;
        if (!empty($row['department_code'])) {
            $department = Department::where('code', $row['department_code'])->first();
        }

        // Find supervisor by employee number
        $supervisor = null;
        if (!empty($row['supervisor_number'])) {
            $supervisor = Employee::where('employee_number', $row['supervisor_number'])->first();
        }

        return new Employee([
            'employee_number' => $row['employee_number'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'department_id' => $department?->id,
            'job_title' => $row['job_title'] ?? null,
            'supervisor_id' => $supervisor?->id,
            'hire_date' => !empty($row['hire_date']) ? \Carbon\Carbon::parse($row['hire_date']) : null,
            'employment_status' => $row['status'] ?? 'active',
        ]);
    }

    public function rules(): array
    {
        return [
            'employee_number' => 'required|unique:employees,employee_number',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'nullable|email|unique:employees,email',
            'status' => 'nullable|in:active,on_leave,terminated',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'employee_number.required' => 'Employee number is required',
            'employee_number.unique' => 'Employee number already exists',
            'email.unique' => 'Email already exists',
        ];
    }
}
