@extends('layouts.app')
@section('page_header_hidden', '1')

@section('title', 'Daily Performance Workbench')

@section('content')
    <div class="content-header workbench-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">Daily Performance Workbench</h1>
                    <p class="text-muted small">
                        Select a week to enter daily performance data.
                        @if($dept)
                            <span class="badge badge-warning ml-1"><i class="fas fa-calendar-day mr-1"></i>{{ $dept->name }}</span>
                            <span class="badge badge-info ml-1"><i class="fas fa-sync-alt mr-1"></i>Daily to Weekly</span>
                        @endif
                    </p>
                </div>
                <div class="col-sm-6 text-right">
                    <form action="{{ route('daily.dashboard') }}" method="GET" class="workbench-filters">
                        @if($canSelectDepartment)
                        <div class="workbench-filter">
                        <label for="workbench-department">Department</label>
                        <select id="workbench-department" name="department_id" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                            <option value="">-- Select Department --</option>
                            @foreach($dailyDepartments as $d)
                                <option value="{{ $d->id }}" {{ $deptId == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                        </div>
                        @endif

                        <div class="workbench-filter">
                        <label for="workbench-year">Fiscal year</label>
                        <select id="workbench-year" name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                            @foreach($years as $y)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if(session('success'))
                <div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}</div>
            @endif

            @if(session('info'))
                <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i> {{ session('info') }}</div>
            @endif

            <div class="card card-warning card-outline shadow-sm">
                @if($canSelectDepartment && !$deptId)
                    <div class="alert alert-info rounded-0 mb-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        Select a sender department above to view its daily submissions.
                    </div>
                @endif
                <div class="card-header border-0 bg-light">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-calendar-alt mr-1"></i> Daily Entry Period Selector ({{ $year }})
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered m-0 text-center" id="weekGrid">
                            <thead class="thead-light small">
                                <tr>
                                    <th width="140" class="text-left pl-4 align-middle">MONTH</th>
                                    @php
                                        $maxWeeks = 0;
                                        foreach($weeksByMonth as $mWeeks) {
                                            $maxWeeks = max($maxWeeks, count($mWeeks));
                                        }
                                    @endphp
                                    @for($w = 1; $w <= $maxWeeks; $w++)
                                        <th class="align-middle">WEEK {{ $w }}</th>
                                    @endfor
                                    <th width="140" class="align-middle">ITEMS RECORDED</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($months as $month)
                                    @php
                                        $monthData = collect(data_get($progress, $month, []));
                                        $weeksInfo = $weeksByMonth[$month] ?? [];
                                        $recordedCount = $monthData->sum('item_count');
                                    @endphp
                                    <tr>
                                        <td class="text-left pl-4 font-weight-bold align-middle bg-light">
                                            {{ ucfirst($month) }}
                                        </td>
                                        @for($w = 1; $w <= $maxWeeks; $w++)
                                            @php
                                                $hasData = $monthData->firstWhere('week_number', $w);
                                                $wInfo   = $weeksInfo[$w] ?? null;
                                                $wLabel  = $wInfo ? $wInfo['label'] : null;
                                                $workflowStatus = $hasData->workflow_status ?? 'draft';
                                                $workflowMeta = match($workflowStatus) {
                                                    'submitted_to_directorate' => ['badge' => 'info', 'label' => 'Directorate'],
                                                    'returned_to_expert' => ['badge' => 'warning', 'label' => 'Returned to Expert'],
                                                    'submitted_to_chief' => ['badge' => 'primary', 'label' => 'Chief'],
                                                    'returned_to_directorate' => ['badge' => 'warning', 'label' => 'Returned to Directorate'],
                                                    'submitted_to_plan_report_department' => ['badge' => 'primary', 'label' => 'Plan & Report'],
                                                    'returned_to_department_chief' => ['badge' => 'warning', 'label' => 'Returned to Chief'],
                                                    'submitted_to_head_office' => ['badge' => 'primary', 'label' => 'Head Office'],
                                                    'returned_to_plan_report_department' => ['badge' => 'warning', 'label' => 'Returned to P&R'],
                                                    'approved_by_head_office' => ['badge' => 'success', 'label' => 'Approved'],
                                                    default => ['badge' => 'secondary', 'label' => 'Draft'],
                                                };
                                            @endphp
                                            <td class="p-0 align-middle">
                                                @if($wInfo)
                                                    <a href="{{ route('daily.entry', ['year' => $year, 'month' => $month, 'week' => $w, 'department_id' => $deptId]) }}"
                                                        class="d-block pt-2 pb-2 px-1 transition-all week-cell {{ $hasData ? 'bg-warning-light' : '' }}"
                                                        title="Enter Daily data for {{ $wLabel }}">
                                                        @if($hasData)
                                                            <i class="fas fa-check-circle text-warning fa-lg"></i>
                                                            <span class="d-block small text-warning font-weight-bold">{{ $hasData->item_count }} Items</span>
                                                            <span class="badge badge-{{ $workflowMeta['badge'] }} mt-1">{{ $workflowMeta['label'] }}</span>
                                                        @else
                                                            <i class="fas fa-edit text-muted opacity-50"></i>
                                                            <span class="d-block small text-muted">Enter Daily</span>
                                                        @endif
                                                        <span class="d-block" style="font-size:0.7rem; color:#888;">{{ $wLabel }}</span>
                                                    </a>
                                                @else
                                                    <div class="pt-2 pb-2 transition-all opacity-25" style="background:#f8f9fa;">
                                                        <span class="d-block small text-muted">-</span>
                                                        <span class="d-block" style="font-size:0.7rem; color:#ccc;">N/A</span>
                                                    </div>
                                                @endif
                                            </td>
                                        @endfor
                                        <td class="align-middle">
                                            <span class="badge badge-pill badge-secondary">{{ $recordedCount }} Records</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('styles')
        <style>
            .week-cell {
                text-decoration: none !important;
                transition: all 0.2s;
                border: 1px solid transparent;
                border-radius: 4px;
            }
            .week-cell:hover {
                background-color: #fff9eb !important;
                border-color: #ffc107;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(255,193,7,0.15);
            }
            .bg-warning-light {
                background-color: #fffdf5;
                border-color: #ffc107 !important;
            }
            .opacity-50 { opacity: 0.5; }
        </style>
    @endpush
@endsection
