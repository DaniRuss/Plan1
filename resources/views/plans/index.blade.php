@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Plans</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Plans</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('success') }}
                </div>
            @endif

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Manage Plans</h3>
                            <div class="card-tools d-flex">
                                @if(auth()->user()->isSystemAdmin())
                                    <form action="{{ route('plans.index') }}" method="GET" class="mr-2">
                                        <select name="department_id" class="form-control form-control-sm" onchange="this.form.submit()">
                                            <option value="">All Departments</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                                    {{ $dept->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                                <a href="{{ route('plans.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Create New Plan
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Period</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($plans as $plan)
                                        <tr>
                                            <td>{{ $plan->title ?? 'Untitled Plan' }}</td>
                                            <td>{{ ucfirst($plan->type) }}</td>
                                            <td>{{ $plan->year }} EFY</td>
                                            <td>{{ $plan->department->name }}</td>
                                            <td>
                                                @if($plan->status == 'draft')
                                                    <span class="badge badge-secondary">Draft</span>
                                                @elseif($plan->status == 'submitted')
                                                    <span class="badge badge-info">Submitted</span>
                                                @elseif($plan->status == 'approved')
                                                    <span class="badge badge-success">Approved</span>
                                                @elseif($plan->status == 'rejected')
                                                    <span class="badge badge-danger">Rejected</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('plans.show', $plan->id) }}" class="btn btn-info btn-sm">
                                                    <i class="fas fa-edit"></i> Open
                                                </a>
                                                @if($plan->status === 'approved')
                                                    <a href="{{ route('plans.download', $plan) }}" class="btn btn-danger btn-sm" title="Download complete EDR Plan"><i class="fas fa-file-pdf"></i> PDF</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">No plans found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            <div class="mt-3">
                                {{ $plans->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
