@extends('layouts.app')
@section('page_header_hidden', '1')

@section('title', 'Project Performance Report')

@section('content')
    <div class="content-header p-2">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded border shadow-sm mb-3">
                <div class="d-flex align-items-center">
                    <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary btn-sm mr-3">
                        <i class="fas fa-arrow-left"></i> Back to Registry
                    </a>
                    <h5 class="m-0 font-weight-bold text-dark border-left pl-3">
                        📊 Infrastructure Projects Performance Report ({{ $year }})
                    </h5>
                </div>
                <div class="card-tools d-flex align-items-center">
                    <form action="{{ route('projects.report') }}" method="GET" class="form-inline mr-2">
                        <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                            @foreach($years as $y)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </form>
                    <a href="{{ route('projects.export', ['year' => $year]) }}"
                        class="btn btn-success btn-sm rounded-pill px-3 shadow-sm mr-2">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                    </a>
                    <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 shadow-sm"
                        onclick="window.print()">
                        <i class="fas fa-file-pdf mr-1"></i> Print PDF
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="info-box shadow-sm border-left border-primary">
                        <span class="info-box-icon bg-primary"><i class="fas fa-tasks"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Projects</span>
                            <span class="info-box-number h4">{{ $stats['total'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box shadow-sm border-left border-success">
                        <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Completed</span>
                            <span class="info-box-number h4">{{ $stats['by_status']['completed'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box shadow-sm border-left border-info">
                        <span class="info-box-icon bg-info"><i class="fas fa-chart-line"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Avg. Completion</span>
                            <span class="info-box-number h4">{{ \App\Support\NumberFormatter::format($stats['avg_completion'],3) }}%</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box shadow-sm border-left border-warning">
                        <span class="info-box-icon bg-warning text-white"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">In Progress</span>
                            <span class="info-box-number h4">{{ $stats['by_status']['in_progress'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-dark text-white">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-table mr-2"></i> Detailed Performance Matrix
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped table-hover m-0">
                                <thead class="bg-light small font-weight-bold">
                                    <tr>
                                        <th class="pl-3">PROJECT NAME</th>
                                        <th>DEPARTMENT</th>
                                        <th width="150">% PROGRESS</th>
                                        <th width="120">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($projects as $project)
                                        <tr>
                                            <td class="pl-3 py-3">
                                                <div class="font-weight-bold">
                                                    <a href="{{ route('projects.show', $project) }}" class="text-dark">{{ $project->name }}</a>
                                                </div>
                                                <div class="small text-muted italic">
                                                    "{{ Str::limit($project->performance_desc, 60) }}"</div>
                                            </td>
                                            <td class="align-middle">
                                                <span class="badge badge-light border">{{ $project->department->name }}</span>
                                            </td>
                                            <td class="align-middle">
                                                <div class="progress progress-xs mb-1" style="height: 6px;">
                                                    <div class="progress-bar bg-{{ $project->completion_percentage >= 90 ? 'success' : ($project->completion_percentage >= 50 ? 'primary' : 'warning') }}"
                                                        style="width: {{ \App\Support\NumberFormatter::format($project->completion_percentage) }}%"></div>
                                                </div>
                                                <span
                                                    class="small font-weight-bold">{{ \App\Support\NumberFormatter::format($project->completion_percentage,3) }}%</span>
                                            </td>
                                            <td class="align-middle text-center">
                                                @php
                                                    $statusClass = [
                                                        'planned' => 'secondary',
                                                        'in_progress' => 'primary',
                                                        'completed' => 'success',
                                                        'on_hold' => 'warning',
                                                        'delayed' => 'danger'
                                                    ][$project->status] ?? 'info';
                                                @endphp
                                                <span class="badge badge-{{ $statusClass }} py-1 px-3 rounded-pill small">
                                                    {{ strtoupper(str_replace('_', ' ', $project->status)) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted italic">No data available for the
                                                selected year.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-primary text-white font-weight-bold">
                            <i class="fas fa-chart-pie mr-2"></i> Status Distribution
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                @foreach($stats['by_status'] as $status => $count)
                                    <div
                                        class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-top-0">
                                        <span class="text-capitalize">{{ str_replace('_', ' ', $status) }}</span>
                                        <span class="badge badge-primary badge-pill">{{ $count }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 mt-3">
                        <div class="card-header bg-info text-white font-weight-bold">
                            <i class="fas fa-building mr-2"></i> Projects by Department
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                @foreach($stats['by_department'] as $dept => $count)
                                    <div
                                        class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-top-0">
                                        <span>{{ $dept }}</span>
                                        <span class="badge badge-info badge-pill">{{ $count }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        @media print {

            .btn,
            .main-sidebar,
            .main-header,
            form {
                display: none !important;
            }

            .content-wrapper {
                margin-left: 0 !important;
            }

            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
        }

        .italic {
            font-style: italic;
        }
    </style>
@endpush