<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'employee', 'department'])
            ->orderBy('is_active', 'asc') // Pending first
            ->orderBy('created_at', 'desc')
            ->get();
        return view('users.index', compact('users'));
    }

    public function approve(User $user)
    {
        $user->update(['is_active' => true]);
        return redirect()->back()->with('success', "Account for {$user->name} has been approved.");
    }

    public function create()
    {
        $employees = Employee::whereDoesntHave('user')->get();
        $roles = Role::all();
        $departments = Department::orderBy('name')->get();

        return view('users.create', compact('employees', 'roles', 'departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id|unique:users,employee_id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'department_id' => 'required|exists:departments,id',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
            'is_active' => 'nullable|boolean',
        ]);

        $user = User::create([
            'employee_id' => $request->employee_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'department_id' => $request->department_id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->roles()->attach($request->roles);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $employees = Employee::all();
        $roles = Role::all();
        $departments = Department::orderBy('name')->get();

        return view('users.edit', compact('user', 'employees', 'roles', 'departments'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id|unique:users,employee_id,' . $user->id,
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'department_id' => 'required|exists:departments,id',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
            'is_active' => 'nullable|boolean',
        ]);

        $user->update([
            'employee_id' => $request->employee_id,
            'name' => $request->name,
            'email' => $request->email,
            'department_id' => $request->department_id,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        $user->roles()->sync($request->roles);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
