@extends('layouts.app')

@section('title', 'Cargo Journey: ' . $cargo->tracking_id)

@section('content')
<style>
    .timeline-steps { display: flex; justify-content: space-between; position: relative; margin-bottom: 2rem; }
    .timeline-steps::before { content: ""; position: absolute; top: 15px; left: 5%; right: 5%; height: 2px; background: #e9ecef; z-index: 0; }
    .step-item { z-index: 1; text-align: center; width: 14%; }
    .step-circle { width: 32px; height: 32px; border-radius: 50%; background: #fff; border: 2px solid #e9ecef; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-weight: bold; font-size: 14px; transition: all 0.3s; }
    .step-item.active .step-circle { border-color: var(--primary); background: var(--primary); color: #fff; box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.1); }
    .step-item.completed .step-circle { border-color: #28a745; background: #28a745; color: #fff; }
    .step-label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #6c757d; line-height: 1.2; }
    .step-item.active .step-label { color: var(--primary); }
    .step-item.completed .step-label { color: #28a745; }
    
    .history-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 10px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('cargo.index') }}" class="btn btn-light btn-sm">
        <i class="fas fa-arrow-left mr-1"></i> Back to Registry
    </a>
    <div class="btn-group">
        <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-toggle="dropdown">
            <i class="fas fa-cog mr-1"></i> Actions
        </button>
        <div class="dropdown-menu dropdown-menu-right shadow border-0">
            <a class="dropdown-item" href="#"><i class="fas fa-print mr-2 text-muted"></i> Print Waybill</a>
            <a class="dropdown-item" href="#"><i class="fas fa-envelope mr-2 text-muted"></i> Notify Shipper</a>
        </div>
    </div>
</div>

{{-- ═══ STICKY STATUS BAR ═══ --}}
<div class="card shadow-sm border-0 mb-4 overflow-hidden">
    <div class="card-body p-4 bg-white">
        <div class="row align-items-center">
            <div class="col-md-3 border-right">
                <div class="small text-muted text-uppercase font-weight-bold mb-1">Tracking ID</div>
                <h4 class="font-weight-bold mb-0 text-primary">{{ $cargo->tracking_id }}</h4>
                <div class="mt-2">
                    <span class="badge badge-light border">{{ $cargo->consignment_number }}</span>
                </div>
            </div>
            <div class="col-md-6 px-4">
                <div class="timeline-steps mt-2">
                    @php
                        $stages = [
                            'Arrived Origin' => ['num' => 1, 'lbl' => 'Origin Receipt'],
                            'In Transit' => ['num' => 2, 'lbl' => 'In Transit'],
                            'Arrived Destination' => ['num' => 3, 'lbl' => 'Destination Arrival'],
                            'Transferred' => ['num' => 4, 'lbl' => 'Company Transfer'],
                            'Accepted' => ['num' => 5, 'lbl' => 'Company Receipt'],
                            'Dispatched' => ['num' => 6, 'lbl' => 'Final Dispatch'],
                        ];
                        $currentStageNum = $stages[$cargo->current_status]['num'] ?? 1;
                    @endphp
                    
                    @foreach($stages as $status => $stage)
                        <div class="step-item {{ $currentStageNum == $stage['num'] ? 'active' : ($currentStageNum > $stage['num'] ? 'completed' : '') }}">
                            <div class="step-circle">
                                @if($currentStageNum > $stage['num'])
                                    <i class="fas fa-check"></i>
                                @else
                                    {{ $stage['num'] }}
                                @endif
                            </div>
                            <div class="step-label">{{ $stage['lbl'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-md-3 text-right">
                <div class="small text-muted text-uppercase font-weight-bold mb-1">Current Status</div>
                @php
                    $statusCls = match($cargo->current_status) {
                        'Arrived Origin' => 'info',
                        'In Transit' => 'primary',
                        'Arrived Destination' => 'warning',
                        'Transferred' => 'secondary',
                        'Accepted' => 'success',
                        'Dispatched' => 'dark',
                        default => 'light'
                    };
                @endphp
                <div class="h5 font-weight-bold text-{{ $statusCls }} mb-0">
                    <i class="fas fa-circle mr-1 small"></i> {{ $cargo->current_status }}
                </div>
                <div class="small text-muted mt-1">Updated {{ $cargo->updated_at->diffForHumans() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Left Column: Details & Forms --}}
    <div class="col-md-8">
        {{-- Cargo Info --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white font-weight-bold py-3">
                <i class="fas fa-info-circle mr-2 text-muted"></i> Consignment Details
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="text-muted small text-uppercase d-block mb-1">Shipper</label>
                        <div class="font-weight-bold">{{ $cargo->shipper_name }}</div>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="text-muted small text-uppercase d-block mb-1">Consignee</label>
                        <div class="font-weight-bold">{{ $cargo->consignee_name ?? '—' }}</div>
                    </div>
                    <div class="col-4 mb-3">
                        <label class="text-muted small text-uppercase d-block mb-1">Origin Station</label>
                        <div class="font-weight-bold">{{ $cargo->origin_station }}</div>
                    </div>
                    <div class="col-4 mb-3 text-center">
                        <i class="fas fa-arrow-right text-muted mt-3"></i>
                    </div>
                    <div class="col-4 mb-3 text-right">
                        <label class="text-muted small text-uppercase d-block mb-1 text-right">Destination</label>
                        <div class="font-weight-bold">{{ $cargo->destination_station }}</div>
                    </div>
                </div>
                <div class="row mt-2 border-top pt-3">
                    <div class="col-md-4">
                        <div class="p-2 bg-light rounded text-center">
                            <div class="small text-muted text-uppercase mb-1">Type</div>
                            <div class="font-weight-bold">{{ $cargo->goods_type ?? 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 bg-light rounded text-center">
                            <div class="small text-muted text-uppercase mb-1">Weight</div>
                            <div class="font-weight-bold">{{ $cargo->weight ? \App\Support\NumberFormatter::format($cargo->weight) . ' Tons' : '—' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 bg-light rounded text-center">
                            <div class="small text-muted text-uppercase mb-1">Container</div>
                            <div class="font-weight-bold text-monospace">{{ $cargo->container_id ?? 'No Container' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Log Movement / Actions --}}
        <div class="card shadow-sm border-0 border-top-primary">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 font-weight-bold">
                    <i class="fas fa-edit mr-2 text-muted"></i> Update Transit Progress
                </h6>
            </div>
            <div class="card-body">
                @if($cargo->current_status === 'Arrived Origin')
                    <div class="text-center py-4">
                        <i class="fas fa-train fa-3x text-light mb-3"></i>
                        <h5>Stage 1: Origin Receipt Registered</h5>
                        <p class="text-muted small px-5">Assign this cargo to a scheduled train departure to move it to the <strong>In Transit</strong> stage.</p>
                        <button class="btn btn-primary btn-sm px-4 font-weight-bold" data-toggle="modal" data-target="#modalAssignTrain">
                            <i class="fas fa-link mr-1"></i> Link to Train Journey
                        </button>
                    </div>
                @elseif($cargo->current_status === 'In Transit')
                    <div class="text-center py-4">
                        <div class="spinner-grow text-primary mb-3" role="status"></div>
                        <h5>Stage 2: In Transit</h5>
                        <p class="text-muted small px-5">The cargo is currently on <strong>Train {{ $cargo->trainJourney->train_number }}</strong> en route to {{ $cargo->destination_station }}.</p>
                        <div class="alert alert-light border small d-inline-block px-4">
                            Waiting for Train Arrival at Destination...
                        </div>
                    </div>
                @elseif($cargo->current_status === 'Arrived Destination')
                    <div class="text-center py-4">
                        <i class="fas fa-warehouse fa-3x text-warning mb-3 opacity-50"></i>
                        <h5>Stage 3: Arrived at Destination</h5>
                        <p class="text-muted small px-5">The cargo has reached {{ $cargo->destination_station }}. Next step is to <strong>Handover</strong> the cargo to the receiving logistics firm.</p>
                        <button class="btn btn-warning btn-sm px-4 font-weight-bold" data-toggle="modal" data-target="#modalHandover">
                            <i class="fas fa-hand-holding mr-1"></i> Transfer to Company
                        </button>
                    </div>
                @elseif($cargo->current_status === 'Transferred')
                    <div class="text-center py-4">
                        <i class="fas fa-building fa-3x text-secondary mb-3 opacity-50"></i>
                        <h5>Stage 4: Handed Over to {{ $cargo->logisticsCompany->name }}</h5>
                        <p class="text-muted small px-5">Waiting for the company to formally <strong>Accept</strong> the goods in the system.</p>
                        <button class="btn btn-secondary btn-sm px-4 font-weight-bold" data-toggle="modal" data-target="#modalAcceptance">
                            <i class="fas fa-clipboard-check mr-1"></i> Confirm Company Acceptance
                        </button>
                    </div>
                @elseif($cargo->current_status === 'Accepted')
                    <div class="text-center py-4">
                        <i class="fas fa-check-double fa-3x text-success mb-3 opacity-50"></i>
                        <h5>Stage 5: Accepted by Company</h5>
                        <p class="text-muted small px-5">The company has confirmed receipt. Final step is to record the <strong>International Dispatch</strong>.</p>
                        <button class="btn btn-success btn-sm px-4 font-weight-bold" data-toggle="modal" data-target="#modalDispatch">
                            <i class="fas fa-globe-africa mr-1"></i> Final Dispatch
                        </button>
                    </div>
                @elseif($cargo->current_status === 'Dispatched')
                    <div class="text-center py-4">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px; height:60px;">
                            <i class="fas fa-flag-checkered fa-2x"></i>
                        </div>
                        <h5 class="text-success font-weight-bold">Journey Completed</h5>
                        <p class="text-muted small px-5">This consignment has been fully processed and dispatched to its final destination.</p>
                        <div class="badge badge-success px-3 py-2">Cycle Completed</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Right Column: Audit Log --}}
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white font-weight-bold py-3 d-flex justify-content-between">
                <span><i class="fas fa-history mr-2 text-muted"></i> Activity Timeline</span>
                <span class="badge badge-pill badge-light border">{{ $cargo->movementLogs->count() }} events</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @foreach($cargo->movementLogs as $log)
                    <div class="list-group-item p-3 border-0">
                        <div class="d-flex mb-1">
                             @php
                                $dotCls = match($log->status) {
                                    'Arrived Origin' => 'bg-info',
                                    'In Transit' => 'bg-primary',
                                    'Arrived Destination' => 'bg-warning',
                                    'Transferred' => 'bg-secondary',
                                    'Accepted' => 'bg-success',
                                    'Dispatched' => 'bg-dark',
                                    default => 'bg-light'
                                };
                            @endphp
                            <span class="history-dot {{ $dotCls }} mt-1"></span>
                            <div>
                                <div class="font-weight-bold small text-dark">{{ $log->status }}</div>
                                <div class="text-muted" style="font-size:11px;">
                                    {{ $log->recorded_at->format('M d, Y | H:i') }}
                                </div>
                            </div>
                        </div>
                        <div class="pl-4 small text-muted">
                            @if($log->location)
                            <div class="mb-1"><i class="fas fa-map-marker-alt mr-1"></i> {{ $log->location }}</div>
                            @endif
                            <div>{{ $log->remarks }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ MODALS ═══ --}}
<div class="modal fade" id="modalAssignTrain" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('cargo.assign-train', $cargo->id) }}" method="POST" class="modal-content shadow border-0">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold text-primary">Assign to Train Journey</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body py-4">
                <p class="small text-muted mb-4">
                    Select a train journey departing from <strong>{{ $cargo->origin_station }}</strong>.
                </p>
                
                <div class="form-group mb-4">
                    <label class="small font-weight-bold text-uppercase">Available Scheduled Trains</label>
                    <select name="train_journey_id" class="form-control select2" required>
                        <option value="">-- Select Train --</option>
                        @foreach($availableTrains as $train)
                            <option value="{{ $train->id }}">
                                {{ $train->train_number }} ({{ $train->departure_time->format('M d, H:i') }}) &rarr; {{ $train->destination_station }}
                            </option>
                        @endforeach
                    </select>
                    @if($availableTrains->isEmpty())
                        <div class="text-danger small mt-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i> No trains currently scheduled from this station.
                        </div>
                    @endif
                </div>
                
                <div class="alert alert-light border small bg-light">
                    <i class="fas fa-info-circle mr-1 text-primary"></i> Don't see a train? 
                    <a href="{{ route('trains.create') }}" class="font-weight-bold">Schedule a new journey</a> first.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary font-weight-bold px-4" {{ $availableTrains->isEmpty() ? 'disabled' : '' }}>
                    <i class="fas fa-link mr-1"></i> Assign & Mark In Transit
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: HANDOVER TO COMPANY ═══ --}}
<div class="modal fade" id="modalHandover" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('cargo.transfer', $cargo->id) }}" method="POST" class="modal-content shadow border-0">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold text-warning">Handover to Logistics Company</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body py-4">
                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-uppercase">Receiving Company</label>
                    <select name="logistics_company_id" class="form-control select2" required>
                        <option value="">-- Select Company --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-uppercase">Company Representative / Personnel</label>
                    <input type="text" name="personnel_name" class="form-control" placeholder="Name of person receiving goods" required>
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold text-uppercase">Transfer Location / Warehouse</label>
                    <input type="text" name="location" class="form-control" value="{{ $cargo->destination_station }}">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning font-weight-bold px-4">Confirm Handover</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: COMPANY ACCEPTANCE ═══ --}}
<div class="modal fade" id="modalAcceptance" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('cargo.accept', $cargo->id) }}" method="POST" class="modal-content shadow border-0">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold text-secondary">Acknowledge Company Acceptance</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body py-4 text-center">
                <i class="fas fa-file-signature fa-3x text-light mb-3"></i>
                <p class="small text-muted mb-4">Confirm that the logistics company has formally inspected and accepted the consignment.</p>
                
                <div class="form-group text-left">
                    <label class="small font-weight-bold text-uppercase">Goods Condition / Remarks</label>
                    <textarea name="condition" class="form-control" rows="2" placeholder="e.g. Received in good condition, seals intact"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-secondary font-weight-bold px-4">Confirm Acceptance</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: FINAL DISPATCH ═══ --}}
<div class="modal fade" id="modalDispatch" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('cargo.dispatch', $cargo->id) }}" method="POST" class="modal-content shadow border-0">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold text-success">Record Final Dispatch</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body py-4">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold text-uppercase">Destination Country</label>
                        <input type="text" name="destination_country" class="form-control" placeholder="e.g. China, UAE, Germany" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold text-uppercase">Dispatch Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Doraleh Container Terminal" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="small font-weight-bold text-uppercase">Mode of Transport</label>
                        <select name="mode" class="form-control" required>
                            <option value="Sea">Sea / Vessel</option>
                            <option value="Air">Air</option>
                            <option value="Road">Road / Truck</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="small font-weight-bold text-uppercase">Carrier / Vessel Name</label>
                        <input type="text" name="carrier" class="form-control" placeholder="e.g. Maersk Denver">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="small font-weight-bold text-uppercase">Estimated Arrival</label>
                        <input type="date" name="eta" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success font-weight-bold px-4">Confirm Dispatch</button>
            </div>
        </form>
    </div>
</div>
@endsection
