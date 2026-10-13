@extends('layouts.app')

@section('title', 'Logistics Operations Dashboard')

@section('content')
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-left-info">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="bg-info-light p-3 rounded mr-3">
                        <i class="fas fa-boxes fa-2x text-infoopacity-50"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase font-weight-bold">Total Consignments</div>
                        <h3 class="mb-0 font-weight-bold">{{ $stats['total'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-left-primary">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="bg-primary-light p-3 rounded mr-3">
                        <i class="fas fa-shipping-fast fa-2x text-primary-opacity-50"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase font-weight-bold">In Transit</div>
                        <h3 class="mb-0 font-weight-bold">{{ $stats['in_transit'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-left-warning">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="bg-warning-light p-3 rounded mr-3">
                        <i class="fas fa-warehouse fa-2x text-warning-opacity-50"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase font-weight-bold">Awaiting Transfer</div>
                        <h3 class="mb-0 font-weight-bold">{{ $stats['awaiting_transfer'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 border-left-success">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="bg-success-light p-3 rounded mr-3">
                        <i class="fas fa-check-double fa-2x text-success-opacity-50"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase font-weight-bold">Dispatched</div>
                        <h3 class="mb-0 font-weight-bold">{{ $stats['dispatched'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Recent Activity --}}
    <div class="col-md-7">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white font-weight-bold py-3">
                <i class="fas fa-stream mr-2 text-primary"></i> Live Movement Log
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th>Cargo ID</th>
                                <th>Status Update</th>
                                <th>Location</th>
                                <th>Timestamp</th>
                                <th>Operator</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($latestLogs as $log)
                            <tr>
                                <td class="font-weight-bold small">
                                    <a href="{{ route('cargo.show', $log->cargo_consignment_id) }}">{{ $log->consignment->tracking_id }}</a>
                                </td>
                                <td>
                                    <span class="badge badge-light border">{{ $log->status }}</span>
                                </td>
                                <td class="small">{{ $log->location ?? '—' }}</td>
                                <td class="small text-muted">{{ $log->recorded_at->format('H:i | d M') }}</td>
                                <td class="small">
                                    <span class="text-muted"><i class="fas fa-user-circle mr-1"></i> {{ $log->operator->name ?? 'System' }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small italic">No activities recorded yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Shortcuts --}}
    <div class="col-md-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white font-weight-bold py-3">
                <i class="fas fa-bolt mr-2 text-warning"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="{{ route('cargo.create') }}" class="list-group-item list-group-item-action border-0 mb-2 bg-light rounded d-flex align-items-center">
                        <div class="bg-primary text-white p-2 rounded mr-3"><i class="fas fa-plus fa-lg"></i></div>
                        <div>
                            <div class="font-weight-bold">Register New Cargo</div>
                            <div class="small text-muted">Arrival at origin station registration</div>
                        </div>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action border-0 mb-2 bg-light rounded d-flex align-items-center">
                        <div class="bg-info text-white p-2 rounded mr-3"><i class="fas fa-train fa-lg"></i></div>
                        <div>
                            <div class="font-weight-bold">Manage Train Journeys</div>
                            <div class="small text-muted">Schedule and update train departures</div>
                        </div>
                    </a>
                    <a href="{{ route('cargo.index') }}" class="list-group-item list-group-item-action border-0 bg-light rounded d-flex align-items-center">
                        <div class="bg-secondary text-white p-2 rounded mr-3"><i class="fas fa-file-invoice fa-lg"></i></div>
                        <div>
                            <div class="font-weight-bold">Consignment Registry</div>
                            <div class="small text-muted">View all tracking records</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        {{-- Distribution Chart Placeholder --}}
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="font-weight-bold text-muted mb-3 small text-uppercase">Logistics Network Overview</h6>
                <div class="text-center py-4">
                    <i class="fas fa-project-diagram fa-3x text-light"></i>
                    <p class="mt-2 text-muted small">Route analysis and transit time metrics will appear here.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
