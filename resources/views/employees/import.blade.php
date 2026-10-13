@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Import Employees</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Employees</a></li>
                        <li class="breadcrumb-item active">Import</li>
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
                <div class="alert alert-danger">{!! session('error') !!}</div>
            @endif

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Upload Excel File</h3>
                        </div>
                        <form action="{{ route('employees.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="file">Excel File (.xlsx, .xls, .csv)</label>
                                    <input type="file" name="file" id="file"
                                        class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv"
                                        required>
                                    @error('file')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-upload"></i> Import Employees
                                </button>
                                <a href="{{ route('employees.index') }}" class="btn btn-default">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Instructions</h3>
                        </div>
                        <div class="card-body">
                            <ol class="pl-3">
                                <li>Download the template file</li>
                                <li>Fill in employee data</li>
                                <li>Upload the completed file</li>
                            </ol>
                            <hr>
                            <h5>Required Columns:</h5>
                            <ul class="pl-3">
                                <li>Employee Number (unique)</li>
                                <li>First Name</li>
                                <li>Last Name</li>
                            </ul>
                            <h5>Optional Columns:</h5>
                            <ul class="pl-3">
                                <li>Email</li>
                                <li>Phone</li>
                                <li>Department Code</li>
                                <li>Job Title</li>
                                <li>Supervisor Number</li>
                                <li>Hire Date (YYYY-MM-DD)</li>
                                <li>Status (active/on_leave/terminated)</li>
                            </ul>
                            <hr>
                            <a href="{{ route('employees.download-template') }}" class="btn btn-primary btn-block">
                                <i class="fas fa-download"></i> Download Template
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection