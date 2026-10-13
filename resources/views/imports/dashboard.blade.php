@extends('layouts.app')
@section('title', 'Import Control Tower')

@section('content')
@php
use App\Models\Import;
$stages = Import::STAGE_ORDER;
$stageLabels = Import::STAGE_LABELS;
$stageIcons = Import::STAGE_ICONS;

$statusColors = [
    'DOCUMENT_SUBMITTED'   => ['bg'=>'#17a2b8','light'=>'rgba(23,162,184,0.12)'],
    'ECD_VALIDATED'        => ['bg'=>'#6610f2','light'=>'rgba(102,16,242,0.12)'],
    'RELEASE_DOC_RECEIVED' => ['bg'=>'#007bff','light'=>'rgba(0,123,255,0.12)'],
    'WAGON_ASSIGNED'       => ['bg'=>'#fd7e14','light'=>'rgba(253,126,20,0.12)'],
    'AT_RAILWAY_YARD'      => ['bg'=>'#6f42c1','light'=>'rgba(111,66,193,0.12)'],
    'TRANSIT_DOC_READY'    => ['bg'=>'#20c997','light'=>'rgba(32,201,151,0.12)'],
    'OPERATION_APPROVED'   => ['bg'=>'#28a745','light'=>'rgba(40,167,69,0.12)'],
    'LOADED_ON_TRAIN'      => ['bg'=>'#343a40','light'=>'rgba(52,58,64,0.12)'],
    'DISPATCHED'           => ['bg'=>'#28a745','light'=>'rgba(40,167,69,0.12)'],
];
@endphp

