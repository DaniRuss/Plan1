<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskReport;
use App\Models\TaskEvidence;
use App\Models\PlanItem;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;

class TaskController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('system_admin') || $user->hasRole('hr_manager')) {
            $tasks = Task::with(['assignedTo', 'assignedBy', 'planItem.strategicObjective'])->get();
        } elseif ($user->hasRole('dept_head') || $user->hasRole('supervisor')) {
            // Tasks in my department or assigned by me
            $employeeId = $user->employee_id;
            $tasks = Task::where('assigned_by', $employeeId)
                ->orWhereHas('assignedTo', function ($q) use ($user) {
                    if ($user->employee) {
                        $q->where('department_id', $user->employee->department_id);
                    }
                })
                ->with(['assignedTo', 'assignedBy', 'planItem.strategicObjective'])
                ->get();
        } else {
            // My tasks
            $tasks = Task::where('assigned_to', $user->employee_id)
                ->with(['assignedTo', 'assignedBy', 'planItem.strategicObjective'])
                ->get();
        }

        return view('tasks.index', compact('tasks'));
    }

    public function myTasks()
    {
        $employeeId = auth()->user()->employee_id;
        if (!$employeeId) {
            return redirect()->back()->with('error', 'User not linked to an employee profile.');
        }

        $tasks = Task::where('assigned_to', $employeeId)
            ->with(['assignedBy', 'planItem.strategicObjective', 'reports'])
            ->get();

        return view('tasks.my_tasks', compact('tasks'));
    }

    public function create()
    {
        $employees = Employee::all();
        $planItems = PlanItem::with('strategicObjective', 'plan')->get();
        return view('tasks.create', compact('employees', 'planItems'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'plan_item_id' => 'required|exists:plan_items,id',
            'title' => 'required|string|max:255',
            'assigned_to' => 'required|exists:employees,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $task = Task::create([
            'plan_item_id' => $request->plan_item_id,
            'title' => $request->title,
            'description' => $request->description,
            'assigned_to' => $request->assigned_to,
            'assigned_by' => auth()->user()->employee_id,
            'start_date' => $request->start_date,
            'due_date' => $request->due_date,
            'status' => 'pending',
            'completion_percentage' => 0,
        ]);

        AuditLog::log('created', $task, "Assigned new task to employee #{$request->assigned_to}");

        // Notify the assigned employee
        $assignedUser = User::where('employee_id', $request->assigned_to)->first();
        if ($assignedUser) {
            $assignedUser->notify(new AppNotification(
                "New Task Assigned",
                "You have been assigned a new task: {$task->title}",
                route('tasks.my'),
                'fas fa-tasks text-info'
            ));
        }

        return redirect()->route('tasks.index')->with('success', 'Task assigned successfully.');
    }

    public function show(Task $task)
    {
        $task->load(['assignedTo', 'assignedBy', 'planItem.strategicObjective', 'reports.evidence', 'reports.employee']);
        return view('tasks.show', compact('task'));
    }

    public function report(Request $request, Task $task)
    {
        $request->validate([
            'reporting_period' => 'required|string',
            'progress_percentage' => 'required|numeric|min:0|max:100',
            'narrative' => 'required|string',
            'evidence.*' => 'required|file|max:10240', // 10MB max
        ]);

        if (!$request->hasFile('evidence')) {
            return redirect()->back()->with('error', 'Evidence is mandatory for task reports.');
        }

        DB::beginTransaction();
        try {
            $report = TaskReport::create([
                'task_id' => $task->id,
                'employee_id' => auth()->user()->employee_id,
                'reporting_period' => $request->reporting_period,
                'progress_percentage' => $request->progress_percentage,
                'narrative' => $request->narrative,
                'challenges' => $request->challenges,
                'status' => 'submitted',
            ]);

            foreach ($request->file('evidence') as $file) {
                $path = $file->store('evidence/' . date('Y/m'));
                TaskEvidence::create([
                    'task_report_id' => $report->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => auth()->user()->employee_id,
                    'uploaded_at' => now(),
                ]);
            }

            DB::commit();
            AuditLog::log('submitted', $report, "Submitted progress report for task #{$task->id}");
            return redirect()->back()->with('success', 'Report submitted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to submit report: ' . $e->getMessage());
        }
    }

    public function approveReport(Request $request, TaskReport $report)
    {
        $request->validate([
            'review_comments' => 'nullable|string',
        ]);

        $report->update([
            'status' => 'approved',
            'reviewed_by' => auth()->user()->employee_id,
            'reviewed_at' => now(),
            'review_comments' => $request->review_comments,
        ]);

        // Update task completion
        $task = $report->task;
        $maxProgress = TaskReport::where('task_id', $task->id)
            ->where('status', 'approved')
            ->max('progress_percentage');

        $updates = [
            'completion_percentage' => $maxProgress,
        ];

        if ($maxProgress == 100) {
            $updates['status'] = 'completed';
            $updates['approved_by'] = auth()->user()->employee_id;
            $updates['approved_at'] = now();
        } else {
            $updates['status'] = 'in_progress';
        }

        $task->update($updates);

        AuditLog::log('approved', $report, "Approved report and updated task #{$task->id} progress to {$maxProgress}%");

        // Notify the employee who submitted the report
        $submitterUser = User::where('employee_id', $report->employee_id)->first();
        if ($submitterUser) {
            $submitterUser->notify(new AppNotification(
                "Task Report Approved",
                "Your progress report for '{$task->title}' has been approved.",
                route('tasks.my'),
                'fas fa-check-circle text-success'
            ));
        }

        return redirect()->back()->with('success', 'Report approved and task progress updated.');
    }
}
