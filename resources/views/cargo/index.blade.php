@extends('layouts.app')

@section('title', 'Cargo Consignment Registry')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 font-weight-bold text-dark">
            <i class="fas fa-boxes mr-2 text-primary"></i> Cargo Consignments
        </h5>
        <div>
            <a href="{{ route('cargo.dashboard') }}" class="btn btn-outline-primary btn-sm mr-2">
                <i class="fas fa-chart-line mr-1"></i> Logistics Board
            </a>
            <a href="{{ route('cargo.create') }}" class="btn btn-primary btn-sm px-3">
                <i class="fas fa-plus mr-1"></i> Register New Cargo
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light text-muted small text-uppercase font-weight-bold">
                    <tr>
                        <th class="pl-4">Tracking ID</th>
                        <th>Consignment #</th>
                        <th>Shipper</th>
                        <th>Origin &rarr; Destination</th>
                        <th>Current Status</th>
                        <th>Last Updated</th>
                        <th class="text-right pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse($consignments as $cargo)
                    <tr>
                        <td class="pl-4 font-weight-bold text-primary">{{ $cargo->tracking_id }}</td>
                        <td>{{ $cargo->consignment_number }}</td>
                        <td>{{ $cargo->shipper_name }}</td>
                        <td>
                            <div class="small">
                                <span class="badge badge-light border">{{ $cargo->origin_station }}</span>
                                <i class="fas fa-arrow-right mx-1 text-muted small"></i>
                                <span class="badge badge-light border">{{ $cargo->destination_station }}</span>
                            </div>
                        </td>
                        <td>
                            @php
                                $statusCls = match($cargo->current_status) {
                                    'Arrived Origin' => 'badge-info',
                                    'In Transit' => 'badge-primary',
                                    'Arrived Destination' => 'badge-warning',
                                    'Transferred' => 'badge-secondary',
                                    'Accepted' => 'badge-success',
                                    'Dispatched' => 'badge-dark',
                                    default => 'badge-light'
                                };
                            @endphp
                            <span class="badge {{ $statusCls }} px-2 py-1">
                                {{ $cargo->current_status }}
                            </span>
                        </td>
                        <td class="small text-muted">
                            {{ $cargo->updated_at->diffForHumans() }}
                        </td>
                        <td class="text-right pr-4">
                            <a href="{{ route('cargo.show', $cargo->id) }}" class="btn btn-outline-primary btn-xs">
                                <i class="fas fa-eye"></i> View Timeline
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-box-open fa-3x mb-3 d-block opacity-25"></i>
                            No cargo consignments registered yet. 
                            <a href="{{ route('cargo.create') }}" class="font-weight-bold">Register one now</a>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($consignments->hasPages())
    <div class="card-footer bg-white border-top py-3">
        {{ $consignments->links() }}
    </div>
    @endif
</div>
@endsection
