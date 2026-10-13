@extends('layouts.app')

@section('title', 'Departments')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
<li class="breadcrumb-item active">Departments</li>
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="text-dark"><i class="fas fa-building mr-2 text-primary"></i> Organizational Units</h3>
        <p class="text-muted">Manage your departments, their hierarchy, and strategic weights.</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="{{ route('departments.create') }}" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus-circle mr-1"></i> Add New Department
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-uppercase small font-weight-bold">
                    <tr>
                        <th class="px-4">Department Name</th>
                        <th>Code</th>
                        <th>Parent</th>
                        <th>Weight</th>
                        <th>Status</th>
                        <th class="text-right px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departments as $dept)
                    <tr>
                        <td class="px-4">
                            <div class="d-flex align-items-center">
                                <div class="dept-icon mr-3 bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-sitemap"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 font-weight-bold">{{ $dept->name }}</h6>
                                    <span class="text-muted small">ID: #{{ str_pad($dept->id, 3, '0', STR_PAD_LEFT) }}</span>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-light border">{{ $dept->code ?? 'N/A' }}</span></td>
                        <td>
                            @if($dept->parent)
                                <span class="text-info"><i class="fas fa-level-up-alt fa-rotate-90 mr-1"></i> {{ $dept->parent->name }}</span>
                            @else
                                <span class="text-muted italic">Top Level</span>
                            @endif
                        </td>
                        <td>
                            <div class="progress progress-xs mb-1" style="width: 60px;">
                                <div class="progress-bar bg-primary" style="width: {{ $dept->objective_weight }}%"></div>
                            </div>
                            <span class="small font-weight-bold">{{ $dept->objective_weight }}%</span>
                        </td>
                        <td>
                            <span class="badge {{ $dept->is_active ? 'badge-success' : 'badge-danger' }} badge-pill">
                                {{ $dept->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right px-4">
                            <div class="btn-group btn-group-sm shadow-sm">
                                <a href="{{ route('departments.show', $dept) }}" class="btn btn-white text-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('departments.edit', $dept) }}" class="btn btn-white text-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('departments.destroy', $dept) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-white text-danger" title="Delete" data-confirm="Delete this department? Ensure it has no active plans or staff." data-confirm-title="Delete department" data-confirm-text="Delete" data-confirm-class="btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <img src="https://cdni.iconscout.com/illustration/premium/thumb/empty-state-2130362-1800926.png" style="width: 200px;" class="mb-3 opacity-50">
                            <p class="text-muted">No departments found. Start by creating your first organizational unit.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .bg-soft-primary { background-color: rgba(78, 115, 223, 0.1); }
    .btn-white { background: #fff; border: 1px solid #e3e6f0; }
    .btn-white:hover { background: #f8f9fc; }
    .align-middle { vertical-align: middle !important; }
</style>
@endpush
