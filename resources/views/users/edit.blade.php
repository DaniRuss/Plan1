@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Edit System User: {{ $user->name }}</h3>
                    </div>
                    <form action="{{ route('users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            <div class="form-group">
                                <label for="employee_id">Link to Employee (Optional)</label>
                                <select name="employee_id" id="employee_id" class="form-control select2">
                                    <option value="">-- No Link --</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ (old('employee_id') ?? $user->employee_id) == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->employee_number }} - {{ $employee->first_name }}
                                            {{ $employee->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                    id="name" value="{{ old('name', $user->name) }}" required>
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                    id="email" value="{{ old('email', $user->email) }}" required>
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
                                        <option value="{{ $department->id }}"
                                            {{ (int) old('department_id', $user->department_id) === $department->id ? 'selected' : '' }}>
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
                                        <label for="password">Password (Leave blank to keep current)</label>
                                        <input type="password" name="password"
                                            class="form-control @error('password') is-invalid @enderror" id="password">
                                        @error('password')
                                            <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="password_confirmation">Confirm Password</label>
                                        <input type="password" name="password_confirmation" class="form-control"
                                            id="password_confirmation">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Assigned Roles</label>
                                <div class="row">
                                    @foreach($roles as $role)
                                        <div class="col-md-4">
                                            <div class="custom-control custom-checkbox">
                                                @php
                                                    $isChecked = is_array(old('roles'))
                                                        ? in_array($role->id, old('roles'))
                                                        : $user->roles->contains($role->id);
                                                @endphp
                                                <input class="custom-control-input" type="checkbox" name="roles[]"
                                                    id="role_{{ $role->id }}" value="{{ $role->id }}" {{ $isChecked ? 'checked' : '' }}>
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
                                    value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">Account is active</label>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('users.index') }}" class="btn btn-default">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update User</button>
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
        });
    </script>
@endpush
