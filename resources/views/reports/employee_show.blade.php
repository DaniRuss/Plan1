@extends('layouts.app')

@section('title', 'Employee Performance Report')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Employees</a></li>
    <li class="breadcrumb-item active">Performance Report</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-9">
            <!-- Employee Header Info -->
            <div class="card shadow-sm border-0 mb-4 overflow-hidden">
                <div class="card-body p-0">
                    <div class="bg-primary p-4 text-white d-flex align-items-center">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center mr-4"
                            style="width: 80px; height: 80px; font-size: 2rem; font-weight: bold;">
                            {{ substr($employee->first_name, 0, 1) }}{{ substr($employee->last_name, 0, 1) }}
                        </div>
                        <div>
                            <h2 class="font-weight-bold mb-1">{{ $employee->first_name }} {{ $employee->last_name }}</h2>
                            <p class="mb-0 opacity-75">
                                <i class="fas fa-id-badge mr-2"></i> {{ $employee->employee_number }} |
                                <i class="fas fa-briefcase mx-2"></i> {{ $employee->job_title }} |
                                <i class="fas fa-building mx-2"></i> {{ $employee->department->name ?? 'N/A' }}
                            </p>
                        </div>
                        <div class="ml-auto text-right">
                            <span
                                class="badge badge-light px-3 py-2 text-uppercase">{{ $employee->employment_status }}</span>
                        </div>
                    </div>
                    <div class="px-4 py-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase font-weight-bold mr-3">Reporting Period:</span>
                            <span class="font-weight-bold">{{ $year }} ({{ ucfirst($period) }})</span>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="{{ route('reports.employee.pdf', $employee->id) }}?year={{ $year }}"
                                class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-file-pdf mr-1"></i> Export PDF
                            </a>
                            <a href="{{ route('reports.employee.excel', $employee->id) }}?year={{ $year }}"
                                class="btn btn-sm btn-outline-success">
                                <i class="fas fa-file-excel mr-1"></i> Export Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Overview Tiles -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm border-left-primary h-100">
                        <div class="card-body py-3">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Tasks</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalTasks }}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tasks fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-left-success h-100">
                        <div class="card-body py-3">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Avg. Progress</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ round($avgProgress, 1) }}%</div>
                                    <div class="progress progress-sm mt-1">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $avgProgress }}%"></div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-left-warning h-100">
                        <div class="card-body py-3">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Task Breakdown</div>
                                    <div class="small text-muted mt-1">
                                        <span title="Completed">C:<strong>{{ $statusCounts['completed'] }}</strong></span> | 
                                        <span title="In Progress">I:<strong>{{ $statusCounts['in_progress'] }}</strong></span> | 
                                        <span title="Pending">P:<strong>{{ $statusCounts['pending'] }}</strong></span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-left-info h-100">
                        <div class="card-body py-3">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Performance Rating</div>
                                    <div class="h5 mb-0 font-weight-bold">
                                        <span class="badge badge-{{ $ratingClass }} px-2 py-1 rounded-pill shadow-xs small">{{ strtoupper($rating) }}</span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-medal fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Task List -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white font-weight-bold border-bottom py-3">
                    <i class="fas fa-list-ul mr-2 text-primary"></i> Detailed Task Performance Breakdown
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small text-uppercase">
                                <tr>
                                    <th class="border-top-0 pl-4 py-3">Task Title & Strategic Objective</th>
                                    <th class="border-top-0 text-center">Status</th>
                                    <th class="border-top-0 text-center">Progress</th>
                                    <th class="border-top-0 text-center">Due Date</th>
                                    <th class="border-top-0 text-right pr-4">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tasks as $task)
                                    <tr>
                                        <td class="pl-4 py-3">
                                            <div class="font-weight-bold text-dark">{{ $task->title }}</div>
                                            <div class="small text-muted italic">Obj:
                                                {{ $task->planItem->strategicObjective->description ?? 'General Task' }}</div>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge badge-pill 
                                                        {{ $task->status == 'completed' ? 'badge-success' : ($task->status == 'pending' ? 'badge-warning' : 'badge-info') }} px-2">
                                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                            </span>
                                        </td>
                                        <td class="text-center" style="width: 150px;">
                                            <div class="small font-weight-bold mb-1">
                                                {{ round($task->completion_percentage, 0) }}%</div>
                                            <div class="progress progress-xs" style="height: 5px;">
                                                <div class="progress-bar {{ $task->completion_percentage >= 80 ? 'bg-success' : ($task->completion_percentage >= 40 ? 'bg-info' : 'bg-warning') }}"
                                                    role="progressbar" style="width: {{ $task->completion_percentage }}%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center small">
                                            {{ $task->due_date ? date('M d, Y', strtotime($task->due_date)) : '-' }}
                                        </td>
                                        <td class="text-right pr-4">
                                            <a href="{{ route('tasks.show', $task->id) }}"
                                                class="btn btn-xs btn-link text-primary p-0">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-5 text-center text-muted">
                                            <i class="fas fa-info-circle fa-2x mb-3 d-block"></i>
                                            No tasks assigned for this period.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Qualitative Summary (Aggregated Narratives) -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white font-weight-bold border-bottom py-3">
                    <i class="fas fa-comment-dots mr-2 text-primary"></i> Achievements & Feedback (Aggregated)
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <h6 class="text-uppercase small font-weight-bold text-muted mb-3">Recent Reporting Summaries
                            </h6>
                    @php
                        $recentReports = \App\Models\TaskReport::whereIn('task_id', $tasks->pluck('id'))
                            ->where('status', 'approved')
                            ->latest()
                            ->take(3)
                            ->get();
                    @endphp

                            @forelse($recentReports as $report)
                                <div class="p-3 bg-light rounded mb-3 border-left border-primary shadow-sm">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="font-weight-bold text-dark">{{ $report->task->title }}</span>
                                        <span class="small text-muted">{{ $report->created_at->format('M d, Y') }}</span>
                                    </div>
                                    <p class="small mb-2 text-justify italic font-italic font-serif">
                                        "{{ Str::limit($report->narrative, 200) }}"</p>
                                    @if($report->review_comments)
                                        <div class="small bg-white p-2 rounded border border-success">
                                            <strong class="text-success"><i class="fas fa-user-check mr-1"></i> Supervisor
                                                Comment:</strong>
                                            {{ $report->review_comments }}
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted small italic">No approved narratives found.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar / Filters -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white font-weight-bold border-bottom">Report Filters</div>
                <div class="card-body">
                    <form action="{{ route('reports.employee.show', $employee->id) }}" method="GET">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted">SELECT YEAR</label>
                            <select name="year" class="form-control form-control-sm border-0 bg-light">
                                @for($i = now()->year; $i >= now()->year - 3; $i--)
                                    <option value="{{ $i }}" {{ $year == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm btn-block shadow-sm">
                            <i class="fas fa-sync-alt mr-1"></i> Update Report
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body py-3">
                    <h6 class="font-weight-bold text-dark small mb-2"><i class="fas fa-lightbulb text-warning mr-1"></i>
                        Performance Tip</h6>
                    <p class="small text-muted mb-0">Individual employee scores are tactical. They are calculated based on
                        the arithmetic mean of all tactical task completion percentages during the period.</p>
                </div>
            </div>
        </div>
    </div>
@endsection