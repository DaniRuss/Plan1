<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectMilestoneController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'status' => 'required|in:pending,in_progress,completed,delayed',
            'completion_percentage' => 'required|numeric|min:0|max:100',
            'weight' => 'required|numeric|min:0.1',
        ]);

        $validated['project_id'] = $project->id;
        $validated['created_by'] = Auth::id();

        ProjectMilestone::create($validated);

        // Update project completion percentage
        $project->update(['completion_percentage' => $project->totalCompletion()]);

        return redirect()->back()->with('success', 'Milestone created successfully.');
    }

    public function update(Request $request, ProjectMilestone $milestone)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'status' => 'required|in:pending,in_progress,completed,delayed',
            'completion_percentage' => 'required|numeric|min:0|max:100',
            'weight' => 'required|numeric|min:0.1',
        ]);

        $milestone->update($validated);

        // Update project completion percentage
        $milestone->project->update(['completion_percentage' => $milestone->project->totalCompletion()]);

        return redirect()->back()->with('success', 'Milestone updated successfully.');
    }

    public function destroy(ProjectMilestone $milestone)
    {
        $project = $milestone->project;
        $milestone->delete();

        // Update project completion percentage
        $project->update(['completion_percentage' => $project->totalCompletion()]);

        return redirect()->back()->with('success', 'Milestone deleted successfully.');
    }
}
