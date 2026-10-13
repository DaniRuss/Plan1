<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskReport;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployeeTaskExport;

class EmployeePerformanceController extends Controller
{
    public function show(Employee $employee, Request $request)
    {
        $defaultYear = \App\Models\Plan::where('status', 'approved')->max('year') ?? now()->year;
        $year = $request->get('year', $defaultYear);
        $period = $request->get('period', 'all');

        $query = Task::where('assigned_to', $employee->id)
            ->with([
                'planItem.strategicObjective',
                'reports' => function ($q) {
                    $q->where('status', 'approved');
                }
            ]);

        if ($period != 'all') {
            $query->whereYear('created_at', $year);
        }

        $tasks = $query->get();

        // Calculate Metrics
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed')->count();
        $avgProgress = $totalTasks > 0 ? $tasks->avg('completion_percentage') : 0;

        // Group tasks by status
        $statusCounts = [
            'completed' => $completedTasks,
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
        ];

        // Calculate qualitative rating
        $rating = 'N/A';
        $ratingClass = 'secondary';
        if ($totalTasks > 0) {
            if ($avgProgress >= 90) {
                $rating = 'Excellent';
                $ratingClass = 'success';
            } elseif ($avgProgress >= 75) {
                $rating = 'Good';
                $ratingClass = 'info';
            } elseif ($avgProgress >= 50) {
                $rating = 'Satisfactory';
                $ratingClass = 'warning';
            } else {
                $rating = 'Needs Improvement';
                $ratingClass = 'danger';
            }
        }

        return view('reports.employee_show', compact('employee', 'tasks', 'totalTasks', 'avgProgress', 'statusCounts', 'year', 'period', 'rating', 'ratingClass'));
    }

    public function exportPdf(Employee $employee, Request $request)
    {
        $defaultYear = \App\Models\Plan::where('status', 'approved')->max('year') ?? now()->year;
        $year = $request->get('year', $defaultYear);
        $tasks = Task::where('assigned_to', $employee->id)
            ->whereYear('created_at', $year)
            ->with([
                'planItem.strategicObjective',
                'reports' => function ($q) {
                    $q->where('status', 'approved');
                }
            ])->get();

        $pdf = Pdf::loadView('reports.employee_pdf', compact('employee', 'tasks', 'year'));
        return $pdf->download("Performance_Report_{$employee->employee_number}_{$year}.pdf");
    }

    public function exportExcel(Employee $employee, Request $request)
    {
        $defaultYear = \App\Models\Plan::where('status', 'approved')->max('year') ?? now()->year;
        $year = $request->get('year', $defaultYear);
        $tasks = Task::where('assigned_to', $employee->id)
            ->whereYear('created_at', $year)
            ->with([
                'planItem.strategicObjective',
                'reports' => function ($q) {
                    $q->where('status', 'approved');
                }
            ])->get();

        return Excel::download(new EmployeeTaskExport($tasks, $employee, $year), "Performance_Report_{$employee->employee_number}_{$year}.xlsx");
    }
}
