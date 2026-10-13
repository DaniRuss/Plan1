@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Employee</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Employees</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <form action="{{ route('employees.update', $employee->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="employee_number">Employee Number <span class="text-danger">*</span></label>
                                    <input type="text" name="employee_number" id="employee_number"
                                        class="form-control @error('employee_number') is-invalid @enderror"
                                        value="{{ old('employee_number', $employee->employee_number) }}" required>
                                    @error('employee_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="employment_status">Employment Status <span
                                            class="text-danger">*</span></label>
                                    <select name="employment_status" id="employment_status"
                                        class="form-control @error('employment_status') is-invalid @enderror" required>
                                        <option value="active" {{ old('employment_status', $employee->employment_status) === 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="on_leave" {{ old('employment_status', $employee->employment_status) === 'on_leave' ? 'selected' : '' }}>On Leave
                                        </option>
                                        <option value="terminated" {{ old('employment_status', $employee->employment_status) === 'terminated' ? 'selected' : '' }}>Terminated
                                        </option>
                                    </select>
                                    @error('employment_status')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" id="first_name"
                                        class="form-control @error('first_name') is-invalid @enderror"
                                        value="{{ old('first_name', $employee->first_name) }}" required>
                                    @error('first_name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" id="last_name"
                                        class="form-control @error('last_name') is-invalid @enderror"
                                        value="{{ old('last_name', $employee->last_name) }}" required>
                                    @error('last_name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" name="email" id="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $employee->email) }}">
                                    @error('email')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone">Phone</label>
                                    <input type="text" name="phone" id="phone"
                                        class="form-control @error('phone') is-invalid @enderror"
                                        value="{{ old('phone', $employee->phone) }}">
                                    @error('phone')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="parent_department_id">Parent Department <span class="text-danger">*</span></label>
                                    <select id="parent_department_id" class="form-control select2" required>
                                        <option value="">-- Select Parent Department --</option>
                                        @foreach($parentDepartments as $dept)
                                            <option value="{{ $dept->id }}" {{ $currentParentId == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="department_id">Department / Unit</label>
                                    <select name="department_id" id="department_id" class="form-control select2 @error('department_id') is-invalid @enderror">
                                        <option value="">-- Select Parent First --</option>
                                        @foreach($childDepartments as $dept)
                                            <option value="{{ $dept->id }}" {{ $employee->department_id == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->name }} ({{ $dept->code ?? 'N/A' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('department_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="job_title">Job Title</label>
                                    <input type="text" name="job_title" id="job_title" class="form-control @error('job_title') is-invalid @enderror" value="{{ old('job_title', $employee->job_title) }}">
                                    @error('job_title')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supervisor_id">Supervisor</label>
                                    <select name="supervisor_id" id="supervisor_id"
                                        class="form-control @error('supervisor_id') is-invalid @enderror">
                                        <option value="">-- Select Supervisor --</option>
                                        @foreach($supervisors as $sup)
                                            <option value="{{ $sup->id }}" {{ old('supervisor_id', $employee->supervisor_id) == $sup->id ? 'selected' : '' }}>
                                                {{ $sup->full_name }} ({{ $sup->employee_number }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('supervisor_id')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="hire_date">Hire Date</label>
                                    <input type="date" name="hire_date" id="hire_date"
                                        class="form-control @error('hire_date') is-invalid @enderror"
                                        value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}">
                                    @error('hire_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Employee
                        </button>
                        <a href="{{ route('employees.index') }}" class="btn btn-default">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </section>

@push('scripts')
<script>
$(document).ready(function() {
    // Parent Department Change
    $('#parent_department_id').on('change', function() {
        const parentId = $(this).val();
        const $childSelect = $('#department_id');
        
        $childSelect.empty().append('<option value="">-- Loading Units... --</option>').prop('disabled', true);
        
        if (!parentId) {
            $childSelect.empty().append('<option value="">-- Select Parent First --</option>');
            return;
        }

        $.get(`/api/departments/${parentId}/children`, function(data) {
            $childSelect.empty().append('<option value="">-- Select Unit / Sub-Department --</option>');
            data.forEach(function(unit) {
                $childSelect.append(`<option value="${unit.id}">${unit.name} (${unit.code || 'N/A'})</option>`);
            });
            $childSelect.prop('disabled', false);
        });
    });

    // Child Department Change -> Option to Refresh Employee Number
    $('#department_id').on('change', function() {
        const deptId = $(this).val();
        const currentDeptId = "{{ $employee->department_id }}";
        
        if (!deptId || deptId == currentDeptId) return;

        window.iprpmsConfirm('Regenerate the employee number based on the new department?', {
            title: 'Regenerate employee number', confirmText: 'Regenerate'
        }).then(function (confirmed) {
            if (!confirmed) return;
            $.get(`/api/employees/next-number/${deptId}`, function(data) {
                $('#employee_number').val(data.number);
            });
        });
    });
});
</script>
@endpush
@endsection
