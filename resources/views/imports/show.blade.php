@extends('layouts.app')
@section('title', 'Import: ' . $import->job_id)

@section('content')
@php
use App\Models\Import;

$statusColors = [
    'DOCUMENT_SUBMITTED'   => '#17a2b8',
    'ECD_VALIDATED'        => '#6610f2',
    'RELEASE_DOC_RECEIVED' => '#007bff',
    'WAGON_ASSIGNED'       => '#fd7e14',
    'AT_RAILWAY_YARD'      => '#6f42c1',
    'TRANSIT_DOC_READY'    => '#20c997',
    'OPERATION_APPROVED'   => '#28a745',
    'LOADED_ON_TRAIN'      => '#343a40',
    'DISPATCHED'           => '#28a745',
];
$activeColor = $statusColors[$import->status] ?? '#6c757d';
$docsByType  = $import->documents->keyBy('document_type');
$payByType   = $import->payments->keyBy('payment_type');
@endphp

<style>
/* ── Hero ───────────────────────────────────── */
.import-hero{background:linear-gradient(135deg,#1a237e 0%,#283593 60%,#1565c0 100%);border-radius:12px;padding:28px 32px;color:#fff;margin-bottom:0;position:relative;overflow:hidden;}
.import-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.05);}
.cargo-type-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:.5px;}
.type-CNTR{background:rgba(255,255,255,.15);color:#fff;}
.type-FZ{background:rgba(255,255,255,.15);color:#fff;}
.type-MACHINERY{background:rgba(255,255,255,.15);color:#fff;}

/* ── Stepper ────────────────────────────────── */
.stepper-wrap{background:#fff;border-radius:0 0 12px 12px;box-shadow:0 4px 16px rgba(0,0,0,.08);padding:24px 32px;margin-bottom:20px;}
.stepper{display:flex;align-items:flex-start;overflow-x:auto;padding-bottom:4px;}
.step-item{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;min-width:88px;}
.step-item:not(:last-child)::after{content:'';position:absolute;top:19px;left:calc(50% + 19px);right:calc(-50% + 19px);height:2px;background:#e9ecef;z-index:0;}
.step-item.done:not(:last-child)::after{background:#28a745;}
.step-item.active:not(:last-child)::after{background:linear-gradient(90deg,v-bind(activeColor) 40%,#e9ecef 100%);}
.step-circle{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;position:relative;z-index:1;margin-bottom:6px;border:2px solid #dee2e6;background:#fff;color:#adb5bd;font-size:13px;font-weight:700;transition:all .3s;}
.step-item.done .step-circle{background:#28a745;border-color:#28a745;color:#fff;}
.step-item.active .step-circle{background:var(--active-clr);border-color:var(--active-clr);color:#fff;box-shadow:0 0 0 5px rgba(0,0,0,.1);animation:stepPulse 2s infinite;}
@keyframes stepPulse{0%,100%{box-shadow:0 0 0 5px rgba(0,123,255,.15);}50%{box-shadow:0 0 0 9px rgba(0,123,255,.06);}}
.step-label{font-size:10px;font-weight:600;text-align:center;color:#adb5bd;text-transform:uppercase;letter-spacing:.3px;line-height:1.3;max-width:74px;}
.step-item.done .step-label,.step-item.active .step-label{color:#343a40;}
.step-item:not(.done):not(.active) { opacity: 0.4; }

/* ── Sections ───────────────────────────────── */
.section-card{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px;overflow:hidden;}
.section-header{padding:14px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px;}
.section-header h6{margin:0;font-weight:700;font-size:.82rem;text-transform:uppercase;letter-spacing:.5px;}
.section-body{padding:18px 20px;}

/* ── Doc Row ─────────────────────────────────── */
.doc-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f8f9fa;}
.doc-row:last-child{border-bottom:none;}
.doc-status-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}

/* ── Action Stage Card ──────────────────────── */
.action-card{border-radius:12px;padding:24px;border:2px solid;margin-bottom:18px;}

/* ── Payment Row ─────────────────────────────── */
.pay-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f8f9fa;}
.pay-row:last-child{border-bottom:none;}

/* ── Log ─────────────────────────────────────── */
.log-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:4px;}
</style>

<div style="--active-clr:{{ $activeColor }}">

{{-- Back --}}
<a href="{{ route('imports.index') }}" class="btn btn-light btn-sm mb-3">
    <i class="fas fa-arrow-left mr-1"></i> Import Jobs
</a>

{{-- ── HERO ──────────────────────────────────────────────────────────── --}}
<div class="import-hero">
    <div class="row align-items-center">
        <div class="col-md-7">
            <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                <h3 class="font-weight-bold mb-0">{{ $import->job_id }}</h3>
                <span class="cargo-type-badge type-{{ $import->cargo_type }}">
                    <i class="fas {{ Import::CARGO_TYPE_ICONS[$import->cargo_type] ?? 'fa-box' }}"></i>
                    {{ $import->cargo_type_label }}
                </span>
            </div>
            <div class="opacity-75 small mb-3">
                <i class="fas fa-building mr-1"></i>{{ $import->shipper_name }}
                @if($import->consignee_name) &nbsp;→&nbsp; {{ $import->consignee_name }} @endif
            </div>
            {{-- Progress bar --}}
            <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height:6px;border-radius:4px;background:rgba(255,255,255,.2);max-width:280px">
                    <div class="progress-bar bg-white" style="width:{{ $import->progress_percentage }}%;border-radius:4px"></div>
                </div>
                <span class="small font-weight-bold">{{ $import->progress_percentage }}%</span>
            </div>
        </div>
        <div class="col-md-5 text-md-right mt-3 mt-md-0">
            <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Current Stage</div>
            <div class="h5 font-weight-bold mb-1">{{ $import->status_label }}</div>
            <div style="font-size:11px;opacity:.6">Stage {{ $import->stage_number }} of 9 &bull; Updated {{ $import->updated_at->diffForHumans() }}</div>
            @if($import->missing_alerts)
            <div class="mt-2">
                @foreach($import->missing_alerts as $alert)
                <div class="badge badge-warning text-dark px-2 py-1 d-inline-block mt-1">
                    <i class="fas fa-exclamation-triangle mr-1"></i>{{ $alert['text'] }}
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ── STEPPER ───────────────────────────────────────────────────────── --}}
<div class="stepper-wrap">
    <div class="stepper">
        @foreach(Import::STAGE_ORDER as $status => $num)
        @php
            $isDone   = $import->isStageComplete($status);
            $isActive = $import->isStageActive($status);
            $cls      = $isDone ? 'done' : ($isActive ? 'active' : '');
        @endphp
        <div class="step-item {{ $cls }}">
            <div class="step-circle" @if($isActive) style="background:{{ $activeColor }};border-color:{{ $activeColor }}" @endif>
                @if($isDone)
                    <i class="fas fa-check" style="font-size:12px"></i>
                @else
                    {{ $num }}
                @endif
            </div>
            <div class="step-label">{{ Import::STAGE_LABELS[$status] }}</div>
        </div>
        @endforeach
    </div>
</div>

<div class="row">
<div class="col-md-8">

{{-- ── CURRENT STAGE ACTION ─────────────────────────────────────────── --}}
@if($import->missing_alerts && $import->status !== Import::S_DISPATCHED)
<div class="alert alert-warning border-warning shadow-sm mb-3">
    <div class="font-weight-bold mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> Cannot proceed to {{ $import->next_stage_name }}:</div>
    <div class="small mb-1">Missing:</div>
    <ul class="mb-0 pl-3 small">
        @foreach($import->missing_alerts as $alert)
        <li class="mb-1">
            {{ $alert['text'] }}
            @if($alert['type'] === 'doc')
                <button type="button" class="btn btn-xs btn-link p-0 ml-1 font-weight-bold" data-toggle="modal" data-target="#docModal" data-type="{{ $alert['key'] }}" data-label="{{ $alert['text'] }}">[Upload]</button>
            @elseif($alert['type'] === 'pay')
                <button type="button" class="btn btn-xs btn-link p-0 ml-1 font-weight-bold" data-toggle="modal" data-target="#payModal" data-type="{{ $alert['key'] }}" data-label="{{ $alert['text'] }}">[Mark Paid]</button>
            @endif
        </li>
        @endforeach
    </ul>
</div>
@endif
@include('imports.partials.stage_action')

{{-- ── DOCUMENTS ────────────────────────────────────────────────────── --}}
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-folder-open text-warning"></i>
        <h6>Documents</h6>
        <span class="badge badge-pill badge-light border ml-auto">
            {{ $import->documents->whereIn('status',['uploaded','verified'])->count() }} / {{ $import->documents->count() }} uploaded
        </span>
    </div>
    <div class="section-body p-0 px-3">
        @php
        $allDocTypes = \App\Models\ImportDocument::TYPE_LABELS;

        // Format: 'TYPE' => true(required) / false(optional)
        $relevantDocs = ['INVOICE' => true, 'PACKING_LIST' => false];
        if($import->cargo_type === 'FZ') { 
            $relevantDocs += ['WAYBILL'=>true];
            if ($import->stage_number >= 2) $relevantDocs += ['CUSTOMS_APPROVAL'=>true];
            if ($import->stage_number >= 3) $relevantDocs += ['DECLARATION_4'=>true];
            if ($import->stage_number >= 5) $relevantDocs += ['GET_PASS'=>true, 'DJ_T1'=>true, 'ET_T1'=>true];
        } else { 
            $relevantDocs += ['BL'=>true];
            if ($import->stage_number >= 2) $relevantDocs += ['CUSTOMS_APPROVAL'=>true];
            if ($import->stage_number >= 3) $relevantDocs += ['DO'=>true];
            if ($import->stage_number >= 5) $relevantDocs += ['DJ_T1'=>true, 'ET_T1'=>true];
        }
        @endphp

        @foreach($relevantDocs as $type => $isRequired)
        @php $doc = $docsByType->get($type); $uploaded = $doc && in_array($doc->status,['uploaded','verified']); @endphp
        <div class="doc-row">
            <div class="d-flex align-items-center gap-2">
                <div class="doc-status-dot" style="background:{{ $uploaded ? '#28a745' : ($isRequired ? '#dc3545' : '#ffc107') }}"></div>
                <div>
                    <div class="small font-weight-bold">
                        @if($isRequired)<span class="text-danger" style="font-size:10px;">[Required]</span>@else<span class="text-muted" style="font-size:10px;">[Optional]</span>@endif
                        {{ $allDocTypes[$type] ?? $type }}
                    </div>
                    @if($doc && $doc->reference_number)
                    <div style="font-size:11px;color:#868e96">Ref: {{ $doc->reference_number }}</div>
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($uploaded)
                    <span class="badge badge-success px-2">✓ {{ ucfirst($doc->status) }}</span>
                @else
                    <span class="badge badge-light text-danger border px-2">Missing</span>
                    <button class="btn btn-xs btn-outline-primary" data-toggle="modal" data-target="#docModal" data-type="{{ $type }}" data-label="{{ $allDocTypes[$type] ?? $type }}">Upload</button>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ── PAYMENTS ─────────────────────────────────────────────────────── --}}
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-money-bill-wave text-success"></i>
        <h6>Payments</h6>
        <span class="badge badge-pill badge-light border ml-auto">
            {{ $import->payments->where('status','paid')->count() }} / {{ $import->payments->count() }} paid
        </span>
    </div>
    <div class="section-body p-0 px-3">
        @forelse($import->payments as $pay)
        <div class="pay-row">
            <div class="d-flex align-items-center gap-2">
                <div class="doc-status-dot" style="background:{{ $pay->status === 'paid' ? '#28a745' : '#fd7e14' }}"></div>
                <div>
                    <div class="small font-weight-bold">{{ $pay->type_label }}</div>
                    @if($pay->receipt_number)<div style="font-size:11px;color:#868e96">Receipt: {{ $pay->receipt_number }}</div>@endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($pay->amount)<span class="small font-weight-bold">ETB {{ number_format($pay->amount,2) }}</span>@endif
                @if($pay->status === 'paid')
                    <span class="badge badge-success px-2">Paid</span>
                @else
                    <span class="badge badge-warning text-dark px-2">Pending</span>
                    <button class="btn btn-xs btn-outline-success" data-toggle="modal" data-target="#payModal" data-type="{{ $pay->payment_type }}" data-label="{{ $pay->type_label }}">Mark Paid</button>
                @endif
            </div>
        </div>
        @empty
        <div class="text-muted small py-3">No payments recorded yet.</div>
        @endforelse
    </div>
</div>

{{-- ── LEGEND ───────────────────────────────────────────────────────── --}}
<div class="mt-3 px-3 py-2 border rounded mb-3" style="background:#f8f9fa;">
    <div class="small font-weight-bold mb-2 border-bottom pb-1">Status Legend</div>
    <div class="d-flex align-items-center gap-2 small mb-1">
        <div class="doc-status-dot" style="background:#dc3545"></div> <span class="text-muted">Red = Required but missing</span>
    </div>
    <div class="d-flex align-items-center gap-2 small mb-1">
        <div class="doc-status-dot" style="background:#ffc107"></div> <span class="text-muted">Yellow = Waiting action / Optional</span>
    </div>
    <div class="d-flex align-items-center gap-2 small">
        <div class="doc-status-dot" style="background:#28a745"></div> <span class="text-muted">Green = Completed / Paid</span>
    </div>
</div>

{{-- ── CONDITIONAL: FZ ──────────────────────────────────────────────── --}}
@if($import->cargo_type === 'FZ' && $import->operation)
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-industry text-purple" style="color:#6a1b9a"></i>
        <h6>Free Zone Specifics</h6>
    </div>
    <div class="section-body">
        <div class="row">
            <div class="col-6"><div class="text-muted small">Storage Location</div><div class="font-weight-bold">{{ $import->operation->storage_location ?? '—' }}</div></div>
            <div class="col-6"><div class="text-muted small">Get Pass</div>
            <div class="font-weight-bold">
                @if($import->operation->get_pass_obtained)<span class="text-success"><i class="fas fa-check-circle"></i> Obtained</span>
                @else<span class="text-danger">Pending</span>@endif
            </div></div>
        </div>
    </div>
</div>
@endif

{{-- ── CONDITIONAL: MACHINERY ────────────────────────────────────────── --}}
@if($import->cargo_type === 'MACHINERY' && $import->operation)
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-cogs" style="color:#e65100"></i>
        <h6>Machinery Specifics</h6>
    </div>
    <div class="section-body">
        <div class="row">
            <div class="col-6"><div class="text-muted small">Lashing Required</div>
            <div class="font-weight-bold">{{ $import->operation->lashing_required ? 'Yes' : 'No' }}</div></div>
            <div class="col-6"><div class="text-muted small">Lashing Status</div>
            <div class="font-weight-bold">
                @if($import->operation->lashing_completed)<span class="text-success"><i class="fas fa-check-circle"></i> Completed</span>
                @else<span class="text-danger">Not done</span>@endif
            </div></div>
        </div>
        @if($import->operation->special_handling_notes)
        <div class="mt-2"><div class="text-muted small">Notes</div><div class="small">{{ $import->operation->special_handling_notes }}</div></div>
        @endif
    </div>
</div>
@endif

</div>{{-- /col-md-8 --}}

{{-- ── RIGHT: Operations + Activity ─────────────────────────────────── --}}
<div class="col-md-4">

{{-- Operations card --}}
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-pallet text-warning"></i>
        <h6>Operations</h6>
    </div>
    <div class="section-body">
        @if($import->operation)
        <div class="mb-2"><div class="text-muted small">Wagon ID</div><div class="font-weight-bold">{{ $import->operation->wagon_id ?? '—' }}</div></div>
        <div class="mb-2"><div class="text-muted small">Yard Location</div><div class="font-weight-bold">{{ $import->operation->yard_location ?? '—' }}</div></div>
        <div class="mb-2"><div class="text-muted small">Escort Required</div><div class="font-weight-bold">{{ $import->operation->requires_escort ? 'Yes' : 'No' }}</div></div>
        <div class="mb-2"><div class="text-muted small">Transfer Date</div><div class="font-weight-bold">{{ $import->operation->transfer_date ? $import->operation->transfer_date->format('M d, Y H:i') : '—' }}</div></div>
        <div class="mb-2"><div class="text-muted small">Train ID</div><div class="font-weight-bold">{{ $import->operation->train_id ?? '—' }}</div></div>
        <div><div class="text-muted small">Loading Time</div><div class="font-weight-bold">{{ $import->operation->loading_time ? $import->operation->loading_time->format('M d, Y H:i') : '—' }}</div></div>
        @else
        <div class="text-muted small py-2">Not yet assigned.</div>
        @endif
    </div>
</div>

{{-- Activity Log --}}
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-history text-muted"></i>
        <h6>Activity Log</h6>
        <span class="badge badge-pill badge-light border ml-auto">{{ $import->logs->count() }}</span>
    </div>
    <div class="section-body p-0">
        @forelse($import->logs->take(10) as $log)
        <div class="d-flex gap-2 px-3 py-2 border-bottom" style="border-color:#f8f9fa!important">
            <div class="log-dot mt-1" style="background:{{ $statusColors[$log->status] ?? '#6c757d' }}"></div>
            <div>
                <div class="small font-weight-bold" style="font-size:12px">[{{ $log->created_at->format('H:i') }}] {{ $log->action }}</div>
                @if($log->notes)<div class="text-muted" style="font-size:11px">{{ $log->notes }}</div>@endif
                <div class="text-muted" style="font-size:10px">{{ $log->created_at->format('M d, Y') }} &bull; {{ $log->user->name ?? 'System' }}</div>
            </div>
        </div>
        @empty
        <div class="text-muted small px-3 py-3">No activity yet.</div>
        @endforelse
    </div>
</div>

</div>{{-- /col-md-4 --}}
</div>{{-- /row --}}

</div>{{-- /active-clr wrapper --}}

{{-- ── MODALS ────────────────────────────────────────────────────────── --}}

{{-- Document Upload Modal --}}
<div class="modal fade" id="docModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('imports.upload-document', $import->id) }}" method="POST" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" name="document_type" id="docModalType">
            <div class="modal-header border-0">
                <h5 class="modal-title font-weight-bold" id="docModalLabel">Upload Document</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold">Reference Number / Document #</label>
                    <input type="text" name="reference_number" class="form-control" placeholder="e.g. D/O-12345, Declaration 4-67890">
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary font-weight-bold px-4">Save Document</button>
            </div>
        </form>
    </div>
</div>

{{-- Payment Modal --}}
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('imports.mark-payment', $import->id) }}" method="POST" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" name="payment_type" id="payModalType">
            <div class="modal-header border-0">
                <h5 class="modal-title font-weight-bold" id="payModalLabel">Mark Payment</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold">Amount (ETB)</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required>
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Receipt Number</label>
                    <input type="text" name="receipt_number" class="form-control" placeholder="e.g. RCP-2024-001">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success font-weight-bold px-4">Mark as Paid</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
$('#docModal').on('show.bs.modal', function(e) {
    var btn = $(e.relatedTarget);
    $(this).find('#docModalType').val(btn.data('type'));
    $(this).find('#docModalLabel').text('Upload: ' + btn.data('label'));
});
$('#payModal').on('show.bs.modal', function(e) {
    var btn = $(e.relatedTarget);
    $(this).find('#payModalType').val(btn.data('type'));
    $(this).find('#payModalLabel').text('Mark Paid: ' + btn.data('label'));
});
</script>
@endpush
@endsection
