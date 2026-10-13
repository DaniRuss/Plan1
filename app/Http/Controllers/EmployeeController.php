<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Imports\EmployeesImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Notifications\AppNotification;

class EmployeeController extends Controller
{
    public const DEFAULT_PASSWORD = 'EDR@123456';

    public function index()
    {
        $employees = Employee::with(['department', 'supervisor'])
            ->orderBy('employee_number')
            ->paginate(20);

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        $departments = Department::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $supervisors = Employee::where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        return view('employees.create', compact('departments', 'supervisors'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_number' => 'nullable|string|max:50|unique:employees',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:employees|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'job_title' => 'nullable|string|max:100',
            'supervisor_id' => 'nullable|exists:employees,id',
            'hire_date' => 'nullable|date',
            'employment_status' => 'required|in:active,on_leave,terminated',
        ]);

        DB::transaction(function () use (&$validated) {
            // Lock the department row so two simultaneous registrations cannot
            // receive the same generated sequence number.
            Department::whereKey($validated['department_id'])->lockForUpdate()->firstOrFail();
            if (empty($validated['employee_number'])) {
                $validated['employee_number'] = $this->generateNextEmployeeNumber((int) $validated['department_id']);
            }
            $employee = Employee::create($validated);
            $this->provisionAccount($employee);
        });

        return redirect()->route('employees.index')
            ->with('success', 'Employee and system account created successfully. Username: '
                . $validated['email'] . '. Temporary password: ' . self::DEFAULT_PASSWORD);
    }

    public function show(Employee $employee)
    {
        $employee->load(['department', 'supervisor', 'subordinates', 'user']);
        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee)
    {
        $parentDepartments = Department::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        $currentDepartment = $employee->department;
        $currentParentId = $currentDepartment ? $currentDepartment->parent_id : null;
        
        $childDepartments = [];
        if ($currentParentId) {
            $childDepartments = Department::where('parent_id', $currentParentId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $supervisors = Employee::where('employment_status', 'active')
            ->where('id', '!=', $employee->id) // Can't supervise themselves
            ->orderBy('first_name')
            ->get();
            
        return view('employees.edit', compact('employee', 'parentDepartments', 'childDepartments', 'currentParentId', 'supervisors'));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'employee_number' => 'required|string|max:50|unique:employees,employee_number,' . $employee->id,
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:employees,email,' . $employee->id,
            'phone' => 'nullable|string|max:20',
            'department_id' => 'nullable|exists:departments,id',
            'job_title' => 'nullable|string|max:100',
            'supervisor_id' => 'nullable|exists:employees,id',
            'hire_date' => 'nullable|date',
            'employment_status' => 'required|in:active,on_leave,terminated',
        ]);

        $employee->update($validated);

        return redirect()->route('employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        // Check if employee has a user account
        if ($employee->user) {
            return back()->with('error', 'Cannot delete employee with active user account.');
        }

        // Check if employee is a supervisor
        if ($employee->subordinates()->count() > 0) {
            return back()->with('error', 'Cannot delete employee who supervises others.');
        }

        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Employee deleted successfully.');
    }

    public function createAccount(Employee $employee)
    {
        if ($employee->user) {
            return redirect()->back()->with('error', 'This employee already has a system account.');
        }

        DB::beginTransaction();
        try {
            $user = $this->provisionAccount($employee);

            // Send Welcome Notification
            $user->notify(new AppNotification(
                "Welcome to IPRPMS",
                "Your system account has been provisioned. Please update your password in the Profile section.",
                route('profile.edit'),
                'fas fa-user-check text-success'
            ));

            DB::commit();
            return redirect()->route('users.index')->with('success', "Account created for {$employee->first_name}. Username: {$employee->email}. Temporary password: " . self::DEFAULT_PASSWORD);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create account: ' . $e->getMessage());
        }
    }

    // Excel Import Methods
    public function importForm()
    {
        return view('employees.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            \Maatwebsite\Excel\Facades\Excel::import(
                new \App\Imports\EmployeesImport,
                $request->file('file')
            );

            return redirect()->route('employees.index')
                ->with('success', 'Employees imported successfully.');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
            }
            return back()->with('error', 'Import failed: ' . implode(' | ', $errors));
        } catch (\Exception $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $headers = [
            'Employee Number',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Department Code',
            'Job Title',
            'Supervisor Number',
            'Hire Date',
            'Status'
        ];

        $sampleData = [
            ['EMP001', 'John', 'Doe', 'john@example.com', '+251911234567', 'DO', 'Software Engineer', 'EMP100', '2020-01-15', 'active'],
            ['EMP002', 'Jane', 'Smith', 'jane@example.com', '+251922345678', 'HR', 'HR Manager', '', '2019-06-01', 'active'],
        ];

        $export = new class ($headers, $sampleData) implements \Maatwebsite\Excel\Concerns\FromArray {
            protected $headers;
            protected $data;

            public function __construct($headers, $data)
            {
                $this->headers = $headers;
                $this->data = $data;
            }

            public function array(): array
            {
                return array_merge([$this->headers], $this->data);
            }
        };

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'employees_template.xlsx');
    }

    public function getNextNumber($departmentId)
    {
        Department::findOrFail($departmentId);

        return response()->json([
            'number' => $this->generateNextEmployeeNumber((int) $departmentId)
        ]);
    }

    private function generateNextEmployeeNumber(int $departmentId): string
    {
        $dept = Department::findOrFail($departmentId);
        $code = $dept->code ?: $dept->id;
        $prefix = "EDR/{$code}/";
        
        $lastIndex = Employee::where('employee_number', 'like', $prefix . '%')
            ->pluck('employee_number')
            ->map(function ($employeeNumber) {
                $parts = explode('/', $employeeNumber);
                return (int) end($parts);
            })
            ->max() ?? 0;

        $nextNumber = $lastIndex + 1;

        return $prefix . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);
    }

    private function provisionAccount(Employee $employee): User
    {
        if (empty($employee->email)) {
            throw new \InvalidArgumentException('An official email address is required to create a system account.');
        }

        $user = User::create([
            'employee_id' => $employee->id,
            'name' => $employee->first_name . ' ' . $employee->last_name,
            'email' => strtolower($employee->email),
            'department_id' => $employee->department_id,
            'password' => self::DEFAULT_PASSWORD,
            'must_change_password' => true,
            'is_active' => $employee->employment_status === 'active',
        ]);

        $employeeRole = Role::where('name', User::ROLE_EMPLOYEE)->first();
        if ($employeeRole) {
            $user->roles()->attach($employeeRole);
        }

        return $user;
    }
}
