@extends('layouts.app')

@section('title', 'Performance Scoreboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('performance.index') }}">Performance</a></li>
    <li class="breadcrumb-item active">Scoreboard</li>
@endsection

@section('content')
    <div class="row mb-4">
        <div class="col-md-9">
            <h3 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-chart-line mr-2 text-success"></i>
                Scoreboard: {{ $plan->department->name }}
            </h3>
            <p class="text-muted">{{ ucfirst($period) }} Evaluation - {{ $plan->year }} EFY</p>
        <div class="col-md-3 text-right">
            <div class="d-flex justify-content-end align-items-center mb-2">
                <form action="{{ route('performance.report', [$plan->id, 'PLACEHOLDER']) }}" method="GET" id="periodForm" class="mr-2">
                    <select id="periodSelector" class="custom-select custom-select-sm shadow-sm font-weight-boldtext-primary" onchange="changePeriod(this.value)">
                        <optgroup label="Quarters">
                            <option value="q1" {{ strtolower($period) == 'q1' ? 'selected' : '' }}>Quarter 1 (Jul-Sep)</option>
                            <option value="q2" {{ strtolower($period) == 'q2' ? 'selected' : '' }}>Quarter 2 (Oct-Dec)</option>
                            <option value="q3" {{ strtolower($period) == 'q3' ? 'selected' : '' }}>Quarter 3 (Jan-Mar)</option>
                            <option value="q4" {{ strtolower($period) == 'q4' ? 'selected' : '' }}>Quarter 4 (Apr-Jun)</option>
                        </optgroup>
                        <optgroup label="Months">
                            <option value="july" {{ strtolower($period) == 'july' ? 'selected' : '' }}>July</option>
                            <option value="august" {{ strtolower($period) == 'august' ? 'selected' : '' }}>August</option>
                            <option value="september" {{ strtolower($period) == 'september' ? 'selected' : '' }}>September</option>
                            <option value="october" {{ strtolower($period) == 'october' ? 'selected' : '' }}>October</option>
                            <option value="november" {{ strtolower($period) == 'november' ? 'selected' : '' }}>November</option>
                            <option value="december" {{ strtolower($period) == 'december' ? 'selected' : '' }}>December</option>
                            <option value="january" {{ strtolower($period) == 'january' ? 'selected' : '' }}>January</option>
                            <option value="february" {{ strtolower($period) == 'february' ? 'selected' : '' }}>February</option>
                            <option value="march" {{ strtolower($period) == 'march' ? 'selected' : '' }}>March</option>
                            <option value="april" {{ strtolower($period) == 'april' ? 'selected' : '' }}>April</option>
                            <option value="may" {{ strtolower($period) == 'may' ? 'selected' : '' }}>May</option>
                            <option value="june" {{ strtolower($period) == 'june' ? 'selected' : '' }}>June</option>
                        </optgroup>
                    </select>
                </form>
            </div>
            <div class="btn-group shadow-sm">
                <button onclick="window.print()" class="btn btn-outline-info">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
                <a href="{{ route('performance.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- Department Score Card -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div
                class="info-box bg-gradient-{{ $deptScore >= 90 ? 'success' : ($deptScore >= 75 ? 'warning' : 'danger') }} shadow">
                <span class="info-box-icon"><i class="fas fa-trophy"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text font-weight-bold">Achievement Score</span>
                    <span class="info-box-number text-xl">{{ number_format($deptScore, 2) }}%</span>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar" style="width: {{ $deptScore }}%"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center bg-light">
                    <div class="row w-100">
                        <div class="col-sm-4 border-right">
                            <label class="text-muted small text-uppercase font-weight-bold mb-0">Departmental Rank</label>
                            <h5 class="mb-0 font-weight-bold">
                                @if($deptScore >= 90) <span class="text-success">Rank 1 - Excellent</span>
                                @elseif($deptScore >= 75) <span class="text-warning">Rank 2 - Good</span>
                                @else <span class="text-danger">Rank 3 - Needs Focus</span>
                                @endif
                            </h5>
                        </div>
                        <div class="col-sm-4 border-right px-4">
                            <label class="text-muted small text-uppercase font-weight-bold mb-0">Measurement Period</label>
                            <h5 class="mb-0">{{ ucfirst($period) }} Quarter</h5>
                        </div>
                        <div class="col-sm-4 px-4">
                            <label class="text-muted small text-uppercase font-weight-bold mb-0">Data Refresh</label>
                            <h5 class="mb-0 small text-muted">Synchronized: {{ now()->format('Y-m-d H:i') }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow border-0 overflow-hidden mb-5">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="card-title mb-0 font-weight-bold">Detailed Performance Matrix</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm text-center align-middle mb-0">
                    <thead class="bg-secondary text-white small">
                        <tr>
                            <th rowspan="2" class="align-middle px-3">SN</th>
                            <th rowspan="2" class="align-middle text-left" style="min-width: 250px;">Objective Description</th>
                            <th rowspan="2" class="align-middle">Unit</th>
                            <th rowspan="2" class="align-middle">Weight</th>
                            <th rowspan="2" class="align-middle">Target</th>
                            <th rowspan="2" class="align-middle">Actual</th>
                            <th class="text-center">{{ ucfirst($period) }} Progress</th>
                            <th rowspan="2" class="align-middle text-warning">Weighted Score</th>
                            <th rowspan="2" class="align-middle">Status</th>
                        </tr>
                        <tr>
                            <th>Comp %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalWeightedScore = 0; @endphp
                        @foreach($plan->items as $index => $item)
                            @php
                                $so = $item->strategicObjective;
                                $targetColumn = strtolower($period) . '_target';
                                $target = $item->{$targetColumn};
                                $actual = $item->getActualValueForPeriod($period);
                                
                                $isPlanned = !is_null($target);
                                $rawProgress = $isPlanned ? $calculator->calculateRawTargetAchievement($item, $period) : null;
                                $progress = $isPlanned ? min(100, $rawProgress) : null;

                                $baseWeight = ($item->weight > 0) ? (float) $item->weight : (float) $so->weight;
                                $weight = $effectiveWeights[$item->id] ?? null;
                                $weightedScore = ($isPlanned && !is_null($weight)) ? min(($progress / 100) * $weight, $weight) : 0;
                                $totalWeightedScore += $weightedScore;

                                $status = $calculator->performanceStatus($rawProgress);
                                $color = $status === 'Overachieved' ? 'info' : ($status === 'Achieved' ? 'success' : ($status === 'Not Planned (N/A)' ? 'secondary' : 'warning'));
                                
                                $isWeekly = in_array($item->effectiveReportingFrequency(), ['weekly', 'daily'], true);
                                $weeks = $isWeekly ? $item->weeklyPerformances->where('month', strtolower($period))->sortBy('week_number') : collect();
                            @endphp
                            <tr class="main-row">
                                <td class="py-3 font-weight-bold">{{ $index + 1 }}.</td>
                                <td class="text-left py-3">
                                    <div class="font-weight-600 text-dark">{{ $so->description }}</div>
                                    <small class="text-muted">{{ $so->code }}</small>
                                    @if($isWeekly && $weeks->count() > 0)
                                        <button class="btn btn-xs btn-link p-0 text-info ml-2" onclick="toggleWeeks({{ $item->id }})">
                                            <i class="fas fa-eye mr-1" id="icon-{{ $item->id }}"></i> View Weekly Breakdown
                                        </button>
                                    @endif
                                </td>
                                <td><span class="badge badge-light border">{{ $so->unit }}</span></td>
                                <td class="font-weight-bold">
                                    @if(!$isPlanned || is_null($weight))
                                        N/A
                                    @elseif(abs((float)$weight - $baseWeight) > 0.0001)
                                        <small class="d-block text-muted"><del>{{ number_format($baseWeight, 4) }}</del></small>
                                        <span class="text-info">{{ number_format((float)$weight, 4) }}%</span>
                                    @else
                                        {{ number_format((float)$weight, 4) }}%
                                    @endif
                                </td>
                                <td class="bg-light font-weight-bold">
                                    {{ !$isPlanned ? 'N/A' : ($so->isTemporal() ? $so->formatTemporal($target) : number_format($target, 2)) }}
                                </td>
                                <td class="bg-light font-weight-bold text-primary">
                                    {{ !$isPlanned ? 'N/A' : ($so->isTemporal() ? $so->formatTemporal($actual) : number_format($actual, 2)) }}
                                </td>
                                <td>
                                    <div class="progress progress-xs mb-1" style="height: 5px;">
                                        <div class="progress-bar bg-{{ $color }}" style="width: {{ min(100, $progress ?? 0) }}%">
                                        </div>
                                    </div>
                                    <span class="font-weight-bold">{{ $isPlanned ? number_format($progress, 1) . '%' : 'N/A' }}</span>
                                    @if($isPlanned && $rawProgress > 100)
                                        <small class="d-block text-info">Raw: {{ number_format($rawProgress, 1) }}%</small>
                                    @endif
                                </td>
                                <td class="bg-warning-light font-weight-bold text-dark">
                                    {{ number_format($weightedScore, 2) }}
                                </td>
                                <td>
                                    <span class="badge badge-{{ $color }} shadow-sm px-3 py-1">{{ $status }}</span>
                                </td>
                            </tr>

                            @if($isWeekly && $weeks->count() > 0)
                                <tr id="weeks-{{ $item->id }}" style="display: none;" class="bg-light">
                                    <td colspan="9" class="p-0">
                                        <div class="px-5 py-2">
                                            <table class="table table-sm table-borderless m-0 text-left small">
                                                <thead>
                                                    <tr class="text-muted border-bottom">
                                                        <th width="100">Month</th>
                                                        <th width="85">Week</th>
                                                        <th width="190">Date Range</th>
                                                        <th width="120">Planned</th>
                                                        <th width="120">Actual</th>
                                                        <th>Achievement / Workflow Status / Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($weeks as $week)
                                                        @php
                                                            $weekMonth = strtolower($week->month ?: $period);
                                                            $weekCalendar = \App\Services\CalendarHelper::getMonthWeeks($weekMonth, (int) $plan->year);
                                                            $weekDates = $weekCalendar[(int) $week->week_number] ?? null;
                                                            $calendarYear = \App\Services\CalendarHelper::getGregorianYear($weekMonth, (int) $plan->year);
                                                            $workflowLabel = match($week->workflow_status ?? 'draft') {
                                                                'submitted_to_directorate' => 'Submitted to Directorate',
                                                                'returned_to_expert' => 'Returned to Expert',
                                                                'submitted_to_chief' => 'Submitted to Chief',
                                                                'returned_to_directorate' => 'Returned to Directorate',
                                                                'submitted_to_plan_report_department' => 'Submitted to Plan & Report',
                                                                'returned_to_department_chief' => 'Returned to Department Chief',
                                                                'submitted_to_head_office' => 'Submitted to Head Office',
                                                                'returned_to_plan_report_department' => 'Returned to Plan & Report',
                                                                'approved_by_head_office' => 'Final Approved',
                                                                default => 'Draft',
                                                            };
                                                            $workflowBadge = match($week->workflow_status ?? 'draft') {
                                                                'approved_by_head_office' => 'success',
                                                                'returned_to_expert', 'returned_to_directorate',
                                                                'returned_to_department_chief', 'returned_to_plan_report_department' => 'warning',
                                                                'draft' => 'secondary',
                                                                default => 'info',
                                                            };
                                                        @endphp
                                                        <tr>
                                                            <td class="font-weight-bold">{{ ucfirst($weekMonth) }}</td>
                                                            <td class="font-weight-bold">Week {{ $week->week_number }}</td>
                                                            <td>
                                                                @if($weekDates)
                                                                    {{ $weekDates['label'] }}, {{ $calendarYear }}
                                                                @else
                                                                    Date unavailable
                                                                @endif
                                                            </td>
                                                            <td>{{ $so->isTemporal() ? $so->formatTemporal($week->planned_value) : number_format($week->planned_value, 2) }}</td>
                                                            <td class="text-primary font-weight-bold">
                                                                {{ $so->isTemporal() ? $so->formatTemporal($week->actual_value) : number_format($week->actual_value, 2) }}
                                                            </td>
                                                            <td>
                                                                @php $wPerf = $so->calculateWeeklyAchievement((float) $week->actual_value, (float) $week->planned_value); @endphp
                                                                <span class="badge badge-{{ $wPerf >= 90 ? 'success' : ($wPerf >= 50 ? 'warning' : 'danger') }} py-0">
                                                                    {{ number_format($wPerf, 0) }}%
                                                                </span>
                                                                <span class="badge badge-{{ $workflowBadge }} ml-1">{{ $workflowLabel }}</span>
                                                                <span class="text-muted italic ml-2 small">{{ $week->notes }}</span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot class="bg-dark text-white font-weight-bold">
                        <tr>
                            <td colspan="7" class="text-right py-2 pr-4">OVERALL PERFORMANCE SCORE:</td>
                            <td class="text-center py-2 bg-success text-lg">
                                {{ number_format($deptScore, 2) }}%
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .progress-xs {
            height: 5px;
        }

        .bg-warning-light {
            background-color: #fffef0 !important;
        }

        .italic {
            font-style: italic;
        }

        @media print {
            .btn-group,
            .breadcrumb,
            footer {
                display: none !important;
            }

            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }

            .bg-gradient-success,
            .bg-gradient-warning,
            .bg-gradient-danger,
            .bg-dark,
            .bg-secondary {
                background: #f8f9fa !important;
                color: #000 !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        function changePeriod(newPeriod) {
            let form = document.getElementById('periodForm');
            let baseAction = form.action.replace('PLACEHOLDER', newPeriod);
            window.location.href = baseAction;
        }

        function toggleWeeks(itemId) {
            let row = document.getElementById('weeks-' + itemId);
            let icon = document.getElementById('icon-' + itemId);
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                row.style.display = 'none';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
@endpush
