@extends('layouts.app')

@section('title', 'Project Tracking Registry')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Project Tracking</li>
@endsection

@section('content')
    <div class="row mb-4">
        <div class="col-md-9">
            <h3 class="font-weight-bold text-dark">
                <i class="fas fa-project-diagram mr-2 text-primary"></i> 🏗️ Infrastructure Project Registry
            </h3>
            <p class="text-muted">Tracking qualitative and quantitative progress for major organizational capital projects.
            </p>
        </div>
        <div class="col-md-3 text-right">
            <a href="{{ route('projects.create') }}" class="btn btn-primary shadow-sm rounded-pill px-4">
                <i class="fas fa-plus mr-1"></i> Register New Project
            </a>
        </div>
    </div>

    <div class="card shadow border-0 overflow-hidden">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="card-title mb-0 font-weight-bold">Active Projects Matrix</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm m-0 align-middle text-center">
                    <thead class="bg-light small font-weight-bold">
                        <tr>
                            <th width="50" class="pl-4">S.N</th>
                            <th class="text-left" style="min-width: 200px;">Project Name</th>
                            <th>Department</th>
                            <th class="text-left" style="min-width: 250px;">Planned Status</th>
                            <th class="text-left" style="min-width: 250px;">Actual Performance</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th width="150" class="pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $index => $project)
                            <tr>
                                <td class="pl-4 font-weight-bold">{{ $index + 1 }}.</td>
                                <td class="text-left py-3">
                                    <a href="{{ route('projects.show', $project) }}" class="font-weight-bold text-primary">{{ $project->name }}</a>
                                    <small class="text-muted d-block">EFY Year: {{ $project->year }}</small>
                                </td>
                                <td>
                                    <span class="badge badge-outline-secondary px-2">{{ $project->department->name }}</span>
                                </td>
                                <td class="text-left small text-muted">
                                    {{ Str::limit($project->planned_progress_desc, 80) }}
                                </td>
                                <td class="text-left small font-weight-bold text-navy">
                                    {{ Str::limit($project->performance_desc, 80) }}
                                </td>
                                <td>
                                    <div class="progress progress-xs mb-1" style="height: 4px;">
                                        <div class="progress-bar bg-{{ $project->completion_percentage >= 90 ? 'success' : ($project->completion_percentage >= 50 ? 'info' : 'warning') }}"
                                            style="width: {{ \App\Support\NumberFormatter::format($project->completion_percentage) }}%"></div>
                                    </div>
                                    <span
                                        class="font-weight-bold small">{{ \App\Support\NumberFormatter::format($project->completion_percentage,3) }}%</span>
                                </td>
                                <td>
                                    @php
                                        $statusClass = [
                                            'planned' => 'secondary',
                                            'in_progress' => 'primary',
                                            'completed' => 'success',
                                            'on_hold' => 'warning',
                                            'delayed' => 'danger'
                                        ][$project->status] ?? 'info';
                                    @endphp
                                    <span class="badge badge-{{ $statusClass }} px-3 py-1 rounded-pill">
                                        {{ strtoupper(str_replace('_', ' ', $project->status)) }}
                                    </span>
                                </td>
                                <td class="pr-4">
                                    <div class="btn-group">
                                        <a href="{{ route('projects.show', $project) }}"
                                            class="btn btn-sm btn-outline-primary rounded-circle mr-1" title="View Dashboard">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('projects.edit', $project) }}"
                                            class="btn btn-sm btn-outline-info rounded-circle mr-1" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('projects.destroy', $project) }}" method="POST"
                                            data-confirm="Delete this project record? This action cannot be undone." data-confirm-title="Delete project" data-confirm-text="Delete" data-confirm-class="btn-danger"
                                            style="display:inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-5 text-center text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0">No infrastructure projects currently registered in the tracking matrix.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .text-navy {
            color: #001f3f;
        }

        .bg-gradient-navy {
            background: linear-gradient(135deg, #001f3f 0%, #003366 100%);
        }

        .badge-outline-secondary {
            border: 1px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }
    </style>
@endpush
