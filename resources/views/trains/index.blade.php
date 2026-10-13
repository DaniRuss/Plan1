@extends('layouts.app')

@section('title', 'Train Journey Management')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 font-weight-bold text-dark">
            <i class="fas fa-train mr-2 text-primary"></i> Train Journeys
        </h5>
        <a href="{{ route('trains.create') }}" class="btn btn-primary btn-sm px-3">
            <i class="fas fa-plus mr-1"></i> Schedule New Train
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light text-muted small text-uppercase font-weight-bold">
                    <tr>
                        <th class="pl-4">Train #</th>
                        <th>Origin &rarr; Destination</th>
                        <th>Departure</th>
                        <th>Arrival</th>
                        <th>Status</th>
                        <th>Consignments</th>
                        <th class="text-right pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse($journeys as $journey)
                    <tr>
                        <td class="pl-4 font-weight-bold">{{ $journey->train_number }}</td>
                        <td>
                            <span class="small font-weight-bold">{{ $journey->origin_station }}</span>
                            <i class="fas fa-arrow-right mx-1 text-muted small"></i>
                            <span class="small font-weight-bold">{{ $journey->destination_station }}</span>
                        </td>
                        <td class="small">
                            {{ $journey->departure_time ? $journey->departure_time->format('M d, H:i') : 'Pending' }}
                        </td>
                        <td class="small">
                            {{ $journey->arrival_time ? $journey->arrival_time->format('M d, H:i') : 'Pending' }}
                        </td>
                        <td>
                            @php
                                $statusCls = match($journey->status) {
                                    'scheduled' => 'badge-info',
                                    'in_transit' => 'badge-primary',
                                    'completed' => 'badge-success',
                                    default => 'badge-light'
                                };
                            @endphp
                            <span class="badge {{ $statusCls }} px-2 py-1">
                                {{ ucfirst(str_replace('_', ' ', $journey->status)) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-pill badge-light border">
                                {{ $journey->consignments->count() }} cargo items
                            </span>
                        </td>
                        <td class="text-right pr-4">
                            @if($journey->status === 'scheduled')
                                <form action="{{ route('trains.depart', $journey->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-primary">
                                        <i class="fas fa-play mr-1"></i> Depart
                                    </button>
                                </form>
                            @elseif($journey->status === 'in_transit')
                                <form action="{{ route('trains.arrive', $journey->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-success">
                                        <i class="fas fa-flag-checkered mr-1"></i> Arrived
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted small">
                            No train journeys scheduled.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
