@extends('layouts.app')

@section('title', 'Master Performance Report')

@section('content')
<div class="content-header p-2">
    <div class="container-fluid">
        <div class="master-toolbar d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <h5 class="m-0 font-weight-bold text-dark">
                    <span class="report-icon"><i class="fas fa-chart-line"></i></span>
                    Master Performance Report
                    <small class="d-block ml-5 mt-1 text-muted font-weight-normal">Approved official results · July–June fiscal year</small>
                </h5>
            </div>
            <div class="d-flex align-items-center">
                <form action="{{ route('reports.master') }}" method="GET" class="form-inline">
                    <div class="input-group input-group-sm mr-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light border-right-0">Plan Year</span>
                        </div>
                        <select name="year" class="form-control form-control-sm border-left-0 shadow-none" onchange="this.form.submit()">
                            @foreach($years as $y)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($canSelectDepartment)
                        <div class="input-group input-group-sm mr-2">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light border-right-0">Department</span>
                            </div>
                            <select name="department_id" class="form-control form-control-sm border-left-0 shadow-none" onchange="this.form.submit()">
                                <option value="all" {{ $deptId == 'all' ? 'selected' : '' }}>All Departments</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d->id }}" {{ $deptId == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                </form>

                @if($deptId && !empty($gridData))
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('reports.master.excel', ['year' => $year, 'department_id' => $deptId]) }}" class="btn btn-success rounded-pill px-3 shadow-sm mr-2">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </a>
                        <a href="{{ route('reports.master.pdf', ['year' => $year, 'department_id' => $deptId]) }}" class="btn btn-danger rounded-pill px-3 shadow-sm">
                            <i class="fas fa-file-pdf mr-1"></i> Export PDF
                        </a>
                    </div>
                @endif
            </div>
        </div>

        @if($deptId && !empty($gridData))
            <div class="row report-summary mb-3">
                <div class="col-xl-3 col-6 mb-2"><div class="summary-card"><i class="fas fa-bullseye text-primary"></i><span><strong>{{ $summary['objectives'] }}</strong><small>Reported objectives</small></span></div></div>
                <div class="col-xl-3 col-6 mb-2"><div class="summary-card"><i class="fas fa-building text-info"></i><span><strong>{{ $summary['performers'] }}</strong><small>Performing departments</small></span></div></div>
                <div class="col-xl-3 col-6 mb-2"><div class="summary-card"><i class="fas fa-check-double text-success"></i><span><strong>{{ $summary['reported_periods'] }}/{{ $summary['planned_periods'] }}</strong><small>Approved periods</small></span></div></div>
                <div class="col-xl-3 col-6 mb-2"><div class="summary-card"><i class="fas fa-chart-pie text-warning"></i><span><strong>{{ number_format($summary['coverage'], 1) }}%</strong><small>Official reporting coverage</small></span></div></div>
            </div>
            @if($summary['coverage'] < 100)
                <div class="provisional-banner mb-3"><i class="fas fa-info-circle"></i><div><strong>Provisional report</strong><span>Only Head Office-approved results are shown. Reporting coverage is incomplete for the selected plan year.</span></div></div>
            @endif
        @endif

        @if(!$deptId)
            <div class="alert alert-info shadow-sm border-0">
                <i class="fas fa-info-circle mr-2"></i> Please select a department to view the master performance report.
            </div>
        @elseif($deptId && empty($gridData))
            <div class="alert alert-warning shadow-sm border-0">
                <i class="fas fa-exclamation-triangle mr-2"></i> No approved plan data found for <strong>{{ $activeDept->name }}</strong> in <strong>{{ $year }}</strong>.
            </div>
        @elseif($deptId)
            <div class="card card-outline card-primary shadow-sm overflow-hidden">
                <div class="card-header bg-white py-2">
                    <h3 class="card-title font-weight-bold text-dark">{{ $activeDept->name }} · Plan and Performance Matrix · {{ $year }}</h3>
                    <div class="table-legend"><span><i class="target-dot"></i> Target</span><span><i class="actual-dot"></i> Approved actual + score</span><span><i class="empty-dot"></i> N/A / not reported</span></div>
                </div>
                <div class="card-body p-0">
                    <div class="master-scroll-top" aria-label="Horizontal report scrollbar"><div></div></div>
                    <div class="master-table-container">
                        <table class="table table-bordered table-sm m-0 master-report-table">
                            <thead class="bg-dark text-white text-center small uppercase sticky-top">
                                <tr>
                                    <th rowspan="2" class="align-middle sticky-col first-col">S.No</th>
                                    <th rowspan="2" class="align-middle text-left pl-3 sticky-col second-col" style="min-width: 300px;">Strategic Objectives and Activities</th>
                                    <th rowspan="2" class="align-middle" width="50">Weight</th>
                                    <th rowspan="2" class="align-middle" width="50">Unit</th>
                                    <th rowspan="2" class="align-middle" width="70">Baseline</th>
                                    <th rowspan="2" class="align-middle" width="80">Annual Target</th>
                                    @foreach($periodGroups as $group)
                                        @foreach($group['months'] as $m)
                                            <th colspan="2" class="py-1 month-header">{{ strtoupper(substr($m, 0, 3)) }}</th>
                                        @endforeach
                                        <th colspan="2" class="py-1 quarter-header bg-info">{{ strtoupper($group['quarter']) }}</th>
                                    @endforeach
                                    <th rowspan="2" class="align-middle" min-width="150">Performers</th>
                                </tr>
                                <tr class="sub-header bg-light text-dark">
                                    @for($i=0; $i<16; $i++)
                                        <th class="small font-weight-bold p-0 target-subhead" width="45">Tar</th>
                                        <th class="small font-weight-bold p-0 actual-subhead" width="45">Act</th>
                                    @endfor
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($gridData as $row)
                                    <tr class="{{ $row['is_root'] ? 'bg-secondary-light root-row' : ($row['is_child'] ? 'bg-light child-row' : 'leaf-row') }} {{ $row['is_reference'] ? 'italic text-muted' : '' }}">
                                        <td class="text-center font-weight-bold bg-white sticky-col first-col">{{ $row['code'] }}</td>
                                        <td class="pl-3 font-weight-bold text-dark bg-white sticky-col second-col objective-desc">
                                            @if($row['is_child']) <i class="fas fa-level-up-alt fa-rotate-90 text-muted mr-1"></i> @endif
                                            @if($row['is_leaf'] && !$row['is_root']) <span class="ml-3"></span> <i class="far fa-dot-circle text-xs text-muted mr-1"></i> @endif
                                            {{ $row['description'] }}
                                        </td>
                                        <td class="text-center">{{ $row['weight'] ?: '-' }}</td>
                                        <td class="text-center small">{{ $row['unit'] }}</td>
                                        <td class="text-center">{{ $row['baseline'] }}</td>
                                        <td class="text-center font-weight-bold">{{ is_null($row['annual_target']) ? 'N/A' : number_format($row['annual_target'], 1) }}</td>
                                        
                                        @foreach($periodGroups as $group)
                                            @foreach($group['months'] as $m)
                                                @php($cell = $row['months'][$m])
                                                <td class="text-right pr-1 small text-muted border-right-0">
                                                    {{ !$cell['planned'] ? 'N/A' : ($cell['is_rollup'] ? 'Roll-up' : number_format((float)$cell['target'], 2)) }}
                                                    @if($cell['planned'] && !$cell['is_rollup'] && abs((float)$cell['effective_weight'] - (float)$row['weight']) > 0.0001)
                                                        <small class="d-block text-info">Eff. wt {{ number_format((float)$cell['effective_weight'], 4) }}</small>
                                                    @endif
                                                </td>
                                                <td class="text-center px-1 small font-weight-bold {{ $cell['reported'] ? 'approved-actual' : 'text-muted unreported' }}">
                                                    @if(!$cell['planned'])
                                                        <span class="badge badge-secondary">N/A</span>
                                                    @elseif(!$cell['reported'])
                                                        <span class="badge badge-light border">Not Reported</span>
                                                    @else
                                                        <div class="text-primary">{{ $cell['is_rollup'] ? 'Roll-up' : number_format((float)$cell['actual'], 2) }}</div>
                                                        <div class="score-chip" style="background:{{ $cell['level']['hex'] }};color:{{ $cell['level']['textHex'] }}">{{ number_format($cell['scorecard_achievement'], 1) }}%</div>
                                                        @if($cell['raw_achievement'] > 100)<small class="d-block text-info">Raw {{ number_format($cell['raw_achievement'], 1) }}%</small>@endif
                                                        @if(($cell['coverage'] ?? 100) < 100)<small class="d-block text-warning">{{ number_format($cell['coverage'], 0) }}% covered</small>@endif
                                                    @endif
                                                </td>
                                            @endforeach
                                            @php($q = $group['quarter'])
                                            @php($cell = $row['quarters'][$q])
                                            <td class="text-right pr-1 small text-muted border-right-0 bg-info-light">
                                                {{ !$cell['planned'] ? 'N/A' : ($cell['is_rollup'] ? 'Roll-up' : number_format((float)$cell['target'], 2)) }}
                                                @if($cell['planned'] && !$cell['is_rollup'] && abs((float)$cell['effective_weight'] - (float)$row['weight']) > 0.0001)
                                                    <small class="d-block text-info">Eff. wt {{ number_format((float)$cell['effective_weight'], 4) }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center px-1 small font-weight-bold bg-info-light">
                                                @if(!$cell['planned'])<span class="badge badge-secondary">N/A</span>
                                                @elseif(!$cell['reported'])<span class="badge badge-light border">Not Reported</span>
                                                @else
                                                    <div class="text-info">{{ $cell['is_rollup'] ? 'Roll-up' : number_format((float)$cell['actual'], 2) }}</div>
                                                    <div class="score-chip" style="background:{{ $cell['level']['hex'] }};color:{{ $cell['level']['textHex'] }}">{{ number_format($cell['scorecard_achievement'], 1) }}%</div>
                                                    @if($cell['raw_achievement'] > 100)<small class="d-block text-info">Raw {{ number_format($cell['raw_achievement'], 1) }}%</small>@endif
                                                    @if(($cell['coverage'] ?? 100) < 100)<small class="d-block text-warning">{{ number_format($cell['coverage'], 0) }}% covered</small>@endif
                                                @endif
                                            </td>
                                        @endforeach

                                        <td class="small pl-2">{{ $row['performers'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<style>
    .master-toolbar {
        padding: 18px 20px;
        border: 1px solid #e5ebf0;
        border-radius: 15px;
        background: linear-gradient(120deg, #fff 0%, #f6fbfb 100%);
        box-shadow: 0 6px 20px rgba(24, 52, 70, .06);
    }
    .report-icon { display:inline-grid; place-items:center; width:38px; height:38px; margin-right:9px; border-radius:11px; color:#fff; background:linear-gradient(135deg,#123d57,#07988a); }
    .summary-card { display:flex; align-items:center; gap:14px; height:100%; padding:15px 17px; border:1px solid #e6edf2; border-radius:13px; background:#fff; box-shadow:0 4px 14px rgba(24,52,70,.05); }
    .summary-card>i { display:grid; place-items:center; width:38px; font-size:1.15rem; }
    .summary-card span,.summary-card strong,.summary-card small { display:block; }
    .summary-card strong { color:#19364a; font-size:1.25rem; line-height:1.1; }
    .summary-card small { margin-top:3px; color:#758491; font-weight:600; }
    .provisional-banner { display:flex; align-items:center; gap:12px; padding:12px 15px; border:1px solid #f2d487; border-radius:11px; background:#fff9e9; color:#73520a; }
    .provisional-banner>i { font-size:1.2rem; }
    .provisional-banner strong,.provisional-banner span { display:block; }
    .provisional-banner span { font-size:.78rem; opacity:.8; }
    .table-legend { float:right; display:flex; gap:15px; color:#71808d; font-size:.7rem; font-weight:500; }
    .table-legend i { display:inline-block; width:8px; height:8px; margin-right:4px; border-radius:50%; }
    .target-dot { background:#8a98a5; }.actual-dot { background:#007bff; }.empty-dot { border:1px solid #adb5bd; background:#fff; }
    .master-table-container {
        height: min(64vh, 720px);
        min-height: 360px;
        overflow: scroll;
        position: relative;
        scrollbar-gutter: stable;
        overscroll-behavior: contain;
    }

    .master-scroll-top {
        height: 17px;
        overflow-x: auto;
        overflow-y: hidden;
        border-top: 1px solid #e1e7ec;
        background: #f7f9fb;
    }
    .master-scroll-top > div { height: 1px; }

    .master-report-table {
        border-collapse: separate;
        border-spacing: 0;
    }

    .master-report-table th, 
    .master-report-table td {
        border: 1px solid #dee2e6 !important;
        white-space: nowrap;
    }

    /* Sticky Header */
    .master-report-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #343a40;
    }

    .master-report-table thead tr.sub-header th {
        top: 33px;
        z-index: 9;
    }
    .master-report-table thead tr.sub-header .target-subhead {
        color: #243746 !important;
        background: #dfe7ed !important;
        border-color: #c8d3dc !important;
    }
    .master-report-table thead tr.sub-header .actual-subhead {
        color: #fff !important;
        background: #087f77 !important;
        border-color: #087068 !important;
    }
    .master-report-table thead .month-header {
        color: #fff !important;
        background: #274b63 !important;
    }
    .master-report-table thead .quarter-header {
        color: #fff !important;
        background: #0b8b9d !important;
    }

    /* Sticky Columns */
    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 5;
    }

    .first-col {
        left: 0;
        width: 60px;
        min-width: 60px;
    }

    .second-col {
        left: 60px;
        z-index: 4;
    }

    .master-report-table thead th.sticky-col {
        z-index: 11;
    }

    /* Row Styling */
    .root-row td { border-top: 2px solid #6c757d !important; font-size: 0.95rem; }
    .child-row td { background-color: #f8f9fa; }
    .bg-secondary-light { background-color: #e9ecef !important; }
    .bg-info-light { background-color: #e3f2fd !important; }
    .italic { font-style: italic; }
    
    .month-header { min-width: 90px; }
    .quarter-header { min-width: 90px; }
    
    .objective-desc {
        max-width: 400px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .master-report-table tbody tr:hover td {
        background-color: #fff9c4 !important;
    }
    .approved-actual { background:#f3f8ff; }
    .unreported { background:#fafbfc; }
    .score-chip { display:inline-block; min-width:42px; margin-top:2px; padding:1px 4px; border-radius:4px; font-size:.64rem; line-height:1.25; }
    @media(max-width: 991px) {
        .master-toolbar { align-items:flex-start!important; flex-direction:column; gap:15px; }
        .master-toolbar>div:last-child { width:100%; flex-wrap:wrap; }
        .master-toolbar form { width:100%; margin-bottom:10px; }
        .master-toolbar .input-group { flex:1; min-width:210px; }
        .table-legend { display:none; }
        .master-table-container { height: 60vh; min-height: 320px; }
    }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const report = document.querySelector('.master-table-container');
    const topScroll = document.querySelector('.master-scroll-top');
    const table = document.querySelector('.master-report-table');
    if (!report || !topScroll || !table) return;

    const spacer = topScroll.firstElementChild;
    const resizeScroller = () => spacer.style.width = table.scrollWidth + 'px';
    let syncing = false;
    topScroll.addEventListener('scroll', () => {
        if (syncing) return;
        syncing = true; report.scrollLeft = topScroll.scrollLeft; syncing = false;
    });
    report.addEventListener('scroll', () => {
        if (syncing) return;
        syncing = true; topScroll.scrollLeft = report.scrollLeft; syncing = false;
    });
    report.addEventListener('wheel', event => {
        if (event.shiftKey && Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
            event.preventDefault(); report.scrollLeft += event.deltaY;
        }
    }, { passive: false });
    resizeScroller();
    window.addEventListener('resize', resizeScroller);
});
</script>
@endpush
@endsection
