@extends('layouts.app')

@section('title', 'My Tasks')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info">
                        <h3 class="card-title">Tasks Assigned to Me</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @forelse($tasks as $task)
                                <div class="col-md-4 mb-4">
                                    <div class="card card-widget widget-user-2 shadow-sm border h-100">
                                        <div
                                            class="widget-user-header bg-{{ $task->status == 'completed' ? 'success' : ($task->status == 'in_progress' ? 'warning' : 'secondary') }}">
                                            <div class="widget-user-image">
                                                <i
                                                    class="fas fa-tasks fa-3x img-circle elevation-2 p-2 bg-white text-{{ $task->status == 'completed' ? 'success' : 'warning' }}"></i>
                                            </div>
                                            <h3 class="widget-user-username">{{ $task->title }}</h3>
                                            <h5 class="widget-user-desc">Due:
                                                {{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('M d, Y') : 'N/A' }}
                                            </h5>
                                        </div>
                                        <div class="card-footer p-0">
                                            <ul class="nav flex-column">
                                                <li class="nav-item">
                                                    <a href="#" class="nav-link">
                                                        Progress <span
                                                            class="float-right badge bg-primary">{{ number_format($task->completion_percentage, 0) }}%</span>
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a href="#" class="nav-link">
                                                        Objective <span
                                                            class="float-right text-muted text-sm">{{ Str::limit($task->planItem->strategicObjective->title, 25) }}</span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="card-footer text-center">
                                            <a href="{{ route('tasks.show', $task) }}" class="btn btn-sm btn-info btn-block">
                                                <i class="fas fa-eye"></i> View & Submit Report
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-center py-5">
                                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                                    <h4>No tasks assigned to you yet.</h4>
                                    <p class="text-muted">When a supervisor assigns you a task, it will appear here.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection