<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with('parent')
            ->orderBy('name')
            ->get();

        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->get();
        return view('departments.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100|unique:departments',
            'code'             => 'nullable|string|max:20|unique:departments',
            'objective_weight' => 'nullable|numeric|min:0',
            'parent_id'        => 'nullable|exists:departments,id',
            'reporting_type'   => 'required|in:daily,weekly,monthly',
        ]);

        // Removed total weight validation as per correction request

        Department::create($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function show(Department $department)
    {
        // Eager load children for the view
        $department->load('children');
        return view('departments.show', compact('department'));
    }

    public function edit(Department $department)
    {
        $departments = Department::where('is_active', true)
            ->where('id', '!=', $department->id)
            ->get();

        return view('departments.edit', compact('department', 'departments'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100|unique:departments,name,' . $department->id,
            'code'             => 'nullable|string|max:20|unique:departments,code,' . $department->id,
            'objective_weight' => 'nullable|numeric|min:0',
            'parent_id'        => 'nullable|exists:departments,id',
            'is_active'        => 'boolean',
            'reporting_type'   => 'required|in:daily,weekly,monthly',
        ]);

        // Removed total weight validation as per correction request

        if (isset($validated['parent_id']) && $validated['parent_id'] == $department->id) {
            return back()->withErrors(['parent_id' => 'Department cannot be its own parent.'])->withInput();
        }

        // Basic cycle detection could go here (optional for now)

        $department->update($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        // Check if department has users, objectives, or children before deleting
        if ($department->users()->exists() || $department->strategicObjectives()->exists() || $department->children()->exists()) {
            return redirect()->route('departments.index')
                ->with('error', 'Cannot delete department with associated users, strategic objectives, or sub-departments.');
        }

        $department->delete();

        return redirect()->route('departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    public function getChildren($id)
    {
        $children = Department::where('parent_id', $id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($children);
    }
}