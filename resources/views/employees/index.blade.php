@extends('layouts.app')
@section('page_header_hidden', '1')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Employees</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Employees</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Employee List</h3>
                    <div class="card-tools">
                        <a href="{{ route('employees.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add Employee
                        </a>
                        <a href="{{ route('employees.import.form') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel"></i> Import Excel
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr class="bg-light">
                                <th style="width: 100px">Emp #</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Job Title</th>
                                <th>Supervisor</th>
                                <th style="width: 100px">Status</th>
                                <th style="width: 180px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $employee)
                                <tr>
                                    <td><strong>{{ $employee->employee_number }}</strong></td>
                                    <td>{{ $employee->full_name }}</td>
                                    <td>{{ $employee->department->name ?? '-' }}</td>
                                    <td>{{ $employee->job_title ?? '-' }}</td>
                                    <td>{{ $employee->supervisor->full_name ?? '-' }}</td>
                                    <td>
                                        @if($employee->employment_status === 'active')
                                            <span class="badge badge-success">Active</span>
                                        @elseif($employee->employment_status === 'on_leave')
                                            <span class="badge badge-warning">On Leave</span>
                                        @else
                                            <span class="badge badge-danger">Terminated</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('reports.employee.show', $employee->id) }}"
                                                class="btn btn-sm btn-primary" title="Performance Report">
                                                <i class="fas fa-chart-line"></i>
                                            </a>
                                            <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-info"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-warning"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            @if(!$employee->user)
                                                <form action="{{ route('employees.create-account', $employee) }}" method="POST"
                                                    class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success"
                                                        title="Create System Account"
                                                        data-confirm="Create a system login account for this employee?" data-confirm-title="Create account" data-confirm-text="Create account" data-confirm-class="btn-success">
                                                        <i class="fas fa-user-plus"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <button class="btn btn-sm btn-default disabled" title="Account Already Exists">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                </button>
                                            @endif

                                            <form action="{{ route('employees.destroy', $employee) }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete"
                                                    data-confirm="Delete this employee? This action cannot be undone." data-confirm-title="Delete employee" data-confirm-text="Delete" data-confirm-class="btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">No employees found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $employees->links() }}
                </div>
            </div>
        </div>
    </section>
@endsection
