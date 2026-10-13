@extends('layouts.app')

@section('title', 'Edit Report Narrative')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-10">
            <form action="{{ route('reports.update', $report) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card shadow-sm border-0 mb-4 bg-light">
                    <div class="card-body py-3">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-0 font-weight-bold">{{ $report->department_label }}</h5>
                                <p class="mb-0 text-muted small">{{ $report->reporting_period }} {{ $report->year }}</p>
                            </div>
                            <div class="col-md-4 text-right">
                                <span class="badge badge-info px-3 py-2 text-uppercase">Status: {{ $report->status }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Preview -->
                <div class="card shadow-sm border-0 mb-4 overflow-hidden">
                    <div class="card-header bg-info text-white py-3">
                        <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-chart-bar mr-2"></i> 3, 4 & 11.
                            Performance Data (Synchronized)</h5>
                    </div>
                    <div class="card-body p-0" id="performancePreviewContent">
                        <div class="text-center py-5">
                            <i class="fas fa-sync fa-spin"></i> Loading synchronized data...
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-align-left mr-2 text-info"></i> EDR
                            Narrative Sections</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-4">
                            <label class="font-weight-bold">1. Executive Summary</label>
                            <textarea name="executive_summary" class="form-control"
                                rows="4">{{ $report->executive_summary }}</textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">2. Introduction</label>
                            <textarea name="introduction" class="form-control"
                                rows="3">{{ $report->introduction }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-danger">5. Challenges Faced</label>
                                    <textarea name="challenges" class="form-control"
                                        rows="4">{{ $report->challenges }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-success">5. Solutions Taken</label>
                                    <textarea name="solutions" class="form-control"
                                        rows="4">{{ $report->solutions }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">6. Non-Planned Activities Performed / Planned Not
                                Performed</label>
                            <textarea name="non_planned_activities" class="form-control"
                                rows="4">{{ $report->non_planned_activities }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold">7. Strength</label>
                                    <textarea name="strengths" class="form-control"
                                        rows="3">{{ $report->strengths }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold">7. Weakness</label>
                                    <textarea name="weaknesses" class="form-control"
                                        rows="3">{{ $report->weaknesses }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">8. Best Practices / Achievements (Reform Period)</label>
                            <textarea name="best_practices" class="form-control"
                                rows="3">{{ $report->best_practices }}</textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">9. Next Focus Areas</label>
                            <textarea name="next_focus_areas" class="form-control"
                                rows="3">{{ $report->next_focus_areas }}</textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">10. Summary and Result</label>
                            <textarea name="summary_and_result" class="form-control"
                                rows="4">{{ $report->summary_and_result }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-5">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-paperclip mr-2 text-warning"></i> 12.
                            Annexes & Attachments</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Upload New Supporting documents</label>
                            <input type="file" name="annexes[]" class="form-control-file" multiple>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-right py-3">
                        <a href="{{ route('reports.show', $report) }}" class="btn btn-light px-4 mr-2">Cancel</a>
                        <button type="submit" class="btn btn-primary px-5 shadow-sm">
                            <i class="fas fa-save mr-1"></i> Update Report
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
            $.ajax({
                url: "{{ route('reports.preview') }}",
                method: "GET",
                data: {
                    department_id: "{{ $report->department_id }}",
                    reporting_period: "{{ $report->reporting_period }}",
                    year: "{{ $report->year }}"
                },
                success: function (response) {
                    $('#performancePreviewContent').html(response.html);
                }
            });
        });
    </script>
@endpush
