@extends('layouts.app')

@section('title', 'Department Details')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
<li class="breadcrumb-item"><a href="{{ route('departments.index') }}">Departments</a></li>
<li class="breadcrumb-item active">{{ $department->name }}</li>
@endsection

@section('content')
<div class="row">
    <!-- Main Info Header -->
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-0 bg-gradient-navy text-white overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="dept-avatar mr-4 bg-white text-navy rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; font-size: 2rem;">
                            <i class="fas fa-building"></i>
                        </div>
                        <div>
                            <h2 class="mb-1 font-weight-bold">{{ $department->name }}</h2>
                            <p class="mb-0 opacity-75">
                                <i class="fas fa-barcode mr-1"></i> Code: {{ $department->code ?? 'N/A' }} | 
                                <i class="fas fa-calendar-alt mr-1"></i> Registered: {{ $department->created_at->format('M Y') }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('departments.edit', $department) }}" class="btn btn-outline-light btn-sm px-3">
                            <i class="fas fa-edit mr-1"></i> Edit Properites
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="col-md-3">
        <div class="info-box shadow-sm rounded-lg">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Staff Count</span>
                <span class="info-box-number">{{ $department->employees_count ?? $department->employees()->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box shadow-sm rounded-lg">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-bullseye"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Objectives</span>
                <span class="info-box-number">{{ $department->strategicObjectives()->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box shadow-sm rounded-lg">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-sitemap"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Sub-Units</span>
                <span class="info-box-number">{{ $department->children()->count() }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box shadow-sm rounded-lg">
            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-weight-hanging"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Org. Weight</span>
                <span class="info-box-number">{{ \App\Support\NumberFormatter::format($department->objective_weight) }}%</span>
            </div>
        </div>
    </div>

    <!-- Details and Children -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 font-weight-bold text-dark">Sub-Departments (Structure)</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    @forelse($department->children as $child)
                    <li class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-chevron-right text-muted mr-3" style="font-size: 0.8rem;"></i>
                            <div>
                                <h6 class="mb-0 font-weight-bold">{{ $child->name }}</h6>
                                <span class="text-muted small">Code: {{ $child->code ?? 'N/A' }} | Staff: {{ $child->employees()->count() }}</span>
                            </div>
                        </div>
                        <a href="{{ route('departments.show', $child) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Explore</a>
                    </li>
                    @empty
                    <li class="list-group-item text-center text-muted py-5">
                        <i class="fas fa-folder-open d-block mb-3 fa-3x opacity-25"></i>
                        No sub-departments registered for this unit.
                    </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <!-- Hierarchy & Context -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-left-primary mb-4">
            <div class="card-body">
                <h6 class="text-primary font-weight-bold text-uppercase small ls-1 mb-3">Parent Organization</h6>
                @if($department->parent)
                <div class="p-3 bg-light rounded border">
                    <h6 class="mb-1 font-weight-bold">{{ $department->parent->name }}</h6>
                    <a href="{{ route('departments.show', $department->parent) }}" class="small text-info">View Parent Unit <i class="fas fa-external-link-alt ml-1" style="font-size: 0.7rem;"></i></a>
                </div>
                @else
                <div class="p-3 bg-light rounded border text-center">
                    <span class="text-muted italic">This is an Entry-Level Headquarters Unit</span>
                </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 font-weight-bold text-dark">Department Head</h5>
            </div>
            <div class="card-body text-center py-4">
                <div class="mb-3">
                    <img src="https://ui-avatars.com/api/?name=H+V&background=4e73df&color=fff&size=80" class="rounded-circle shadow-sm">
                </div>
                <h6 class="font-weight-bold mb-1">Assigned Manager</h6>
                <p class="text-muted small">The department head is responsible for annual planning and objective oversight.</p>
                <button class="btn btn-sm btn-primary rounded-pill px-4" disabled>View Profile</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .bg-gradient-navy { background: linear-gradient(135deg, #1c2d41 0%, #34495e 100%); }
    .text-navy { color: #1c2d41; }
    .ls-1 { letter-spacing: 1px; }
    .border-left-primary { border-left: 4px solid #4e73df !important; }
</style>
@endpush