@extends('layouts.app')

@section('title', 'Task Management')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Tasks</h3>
                        @if(auth()->user()->hasPermission('assign_task'))
                            <div class="card-tools">
                                <a href="{{ route('tasks.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Assign New Task
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="card-body">
                        <table id="tasks-table" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Task Title</th>
                                    <th>Assigned To</th>
                                    <th>Strategic Objective</th>
                                    <th>Target Date</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tasks as $task)
                                    <tr>
                                        <td>{{ $task->title }}</td>
                                        <td>{{ $task->assignedTo->first_name }} {{ $task->assignedTo->last_name }}</td>
                                        <td>{{ $task->planItem->strategicObjective->title }}</td>
                                        <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('M d, Y') : 'N/A' }}
                                        </td>
                                        <td>
                                            <span
                                                class="badge badge-{{ $task->status == 'completed' ? 'success' : ($task->status == 'in_progress' ? 'info' : ($task->status == 'cancelled' ? 'danger' : 'secondary')) }}">
                                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress progress-xs">
                                                <div class="progress-bar bg-{{ $task->completion_percentage == 100 ? 'success' : 'primary' }}"
                                                    style="width: {{ $task->completion_percentage }}%"></div>
                                            </div>
                                            <small>{{ number_format($task->completion_percentage, 0) }}%</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('tasks.show', $task) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $("#tasks-table").DataTable({
                "responsive": true,
                "autoWidth": false,
            });
        });
    </script>
@endpush