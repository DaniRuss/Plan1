@extends('layouts.app')
@section('title', 'Register New Import Job')

@section('content')
<style>
.create-hero{background:linear-gradient(135deg,#1a237e,#1565c0);border-radius:12px;padding:24px 32px;color:#fff;margin-bottom:24px;}
.type-selector{display:flex;gap:16px;flex-wrap:wrap;}
.type-card{flex:1;min-width:160px;border:2px solid #e9ecef;border-radius:10px;padding:20px 16px;text-align:center;cursor:pointer;transition:all .25s;}
.type-card:hover,.type-card.selected{border-color:var(--primary,#007bff);background:rgba(0,123,255,.04);}
.type-card input[type=radio]{position:absolute;opacity:0;}
.type-card .type-icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:1.4rem;}
.type-card.selected .type-icon{box-shadow:0 4px 12px rgba(0,123,255,.25);}
.form-section{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:20px;overflow:hidden;}
.form-section-header{padding:14px 20px;border-bottom:1px solid #f0f0f0;font-weight:700;font-size:.85rem;text-transform:uppercase;letter-spacing:.5px;color:#495057;}
.form-section-body{padding:20px;}
</style>

<div class="create-hero">
    <a href="{{ route('imports.index') }}" class="btn btn-sm btn-light btn-sm mb-2">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
    <h4 class="font-weight-bold mb-1 mt-2"><i class="fas fa-plus-circle mr-2"></i>Register New Import Job</h4>
    <p class="mb-0 small opacity-75">Stage 1: Transitor Document Submission</p>
</div>

<form action="{{ route('imports.store') }}" method="POST" id="createImportForm">
@csrf

{{-- ── Step 1: Cargo Type ───────────────────────────────────────────── --}}
<div class="form-section">
    <div class="form-section-header"><i class="fas fa-boxes mr-2 text-primary"></i>Step 1 — Select Cargo Type</div>
    <div class="form-section-body">
        <div class="type-selector">
            <label class="type-card {{ old('cargo_type') === 'CNTR' ? 'selected' : '' }}" id="card-CNTR">
                <input type="radio" name="cargo_type" value="CNTR" {{ old('cargo_type') === 'CNTR' ? 'checked' : '' }} required>
                <div class="type-icon" style="background:#e3f2fd"><i class="fas fa-box" style="color:#1565c0"></i></div>
                <div class="font-weight-bold">Container</div>
                <div class="text-muted small mt-1">Uses BL + D/O</div>
                <div class="text-muted" style="font-size:11px">May need escort fee</div>
            </label>
            <label class="type-card {{ old('cargo_type') === 'FZ' ? 'selected' : '' }}" id="card-FZ">
                <input type="radio" name="cargo_type" value="FZ" {{ old('cargo_type') === 'FZ' ? 'checked' : '' }} required>
                <div class="type-icon" style="background:#f3e5f5"><i class="fas fa-industry" style="color:#6a1b9a"></i></div>
                <div class="font-weight-bold">Free Zone</div>
                <div class="text-muted small mt-1">Uses Waybill + Decl.4</div>
                <div class="text-muted" style="font-size:11px">Get Pass + Storage</div>
            </label>
            <label class="type-card {{ old('cargo_type') === 'MACHINERY' ? 'selected' : '' }}" id="card-MACHINERY">
                <input type="radio" name="cargo_type" value="MACHINERY" {{ old('cargo_type') === 'MACHINERY' ? 'checked' : '' }} required>
                <div class="type-icon" style="background:#fff3e0"><i class="fas fa-cogs" style="color:#e65100"></i></div>
                <div class="font-weight-bold">Machinery</div>
                <div class="text-muted small mt-1">Uses BL + D/O</div>
                <div class="text-muted" style="font-size:11px">Lashing + Special handling</div>
            </label>
        </div>
    </div>
</div>

{{-- ── Step 2: Parties ──────────────────────────────────────────────── --}}
<div class="form-section">
    <div class="form-section-header"><i class="fas fa-users mr-2 text-info"></i>Step 2 — Parties</div>
    <div class="form-section-body">
        <div class="row">
            <div class="col-md-4 form-group">
                <label class="small font-weight-bold">Shipper Name <span class="text-danger">*</span></label>
                <input type="text" name="shipper_name" class="form-control" value="{{ old('shipper_name') }}" placeholder="e.g. Maersk Global" required>
            </div>
            <div class="col-md-4 form-group">
                <label class="small font-weight-bold">Consignee Name</label>
                <input type="text" name="consignee_name" class="form-control" value="{{ old('consignee_name') }}" placeholder="e.g. ESLSE Ethiopia">
            </div>
            <div class="col-md-4 form-group">
                <label class="small font-weight-bold">Freight Forwarder / Transitor</label>
                <input type="text" name="freight_forwarder" class="form-control" value="{{ old('freight_forwarder') }}" placeholder="e.g. Bolloré Logistics">
            </div>
        </div>
    </div>
</div>

{{-- ── Step 3: Cargo Details ────────────────────────────────────────── --}}
<div class="form-section">
    <div class="form-section-header"><i class="fas fa-clipboard-list mr-2 text-warning"></i>Step 3 — Cargo Details</div>
    <div class="form-section-body">
        <div class="row">
            <div class="col-md-6 form-group">
                <label class="small font-weight-bold">Cargo Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="e.g. Coffee beans, construction equipment…">
            </div>
            <div class="col-md-3 form-group">
                <label class="small font-weight-bold">Weight (Tons)</label>
                <input type="number" step="any" name="weight" class="form-control" data-original-number="{{ old('weight') }}" value="{{ \App\Support\NumberFormatter::format(old('weight'),3,'.','') }}" placeholder="0.00">
            </div>
            <div class="col-md-3 form-group" id="container-count-group">
                <label class="small font-weight-bold">Container Count</label>
                <input type="number" name="container_count" class="form-control" data-original-number="{{ old('container_count') }}" value="{{ \App\Support\NumberFormatter::format(old('container_count'),3,'.','') }}" placeholder="e.g. 2">
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 form-group">
                <label class="small font-weight-bold">BL / Waybill Number</label>
                <input type="text" name="bl_number" class="form-control" value="{{ old('bl_number') }}" placeholder="e.g. MSKU1234567-8">
            </div>
            <div class="col-md-4 form-group">
                <label class="small font-weight-bold">Vessel / Carrier Name</label>
                <input type="text" name="vessel_name" class="form-control" value="{{ old('vessel_name') }}" placeholder="e.g. Maersk Denver">
            </div>
            <div class="col-md-4 form-group">
                <label class="small font-weight-bold">Port of Origin</label>
                <input type="text" name="port_of_origin" class="form-control" value="{{ old('port_of_origin') }}" placeholder="e.g. Shanghai, Jebel Ali">
            </div>
        </div>
    </div>
</div>

{{-- ── Submit ───────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-end">
    <a href="{{ route('imports.index') }}" class="btn btn-light px-4 mr-2">Cancel</a>
    <button type="submit" class="btn btn-primary px-5 font-weight-bold">
        <i class="fas fa-file-alt mr-1"></i> Submit Documents & Create Job
    </button>
</div>
</form>

@push('scripts')
<script>
document.querySelectorAll('input[name="cargo_type"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
        this.closest('.type-card').classList.add('selected');
        var isFZ = this.value === 'FZ';
        var isCNTR = this.value === 'CNTR';
        document.getElementById('container-count-group').style.display = (isFZ ? 'none' : 'block');
    });
});
// Re-apply selected state on load
var checked = document.querySelector('input[name="cargo_type"]:checked');
if (checked) checked.closest('.type-card').classList.add('selected');
</script>
@endpush
@endsection
