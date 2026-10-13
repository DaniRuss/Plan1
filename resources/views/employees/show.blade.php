@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">Employee Profile</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Back to List
                    </a>
                    <a href="{{ route('reports.employee.show', $employee->id) }}" class="btn btn-primary btn-sm ml-2">
                        <i class="fas fa-chart-line mr-1"></i> View Performance Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-3">
                    <!-- Profile Image -->
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 3rem; color: #adb5bd;">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>

                            <h3 class="profile-username text-center font-weight-bold">{{ $employee->full_name }}</h3>
                            <p class="text-muted text-center">{{ $employee->job_title }}</p>

                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item">
                                    <b>Emp #</b> <a class="float-right text-dark font-weight-bold">{{ $employee->employee_number }}</a>
                                </li>
                                <li class="list-group-item">
                                    <b>Status</b> 
                                    <span class="float-right badge {{ $employee->employment_status === 'active' ? 'badge-success' : 'badge-warning' }}">
                                        {{ ucfirst($employee->employment_status) }}
                                    </span>
                                </li>
                                <li class="list-group-item">
                                    <b>Joined</b> <a class="float-right text-muted">{{ $employee->hire_date ? date('M d, Y', strtotime($employee->hire_date)) : '-' }}</a>
                                </li>
                            </ul>

                            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-warning btn-block"><b>Edit Profile</b></a>
                        </div>
                    </div>
                </div>

                <div class="col-md-9">
                    <div class="card shadow-sm border-0">
                        <div class="card-header p-2 bg-white">
                            <ul class="nav nav-pills small">
                                <li class="nav-item"><a class="nav-link active" href="#details" data-toggle="tab">General Details</a></li>
                                <li class="nav-item"><a class="nav-link" href="#subordinates" data-toggle="tab">Team Members ({{ $employee->subordinates->count() }})</a></li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="active tab-pane" id="details">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-primary font-weight-bold border-bottom pb-2">Contact Information</h6>
                                            <p><strong>Email:</strong> {{ $employee->email ?? 'N/A' }}</p>
                                            <p><strong>Phone:</strong> {{ $employee->phone ?? 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-primary font-weight-bold border-bottom pb-2">Organizational Info</h6>
                                            <p><strong>Department:</strong> {{ $employee->department->name ?? 'N/A' }}</p>
                                            <p><strong>Supervisor:</strong> {{ $employee->supervisor->full_name ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    
                                    @if($employee->user)
                                        <div class="mt-4 p-3 bg-light rounded shadow-xs">
                                            <h6 class="text-success font-weight-bold"><i class="fas fa-lock mr-2"></i> System Account Linked</h6>
                                            <p class="mb-0 small text-muted">Username: <strong>{{ $employee->user->email }}</strong> | Last Login: {{ $employee->user->last_login_at ? $employee->user->last_login_at->format('M d, Y H:i') : 'Never' }}</p>
                                        </div>
                                    @endif
                                </div>

                                <div class="tab-pane" id="subordinates">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Emp #</th>
                                                <th>Name</th>
                                                <th>Job Title</th>
                                                <th>Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($employee->subordinates as $sub)
                                                <tr>
                                                    <td>{{ $sub->employee_number }}</td>
                                                    <td>{{ $sub->full_name }}</td>
                                                    <td>{{ $sub->job_title }}</td>
                                                    <td>
                                                        <a href="{{ route('reports.employee.show', $sub->id) }}" class="btn btn-xs btn-outline-primary">
                                                            View Report
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted italic">No team members assigned.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
