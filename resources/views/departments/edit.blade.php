@extends('layouts.app')

@section('title', 'Edit Department')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
<li class="breadcrumb-item"><a href="{{ route('departments.index') }}">Departments</a></li>
<li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0 rounded-lg">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 font-weight-bold text-dark">
                    <i class="fas fa-edit mr-2 text-warning"></i> Update Department: {{ $department->name }}
                </h5>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('departments.update', $department) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group mb-4">
                                <label for="name" class="font-weight-bold small text-uppercase text-muted">Department Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror form-control-lg" value="{{ old('name', $department->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-4">
                                <label for="code" class="font-weight-bold small text-uppercase text-muted">Abreviation / Code</label>
                                <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror form-control-lg" value="{{ old('code', $department->code) }}">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label for="parent_id" class="font-weight-bold small text-uppercase text-muted">Parent Unit (Hierarchy)</label>
                        <select name="parent_id" id="parent_id" class="form-control select2 @error('parent_id') is-invalid @enderror">
                            <option value="">-- No Parent (Top Level Unit) --</option>
                            @foreach($departments as $parent)
                                <option value="{{ $parent->id }}" {{ old('parent_id', $department->parent_id) == $parent->id ? 'selected' : '' }}>
                                    {{ $parent->name }} ({{ $parent->code ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-weight-bold small text-uppercase text-muted d-block">Reporting Type <span class="text-danger">*</span></label>
                        <div class="d-flex mt-2">
                             <div class="custom-control custom-radio mr-4">
                                <input type="radio" id="rt_daily" name="reporting_type" class="custom-control-input" value="daily" {{ old('reporting_type', $department->reporting_type) == 'daily' ? 'checked' : '' }} required>
                                <label class="custom-control-label" for="rt_daily">
                                    <i class="fas fa-calendar-day mr-1 text-warning"></i> <strong>Daily</strong>
                                    <small class="d-block text-muted">Submits daily data, weekly/monthly auto-aggregates.</small>
                                </label>
                            </div>
                            <div class="custom-control custom-radio mr-4">
                                <input type="radio" id="rt_weekly" name="reporting_type" class="custom-control-input" value="weekly" {{ old('reporting_type', $department->reporting_type) == 'weekly' ? 'checked' : '' }} required>
                                <label class="custom-control-label" for="rt_weekly">
                                    <i class="fas fa-calendar-week mr-1 text-primary"></i> <strong>Weekly</strong>
                                    <small class="d-block text-muted">Submits weekly reports, monthly is auto-aggregated.</small>
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="rt_monthly" name="reporting_type" class="custom-control-input" value="monthly" {{ old('reporting_type', $department->reporting_type) == 'monthly' ? 'checked' : '' }}>
                                <label class="custom-control-label" for="rt_monthly">
                                    <i class="fas fa-calendar-alt mr-1 text-info"></i> <strong>Monthly</strong>
                                    <small class="d-block text-muted">Submits one monthly report manually.</small>
                                </label>
                            </div>
                        </div>
                        @error('reporting_type')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="font-weight-bold small text-uppercase text-muted d-block">System Status</label>
                                <div class="custom-control custom-switch custom-switch-lg mt-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" class="custom-control-input" id="is_active_switch" value="1" {{ old('is_active', $department->is_active) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-normal" for="is_active_switch">
                                        Unit is currently <span class="status-label {{ $department->is_active ? 'text-success' : 'text-danger' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-top d-flex justify-content-between">
                        <a href="{{ route('departments.index') }}" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-warning px-5 shadow-sm font-weight-bold">
                            <i class="fas fa-sync-alt mr-1"></i> Update Unit Info
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%'
        });

        $('#is_active_switch').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.status-label').text(isChecked ? 'Active' : 'Inactive')
                .toggleClass('text-success', isChecked)
                .toggleClass('text-danger', !isChecked);
        });
    });
</script>
@endpush