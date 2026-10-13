<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Department;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProjectExport;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::with('department')->orderBy('year', 'desc')->get();
        return view('projects.index', compact('projects'));
    }

    public function show(Project $project)
    {
        $project->load(['department', 'milestones.creator']);
        return view('projects.show', compact('project'));
    }

    public function report(Request $request)
    {
        $year = $request->get('year', 2026);
        $projects = Project::with('department')
            ->where('year', $year)
            ->get();

        // Aggregations
        $stats = [
            'total' => $projects->count(),
            'avg_completion' => $projects->avg('completion_percentage') ?? 0,
            'by_status' => $projects->groupBy('status')->map->count(),
            'by_department' => $projects->groupBy(fn($p) => $p->department->name)->map->count(),
        ];

        $years = \App\Services\CalendarHelper::getAppYears();

        return view('projects.report', compact('projects', 'stats', 'year', 'years'));
    }

    public function export(Request $request)
    {
        $year = $request->get('year', 2026);
        return Excel::download(new ProjectExport($year), "projects_report_{$year}.xlsx");
    }

    public function create()
    {
        $departments = Department::all();
        $years = \App\Services\CalendarHelper::getAppYears();
        return view('projects.create', compact('departments', 'years'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'total_budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'planned_progress_desc' => 'nullable|string',
            'performance_desc' => 'nullable|string',
            'completion_percentage' => 'required|numeric|min:0|max:100',
            'status' => 'required|in:planned,in_progress,completed,on_hold,delayed',
            'year' => 'required|integer|min:2018|max:2030',
        ]);

        Project::create($validated);

        return redirect()->route('projects.index')->with('success', 'Project created successfully.');
    }

    public function edit(Project $project)
    {
        $departments = Department::all();
        $years = \App\Services\CalendarHelper::getAppYears();
        return view('projects.edit', compact('project', 'departments', 'years'));
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'total_budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'planned_progress_desc' => 'nullable|string',
            'performance_desc' => 'nullable|string',
            'completion_percentage' => 'required|numeric|min:0|max:100',
            'status' => 'required|in:planned,in_progress,completed,on_hold,delayed',
            'year' => 'required|integer|min:2018|max:2030',
        ]);

        $project->update($validated);

        return redirect()->route('projects.index')->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Project deleted successfully.');
    }
}
