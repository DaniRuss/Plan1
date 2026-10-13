@extends('layouts.app')
@section('title', 'Import Jobs Registry')

@section('content')
@php use App\Models\Import; @endphp
<style>
.status-pill{display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;}
.type-badge{font-size:10px;font-weight:700;padding:3px 8px;border-radius:6px;letter-spacing:.5px;}
.type-CNTR{background:#e3f2fd;color:#1565c0;}
.type-FZ{background:#f3e5f5;color:#6a1b9a;}
.type-MACHINERY{background:#fff3e0;color:#e65100;}
.filter-bar{background:#fff;border-radius:10px;padding:16px 20px;box-shadow:0 1px 6px rgba(0,0,0,.06);margin-bottom:20px;}
.import-table th{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#868e96;font-weight:600;border-top:none;}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="font-weight-bold mb-0">Import Jobs</h5>
        <small class="text-muted">All registered import shipments</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('imports.dashboard') }}" class="btn btn-light btn-sm"><i class="fas fa-satellite-dish mr-1"></i>Control Tower</a>
        <a href="{{ route('imports.create') }}" class="btn btn-primary btn-sm px-3"><i class="fas fa-plus mr-1"></i>New Import</a>
    </div>
</div>

{{-- Filter Bar --}}
<div class="filter-bar">
    <form method="GET" class="row align-items-end g-3 mb-0">
        <div class="col-md-4">
            <label class="small font-weight-bold text-muted text-uppercase mb-1">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Job ID, Shipper, Consignee…" value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <label class="small font-weight-bold text-muted text-uppercase mb-1">Status</label>
            <select name="status" class="form-control form-control-sm">
                <option value="">All Statuses</option>
                @foreach(Import::STAGE_LABELS as $val => $label)
                <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="small font-weight-bold text-muted text-uppercase mb-1">Type</label>
            <select name="cargo_type" class="form-control form-control-sm">
                <option value="">All Types</option>
                @foreach(Import::CARGO_TYPE_LABELS as $val => $label)
                <option value="{{ $val }}" {{ request('cargo_type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary btn-sm px-3"><i class="fas fa-search mr-1"></i>Filter</button>
            <a href="{{ route('imports.index') }}" class="btn btn-light btn-sm ml-1">Reset</a>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table import-table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="pl-4">Job ID</th>
                        <th>Type</th>
                        <th>Shipper / Consignee</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Last Updated</th>
                        <th class="text-right pr-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($imports as $import)
                    @php
                        $statusColors = [
                            'DOCUMENT_SUBMITTED'   => ['#17a2b8','rgba(23,162,184,.1)'],
                            'ECD_VALIDATED'        => ['#6610f2','rgba(102,16,242,.1)'],
                            'RELEASE_DOC_RECEIVED' => ['#007bff','rgba(0,123,255,.1)'],
                            'WAGON_ASSIGNED'       => ['#fd7e14','rgba(253,126,20,.1)'],
                            'AT_RAILWAY_YARD'      => ['#6f42c1','rgba(111,66,193,.1)'],
                            'TRANSIT_DOC_READY'    => ['#20c997','rgba(32,201,151,.1)'],
                            'OPERATION_APPROVED'   => ['#28a745','rgba(40,167,69,.1)'],
                            'LOADED_ON_TRAIN'      => ['#343a40','rgba(52,58,64,.1)'],
                            'DISPATCHED'           => ['#28a745','rgba(40,167,69,.1)'],
                        ];
                        [$sc, $sbg] = $statusColors[$import->status] ?? ['#6c757d','#f8f9fa'];
                    @endphp
                    <tr>
                        <td class="pl-4">
                            <div class="font-weight-bold text-primary small">{{ $import->job_id }}</div>
                            @if($import->bl_number)<div class="text-muted" style="font-size:11px">BL: {{ $import->bl_number }}</div>@endif
                        </td>
                        <td>
                            <span class="type-badge type-{{ $import->cargo_type }}">
                                {{ $import->cargo_type_label }}
                            </span>
                        </td>
                        <td>
                            <div class="small font-weight-bold">{{ $import->shipper_name }}</div>
                            @if($import->consignee_name)<div class="text-muted" style="font-size:11px">→ {{ $import->consignee_name }}</div>@endif
                        </td>
                        <td>
                            <span class="status-pill" style="background:{{ $sbg }};color:{{ $sc }}">
                                <span style="width:6px;height:6px;border-radius:50%;background:{{ $sc }};display:inline-block;flex-shrink:0"></span>
                                {{ $import->status_label }}
                            </span>
                        </td>
                        <td style="min-width:120px">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:5px;border-radius:3px;background:#e9ecef">
                                    <div class="progress-bar" style="width:{{ $import->progress_percentage }}%;background:{{ $sc }};border-radius:3px"></div>
                                </div>
                                <small class="text-muted font-weight-bold" style="font-size:10px;min-width:30px">{{ $import->progress_percentage }}%</small>
                            </div>
                            <div style="font-size:10px;color:#adb5bd;margin-top:2px">Stage {{ $import->stage_number }} of 9</div>
                        </td>
                        <td class="small text-muted">{{ $import->updated_at->diffForHumans() }}</td>
                        <td class="text-right pr-4">
                            <a href="{{ route('imports.show', $import->id) }}" class="btn btn-xs btn-outline-primary">
                                <i class="fas fa-arrow-right mr-1"></i>Open
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="fas fa-boxes fa-3x text-light mb-3 d-block"></i>
                            <div class="text-muted">No import jobs found.</div>
                            <a href="{{ route('imports.create') }}" class="btn btn-primary btn-sm mt-3">Register First Import</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($imports->hasPages())
    <div class="card-footer bg-white border-top-0 py-3">
        {{ $imports->links() }}
    </div>
    @endif
</div>
@endsection
