@php 
use App\Models\Import; 
$btnDisabled = count($import->missing_alerts) > 0 ? 'disabled' : '';
$btnTitle = count($import->missing_alerts) > 0 ? 'Upload required documents or complete payments to proceed' : '';
@endphp

@if($import->status === Import::S_DOCUMENT_SUBMITTED)
{{-- Stage 2: ECD Validation --}}
<div class="action-card" style="border-color:#6610f2;background:rgba(102,16,242,.03)">
    <div class="d-flex align-items-center mb-3">
        <div class="mr-3" style="width:44px;height:44px;border-radius:10px;background:rgba(102,16,242,.1);display:flex;align-items:center;justify-content:center;color:#6610f2;font-size:1.2rem"><i class="fas fa-stamp"></i></div>
        <div><div class="font-weight-bold">Next: ECD Validation</div><div class="small text-muted">Submit documents to Ethiopian Customs Directorate for approval</div></div>
    </div>
    <form action="{{ route('imports.validate-ecd', $import->id) }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-md-6 form-group mb-2">
                <label class="small font-weight-bold">ECD Approval / Reference # <span class="text-danger">*</span></label>
                <input type="text" name="approval_number" class="form-control form-control-sm @error('approval_number') is-invalid @enderror" placeholder="e.g. ECD-2024-XXXXX" required>
                @error('approval_number')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 form-group mb-2">
                <label class="small font-weight-bold">Notes (optional)</label>
                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Any remarks…">
            </div>
        </div>
        <button type="submit" class="btn btn-sm font-weight-bold px-4" style="background:#6610f2;color:#fff" {{ $btnDisabled }} title="{{ $btnTitle }}">
            <i class="fas fa-stamp mr-1"></i> Record ECD Validation
        </button>
    </form>
</div>

@elseif($import->status === Import::S_ECD_VALIDATED)
{{-- Stage 3: Release Doc --}}
@php $docLabel = $import->cargo_type === 'FZ' ? 'Declaration No. 4' : 'Delivery Order (D/O)'; @endphp
<div class="action-card" style="border-color:#007bff;background:rgba(0,123,255,.03)">
    <div class="d-flex align-items-center mb-3">
        <div class="mr-3" style="width:44px;height:44px;border-radius:10px;background:rgba(0,123,255,.1);display:flex;align-items:center;justify-content:center;color:#007bff;font-size:1.2rem"><i class="fas fa-file-signature"></i></div>
        <div><div class="font-weight-bold">Next: Attach Release Document</div><div class="small text-muted">{{ $import->cargo_type === 'FZ' ? 'Submit Truck Waybill to shipper and receive Declaration No.4' : 'Submit BL to shipping line and receive Delivery Order (D/O)' }}</div></div>
    </div>
    <form action="{{ route('imports.release-doc', $import->id) }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-md-6 form-group mb-2">
                <label class="small font-weight-bold">{{ $docLabel }} Reference Number <span class="text-danger">*</span></label>
                <input type="text" name="reference_number" class="form-control form-control-sm @error('reference_number') is-invalid @enderror" placeholder="e.g. DO-2024-00123" required>
                @error('reference_number')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 form-group mb-2">
                <label class="small font-weight-bold">Notes</label>
                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Issuing shipping line, date…">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-4" {{ $btnDisabled }} title="{{ $btnTitle }}">
            <i class="fas fa-file-signature mr-1"></i> Confirm {{ $docLabel }} Received
        </button>
    </form>
</div>

