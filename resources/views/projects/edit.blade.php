@extends('layouts.app')

@section('title', 'Edit Project Record')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Project Tracking</a></li>
    <li class="breadcrumb-item active">Edit Project</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow border-0">
                <div class="card-header bg-navy text-white py-3">
                    <h5 class="card-title mb-0 font-weight-bold">
                        <i class="fas fa-edit mr-2"></i> Update Infrastructure Project Status
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('projects.update', $project) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Project Name / Title</label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $project->name) }}" required>
                                    @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Project Objective / Description</label>
                                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                              rows="2" placeholder="Briefly describe the project's purpose and scope...">{{ old('description', $project->description) }}</textarea>
                                    @error('description') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Owner Department</label>
                                    <select name="department_id"
                                        class="form-control @error('department_id') is-invalid @enderror" required>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" {{ old('department_id', $project->department_id) == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('department_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">EFY Year</label>
                                    <select name="year" class="form-control @error('year') is-invalid @enderror" required>
                                        @foreach($years as $y)
                                            <option value="{{ $y }}" {{ old('year', $project->year) == $y ? 'selected' : '' }}>
                                                {{ $y }}</option>
                                        @endforeach
                                    </select>
                                    @error('year') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Start Date</label>
                                    <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" 
                                           value="{{ old('start_date', $project->start_date ? $project->start_date->format('Y-m-d') : '') }}">
                                    @error('start_date') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">End Date</label>
                                    <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" 
                                           value="{{ old('end_date', $project->end_date ? $project->end_date->format('Y-m-d') : '') }}">
                                    @error('end_date') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Current Progress %</label>
                                    <div class="input-group shadow-sm">
                                        <input type="number" name="completion_percentage" step="any"
                                            class="form-control @error('completion_percentage') is-invalid @enderror"
                                            data-original-number="{{ old('completion_percentage', $project->completion_percentage) }}" value="{{ \App\Support\NumberFormatter::format(old('completion_percentage', $project->completion_percentage),3,'.','') }}"
                                            min="0" max="100" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text font-weight-bold">%</span>
                                        </div>
                                    </div>
                                    @error('completion_percentage') <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Project Status Badge</label>
                                    <select name="status" class="form-control @error('status') is-invalid @enderror"
                                        required>
                                        <option value="planned" {{ $project->status == 'planned' ? 'selected' : '' }}>Planned
                                        </option>
                                        <option value="in_progress" {{ $project->status == 'in_progress' ? 'selected' : '' }}>
                                            In Progress</option>
                                        <option value="completed" {{ $project->status == 'completed' ? 'selected' : '' }}>
                                            Completed</option>
                                        <option value="on_hold" {{ $project->status == 'on_hold' ? 'selected' : '' }}>On Hold
                                        </option>
                                        <option value="delayed" {{ $project->status == 'delayed' ? 'selected' : '' }}>Delayed
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Total Budget</label>
                                    <input type="number" name="total_budget" step="any" 
                                           class="form-control @error('total_budget') is-invalid @enderror" 
                                           data-original-number="{{ old('total_budget', $project->total_budget) }}" value="{{ \App\Support\NumberFormatter::format(old('total_budget', $project->total_budget),3,'.','') }}" min="0">
                                    @error('total_budget') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Currency</label>
                                    <input type="text" name="currency" class="form-control @error('currency') is-invalid @enderror" 
                                           value="{{ old('currency', $project->currency) }}" maxlength="10">
                                    @error('currency') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Planned Progress Description (The
                                        "Plan")</label>
                                    <textarea name="planned_progress_desc"
                                        class="form-control @error('planned_progress_desc') is-invalid @enderror"
                                        rows="3">{{ old('planned_progress_desc', $project->planned_progress_desc) }}</textarea>
                                    @error('planned_progress_desc') <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Actual Performance Description (Fidelity
                                        Reporting)</label>
                                    <textarea name="performance_desc"
                                        class="form-control @error('performance_desc') is-invalid @enderror"
                                        rows="3">{{ old('performance_desc', $project->performance_desc) }}</textarea>
                                    @error('performance_desc') <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
                                <i class="fas fa-times-circle mr-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-navy rounded-pill px-5 shadow">
                                <i class="fas fa-sync-alt mr-1"></i> Update Project Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .bg-navy {
            background-color: #001f3f;
        }

        .btn-navy {
            background-color: #001f3f;
            color: white;
            border: none;
        }

        .btn-navy:hover {
            background-color: #003366;
            color: white;
        }
    </style>
@endpush