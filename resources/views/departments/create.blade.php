@extends('layouts.app')

@section('title', 'Add Department')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
<li class="breadcrumb-item"><a href="{{ route('departments.index') }}">Departments</a></li>
<li class="breadcrumb-item active">Add New</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0 rounded-lg">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 font-weight-bold text-dark">
                    <i class="fas fa-plus-circle mr-2 text-primary"></i> Register New Organizational Unit
                </h5>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('departments.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group mb-4">
                                <label for="name" class="font-weight-bold small text-uppercase text-muted">Department Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror form-control-lg" value="{{ old('name') }}" placeholder="e.g. Information Technology Directorate" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-4">
                                <label for="code" class="font-weight-bold small text-uppercase text-muted">Abreviation / Code</label>
                                <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror form-control-lg" value="{{ old('code') }}" placeholder="e.g. ITD">
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
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ old('parent_id') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }} ({{ $dept->code ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Select the unit that this department reports to.</small>
                    </div>


                    <div class="form-group mb-4">
                        <label class="font-weight-bold small text-uppercase text-muted d-block">Reporting Type <span class="text-danger">*</span></label>
                        <div class="d-flex mt-2">
                             <div class="custom-control custom-radio mr-4">
                                <input type="radio" id="rt_daily" name="reporting_type" class="custom-control-input" value="daily" {{ old('reporting_type') == 'daily' ? 'checked' : '' }} required>
                                <label class="custom-control-label" for="rt_daily">
                                    <i class="fas fa-calendar-day mr-1 text-warning"></i> <strong>Daily</strong>
                                    <small class="d-block text-muted">Submits daily data, weekly/monthly auto-aggregates.</small>
                                </label>
                            </div>
                            <div class="custom-control custom-radio mr-4">
                                <input type="radio" id="rt_weekly" name="reporting_type" class="custom-control-input" value="weekly" {{ old('reporting_type', 'weekly') == 'weekly' ? 'checked' : '' }} required>
                                <label class="custom-control-label" for="rt_weekly">
                                    <i class="fas fa-calendar-week mr-1 text-primary"></i> <strong>Weekly</strong>
                                    <small class="d-block text-muted">Submits weekly reports, monthly is auto-aggregated.</small>
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="rt_monthly" name="reporting_type" class="custom-control-input" value="monthly" {{ old('reporting_type') == 'monthly' ? 'checked' : '' }}>
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

                    <div class="pt-3 border-top d-flex justify-content-between">
                        <a href="{{ route('departments.index') }}" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-5 shadow-sm">
                            <i class="fas fa-save mr-1"></i> Save Department
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

        // Trigger auto-abbreviation when clicking or focusing the code field
        $('#code').on('click focus', function() {
            // Only auto-generate if the field is currently empty
            if ($(this).val().length > 0) return;

            const name = $('#name').val();
            if (!name) return;

            const ignoreWords = ['of', 'the', 'and', 'in', 'to', 'for', 'with', 'a', 'an', '&', 'at', 'by'];
            const words = name.trim().split(/\s+/);
            
            const abbreviation = words
                .filter(word => word.length > 0 && !ignoreWords.includes(word.toLowerCase()))
                .map(word => word[0].toUpperCase())
                .join('');

            $(this).val(abbreviation);
        });
    });
</script>
@endpush