@extends('layouts.app')

@section('title', 'Audit Logs')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Audit Logs</li>
@endsection

@section('content')
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h3 class="card-title"><i class="fas fa-history mr-2"></i> System Activity Logs</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 180px;">Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Resource</th>
                            <th>ID</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td class="text-muted small">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    @if($log->user)
                                        <span class="badge badge-info">{{ $log->user->name }}</span>
                                    @else
                                        <span class="badge badge-secondary">System</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match ($log->action) {
                                            'created' => 'badge-success',
                                            'updated' => 'badge-warning',
                                            'deleted' => 'badge-danger',
                                            'approved' => 'badge-primary',
                                            default => 'badge-dark'
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ strtoupper($log->action) }}</span>
                                </td>
                                <td>
                                    @if($log->model_type)
                                        <code class="small">{{ class_basename($log->model_type) }}</code>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $log->model_id ?? '-' }}</td>
                                <td class="small">{{ Str::limit($log->details, 100) }}</td>
                                <td class="text-muted small">{{ $log->ip_address }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">No audit logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer clearfix">
            <div class="float-right">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection