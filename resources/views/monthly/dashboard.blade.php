@extends('layouts.app')

@section('title', 'Monthly Performance Aggregator')

@section('content')
    <div class="content-header p-2">
        <div class="container-fluid">
            <div class="row align-items-center bg-white p-3 rounded border shadow-sm mb-4">
                <div class="col-md-5">
                    <h4 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-layer-group mr-2"></i> Monthly Aggregator Module
                    </h4>
                    <p class="text-muted small m-0">Reviewing compiled weekly evidence into monthly objective scores.
                    </p>
                </div>
                <div class="col-md-7 text-right">
                    <form action="{{ route('monthly.dashboard') }}" method="GET" class="form-inline d-inline-block" id="filterForm">
                        
                        @if($canSelectDepartment && $monthlyDepartments)
                            <!-- Department Selector (Admin Only) -->
                            <div class="input-group input-group-sm mr-2 mb-2 mb-md-0">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light border-right-0">Department:</span>
                                </div>
                                <select name="department_id" class="form-control border-left-0" onchange="document.getElementById('filterForm').submit()">
                                    <option value="">-- Select Department --</option>
                                    @foreach($monthlyDepartments as $d)
                                        <option value="{{ $d->id }}" {{ $deptId == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <!-- Year Selector -->
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light border-right-0">EFY Year:</span>
                            </div>
                            <select name="year" class="form-control border-left-0" onchange="document.getElementById('filterForm').submit()">
                                @foreach($years as $y)
                                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                @if($canSelectDepartment && !$deptId)
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-1"></i>
                            Select a sender department above to view its monthly submissions.
                        </div>
                    </div>
                @endif
                @if($deptId && $plan)
                    <!-- Department Dashboard -->
                    <div class="col-md-12">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="card-title font-weight-bold mb-0">
                                    <i class="fas fa-building mr-2 text-info"></i> Performance Records: {{ $dept->name ?? 'Department' }}
                                </h5>
                                <div>
                                    <span class="badge badge-success px-3 py-2 rounded-pill shadow-xs">Approved Plan #{{ $plan->id }}</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                @if(count($months) === 0)
                                    <div class="p-4 text-center">
                                        <div class="text-muted mb-2"><i class="fas fa-folder-open fa-3x"></i></div>
                                        <h6 class="font-weight-bold">No Performance Data Yet</h6>
                                        <p class="small">There is no weekly or monthly performance data entered for EFY {{ $year }} yet.</p>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-hover m-0">
                                            <thead class="bg-light small font-weight-bold">
                                                <tr>
                                                    <th class="pl-4">MONTHLY PERIOD</th>
                                                    <th class="text-center">TARGETS SET</th>
                                                    <th class="text-center">DATA ENTRIES</th>
                                                    <th class="text-center">STATUS</th>
                                                    <th class="text-right pr-4">ACTION</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($months as $month)
                                                    <tr>
                                                        <td class="pl-4 align-middle">
                                                            <div class="d-flex align-items-center">
                                                                <div class="month-icon bg-primary-light text-primary mr-3 rounded-circle d-flex align-items-center justify-content-center"
                                                                    style="width: 40px; height: 40px; background-color: #eef2ff;">
                                                                    <i class="fas fa-calendar-alt"></i>
                                                                </div>
                                                                <div>
                                                                    <span class="h6 font-weight-bold m-0">{{ ucfirst($month) }} {{ $year }}</span>
                                                                    <small class="d-block text-muted">Performance Aggregation</small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="text-center align-middle font-weight-bold">
                                                            <span class="badge badge-light border">Active</span>
                                                        </td>
                                                        <td class="text-center align-middle">
                                                            <span class="badge badge-success px-2 py-1">Data Available</span>
                                                        </td>
                                                        <td class="text-center align-middle">
                                                            @php
                                                                $closure = \App\Models\PeriodClosure::where('year', $year)
                                                                    ->where('month', $month)
                                                                    ->where('department_id', $deptId)
                                                                    ->exists();
                                                                $workflow = $workflowSummaries[$month] ?? ['badge' => 'secondary', 'label' => 'Draft'];
                                                            @endphp
                                                            @if($closure)
                                                                <span class="text-success small font-weight-bold"><i class="fas fa-lock mr-1"></i> Certified & Locked</span>
                                                            @else
                                                                <span class="badge badge-{{ $workflow['badge'] }}">{{ $workflow['label'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-right pr-4 align-middle">
                                                            @if($hasMonthlyData[$month] ?? false)
                                                                <a href="{{ route('monthly.report', ['year' => $year, 'month' => $month, 'department_id' => $deptId]) }}"
                                                                    class="btn btn-outline-primary btn-sm px-4 rounded-pill">
                                                                    <i class="fas fa-eye mr-1"></i> View Report
                                                                </a>
                                                            @else
                                                                <a href="{{ route('monthly.entry.form', ['year' => $year, 'month' => $month, 'department_id' => $deptId]) }}"
                                                                    class="btn btn-primary btn-sm px-4 rounded-pill">
                                                                    <i class="fas fa-edit mr-1"></i> Open Entry
                                                                </a>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @elseif($isAdmin && !$deptId)
                    <!-- Admin Prompt -->
                    <div class="col-12">
                        <div class="alert alert-info shadow-sm border-0 d-flex align-items-center">
                            <i class="fas fa-info-circle fa-2x mr-3"></i> 
                            <div>
                                <strong>Select a Department</strong><br>
                                Please select a department from the dropdown menu above to view its monthly performance history.
                            </div>
                        </div>
                    </div>
                @else
                    <!-- No Plan Prompt -->
                    <div class="col-12">
                        <div class="alert alert-warning shadow-sm border-0 d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x mr-3"></i> 
                            <div>
                                <strong>No Approved Plan Found</strong><br>
                                No approved annual plan was found for EFY {{ $year }} for the selected department. 
                                Please ensure a plan is created and approved first.
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            .month-icon {
                font-size: 1.1rem;
            }

            .shadow-xs {
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            }

            .table-hover tbody tr:hover {
                background-color: #f8fafc;
                cursor: pointer;
            }
        </style>
    @endpush
@endsection
