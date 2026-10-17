@extends('layouts.app')

@section('title', 'Weekly Performance Report — ' . $weekLabel)

@push('styles')
<style>
    .weekly-report-table { font-size: 0.82rem; }
    .weekly-report-table th { text-align: center; vertical-align: middle !important; }
    .weekly-report-table td { vertical-align: middle !important; }

    /* Row levels */
    .row-parent   { background-color: #d4edda !important; font-weight: bold; }
    .row-child    { background-color: #ffffff; }
    .row-ref      { background-color: #cce5ff !important; color: #004085; }
    .row-gc       { background-color: #f8f9fa; }

    /* Indent helpers */
    .indent-1 { padding-left: 1.5rem !important; }
    .indent-2 { padding-left: 3.0rem !important; }

    /* Achievement badge overrides */
    .badge-achievement { min-width: 48px; display: inline-block; font-size: 0.9em; }

    @media print {
        .no-print { display: none !important; }
        .card { border: none !important; }
        body { font-size: 11pt; }
    }
</style>
@endpush

@section('content')
@php
    $performanceScale = \App\Models\Setting::getPerformanceScale();
    $referenceBg = \App\Models\Setting::get('performance_reference_bg', '#cce5ff');
    $referenceText = \App\Models\Setting::get('performance_reference_text', '#004085');
@endphp
@include('weekly._shared-review')
<div class="content-header p-2 no-print">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded border shadow-sm mb-3">
            <div class="d-flex align-items-center">
                <a href="{{ route($reportFrequency === 'daily' ? 'daily.entry' : 'weekly.entry', ['year' => $year, 'month' => $month, 'week' => $week, 'department_id' => $deptId, 'frequency' => $reportFrequency]) }}"
                   class="btn btn-outline-secondary btn-sm mr-2">
                    <i class="fas {{ $workflowState['key'] === 'returned_to_department_chief' ? 'fa-edit' : 'fa-arrow-left' }}"></i> {{ $workflowState['key'] === 'returned_to_department_chief' ? 'Edit Returned Performance' : 'Back to Entry' }}
                </a>
                <div class="btn-group btn-group-sm mr-2">
                    @foreach([1, 2, 3, 4] as $w)
                        <a href="{{ route('weekly.report', ['year' => $year, 'month' => $month, 'week' => $w, 'department_id' => $deptId, 'frequency' => $reportFrequency]) }}"
                            class="btn btn-{{ $week == $w ? 'primary' : 'outline-primary' }}">W{{ $w }}</a>
                    @endforeach
                </div>
                <select class="form-control form-control-sm mr-2" style="width: 130px;"
                    onchange="location.href='{{ url('/weekly/report') }}/' + {{ $year }} + '/' + this.value + '/{{ $week }}?department_id={{ $deptId }}&frequency={{ $reportFrequency }}'">
                    @foreach(['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'] as $m)
                        <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                    @endforeach
                </select>
                <select class="form-control form-control-sm" style="width: 100px;"
                    onchange="location.href='{{ url('/weekly/report') }}/' + this.value + '/{{ $month }}/{{ $week }}?department_id={{ $deptId }}&frequency={{ $reportFrequency }}'">
                    @foreach(\App\Services\CalendarHelper::getAppYears() as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex">
                <a href="{{ route('weekly.export', ['year' => $year, 'month' => $month, 'week' => $week, 'department_id' => $deptId, 'frequency' => $reportFrequency]) }}"
                   class="btn btn-success btn-sm mr-2">
                    <i class="fas fa-file-csv mr-1"></i> Export CSV
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <div class="card shadow-sm border-0 mb-3 no-print">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div class="mb-2">
                        <div class="text-muted small">Workflow Status</div>
                        <span class="badge badge-{{ $workflowState['badge'] }} px-3 py-2">{{ $workflowState['label'] }}</span>
                        @if($workflowState['note'])
                            <div class="small text-muted mt-2">
                                <i class="fas fa-comment-dots mr-1"></i>{{ $workflowState['note'] }}
                            </div>
                        @endif
                        @if($workflowState['submitted_by'])
                            <div class="small text-muted mt-2">
                                Submitted by {{ $workflowState['submitted_by'] }}
                                @if($workflowState['submitted_at'])
                                    on {{ $workflowState['submitted_at']->format('M d, Y H:i') }}
                                @endif
                            </div>
                        @endif
                        @if($workflowState['reviewed_by'])
                            <div class="small text-muted">
                                Last reviewed by {{ $workflowState['reviewed_by'] }}
                                @if($workflowState['reviewed_at'])
                                    on {{ $workflowState['reviewed_at']->format('M d, Y H:i') }}
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="text-right">
                        @if(
                            $workflowActions['submit_to_chief']
                            || $workflowActions['return_to_expert']
                            || $workflowActions['return_to_directorate']
                            || $workflowActions['approve_by_chief']
                            || $workflowActions['return_to_department_chief']
                            || $workflowActions['submit_to_plan_report_directorate']
                            || $workflowActions['submit_to_head_office']
                            || $workflowActions['return_to_plan_report_department']
                            || $workflowActions['return_to_plan_report_directorate']
                            || $workflowActions['approve_by_head_office']
                            || $workflowActions['mark_reviewed_no_correction']
                        )
                            <form action="{{ route('weekly.workflow', ['year' => $year, 'month' => $month, 'week' => $week]) }}" method="POST" class="d-inline-block">
                                @csrf
                                <input type="hidden" name="department_id" value="{{ $deptId }}"><input type="hidden" name="frequency" value="{{ $reportFrequency }}">
                                <div class="form-group mb-2">
                                    <input type="text" name="workflow_note" class="form-control form-control-sm"
                                        placeholder="Optional review note for the next person">
                                </div>
                                <div>
                                    @if($workflowActions['return_to_expert'])
                                        <button type="submit" name="action" value="return_to_expert" class="btn btn-outline-warning btn-sm mr-2">
                                            Return to Expert
                                        </button>
                                    @endif
                                    @if($workflowActions['submit_to_chief'])
                                        <a href="{{ route($reportFrequency === 'daily' ? 'daily.entry' : 'weekly.entry', ['year' => $year, 'month' => $month, 'week' => $week, 'department_id' => $deptId, 'frequency' => $reportFrequency]) }}"
                                           class="btn btn-outline-primary btn-sm mr-2">
                                            Open Review Form
                                        </a>
                                        <button type="submit" name="action" value="submit_to_chief" class="btn btn-primary btn-sm">
                                            Approve and Send to Chief
                                        </button>
                                    @endif
                                    @if($workflowActions['return_to_directorate'])
                                        <button type="submit" name="action" value="return_to_directorate" class="btn btn-outline-warning btn-sm mr-2">
                                            Return to Directorate
                                        </button>
                                    @endif
                                    @if($workflowActions['approve_by_chief'])
                                        @if($workflowState['key'] === 'returned_to_department_chief')
                                            <a href="{{ route($reportFrequency === 'daily' ? 'daily.entry' : 'weekly.entry', ['year'=>$year,'month'=>$month,'week'=>$week,'department_id'=>$deptId,'frequency'=>$reportFrequency]) }}" class="btn btn-warning btn-sm mr-2"><i class="fas fa-edit mr-1"></i>Edit Returned Performance</a>
                                        @endif
                                        <button type="submit" name="action" value="approve_by_chief" class="btn btn-success btn-sm">
                                            {{ $workflowState['key'] === 'returned_to_department_chief' ? 'Resend Corrected Performance to PR Expert' : 'Approve and Send to Plan & Report Department' }}
                                        </button>
                                    @endif
                                    @if($workflowActions['return_to_department_chief'])
                                        <button type="submit" name="action" value="return_to_department_chief" class="btn btn-outline-warning btn-sm mr-2">
                                            Return to Department Chief for Correction
                                        </button>
                                    @endif
                                                                       @if($workflowActions['mark_reviewed_no_correction'])
                                        <button type="submit" name="action" value="mark_reviewed_no_correction" class="btn btn-success btn-sm mr-2">Reviewed — No Correction Required</button>
                                    @endif
                                    @if($workflowActions['submit_to_head_office'])
                                        <a href="{{ route($reportFrequency === 'daily' ? 'daily.entry' : 'weekly.entry', ['year' => $year, 'month' => $month, 'week' => $week, 'department_id' => $deptId, 'frequency' => $reportFrequency]) }}"
                                           class="btn btn-outline-primary btn-sm mr-2">
                                            Open Office Review Form
                                        </a>
                                        <button type="submit" name="action" value="submit_to_head_office" class="btn btn-primary btn-sm">
                                            Accept and Send to Head of Plan &amp; Report
                                        </button>
                                    @endif
                                    @if($workflowActions['submit_to_plan_report_directorate'])
                                        <button type="submit" name="action" value="submit_to_plan_report_directorate" class="btn btn-primary btn-sm">
                                            Request Correction Through Directorate
                                        </button>
                                    @endif
                                    @if($workflowActions['return_to_plan_report_department'])
                                        <button type="submit" name="action" value="return_to_plan_report_department" class="btn btn-outline-warning btn-sm mr-2">
                                            Return to Plan & Report Department
                                        </button>
                                    @endif
                                    @if($workflowActions['return_to_plan_report_directorate'])
                                        <button type="submit" name="action" value="return_to_plan_report_directorate" class="btn btn-outline-warning btn-sm mr-2">
                                            Return to Plan &amp; Report Directorate
                                        </button>
                                    @endif
                                    @if($workflowActions['approve_by_head_office'])
                                        <button type="submit" name="action" value="approve_by_head_office" class="btn btn-success btn-sm">
                                            Final Approve Performance
                                        </button>
                                    @endif
                                </div>
                            </form>
                        @elseif($workflowActions['submit_to_directorate'])
                            <form action="{{ route('weekly.workflow', ['year' => $year, 'month' => $month, 'week' => $week]) }}" method="POST" class="d-inline-block">
                                @csrf
                                <input type="hidden" name="department_id" value="{{ $deptId }}"><input type="hidden" name="frequency" value="{{ $reportFrequency }}">
                                <button type="submit" name="action" value="submit_to_directorate" class="btn btn-success btn-sm">
                                    Submit to Directorate
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @include('performance._attachments', ['attachments' => $attachments])

        {{-- Report Header --}}
        <div class="card card-outline card-success shadow mb-3">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="font-weight-bold mb-0 text-success">
                            <i class="fas fa-chart-bar mr-2"></i>
                            Weekly Performance Report — {{ $weekLabel }}
                        </h4>
                        <small class="text-muted">
                            Department: <strong>{{ $dept->name ?? 'N/A' }}</strong>
                            &nbsp;|&nbsp; Plan Year: <strong>{{ $year }}</strong>
                        </small>
                    </div>
                    <div class="col-md-4 text-right">
                        @php
                            $rowsWithPct = array_filter($reportRows, fn($r) => !$r['is_reference'] && $r['percentage'] !== null);
                            $avgPct = count($rowsWithPct) > 0
                                ? array_sum(array_column($rowsWithPct, 'percentage')) / count($rowsWithPct)
                                : null;
                        @endphp
                        @if($avgPct !== null)
                            @php
                                $avgBand = \App\Models\Setting::getPerformanceBand($avgPct);
                            @endphp
                            <span class="h4 font-weight-bold" style="color: {{ $avgBand['color'] }};">
                                {{ \App\Support\NumberFormatter::format($avgPct) }}%
                            </span>
                            <div class="small text-muted">Overall Average</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0 weekly-report-table">
                        <thead style="background-color: #1a5c37; color: #fff;">
                            <tr>
                                <th style="width:70px">S.No</th>
                                <th class="text-left" style="min-width:260px">Activities</th>
                                <th style="width:70px">Weight</th>
                                <th style="width:80px">Unit</th>
                                <th style="width:80px">Baseline</th>
                                <th style="width:110px">{{ $weekLabel }} Plan</th>
                                <th style="width:120px">{{ $weekLabel }} Performance</th>
                                <th style="width:90px; background-color:#2e7d32;">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportRows as $row)
                                @php
                                    $pct       = $row['percentage'] !== null ? (float) $row['percentage'] : null;
                                    $band      = $pct === null ? null : \App\Models\Setting::getPerformanceBand($pct);
                                    $rowCls    = $row['is_reference']
                                        ? 'row-ref'
                                        : ($row['is_parent'] ? 'row-parent' : ($row['level'] === 2 ? 'row-gc' : 'row-child'));
                                    $indentCls = $row['level'] === 1 ? 'indent-1' : ($row['level'] === 2 ? 'indent-2' : '');
                                @endphp
                                <tr class="{{ $rowCls }}" @if($row['is_reference']) style="background-color: {{ $referenceBg }} !important; color: {{ $referenceText }};" @endif>
                                    <td class="text-center">{{ $row['sno'] }}</td>
                                    <td class="{{ $indentCls }}">{{ $row['label'] }}</td>
                                    <td class="text-center">{{ \App\Support\NumberFormatter::format($row['weight']) }}</td>
                                    <td class="text-center">{{ $row['unit'] }}</td>
                                    <td class="text-right">{{ $row['baseline'] !== null ? \App\Support\NumberFormatter::format((float)$row['baseline'],3) : '0' }}</td>

                                    {{-- Plan Column --}}
                                    <td class="text-right">
                                        @if(!$row['is_reference'] && $row['plan'] !== null)
                                            {{ \App\Support\NumberFormatter::format((float)$row['plan'],3) }}
                                        @endif
                                    </td>

                                    {{-- Actual Column --}}
                                    <td class="text-right {{ $row['actual'] ? 'font-weight-bold' : 'text-muted' }}">
                                        @if($row['actual'] !== null)
                                            {{ \App\Support\NumberFormatter::format((float)$row['actual'],3) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    {{-- Achievement % Column --}}
                                    <td class="text-center">
                                        @if(!$row['is_reference'] && $pct !== null)
                                            <span class="badge badge-achievement font-weight-bold"
                                                  style="font-size: 0.85em; background: {{ $band['color'] }}; color: #fff;">
                                                {{ \App\Support\NumberFormatter::format($pct) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No performance data found for {{ $weekLabel }}.
                                        Please enter data first from the
                                        <a href="{{ route($reportFrequency === 'daily' ? 'daily.entry' : 'weekly.entry', ['year' => $year, 'month' => $month, 'week' => $week, 'department_id' => $deptId, 'frequency' => $reportFrequency]) }}">
                                            entry workbench</a>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        @if(count($reportRows) > 0 && $avgPct !== null)
                            <tfoot style="background-color: #1a5c37; color: #fff; font-weight: bold;">
                                <tr>
                                    <td colspan="5" class="text-right py-2">OVERALL ACHIEVEMENT AVERAGE:</td>
                                    <td class="text-center">
                                        @php
                                            $avgFooterBand = \App\Models\Setting::getPerformanceBand($avgPct);
                                        @endphp
                                        <span class="badge badge-achievement"
                                              style="font-size: 1em; background: {{ $avgFooterBand['color'] }}; color: #fff;">
                                            {{ \App\Support\NumberFormatter::format($avgPct) }}%
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Legend --}}
            <div class="card-footer bg-white small d-flex flex-wrap gap-3 no-print">
                @foreach($performanceScale as $band)
                    <span class="mr-3"><span style="display:inline-block; min-width:34px; padding:2px 8px; border-radius:3px; background:{{ $band['color'] }}; color:#fff;">■</span> {{ \App\Models\Setting::formatPerformanceBandRange($band) }} — {{ $band['label'] }}</span>
                @endforeach
                <span class="mr-3"><span style="background:{{ $referenceBg }}; color:{{ $referenceText }}; padding:2px 8px; border-radius:3px;">■</span> Reference / Breakdown Row</span>
            </div>
        </div>

        {{-- Week Navigation --}}
        <div class="d-flex justify-content-between no-print">
            @if($week > 1)
                <a href="{{ route('weekly.report', ['year' => $year, 'month' => $month, 'week' => $week - 1, 'department_id' => $deptId, 'frequency' => $reportFrequency]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-chevron-left mr-1"></i> W{{ $week - 1 }}
                </a>
            @else
                <span></span>
            @endif

            @if($week < 4)
                <a href="{{ route('weekly.report', ['year' => $year, 'month' => $month, 'week' => $week + 1, 'department_id' => $deptId, 'frequency' => $reportFrequency]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    W{{ $week + 1 }} <i class="fas fa-chevron-right ml-1"></i>
                </a>
            @else
                <span></span>
            @endif
        </div>

    </div>
</section>
@endsection