<style>
.control-tower-header{background:linear-gradient(135deg,#1a237e 0%,#283593 50%,#1565c0 100%);border-radius:12px;padding:28px 32px;color:#fff;margin-bottom:24px;}
.kpi-card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.07);border:none;transition:transform .2s,box-shadow .2s;}
.kpi-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.1);}
.kpi-icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;}
.pipeline-wrap{background:#fff;border-radius:10px;padding:24px 32px;box-shadow:0 2px 12px rgba(0,0,0,.07);margin-bottom:24px;overflow-x:auto;}
.pipeline{display:flex;align-items:flex-start;min-width:700px;}
.pipe-stage{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;}
.pipe-stage:not(:last-child)::after{content:'';position:absolute;top:22px;left:55%;right:-5%;height:2px;background:#e9ecef;z-index:0;}
.pipe-bubble{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;position:relative;z-index:1;margin-bottom:8px;box-shadow:0 3px 8px rgba(0,0,0,.15);}
.pipe-label{font-size:10px;font-weight:600;text-align:center;text-transform:uppercase;letter-spacing:.4px;color:#6c757d;line-height:1.3;max-width:70px;}
.pipe-count{font-size:10px;font-weight:700;margin-top:4px;}
.alert-card{border-radius:8px;border-left:4px solid #dc3545;background:#fff8f8;padding:14px 16px;margin-bottom:10px;}
.job-row-status{display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;}
</style>

{{-- ── Header ─────────────────────────────────────────────── --}}
<div class="control-tower-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 font-weight-bold"><i class="fas fa-satellite-dish mr-2"></i>Import Control Tower</h4>
            <p class="mb-0 opacity-75 small">Real-time view of all active import jobs — EDR Logistics</p>
        </div>
        <a href="{{ route('imports.create') }}" class="btn btn-light font-weight-bold shadow-sm">
            <i class="fas fa-plus mr-1"></i> New Import Job
        </a>
    </div>
</div>

{{-- ── KPI Cards ───────────────────────────────────────────── --}}
<div class="row mb-4">
    @foreach([
        ['icon'=>'fa-boxes','color'=>'#007bff','bg'=>'rgba(0,123,255,.1)','label'=>'Total Active','val'=>$kpi['active']],
        ['icon'=>'fa-stamp','color'=>'#17a2b8','bg'=>'rgba(23,162,184,.1)','label'=>'Waiting ECD','val'=>$kpi['waiting_ecd']],
        ['icon'=>'fa-file-signature','color'=>'#6610f2','bg'=>'rgba(102,16,242,.1)','label'=>'Waiting Release Doc','val'=>$kpi['waiting_release']],
        ['icon'=>'fa-pallet','color'=>'#fd7e14','bg'=>'rgba(253,126,20,.1)','label'=>'Waiting Wagon','val'=>$kpi['waiting_wagon']],
        ['icon'=>'fa-flag-checkered','color'=>'#28a745','bg'=>'rgba(40,167,69,.1)','label'=>'Dispatched','val'=>$kpi['dispatched']],
    ] as $c)
    <div class="col-md col-6 mb-3">
        <div class="kpi-card d-flex align-items-center">
            <div class="kpi-icon mr-3" style="background:{{ $c['bg'] }}">
                <i class="fas {{ $c['icon'] }}" style="color:{{ $c['color'] }}"></i>
            </div>
            <div>
                <div class="h3 font-weight-bold mb-0">{{ $c['val'] }}</div>
                <div class="small text-muted">{{ $c['label'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── Pipeline Visualization ──────────────────────────────── --}}
<div class="pipeline-wrap mb-4">
    <h6 class="font-weight-bold text-muted text-uppercase small mb-3"><i class="fas fa-stream mr-1"></i> Workflow Pipeline</h6>
    <div class="pipeline">
        @foreach($stages as $status => $num)
        @php
            $count = $stats[$status] ?? 0;
            $color = $statusColors[$status]['bg'] ?? '#6c757d';
        @endphp
        <div class="pipe-stage">
            <div class="pipe-bubble" style="background:{{ $color }}">
                {{ $count > 0 ? $count : '—' }}
            </div>
            <div class="pipe-label">{{ $stageLabels[$status] }}</div>
            @if($count > 0)
            <div class="pipe-count" style="color:{{ $color }}">{{ $count }} job{{ $count > 1 ? 's' : '' }}</div>
            @endif
        </div>
        @endforeach
    </div>
</div>

<div class="row">
    {{-- ── Alert Panel ─────────────────────────────────────── --}}
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 font-weight-bold py-3 d-flex align-items-center">
                <i class="fas fa-exclamation-triangle text-danger mr-2"></i> Alerts
                <span class="badge badge-danger badge-pill ml-auto">{{ $alerts->count() }}</span>
            </div>
            <div class="card-body p-0">
                @forelse($alerts as $imp)
                <div class="alert-card mx-3 mb-0 mt-2">
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('imports.show', $imp->id) }}" class="font-weight-bold small text-dark">{{ $imp->job_id }}</a>
                        <span class="badge badge-light border small">{{ $imp->cargo_type }}</span>
                    </div>
                    @foreach($imp->missing_alerts as $alert)
                    <div class="text-danger small mt-1"><i class="fas fa-times-circle mr-1"></i>{{ $alert['text'] }}</div>
                    @endforeach
                </div>
                @empty
                <div class="text-center py-5 text-muted small">
                    <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                    No alerts — all jobs on track!
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Recent Jobs Table ───────────────────────────────── --}}
    <div class="col-md-8 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 font-weight-bold py-3 d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list-ul mr-2 text-muted"></i>Recent Jobs</span>
                <a href="{{ route('imports.index') }}" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light small text-muted text-uppercase">
                            <tr>
                                <th class="pl-4">Job ID</th>
                                <th>Type</th>
                                <th>Consignee</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentJobs as $job)
                            @php $color = $statusColors[$job->status]['bg'] ?? '#6c757d'; @endphp
                            <tr>
                                <td class="pl-4 font-weight-bold small text-primary">{{ $job->job_id }}</td>
                                <td><span class="badge badge-light border">{{ $job->cargo_type }}</span></td>
                                <td class="small">{{ $job->consignee_name ?? $job->shipper_name }}</td>
                                <td>
                                    <span class="job-row-status" style="background:{{ $statusColors[$job->status]['light'] ?? '#f8f9fa' }};color:{{ $color }}">
                                        <span style="width:6px;height:6px;border-radius:50%;background:{{ $color }};display:inline-block"></span>
                                        {{ $job->status_label }}
                                    </span>
                                </td>
                                <td class="small text-muted">{{ $job->updated_at->diffForHumans() }}</td>
                                <td><a href="{{ route('imports.show', $job->id) }}" class="btn btn-xs btn-light border">View</a></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted small">No imports yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