@elseif($import->status === Import::S_RELEASE_DOC)
{{-- Stage 4: Wagon Assignment --}}
<div class="action-card" style="border-color:#fd7e14;background:rgba(253,126,20,.03)">
    <div class="d-flex align-items-center mb-3">
        <div class="mr-3" style="width:44px;height:44px;border-radius:10px;background:rgba(253,126,20,.1);display:flex;align-items:center;justify-content:center;color:#fd7e14;font-size:1.2rem"><i class="fas fa-pallet"></i></div>
        <div><div class="font-weight-bold">Next: Port & Wagon Operations</div><div class="small text-muted">Pay port fees and assign a wagon at the yard</div></div>
    </div>
    <form action="{{ route('imports.assign-wagon', $import->id) }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-md-4 form-group mb-2">
                <label class="small font-weight-bold">Wagon ID <span class="text-danger">*</span></label>
                <input type="text" name="wagon_id" class="form-control form-control-sm @error('wagon_id') is-invalid @enderror" placeholder="e.g. WGN-1042" required>
                @error('wagon_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 form-group mb-2">
                <label class="small font-weight-bold">Yard Location <span class="text-danger">*</span></label>
                <select name="yard_location" class="form-control form-control-sm @error('yard_location') is-invalid @enderror" required>
                    <option value="">-- Select --</option>
                    <option value="Yard 8">Yard 8 (+ Escort)</option>
                    <option value="DCT">DCT</option>
                    <option value="NAGAD">NAGAD</option>
                    <option value="DMP">Doraleh (DMP)</option>
                </select>
                @error('yard_location')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            @if($import->cargo_type === 'FZ')
            <div class="col-md-4 form-group mb-2">
                <label class="small font-weight-bold">Storage Location</label>
                <select name="storage_location" class="form-control form-control-sm">
                    <option value="">-- Select --</option>
                    <option value="DCT">DCT</option><option value="NAGAD">NAGAD</option><option value="DMP">DMP</option>
                </select>
            </div>
            @endif
        </div>
        <div class="row">
            <div class="col-md-4 form-group mb-2">
                <div class="custom-control custom-checkbox mt-4">
                    <input type="checkbox" class="custom-control-input" id="escort" name="requires_escort" value="1">
                    <label class="custom-control-label small font-weight-bold" for="escort">Requires Escort</label>
                </div>
            </div>
            <div class="col-md-4 form-group mb-2">
                <div class="custom-control custom-checkbox mt-4">
                    <input type="checkbox" class="custom-control-input" id="portPaid" name="port_fee_paid" value="1">
                    <label class="custom-control-label small font-weight-bold" for="portPaid">Port Fee Paid</label>
                </div>
            </div>
            <div class="col-md-4 form-group mb-2">
                <label class="small font-weight-bold">Port Fee Amount (ETB)</label>
                <input type="number" step="0.01" name="port_fee_amount" class="form-control form-control-sm" placeholder="0.00">
            </div>
        </div>
        <button type="submit" class="btn btn-sm font-weight-bold px-4" style="background:#fd7e14;color:#fff" {{ $btnDisabled }} title="{{ $btnTitle }}">
            <i class="fas fa-pallet mr-1"></i> Confirm Wagon Assignment
        </button>
    </form>
</div>

@elseif($import->status === Import::S_WAGON_ASSIGNED)
{{-- Stage 5: Transfer to Yard --}}
<div class="action-card" style="border-color:#6f42c1;background:rgba(111,66,193,.03)">
    <div class="d-flex align-items-center mb-3">
        <div class="mr-3" style="width:44px;height:44px;border-radius:10px;background:rgba(111,66,193,.1);display:flex;align-items:center;justify-content:center;color:#6f42c1;font-size:1.2rem"><i class="fas fa-warehouse"></i></div>
        <div><div class="font-weight-bold">Next: Transfer to Railway Yard</div><div class="small text-muted">Move cargo from port to railway loading point</div></div>
    </div>
    <form action="{{ route('imports.transfer-yard', $import->id) }}" method="POST" class="d-flex align-items-center gap-3 flex-wrap">
        @csrf
        @if($import->cargo_type === 'FZ')
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="getPass" name="get_pass" value="1">
            <label class="custom-control-label small font-weight-bold" for="getPass">Get Pass Obtained</label>
        </div>
        @endif
        <button type="submit" class="btn btn-sm font-weight-bold px-4" style="background:#6f42c1;color:#fff" {{ $btnDisabled }} title="{{ $btnTitle }}">
            <i class="fas fa-warehouse mr-1"></i> Mark as Transferred to Railway Yard
        </button>
    </form>
</div>

@elseif($import->status === Import::S_AT_RAILWAY_YARD)
{{-- Stage 6: T1 Documents --}}
<div class="action-card" style="border-color:#20c997;background:rgba(32,201,151,.03)">
    <div class="d-flex align-items-center mb-3">
        <div class="mr-3" style="width:44px;height:44px;border-radius:10px;background:rgba(32,201,151,.1);display:flex;align-items:center;justify-content:center;color:#20c997;font-size:1.2rem"><i class="fas fa-passport"></i></div>
        <div><div class="font-weight-bold">Next: Transit Documents (T1)</div><div class="small text-muted">Prepare and record DJ T1 and ET T1 transit documents</div></div>
    </div>
    <form action="{{ route('imports.transit-docs', $import->id) }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-md-4 form-group mb-2">
                <label class="small font-weight-bold">Djibouti T1 (DJ T1) # <span class="text-danger">*</span></label>
                <input type="text" name="dj_t1_number" class="form-control form-control-sm @error('dj_t1_number') is-invalid @enderror" placeholder="e.g. DJT1-2024-0001" required>
                @error('dj_t1_number')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 form-group mb-2">
                <label class="small font-weight-bold">Ethiopian T1 (ET T1) # <span class="text-danger">*</span></label>
                <input type="text" name="et_t1_number" class="form-control form-control-sm @error('et_t1_number') is-invalid @enderror" placeholder="e.g. ETT1-2024-0001" required>
                @error('et_t1_number')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            @if($import->cargo_type === 'FZ')
            <div class="col-md-4 form-group mb-2">
                <label class="small font-weight-bold">FZ Waybill #</label>
                <input type="text" name="fz_waybill_number" class="form-control form-control-sm" placeholder="e.g. FZ-WB-0001">
            </div>
            @endif
        </div>
        <button type="submit" class="btn btn-sm font-weight-bold px-4" style="background:#20c997;color:#fff" {{ $btnDisabled }} title="{{ $btnTitle }}">
            <i class="fas fa-passport mr-1"></i> Submit T1 Documents
        </button>
    </form>
</div>

@elseif($import->status === Import::S_TRANSIT_DOC_READY)
{{-- Stage 7: Operation Approval --}}
<div class="action-card" style="border-color:#28a745;background:rgba(40,167,69,.03)">
    <div class="d-flex align-items-center mb-3">
        <div class="mr-3" style="width:44px;height:44px;border-radius:10px;background:rgba(40,167,69,.1);display:flex;align-items:center;justify-content:center;color:#28a745;font-size:1.2rem"><i class="fas fa-check-double"></i></div>
        <div><div class="font-weight-bold">Next: Operation Department Approval</div><div class="small text-muted">Submit all documents to operations team for final loading authorization</div></div>
    </div>
    <form action="{{ route('imports.approve-operation', $import->id) }}" method="POST">
        @csrf
        @if($import->cargo_type === 'MACHINERY')
        <div class="row mb-2">
            <div class="col-md-4">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="lashDone" name="lashing_completed" value="1">
                    <label class="custom-control-label small font-weight-bold" for="lashDone">Lashing Completed</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="small font-weight-bold">Lashing Fee (ETB)</label>
                <input type="number" step="0.01" name="lashing_amount" class="form-control form-control-sm">
            </div>
        </div>
        @endif
        <div class="form-group mb-3">
            <label class="small font-weight-bold">Approval Notes</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Reviewed by, checklist items…">
        </div>
        <button type="submit" class="btn btn-success btn-sm font-weight-bold px-4" {{ $btnDisabled }} title="{{ $btnTitle }}">
            <i class="fas fa-check-double mr-1"></i> Approve for Train Loading
        </button>
    </form>
</div>

@elseif($import->status === Import::S_OPERATION_APPROVED)
{{-- Stage 8: Train Loading --}}
<div class="action-card" style="border-color:#343a40;background:rgba(52,58,64,.03)">
    <div class="d-flex align-items-center mb-3">
        <div class="mr-3" style="width:44px;height:44px;border-radius:10px;background:rgba(52,58,64,.1);display:flex;align-items:center;justify-content:center;color:#343a40;font-size:1.2rem"><i class="fas fa-train"></i></div>
        <div><div class="font-weight-bold">Next: Train Loading</div><div class="small text-muted">Load cargo onto the train and record departure</div></div>
    </div>
    <form action="{{ route('imports.mark-loaded', $import->id) }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-md-5 form-group mb-2">
                <label class="small font-weight-bold">Train ID <span class="text-danger">*</span></label>
                <input type="text" name="train_id" class="form-control form-control-sm @error('train_id') is-invalid @enderror" placeholder="e.g. EDR-TR-2024-042" required>
                @error('train_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
        <button type="submit" class="btn btn-dark btn-sm font-weight-bold px-4" {{ $btnDisabled }} title="{{ $btnTitle }}">
            <i class="fas fa-train mr-1"></i> Mark as Loaded on Train
        </button>
    </form>
</div>

@elseif($import->status === Import::S_LOADED_ON_TRAIN)
{{-- Stage 9: Dispatch --}}
<div class="action-card" style="border-color:#1565c0;background:rgba(21,101,192,.03)">
    <div class="d-flex align-items-center mb-3">
        <div class="mr-3" style="width:44px;height:44px;border-radius:10px;background:rgba(21,101,192,.1);display:flex;align-items:center;justify-content:center;color:#1565c0;font-size:1.2rem"><i class="fas fa-shipping-fast"></i></div>
        <div><div class="font-weight-bold">Final Step: Confirm Dispatch</div><div class="small text-muted">Confirm that the train has formally departed Djibouti with the cargo</div></div>
    </div>
    <form action="{{ route('imports.dispatch-train', $import->id) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-4" style="background:#1565c0;color:#fff;" {{ $btnDisabled }} title="{{ $btnTitle }}">
            <i class="fas fa-shipping-fast mr-1"></i> Confirm Dispatch
        </button>
    </form>
</div>

@elseif($import->status === Import::S_DISPATCHED)
<div class="action-card text-center" style="border-color:#28a745;background:rgba(40,167,69,.05)">
    <div style="width:60px;height:60px;border-radius:50%;background:#28a745;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:#fff;font-size:1.5rem">
        <i class="fas fa-flag-checkered"></i>
    </div>
    <h5 class="font-weight-bold text-success">Journey Completed!</h5>
    <p class="text-muted small mb-0">This import job has been fully processed and dispatched. All 9 stages complete.</p>
</div>
@endif
