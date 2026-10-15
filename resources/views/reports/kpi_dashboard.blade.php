@extends('layouts.app')

@section('title', 'EDR KPI Performance Board')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">KPI Performance Board</li>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Inter', sans-serif; }
    .kpi-header {
        background: linear-gradient(135deg, #0a2550 0%, #1a4b8c 60%, #c8a84b 100%);
        padding: 24px 30px; border-radius: 12px; color: #fff; margin-bottom: 24px;
    }
    .kpi-header .badge-efy { background: rgba(200,168,75,0.25); color: #f0d080; border: 1px solid rgba(200,168,75,0.5); padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    .nav-pills.kpi-tabs .nav-link { border-radius: 8px; padding: 8px 16px; font-size: 13px; font-weight: 500; color: #495057; margin-right: 6px; margin-bottom: 6px; border: 1px solid #dee2e6; }
    .nav-pills.kpi-tabs .nav-link.active { background: #0a2550; color: #fff; border-color: #0a2550; }
    .kpi-card { border: 0; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); transition: transform 0.15s; }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
    .status-green  { background: linear-gradient(135deg,#28a745,#20c75a); color:#fff; }
    .status-yellow { background: linear-gradient(135deg,#f39c12,#f5a623); color:#fff; }
    .status-red    { background: linear-gradient(135deg,#dc3545,#c0392b); color:#fff; }
    .status-gray   { background: linear-gradient(135deg,#6c757d,#868e96); color:#fff; }
    .gauge-wrapper { position:relative; text-align:center; }
    .gauge-value   { position:absolute; bottom:0; left:50%; transform:translateX(-50%); font-size:20px; font-weight:700; }
    .traffic-light { display:inline-block; width:14px; height:14px; border-radius:50%; margin-right:4px; vertical-align:middle; }
    .tl-green  { background:#28a745; box-shadow:0 0 6px #28a745; }
    .tl-yellow { background:#f39c12; box-shadow:0 0 6px #f39c12; }
    .tl-red    { background:#dc3545; box-shadow:0 0 6px #dc3545; }
    .tl-gray   { background:#adb5bd; }
    .kpi-row td, .kpi-row th { padding: 8px 12px; font-size: 13px; vertical-align:middle; }
    .perf-bar { height:8px; border-radius:4px; background:#e9ecef; }
    .perf-bar-fill { height:8px; border-radius:4px; transition: width .6s ease; }
    .summary-stat { text-align:center; padding:16px; border-radius:12px; background:#f8f9fa; }
    .summary-stat .val { font-size:28px; font-weight:700; line-height:1; }
    .summary-stat .lbl { font-size:11px; color:#6c757d; font-weight:500; margin-top:4px; }
    .trend-up   { color:#28a745; } .trend-down { color:#dc3545; }
    .section-title { font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#6c757d; margin-bottom:12px; padding-bottom:6px; border-bottom:2px solid #e9ecef; }
</style>
@endpush

@section('content')
@php
    // ═══════════════════════════════════════════════════════
    // EDR 2018 EFY — 27 Strategic KPI Master Definition List
    // ═══════════════════════════════════════════════════════
    $kpiMaster = [
        'K1'  => ['name'=>'Train Punctuality/OTP',          'target'=>90,     'unit'=>'%',      'dir'=>'increase','cat'=>'Service Quality','color'=>'info'],
        'K2'  => ['name'=>'On-Time Delivery Rate',           'target'=>85,     'unit'=>'%',      'dir'=>'increase','cat'=>'Service Quality','color'=>'info'],
        'K3'  => ['name'=>'Customer Satisfaction Rate',      'target'=>80,     'unit'=>'%',      'dir'=>'increase','cat'=>'Service Quality','color'=>'info'],
        'K4'  => ['name'=>'Freight Train Load Ratio',        'target'=>98,     'unit'=>'%',      'dir'=>'increase','cat'=>'Productivity','color'=>'primary'],
        'K5'  => ['name'=>'Seat Occupancy Rate',             'target'=>80,     'unit'=>'%',      'dir'=>'increase','cat'=>'Productivity','color'=>'primary'],
        'K6'  => ['name'=>'Freight Ton-Km',                  'target'=>1900.2, 'unit'=>'M Ton-km','dir'=>'increase','cat'=>'Productivity','color'=>'primary'],
        'K7'  => ['name'=>'Cargo Damage/Loss Rate',          'target'=>2,      'unit'=>'%',      'dir'=>'decrease','cat'=>'Productivity','color'=>'primary'],
        'K8'  => ['name'=>'National Cargo Share',            'target'=>16,     'unit'=>'%',      'dir'=>'increase','cat'=>'Productivity','color'=>'primary'],
        'K9'  => ['name'=>'Passenger-Km',                    'target'=>130.3,  'unit'=>'M Pax-km','dir'=>'increase','cat'=>'Productivity','color'=>'primary'],
        'K10' => ['name'=>'Maintenance Time Efficiency',     'target'=>98,     'unit'=>'%',      'dir'=>'increase','cat'=>'Productivity','color'=>'primary'],
        'K11' => ['name'=>'Locomotive Fleet Utilization',    'target'=>85,     'unit'=>'%',      'dir'=>'increase','cat'=>'Productivity','color'=>'primary'],
        'K12' => ['name'=>'On-time Project Completion',      'target'=>70,     'unit'=>'%',      'dir'=>'increase','cat'=>'Productivity','color'=>'primary'],
        'K13' => ['name'=>'Rolling Stock Availability',      'target'=>90,     'unit'=>'%',      'dir'=>'increase','cat'=>'Availability','color'=>'success'],
        'K14' => ['name'=>'Infrastructure Availability',     'target'=>94,     'unit'=>'%',      'dir'=>'increase','cat'=>'Availability','color'=>'success'],
        'K15' => ['name'=>'Track Quality Index',             'target'=>8.6,    'unit'=>'Score',  'dir'=>'increase','cat'=>'Availability','color'=>'success'],
        'K16' => ['name'=>'OCS Quality Excellence Rate',     'target'=>99,     'unit'=>'%',      'dir'=>'increase','cat'=>'Availability','color'=>'success'],
        'K17' => ['name'=>'Revenue Growth Rate',             'target'=>38,     'unit'=>'%',      'dir'=>'increase','cat'=>'Financial','color'=>'warning'],
        'K18' => ['name'=>'Net Profit Margin',               'target'=>18,     'unit'=>'%',      'dir'=>'increase','cat'=>'Financial','color'=>'warning'],
        'K19' => ['name'=>'Operating Ratio',                 'target'=>65,     'unit'=>'%',      'dir'=>'decrease','cat'=>'Financial','color'=>'warning'],
        'K20' => ['name'=>'Project Cost Variance Rate',      'target'=>40,     'unit'=>'%',      'dir'=>'decrease','cat'=>'Financial','color'=>'warning'],
        'K21' => ['name'=>'Procurement Cycle Time Rate',     'target'=>80,     'unit'=>'%',      'dir'=>'increase','cat'=>'Financial','color'=>'warning'],
        'K22' => ['name'=>'System Automation Rate',          'target'=>40,     'unit'=>'%',      'dir'=>'increase','cat'=>'Technology','color'=>'secondary'],
        'K23' => ['name'=>'Real-Time Cargo Tracking Rate',   'target'=>80,     'unit'=>'%',      'dir'=>'increase','cat'=>'Technology','color'=>'secondary'],
        'K24' => ['name'=>'Safety Indicators Success Rate',  'target'=>80,     'unit'=>'%',      'dir'=>'increase','cat'=>'Safety','color'=>'danger'],
        'K25' => ['name'=>'Security Incident Rate',          'target'=>93.6,   'unit'=>'% red.', 'dir'=>'increase','cat'=>'Safety','color'=>'danger'],
        'K26' => ['name'=>'Employee Retention Rate',         'target'=>97,     'unit'=>'%',      'dir'=>'increase','cat'=>'HR','color'=>'teal'],
        'K27' => ['name'=>'Employee Productivity Rate',      'target'=>89,     'unit'=>'%',      'dir'=>'increase','cat'=>'HR','color'=>'teal'],
    ];

    // Merge DB data (actual values from tagged objectives) with KPI master
    $kpiData = [];
    foreach ($kpiMaster as $code => $def) {
        $actual = null;
        $dbTarget = null;
        if (isset($kpiGroups[$code])) {
            $taggedObjective = $kpiGroups[$code]->first();
            $def['name'] = $taggedObjective->description ?: $def['name'];
            $def['unit'] = $taggedObjective->unit ?: $def['unit'];
            $def['dir'] = $taggedObjective->goal_direction ?: $def['dir'];
            $totalActual = 0; $totalTarget = 0; $actualCount = 0; $targetCount = 0;
            
            foreach ($kpiGroups[$code] as $obj) {
                foreach ($obj->planItems as $item) {
                    // Resolve the selected period only. Do not silently compare
                    // quarterly actuals with annual or hard-coded targets.
                    $periodTarget = $item->getPlannedTargetForPeriod($period);
                    if (!is_null($periodTarget)) {
                        $totalTarget += $periodTarget;
                        $targetCount++;
                    }
                    
                    $actualVal = $item->getActualValueForPeriod($period);
                    
                    // Only count this item's performance if it has been reported
                    $hasPerf = $item->hasPerformanceForPeriod($period);

                    if ($hasPerf) { 
                        $totalActual += $actualVal; 
                        $actualCount++; 
                    }
                }
            }
            if ($targetCount > 0) $dbTarget = round($totalTarget / $targetCount, 2);
            if ($actualCount > 0) $actual = round($totalActual / $actualCount, 2);
        }

        // Use database target if available, otherwise fallback to master definition
        $effectiveTarget = $dbTarget ?? $def['target'];

        // Achievement calculation
        $achievement = null;
        $status = 'gray';
        $performanceStatus = 'Not Reported';
        if ($actual !== null) {
            $achievement = round(app(\App\Services\EdrPerformanceCalculator::class)->rawAchievement((float)$actual, (float)$effectiveTarget, $def['dir']), 1);
            $performanceStatus = app(\App\Services\EdrPerformanceCalculator::class)->performanceStatus($achievement);
            $status = $achievement >= 100 ? 'green' : ($achievement >= 80 ? 'yellow' : 'red');
        }

        $kpiData[$code] = array_merge($def, [
            'target' => $effectiveTarget,
            'actual' => $actual,
            'achievement' => $achievement,
            'scorecard_achievement' => is_null($achievement) ? null : min(100, $achievement),
            'performance_status' => $performanceStatus,
            'status' => $status,
        ]);
    }

    // Summary stats
    $totalKPIs   = count($kpiData);
    $withData    = collect($kpiData)->where('status', '!=', 'gray')->count();
    $greenCount  = collect($kpiData)->where('status', 'green')->count();
    $yellowCount = collect($kpiData)->where('status', 'yellow')->count();
    $redCount    = collect($kpiData)->where('status', 'red')->count();
    $onTrackPct  = $withData > 0 ? round(($greenCount / $withData) * 100) : 0;

    $catGroups = collect($kpiData)->groupBy('cat');

    // Chart.js data
    $chartLabels  = array_keys($kpiData);
    $chartTargets = collect($kpiData)->pluck('target')->toArray();
    $chartActuals = collect($kpiData)->map(fn($k) => $k['actual'] ?? null)->values()->toArray();
    $chartColors  = collect($kpiData)->map(function($k) {
        return $k['status'] === 'green' ? '#28a745' : ($k['status'] === 'yellow' ? '#f39c12' : ($k['status'] === 'red' ? '#dc3545' : '#adb5bd'));
    })->values()->toArray();

    // Category achievement for radar
    $catNames       = $catGroups->keys()->toArray();
    $catAchievement = $catGroups->map(function($group) {
        $scored = $group->whereNotNull('achievement');
        return $scored->count() > 0 ? round($scored->avg(fn($k) => min(100, $k['achievement'])), 1) : 0;
    })->values()->toArray();
@endphp

{{-- ═══ HEADER ═══ --}}
<div class="kpi-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <div class="d-flex align-items-center mb-1">
            <span class="badge-efy mr-3">2018 EFY Annual Plan</span>
            <span class="badge-efy">Reporting Period: <strong>{{ strtoupper($period) }} {{ $year }}</strong></span>
        </div>
        <h3 class="mb-0 font-weight-bold" style="font-size:22px;">
            <i class="fas fa-chart-network mr-2" style="color:#c8a84b;"></i>
            EDR Strategic KPI Performance Board
        </h3>
        <p class="mb-0 mt-1" style="opacity:.8;font-size:13px;">Ethio-Djibouti Railways S.C. — 756 km Track | 27 Strategic KPIs</p>
    </div>
    <div class="d-flex align-items-center mt-2 mt-md-0">
        <form action="{{ route('reports.kpi-dashboard') }}" method="GET" class="d-flex align-items-center mr-3">
            <select name="year" class="form-control form-control-sm mr-2" onchange="this.form.submit()" style="width:80px;">
                @for($y = 2018; $y <= 2027; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <select name="period" class="form-control form-control-sm" onchange="this.form.submit()" style="width:80px;">
                @foreach(['q1','q2','q3','q4','annual'] as $p)
                    <option value="{{ $p }}" {{ $period == $p ? 'selected' : '' }}>{{ strtoupper($p) }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light">
            <i class="fas fa-file-alt mr-1"></i> EDR Reports
        </a>
    </div>
</div>

{{-- ═══ SUMMARY STATS ROW ═══ --}}
<div class="row mb-4">
    <div class="col-6 col-md-3 mb-3">
        <div class="summary-stat">
            <div class="val text-success">{{ $greenCount }}</div>
            <div class="lbl"><span class="traffic-light tl-green"></span> ON TARGET</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="summary-stat">
            <div class="val text-warning">{{ $yellowCount }}</div>
            <div class="lbl"><span class="traffic-light tl-yellow"></span> AT RISK</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="summary-stat">
            <div class="val text-danger">{{ $redCount }}</div>
            <div class="lbl"><span class="traffic-light tl-red"></span> MISSED</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="summary-stat" style="background:linear-gradient(135deg,#0a2550,#1a4b8c);color:#fff;">
            <div class="val" style="color:#f0d080;">{{ $onTrackPct }}%</div>
            <div class="lbl" style="color:rgba(255,255,255,.7);">KPIs ON TRACK</div>
        </div>
    </div>
</div>

{{-- ═══ TABS ═══ --}}
<ul class="nav nav-pills kpi-tabs mb-3 flex-wrap" id="kpiTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#tab-executive"><i class="fas fa-tachometer-alt mr-1"></i>Executive Summary</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab-operational"><i class="fas fa-train mr-1"></i>Operational</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab-asset"><i class="fas fa-tools mr-1"></i>Asset Mgmt</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab-safety"><i class="fas fa-shield-alt mr-1"></i>Safety & Security</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab-financial"><i class="fas fa-coins mr-1"></i>Financial</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab-institutional"><i class="fas fa-users-cog mr-1"></i>Institutional</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab-details"><i class="fas fa-table mr-1"></i>All KPIs</a></li>
</ul>

<div class="tab-content" id="kpiTabContent">

    {{-- ═══════════════════════════════ TAB 1: EXECUTIVE ═══════════════════════════════ --}}
    <div class="tab-pane fade show active" id="tab-executive">
        <div class="row">
            {{-- Category Overview Radar --}}
            <div class="col-md-5 mb-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="section-title">Performance by Category</div>
                        <canvas id="radarChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            {{-- Overall KPI Bar Overview --}}
            <div class="col-md-7 mb-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="section-title">All 27 KPIs — Achievement %</div>
                        <canvas id="overviewBar" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Revenue Thermometer --}}
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="section-title">Revenue vs Target (14.09B Birr)</div>
                        @php
                            $revenueKpi = $kpiData['K17'];
                            $revPct = $revenueKpi['actual'] !== null ? min(100, $revenueKpi['actual']) : 0;
                        @endphp
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-weight-bold">Revenue Growth</span>
                            <span class="badge badge-{{ $revenueKpi['status'] === 'green' ? 'success' : ($revenueKpi['status'] === 'yellow' ? 'warning' : 'danger') }}" style="font-size:14px;">
                                {{ $revenueKpi['actual'] !== null ? \App\Support\NumberFormatter::format($revenueKpi['actual']).'%' : 'No Data' }}
                            </span>
                        </div>
                        <div class="perf-bar mb-1">
                            <div class="perf-bar-fill {{ $revenueKpi['status'] === 'green' ? 'bg-success' : ($revenueKpi['status'] === 'yellow' ? 'bg-warning' : 'bg-danger') }}" style="width:{{ $revPct }}%"></div>
                        </div>
                        <small class="text-muted">Target: 38% growth | Baseline: 7.894B → Target: 14.09B Birr</small>

                        <hr>
                        <canvas id="revenueDonut" height="180"></canvas>
                    </div>
                </div>
            </div>

            {{-- Traffic Light Grid --}}
            <div class="col-md-6 mb-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="section-title">Traffic Light Status — All 27 KPIs</div>
                        <div class="row no-gutters">
                            @foreach($kpiData as $code => $kpi)
                                @php
                                    $tlClass = $kpi['status'] === 'green' ? 'tl-green' : ($kpi['status'] === 'yellow' ? 'tl-yellow' : ($kpi['status'] === 'red' ? 'tl-red' : 'tl-gray'));
                                @endphp
                                <div class="col-4 p-1">
                                    <div style="background:#f8f9fa; border-radius:8px; padding:8px 6px; font-size:11px; line-height:1.3;">
                                        <span class="traffic-light {{ $tlClass }}"></span>
                                        <strong>{{ $code }}</strong><br>
                                        <span class="text-muted" style="font-size:10px;">{{ $kpi['actual'] !== null ? $kpi['actual'].$kpi['unit'] : '—' }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ TAB 2: OPERATIONAL ═══════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-operational">
        <div class="row">
            @foreach(['K1','K2','K3','K4','K5','K6','K7','K8','K9','K10','K11','K12'] as $code)
            @if(isset($kpiData[$code]))
            @php $k = $kpiData[$code]; $pct = $k['achievement'] ?? 0; $pctCapped = min(100,$pct); @endphp
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge badge-secondary" style="font-size:10px;">{{ $code }}</span>
                                <p class="font-weight-bold mb-0 mt-1" style="font-size:13px;">{{ $k['name'] }}</p>
                            </div>
                            <span class="traffic-light {{ $k['status']==='green'?'tl-green':($k['status']==='yellow'?'tl-yellow':($k['status']==='red'?'tl-red':'tl-gray')) }}" style="width:18px;height:18px;flex-shrink:0;margin-left:8px;"></span>
                        </div>
                        <div class="d-flex justify-content-between text-muted" style="font-size:11px;">
                            <span>Actual: <strong class="text-dark">{{ $k['actual']!==null ? \App\Support\NumberFormatter::format($k['actual']).' '.$k['unit'] : '—' }}</strong></span>
                            <span>Target: <strong class="text-dark">{{ \App\Support\NumberFormatter::format($k['target']).' '.$k['unit'] }}</strong></span>
                        </div>
                        <div class="perf-bar mt-2">
                            <div class="perf-bar-fill {{ $k['status']==='green'?'bg-success':($k['status']==='yellow'?'bg-warning':'bg-danger') }}" style="width:{{ $pctCapped }}%"></div>
                        </div>
                        <small class="text-muted">Achievement: {{ $pct !== null ? $pct.'%' : 'No Data' }}</small>
                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="section-title">Operational KPIs — Actual vs Target</div>
                        <canvas id="operationalBar" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ TAB 3: ASSET MGMT ═══════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-asset">
        <div class="row">
            @foreach(['K13','K14','K15','K16'] as $code)
            @if(isset($kpiData[$code]))
            @php $k = $kpiData[$code]; $pct = $k['achievement'] ?? 0; $pctCapped = min(100,$pct); @endphp
            <div class="col-md-3 mb-4">
                <div class="card kpi-card text-center" style="border-top:4px solid #28a745;">
                    <div class="card-body">
                        <span class="badge badge-success mb-2">{{ $code }}</span>
                        <div style="font-size:28px;font-weight:700;color:{{ $k['status']==='green'?'#28a745':($k['status']==='yellow'?'#f39c12':'#dc3545') }}">
                            {{ $k['actual']!==null ? \App\Support\NumberFormatter::format($k['actual']) : '—' }}
                        </div>
                        <div class="text-muted" style="font-size:11px;">{{ $k['unit'] }}</div>
                        <div class="text-dark font-weight-bold mt-1" style="font-size:12px;">{{ $k['name'] }}</div>
                        <div class="perf-bar mt-2">
                            <div class="perf-bar-fill {{ $k['status']==='green'?'bg-success':($k['status']==='yellow'?'bg-warning':'bg-danger') }}" style="width:{{ $pctCapped }}%"></div>
                        </div>
                        <small class="text-muted">Target: {{ \App\Support\NumberFormatter::format($k['target']) }} | Achiev: {{ $pct !== null ? $pct.'%' : 'N/A' }}</small>
                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
        <div class="card kpi-card">
            <div class="card-body">
                <div class="section-title">Asset KPIs — Target vs Actual</div>
                <canvas id="assetBar" height="100"></canvas>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ TAB 4: SAFETY ═══════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-safety">
        <div class="row">
            @foreach(['K24','K25'] as $code)
            @if(isset($kpiData[$code]))
            @php $k = $kpiData[$code]; $pct = $k['achievement'] ?? 0; $pctCapped = min(100,$pct); @endphp
            <div class="col-md-6 mb-4">
                <div class="card kpi-card" style="border-left:5px solid #dc3545;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge badge-danger">{{ $code }}</span>
                                <h5 class="mt-1 mb-0">{{ $k['name'] }}</h5>
                                <p class="text-muted mb-2" style="font-size:12px;">Target: {{ $k['target'].$k['unit'] }}</p>
                            </div>
                            <div style="font-size:36px;font-weight:800;color:{{ $k['status']==='green'?'#28a745':($k['status']==='yellow'?'#f39c12':'#dc3545') }}">
                                {{ $k['actual']!==null ? \App\Support\NumberFormatter::format($k['actual']) : '—' }}
                            </div>
                        </div>
                        <div class="perf-bar"><div class="perf-bar-fill bg-danger" style="width:{{ $pctCapped }}%"></div></div>
                        <small class="text-muted">Achievement: {{ $pct !== null ? $pct.'%' : 'No Data' }}</small>
                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
        <div class="card kpi-card">
            <div class="card-body">
                <div class="section-title">Safety &amp; Security Operational Targets (2018 EFY)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover kpi-row">
                        <thead class="thead-light"><tr><th>Objective</th><th>KPI</th><th class="text-right">Target</th></tr></thead>
                        <tbody>
                            <tr><td>Obj 7: Safety</td><td>Railway accident/incident reduction</td><td class="text-right text-success font-weight-bold">50%</td></tr>
                            <tr><td>Obj 7: Safety</td><td>SMS Implementation</td><td class="text-right text-success font-weight-bold">100%</td></tr>
                            <tr><td>Obj 7: Safety</td><td>Off-road collision reduction</td><td class="text-right">20%</td></tr>
                            <tr><td>Obj 7: Safety</td><td>Collision accident reduction</td><td class="text-right">30%</td></tr>
                            <tr><td>Obj 7: Safety</td><td>Carbon emission reduction</td><td class="text-right">99.2K tons</td></tr>
                            <tr><td>Obj 8: Security</td><td>Security incident reduction</td><td class="text-right text-danger font-weight-bold">60%</td></tr>
                            <tr><td>Obj 8: Security</td><td>Theft/vandalism/attack reduction</td><td class="text-right">95%</td></tr>
                            <tr><td>Obj 8: Security</td><td>Community awareness reached</td><td class="text-right">116,000 people</td></tr>
                            <tr><td>Obj 8: Security</td><td>Train blocking elimination</td><td class="text-right font-weight-bold">100%</td></tr>
                            <tr><td>Obj 8: Security</td><td>Job opportunities created</td><td class="text-right">600</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ TAB 5: FINANCIAL ═══════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-financial">
        <div class="row">
            @foreach(['K17','K18','K19','K20','K21'] as $code)
            @if(isset($kpiData[$code]))
            @php $k = $kpiData[$code]; $pct = $k['achievement'] ?? 0; $pctCapped = min(100,$pct); @endphp
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card kpi-card" style="border-top:4px solid #f39c12;">
                    <div class="card-body text-center">
                        <span class="badge badge-warning">{{ $code }}</span>
                        <div style="font-size:30px;font-weight:700;margin-top:8px;color:{{ $k['status']==='green'?'#28a745':($k['status']==='yellow'?'#e67e22':'#dc3545') }}">
                            {{ $k['actual']!==null ? \App\Support\NumberFormatter::format($k['actual']).' '.$k['unit'] : '—' }}
                        </div>
                        <p class="font-weight-bold" style="font-size:12px;">{{ $k['name'] }}</p>
                        <div class="perf-bar"><div class="perf-bar-fill bg-warning" style="width:{{ $pctCapped }}%"></div></div>
                        <small class="text-muted">Target: {{ $k['target'].$k['unit'] }} | {{ $pct !== null ? $pct.'%' : 'No Data' }}</small>
                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="section-title">Revenue Breakdown — Target (14.09B Birr)</div>
                        <canvas id="revBreakdown" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="section-title">Profitability Targets (Obj 16)</div>
                        <table class="table table-sm kpi-row">
                            <thead class="thead-light"><tr><th>Metric</th><th class="text-right">Target</th><th class="text-right">Formula</th></tr></thead>
                            <tbody>
                                <tr><td>Total Revenue</td><td class="text-right font-weight-bold text-success">14.09B Birr</td><td class="text-right text-muted">—</td></tr>
                                <tr><td>Freight Revenue</td><td class="text-right text-success">11B Birr</td><td class="text-right text-muted">78%</td></tr>
                                <tr><td>Passenger Revenue</td><td class="text-right">407M Birr</td><td class="text-right text-muted">2.9%</td></tr>
                                <tr><td>Logistics Revenue</td><td class="text-right">2.68B Birr</td><td class="text-right text-muted">19%</td></tr>
                                <tr><td>Other Revenue</td><td class="text-right">164M Birr</td><td class="text-right text-muted">1.2%</td></tr>
                                <tr class="table-warning"><td>Net Profit Margin</td><td class="text-right font-weight-bold">18%</td><td class="text-right text-muted">(Rev−Exp)/Rev</td></tr>
                                <tr class="table-warning"><td>Operating Ratio</td><td class="text-right font-weight-bold">65%</td><td class="text-right text-muted">Exp/Rev</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ TAB 6: INSTITUTIONAL ═══════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-institutional">
        <div class="row">
            @foreach(['K22','K23','K26','K27'] as $code)
            @if(isset($kpiData[$code]))
            @php $k = $kpiData[$code]; $pct = $k['achievement'] ?? 0; $pctCapped = min(100,$pct); @endphp
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card kpi-card text-center" style="border-top:4px solid #6f42c1;">
                    <div class="card-body">
                        <span class="badge" style="background:#6f42c1;color:#fff;">{{ $code }}</span>
                        <div style="font-size:26px;font-weight:700;margin:8px 0;color:#6f42c1;">
                            {{ $k['actual']!==null ? \App\Support\NumberFormatter::format($k['actual']) : '—' }}
                        </div>
                        <p style="font-size:11px;" class="font-weight-bold mb-1">{{ $k['name'] }}</p>
                        <div class="perf-bar"><div class="perf-bar-fill" style="background:#6f42c1;width:{{ $pctCapped }}%"></div></div>
                        <small class="text-muted">Target {{ $k['target'].$k['unit'] }}</small>
                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
        <div class="card kpi-card">
            <div class="card-body">
                <div class="section-title">HR, Procurement &amp; Digitalization Targets (Obj 11–14)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover kpi-row">
                        <thead class="thead-light"><tr><th>Objective</th><th>KPI</th><th class="text-right">Target</th></tr></thead>
                        <tbody>
                            <tr><td rowspan="4">Obj 11: HR</td><td>Employee retention</td><td class="text-right font-weight-bold">95%</td></tr>
                            <tr><td>Recruitment lead time</td><td class="text-right">35 days</td></tr>
                            <tr><td>Employees trained</td><td class="text-right">1,274</td></tr>
                            <tr><td>Avg training hours/employee</td><td class="text-right">15 hrs</td></tr>
                            <tr><td rowspan="3">Obj 13: Procurement</td><td>Purchase requests on time</td><td class="text-right font-weight-bold">70%</td></tr>
                            <tr><td>Rolling stock purchased</td><td class="text-right">522 units</td></tr>
                            <tr><td>Freight trucks purchased</td><td class="text-right">200</td></tr>
                            <tr><td rowspan="4">Obj 14: Digital</td><td>Digital transformation strategy</td><td class="text-right font-weight-bold">100%</td></tr>
                            <tr><td>ERP implementation</td><td class="text-right font-weight-bold">100%</td></tr>
                            <tr><td>Integrated TMS</td><td class="text-right">50%</td></tr>
                            <tr><td>Digital security mgmt</td><td class="text-right">40%</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ TAB 7: ALL KPIs TABLE ═══════════════════════════════ --}}
    <div class="tab-pane fade" id="tab-details">
        <div class="card kpi-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead style="background:#0a2550;color:#fff;font-size:12px;">
                            <tr>
                                <th>Code</th><th>Category</th><th>KPI Name</th><th>Direction</th>
                                <th class="text-right">Target</th><th class="text-right">Actual</th>
                                <th class="text-right">Achievement</th><th class="text-center">Status</th><th style="min-width:120px;">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kpiData as $code => $k)
                            @php
                                $pct = $k['achievement'] ?? 0;
                                $pctCapped = min(100, $pct);
                                $tlCls = $k['status']==='green'?'tl-green':($k['status']==='yellow'?'tl-yellow':($k['status']==='red'?'tl-red':'tl-gray'));
                                $barCls = $k['status']==='green'?'bg-success':($k['status']==='yellow'?'bg-warning':'bg-danger');
                                $dirIcon = $k['dir']==='decrease' ? 'fa-arrow-down text-danger' : 'fa-arrow-up text-success';
                            @endphp
                            <tr class="kpi-row">
                                <td><strong>{{ $code }}</strong></td>
                                <td><span class="badge badge-light border text-muted">{{ $k['cat'] }}</span></td>
                                <td style="font-size:12px;">{{ $k['name'] }}</td>
                                <td><i class="fas {{ $dirIcon }}"></i></td>
                                <td class="text-right">{{ \App\Support\NumberFormatter::format($k['target']) }} <small class="text-muted">{{ $k['unit'] }}</small></td>
                                <td class="text-right font-weight-bold">{{ $k['actual'] !== null ? \App\Support\NumberFormatter::format($k['actual']) : '—' }}</td>
                                <td class="text-right">{{ $k['achievement'] !== null ? $k['achievement'].'%' : '—' }}@if($k['achievement'] !== null && $k['achievement'] > 100)<small class="d-block text-muted">Score: 100%</small>@endif</td>
                                <td class="text-center"><span class="traffic-light {{ $tlCls }}" style="width:16px;height:16px;"></span><small class="d-block mt-1">{{ $k['performance_status'] }}</small></td>
                                <td>
                                    <div class="perf-bar">
                                        <div class="perf-bar-fill {{ $barCls }}" style="width:{{ $pctCapped }}%"></div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light text-muted" style="font-size:12px;">
                <i class="fas fa-info-circle mr-1"></i>
                Data is pulled from objectives tagged with KPI codes (K1–K27). Tag objectives at
                <a href="{{ route('objectives.index') }}">Objective Management</a>.
                <span class="float-right"><strong>{{ $withData }}</strong> of <strong>{{ $totalKPIs }}</strong> KPIs have data for {{ strtoupper($period) }} {{ $year }}.</span>
            </div>
        </div>
    </div>

</div>{{-- end tab-content --}}
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
const KPILABELS  = @json($chartLabels);
const KPITARGETS = @json($chartTargets);
const KPIACTUALS = @json($chartActuals);
const KPIACHIEVEMENTS = @json(collect($kpiData)->pluck('achievement')->values());
const KPICOLORS  = @json($chartColors);
const CATNAMES   = @json($catNames);
const CATACHIVEMENT = @json($catAchievement);

// ── 1. Overview Horizontal Bar
new Chart(document.getElementById('overviewBar'), {
    type: 'bar',
    data: {
        labels: KPILABELS,
        datasets: [{
            label: 'Achievement %',
            data: KPIACHIEVEMENTS.map(value => value === null ? null : Math.min(100, value)),
            backgroundColor: KPICOLORS,
            borderRadius: 4,
        }]
    },
    options: {
        indexAxis: 'y',
        plugins: { legend: { display:false } },
        scales: {
            x: { min:0, max:150, ticks:{ callback: v => v+'%' } },
            y: { ticks:{ font:{ size:11 } } }
        }
    }
});

// ── 2. Category Radar
new Chart(document.getElementById('radarChart'), {
    type: 'radar',
    data: {
        labels: CATNAMES,
        datasets: [{
            label: 'Achievement %',
            data: CATACHIVEMENT,
            backgroundColor: 'rgba(10,37,80,.15)',
            borderColor: '#0a2550',
            pointBackgroundColor: '#c8a84b',
            pointRadius: 5
        }]
    },
    options: {
        plugins: { legend:{ display:false } },
        scales: { r: { min:0, max:120, ticks:{ callback: v=>v+'%' } } }
    }
});

// ── 3. Revenue Donut
new Chart(document.getElementById('revenueDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Freight (11B)', 'Logistics (2.68B)', 'Passenger (407M)', 'Other (164M)'],
        datasets: [{ data:[11000,2680,407,164], backgroundColor:['#0a2550','#c8a84b','#28a745','#6c757d'], borderWidth:2 }]
    },
    options: { plugins:{ legend:{ position:'right', labels:{ font:{ size:11 } } } } }
});

// ── 4. Operational Bar (K1-K12)
const opCodes   = ['K1','K2','K3','K4','K5','K6','K7','K8','K9','K10','K11','K12'];
const opTargets = opCodes.map(c => {
    const idx = KPILABELS.indexOf(c);
    return idx >= 0 ? KPITARGETS[idx] : null;
});
const opActuals = opCodes.map(c => {
    const idx = KPILABELS.indexOf(c);
    return idx>=0 ? KPIACTUALS[idx] : null;
});
new Chart(document.getElementById('operationalBar'), {
    type: 'bar',
    data: {
        labels: opCodes,
        datasets: [
            { label:'Target', data: opTargets, backgroundColor:'rgba(10,37,80,0.2)', borderColor:'#0a2550', borderWidth:1, borderRadius:4 },
            { label:'Actual', data: opActuals, backgroundColor:'rgba(200,168,75,0.7)', borderRadius:4 }
        ]
    },
    options: { plugins:{ legend:{ position:'top' } }, scales:{ y:{ beginAtZero:true } } }
});

// ── 5. Asset Bar (K13-K16)
const assetCodes   = ['K13','K14','K15','K16'];
const assetTargets = [90,94,8.6,99];
const assetActuals = assetCodes.map(c => { const i=KPILABELS.indexOf(c); return i>=0?KPIACTUALS[i]:null; });
new Chart(document.getElementById('assetBar'), {
    type: 'bar',
    data: {
        labels: ['Rolling Stock Avail.','Infrastructure Avail.','Track Quality Index','OCS Excellence'],
        datasets: [
            { label:'Target', data:assetTargets, backgroundColor:'rgba(40,167,69,.2)', borderColor:'#28a745', borderWidth:1, borderRadius:4 },
            { label:'Actual', data:assetActuals, backgroundColor:'rgba(40,167,69,.65)', borderRadius:4 }
        ]
    },
    options: { plugins:{ legend:{ position:'top' } }, scales:{ y:{ beginAtZero:true } } }
});

// ── 6. Revenue Breakdown
new Chart(document.getElementById('revBreakdown'), {
    type: 'pie',
    data: {
        labels: ['Freight (11B Birr)','Logistics (2.68B)','Passenger (407M)','Other (164M)'],
        datasets: [{ data:[11000,2680,407,164], backgroundColor:['#0a2550','#c8a84b','#2ecc71','#95a5a6'], borderWidth:2 }]
    },
    options: { plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } } }
});

// ── Tab state persistence
const savedTab = localStorage.getItem('kpiActiveTab');
if (savedTab) {
    document.querySelector('#kpiTabs [href="' + savedTab + '"]')?.click();
}
document.querySelectorAll('#kpiTabs .nav-link').forEach(el => {
    el.addEventListener('shown.bs.tab', e => localStorage.setItem('kpiActiveTab', e.target.getAttribute('href')));
});
document.querySelectorAll('#kpiTabs .nav-link').forEach(el => {
    el.addEventListener('show.bs.tab', function() {
        // trigger fix for chart inside hidden tabs
        setTimeout(() => window.dispatchEvent(new Event('resize')), 50);
    });
});
</script>
@endpush
