@extends('layouts.app')

@section('title', 'Create User')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Add New System User</h3>
                    </div>
                    <form action="{{ route('users.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="form-group">
                                <label for="employee_id">Link to Employee (Optional)</label>
                                <select name="employee_id" id="employee_id" class="form-control select2">
                                    <option value="">-- No Link --</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->employee_number }} - {{ $employee->first_name }}
                                            {{ $employee->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Linking ensures performance records are tied to this user.</small>
                            </div>

                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                    id="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                    id="email" value="{{ old('email') }}" required>
                                @error('email')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="department_id">Department</label>
                                <select name="department_id" id="department_id"
                                    class="form-control select2 @error('department_id') is-invalid @enderror" required>
                                    <option value="">-- Select Department --</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('department_id')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="password">Password</label>
                                        <input type="password" name="password"
                                            class="form-control @error('password') is-invalid @enderror" id="password"
                                            required>
                                        @error('password')
                                            <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="password_confirmation">Confirm Password</label>
                                        <input type="password" name="password_confirmation" class="form-control"
                                            id="password_confirmation" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Assigned Roles</label>
                                <div class="row">
                                    @foreach($roles as $role)
                                        <div class="col-md-4">
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" name="roles[]"
                                                    id="role_{{ $role->id }}" value="{{ $role->id }}" {{ is_array(old('roles')) && in_array($role->id, old('roles')) ? 'checked' : '' }}>
                                                <label for="role_{{ $role->id }}"
                                                    class="custom-control-label">{{ $role->name }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @error('roles')
                                    <span class="text-danger small">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active"
                                    value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">Activate account immediately</label>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('users.index') }}" class="btn btn-default">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('.select2').select2({
                theme: 'bootstrap4'
            });

            // Auto-fill name and email if employee is selected
            $('#employee_id').on('change', function () {
                var selectedText = $(this).find('option:selected').text();
                if (selectedText && selectedText !== '-- No Link --') {
                    var parts = selectedText.split(' - ');
                    if (parts.length > 1 && $('#name').val() == '') {
                        $('#name').val(parts[1].trim());
                    }
                }
            });
        });
    </script>
@endpush
