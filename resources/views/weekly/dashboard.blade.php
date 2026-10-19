@extends('layouts.app')
@section('page_header_hidden', '1')

@section('title', 'Weekly Performance Workbench')

@section('content')
    <div class="content-header workbench-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">Weekly Performance Workbench</h1>
                    <p class="text-muted small">
                        Select a week to enter performance data.
                        @if($dept)
                            <span class="badge badge-primary ml-1"><i class="fas fa-building mr-1"></i>{{ $dept->name }}</span>
                            <span class="badge badge-success ml-1"><i class="fas fa-calendar-week mr-1"></i>Weekly Reporting</span>
                            @if($currentPeriod)<span class="badge badge-info ml-1">Current: {{ ucfirst($currentPeriod['month']) }} W{{ $currentPeriod['week'] }} · {{ $currentPeriod['label'] }}</span>@endif
                        @endif
                    </p>
                </div>
                <div class="col-sm-6 text-right">
                    <form action="{{ route('weekly.dashboard') }}" method="GET" class="workbench-filters">
                        @if($canSelectDepartment)
                        <div class="workbench-filter">
                        <label for="workbench-department">Department</label>
                        <select id="workbench-department" name="department_id" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                            <option value="">-- Select Department --</option>
                            @foreach($weeklyDepartments as $d)
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
            <div class="card card-primary card-outline shadow-sm">
                @if($canSelectDepartment && !$deptId)
                    <div class="alert alert-info rounded-0 mb-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        Select a sender department above to view its weekly submissions.
                    </div>
                @endif
                <div class="card-header border-0 bg-light">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-calendar-alt mr-1"></i> Entry Period Selector ({{ $year }})
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-secondary">Fixed Saturday–Friday Weeks</span>
                    </div>
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
                                        <th class="align-middle">
                                            WEEK {{ $w }}
                                        </th>
                                    @endfor
                                    <th width="140" class="align-middle">PROGRESS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($months as $month)
                                    @php
                                        $monthData = collect(data_get($progress, $month, []));
                                        $submittedCount = $monthData->count();
                                        $weeksInfo = $weeksByMonth[$month] ?? [];
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
                                                $startsAt = $wInfo ? \Illuminate\Support\Carbon::parse($wInfo['start_full'])->startOfDay() : null;
                                                $dueAt = $wInfo ? \Illuminate\Support\Carbon::parse($wInfo['due_at']) : null;
                                                $isFuture = $startsAt && $now->lt($startsAt);
                                                $isOverdue = !$hasData && $dueAt && $now->gt($dueAt);
                                                $isLate = $hasData?->submitted_at && $dueAt && \Illuminate\Support\Carbon::parse($hasData->submitted_at)->gt($dueAt);
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
                                                    <a @if(!$isFuture) href="{{ route('weekly.entry', ['year' => $year, 'month' => $month, 'week' => $w, 'department_id' => $deptId]) }}" @endif
                                                        class="d-block pt-2 pb-2 px-1 transition-all week-cell {{ $hasData ? 'bg-success-light' : '' }}"
                                                        title="Enter Week {{ $w }} data ({{ $wLabel }})">
                                                        @if($hasData)
                                                            <i class="fas fa-check-circle text-success fa-lg"></i>
                                                            <span class="d-block small text-success font-weight-bold">Entered</span>
                                                            <span class="badge badge-{{ $workflowMeta['badge'] }} mt-1">{{ $workflowMeta['label'] }}</span>
                                                            @if($isLate)<span class="badge badge-warning mt-1">Late Submitted</span>@endif
                                                        @else
                                                            <i class="fas {{ $isFuture ? 'fa-lock' : 'fa-edit' }} text-muted opacity-50"></i>
                                                            <span class="d-block small {{ $isOverdue ? 'text-danger font-weight-bold' : 'text-muted' }}">{{ $isFuture ? 'Future' : ($isOverdue ? 'Missing / Overdue' : 'Missing') }}</span>
                                                        @endif
                                                        <span class="d-block" style="font-size:0.7rem; color:#888;">{{ $wLabel }}</span>
                                                        <span class="d-block" style="font-size:0.65rem; color:#888;">Due {{ $dueAt?->format('M d, H:i') }}</span>
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
                                            @php
                                                $totalWeeksInMonth = count($weeksInfo);
                                            @endphp
                                            <div class="progress progress-sm rounded-pill mx-2" style="height: 8px;">
                                                <div class="progress-bar bg-primary" role="progressbar"
                                                    style="width: {{ $totalWeeksInMonth > 0 ? ($submittedCount / $totalWeeksInMonth) * 100 : 0 }}%"></div>
                                            </div>
                                            <small class="text-muted font-weight-bold">{{ $submittedCount }}/{{ $totalWeeksInMonth }} Weeks</small>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($deptId)
            <div class="row mb-3">
                @foreach([
                    ['Missing Weekly Reports', $timeliness['missing'], 'secondary', 'calendar-times'],
                    ['Overdue', $timeliness['overdue'], 'danger', 'clock'],
                    ['Late Submitted', $timeliness['late'], 'warning', 'history'],
                    ['Awaiting Review', $timeliness['awaiting'], 'info', 'hourglass-half'],
                    ['Approved', $timeliness['approved'], 'success', 'check-circle'],
                ] as [$label,$value,$color,$icon])
                <div class="col"><div class="small-box bg-{{ $color }} mb-0"><div class="inner"><h3>{{ $value }}</h3><p>{{ $label }}</p></div><div class="icon"><i class="fas fa-{{ $icon }}"></i></div></div></div>
                @endforeach
            </div>
            @endif

            <div class="row">
                <div class="col-md-4">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon bg-primary"><i class="fas fa-calendar-week"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Weeks Per Month</span>
                            <span class="info-box-number">4–5</span>
                            <span class="progress-description small">Every period runs Saturday through Friday.</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon bg-success"><i class="fas fa-check-double"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Calendar Accuracy</span>
                            <span class="info-box-number">Real Days</span>
                            <span class="progress-description small">Cross-month weeks belong to the month containing Friday.</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon bg-info"><i class="fas fa-layer-group"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Auto Monthly Summary</span>
                            <span class="info-box-number">Active</span>
                            <span class="progress-description small">All calendar weeks aggregate into monthly performance automatically.</span>
                        </div>
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
                background-color: #f1f8ff !important;
                border-color: #007bff;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,123,255,0.15);
            }
            .bg-success-light {
                background-color: #e6fffa;
                border-color: #38b2ac !important;
            }
            .opacity-50 { opacity: 0.5; }
        </style>
    @endpush
@endsection
