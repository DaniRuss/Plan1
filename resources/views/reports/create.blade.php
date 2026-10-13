@extends('layouts.app')

@section('title', 'Draft New Report')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Draft</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-10">
            <form action="{{ route('reports.store') }}" method="POST" enctype="multipart/form-data" id="reportForm">
                @csrf

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-white py-3">
                        <h5 class="card-title mb-0"><i class="fas fa-pen-nib mr-2"></i> 1 & 2. Report Metadata & Intro</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Department</label>
                                    <select name="department_id" id="department_id" class="form-control select2" required>
                                        <option value="all">All Departments — Collected EDR Report</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" data-reporting-type="{{ $dept->reporting_type }}" {{ (auth()->user()->employee?->department_id == $dept->id) ? 'selected' : '' }}>
                                                {{ $dept->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="font-weight-bold">Reporting Period</label>
                                    <select name="reporting_period" id="reporting_period" class="form-control" required>
                                        <option value="">Select Period...</option>
                                        <option value="Q1">Quarter 1 (July-Sept)</option>
                                        <option value="Q2">Quarter 2 (Oct-Dec)</option>
                                        <option value="Q3">Quarter 3 (Jan-Mar)</option>
                                        <option value="Q4">Quarter 4 (Apr-Jun)</option>
                                        <option value="Annual">Annual Report</option>
                                        <optgroup label="Monthly Reports">
                                            @foreach($months as $month)
                                                <option value="month:{{ $month }}">Monthly — {{ ucfirst($month) }}</option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Weekly Reports" id="weeklyReportsGroup">
                                            @foreach($months as $month)
                                                @for($week = 1; $week <= 6; $week++)
                                                    <option value="week:{{ $month }}:{{ $week }}">Weekly — {{ ucfirst($month) }} W{{ $week }}</option>
                                                @endfor
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="font-weight-bold">Year</label>
                                    <select name="year" id="year" class="form-control" required>
                                        @foreach($years as $year)
                                            <option value="{{ $year }}">{{ $year }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3 & 4 & 11. Performance Preview Section -->
                <div class="card shadow-sm border-0 mb-4 overflow-hidden" id="performancePreviewCard">
                    <div class="card-header bg-info text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-chart-bar mr-2"></i> 3, 4 & 11.
                            Performance Analysis (Automated)</h5>
                        <span class="badge badge-light" id="previewStatus">Waiting for input...</span>
                    </div>
                    <div class="card-body p-0" id="performancePreviewContent">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-sync fa-spawn fa-3x mb-3 opacity-25"></i>
                            <p>Select department and period above to load performance data.</p>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-align-left mr-2 text-info"></i>
                            Narrative Analysis</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-4">
                            <label class="font-weight-bold">1. Executive Summary</label>
                            <textarea name="executive_summary" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">2. Introduction</label>
                            <textarea name="introduction" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-danger">5. Challenges Faced</label>
                                    <textarea name="challenges" class="form-control" rows="4"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-success">5. Solutions Taken</label>
                                    <textarea name="solutions" class="form-control" rows="4"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">6. Non-Planned Activities Performed / Planned Not
                                Performed</label>
                            <textarea name="non_planned_activities" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold">7. Strength</label>
                                    <textarea name="strengths" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold">7. Weakness</label>
                                    <textarea name="weaknesses" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">8. Best Practices / Top Achievements (Reform Period)</label>
                            <textarea name="best_practices" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">9. Next Focus Areas</label>
                            <textarea name="next_focus_areas" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">10. Summary and Result</label>
                            <textarea name="summary_and_result" class="form-control" rows="4"
                                placeholder="Overal summary of the results achieved..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-5">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-paperclip mr-2 text-warning"></i> 12.
                            Annexes (Pictures, Explanations)</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Upload Support Files</label>
                            <input type="file" name="annexes[]" class="form-control-file" multiple>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-right py-3">
                        <a href="{{ route('reports.index') }}" class="btn btn-light px-4 mr-2">Cancel</a>
                        <button type="submit" class="btn btn-info px-5 shadow-sm">
                            <i class="fas fa-save mr-1"></i> Save Draft Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const weekCounts = @json($weekCounts);

            function filterPeriodOptions() {
                const year = String($('#year').val() || '');
                const departmentOption = $('#department_id').find(':selected');
                const reportingType = departmentOption.data('reporting-type');
                const supportsWeekly = $('#department_id').val() === 'all' || reportingType === 'weekly' || reportingType === 'daily';
                $('#weeklyReportsGroup').prop('disabled', !supportsWeekly);

                $('#weeklyReportsGroup option').each(function () {
                    const parts = String(this.value).split(':');
                    const valid = supportsWeekly && Number(parts[2]) <= Number((weekCounts[year] || {})[parts[1]] || 0);
                    $(this).prop('disabled', !valid).prop('hidden', !valid);
                });

                const selected = $('#reporting_period').find(':selected');
                if (selected.prop('disabled')) $('#reporting_period').val('');
            }

            function updatePreview() {
                const deptId = $('#department_id').val();
                const period = $('#reporting_period').val();
                const year = $('#year').val();

                if (!deptId || !period || !year) return;

                $('#previewStatus').text('Loading...').removeClass('badge-light badge-success badge-warning').addClass('badge-info');
                $('#performancePreviewContent').css('opacity', '0.5');

                $.ajax({
                    url: "{{ route('reports.preview') }}",
                    method: "GET",
                    data: {
                        department_id: deptId,
                        reporting_period: period,
                        year: year
                    },
                    success: function (response) {
                        $('#performancePreviewContent').html(response.html).css('opacity', '1');
                        $('#previewStatus').text('Data Synchronized').removeClass('badge-info').addClass('badge-success');
                    },
                    error: function (xhr) {
                        const message = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'Failed to load performance data. Verify that an approved plan exists for this selection.';
                        const safeMessage = $('<div>').text(message).html();
                        $('#performancePreviewContent').html('<div class="p-4 text-center text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>' + safeMessage + '</div>');
                        $('#previewStatus').text('Error').removeClass('badge-info').addClass('badge-danger');
                    }
                });
            }

            $('#department_id, #year').on('change', function () { filterPeriodOptions(); updatePreview(); });
            $('#reporting_period').on('change', updatePreview);
            filterPeriodOptions();
            if ($('#reporting_period').val()) updatePreview();
        });
    </script>
@endpush
