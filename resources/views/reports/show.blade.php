@extends('layouts.app')

@section('title', 'Report Details')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">View Report</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <!-- Main Report Content -->
            <div class="card shadow-lg border-0 mb-4" id="printableReport">
                <div class="card-header bg-white py-4 border-bottom text-center">
                    @if(\App\Models\Setting::get('site_logo'))
                        <img src="{{ asset('storage/' . \App\Models\Setting::get('site_logo')) }}" style="height: 60px;"
                            class="mb-3">
                    @else
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/84/Coat_of_arms_of_Ethiopia.svg/1200px-Coat_of_arms_of_Ethiopia.svg.png"
                            style="height: 60px;" class="mb-3">
                    @endif
                    <h3 class="font-weight-bold text-dark mb-1">Performance Report (EDR)</h3>
                    <h5 class="text-muted">{{ $report->department_label }} - {{ \App\Models\EdrReport::periodLabel($report->reporting_period) }}
                        {{ $report->year }}</h5>
                    <div class="mt-2">
                        <span
                            class="badge badge-pill {{ $report->status == 'approved' ? 'badge-success' : 'badge-warning' }} px-3 py-2 text-uppercase shadow-sm">
                            Status: {{ $report->status }}
                        </span>
                    </div>
                </div>

                <div class="card-body p-5">
                    <!-- 1. Executive Summary -->
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <h5 class="section-title border-left-info pl-3 font-weight-bold">1. Executive Summary</h5>
                            <div class="text-justify px-3">
                                {!! nl2br(e($report->executive_summary ?: 'No executive summary provided.')) !!}
                            </div>
                        </div>
                    </div>

                    <!-- 2. Introduction -->
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <h5 class="section-title border-left-info pl-3 font-weight-bold">2. Introduction</h5>
                            <div class="text-justify px-3">
                                {!! nl2br(e($report->introduction ?: 'No introduction provided.')) !!}
                            </div>
                        </div>
                    </div>

                    <!-- 3, 4 & 11. Performance Tables -->
                    <section class="mb-5">
                        <h5 class="section-title border-left-info pl-3 font-weight-bold mb-4">3, 4 & 11. Plan Performance &
                            KPI Comparison</h5>
                        @include('reports._performance_table', ['performanceData' => $performanceData, 'report' => $report])
                    </section>

                    <!-- 5. Challenges and Solutions -->
                    <div class="row mb-5">
                        <div class="col-md-6 mb-4">
                            <h5 class="section-title border-left-danger pl-3 font-weight-bold mb-3 text-sm">5.1 Challenges
                                Faced</h5>
                            <div class="p-3 bg-light rounded shadow-sm min-h-100 italic">
                                {!! nl2br(e($report->challenges)) ?: 'None reported.' !!}
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h5 class="section-title border-left-success pl-3 font-weight-bold mb-3 text-sm">5.2 Solutions
                                Taken</h5>
                            <div class="p-3 bg-light rounded shadow-sm min-h-100">
                                {!! nl2br(e($report->solutions)) ?: 'None reported.' !!}
                            </div>
                        </div>
                    </div>

                    <!-- 6. Non-Planned Activities -->
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <h5 class="section-title border-left-info pl-3 font-weight-bold mb-3 text-sm">6. Non-Planned
                                Activities</h5>
                            <div class="p-3 bg-light rounded shadow-sm">
                                {!! nl2br(e($report->non_planned_activities ?: 'None reported.')) !!}
                            </div>
                        </div>
                    </div>

                    <!-- 7. Strength and Weakness -->
                    <div class="row mb-5">
                        <div class="col-md-6 mb-4">
                            <h5 class="section-title border-left-success pl-3 font-weight-bold mb-3 text-sm">7.1 Strengths
                            </h5>
                            <div class="p-3 bg-light rounded shadow-sm min-h-100">
                                {!! nl2br(e($report->strengths)) ?: 'Not specified.' !!}
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h5 class="section-title border-left-warning pl-3 font-weight-bold mb-3 text-sm">7.2 Weaknesses
                            </h5>
                            <div class="p-3 bg-light rounded shadow-sm min-h-100">
                                {!! nl2br(e($report->weaknesses)) ?: 'Not specified.' !!}
                            </div>
                        </div>
                    </div>

                    <!-- 8. Best Practices -->
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <h5 class="section-title border-left-primary pl-3 font-weight-bold mb-3 text-sm">8. Best
                                Practices / Achievements (Reform Period)</h5>
                            <div class="p-3 bg-light rounded shadow-sm">
                                {!! nl2br(e($report->best_practices ?: 'Not specified.')) !!}
                            </div>
                        </div>
                    </div>

                    <!-- 9. Next Focus Areas -->
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <h5 class="section-title border-left-info pl-3 font-weight-bold mb-3 text-sm">9. Next Focus
                                Areas</h5>
                            <div class="p-3 bg-light rounded shadow-sm">
                                {!! nl2br(e($report->next_focus_areas ?: 'Not specified.')) !!}
                            </div>
                        </div>
                    </div>

                    <!-- 10. Summary and Result -->
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <h5 class="section-title border-left-dark pl-3 font-weight-bold mb-3 text-sm">10. Summary and
                                Result</h5>
                            <div class="p-4 bg-dark text-white rounded shadow-sm">
                                {!! nl2br(e($report->summary_and_result ?: 'No final summary provided.')) !!}
                            </div>
                        </div>
                    </div>

                    <!-- 12. Annexes -->
                    @if($report->annexes->count() > 0)
                        <div class="row mb-5">
                            <div class="col-md-12">
                                <h5 class="section-title border-left-warning pl-3 font-weight-bold mb-3 text-sm">12. Annexes
                                    (Attachments)</h5>
                                <div class="row">
                                    @foreach($report->annexes as $annex)
                                        <div class="col-md-3 mb-3">
                                            <div class="card h-100 border-0 shadow-sm">
                                                <div class="card-body p-2 text-center">
                                                    @php $ext = pathinfo($annex->file_path, PATHINFO_EXTENSION); @endphp
                                                    @if(in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif']))
                                                        <img src="{{ asset('storage/' . $annex->file_path) }}"
                                                            class="img-fluid rounded mb-2" style="max-height: 100px;">
                                                    @else
                                                        <i class="fas fa-file-alt fa-3x text-muted mb-2"></i>
                                                    @endif
                                                    <div class="small text-truncate" title="{{ $annex->file_name }}">
                                                        {{ $annex->file_name }}</div>
                                                    <a href="{{ asset('storage/' . $annex->file_path) }}" target="_blank"
                                                        class="btn btn-xs btn-outline-primary mt-2">View</a>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                </div>

                <div class="card-footer bg-light p-4">
                    <div class="row text-center small">
                        <div class="col-md-4">
                            <div class="font-weight-bold border-bottom mb-2 pb-1">Drafted By</div>
                            <div>{{ $report->submittedBy->name ?? 'System' }}</div>
                            <small class="text-muted">{{ $report->created_at->format('Y-m-d') }}</small>
                        </div>
                        <div class="col-md-4">
                            <div class="font-weight-bold border-bottom mb-2 pb-1">Reviewed By</div>
                            <div>{{ $report->reviewedBy->name ?? 'Pending' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="font-weight-bold border-bottom mb-2 pb-1">Approved By</div>
                            <div>{{ $report->approvedBy->name ?? 'Pending' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Sidebar -->
        <div class="col-md-1">
            <div class="sticky-top pt-3" style="z-index: 10;">
                <div class="btn-group-vertical w-100">
                    <a href="{{ route('reports.pdf', $report) }}" class="btn btn-danger mb-2 shadow-sm" title="Export PDF">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                    <a href="{{ route('reports.excel', $report) }}" class="btn btn-success mb-2 shadow-sm"
                        title="Export Excel">
                        <i class="fas fa-file-excel"></i>
                    </a>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary shadow-sm" title="Back">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .section-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }

        .border-left-info {
            border-left: 4px solid #17a2b8;
        }

        .border-left-danger {
            border-left: 4px solid #dc3545;
        }

        .border-left-success {
            border-left: 4px solid #28a745;
        }

        .border-left-warning {
            border-left: 4px solid #ffc107;
        }

        .border-left-primary {
            border-left: 4px solid #007bff;
        }

        .border-left-dark {
            border-left: 4px solid #343a40;
        }

        @media print {
            .col-md-1 {
                display: none !important;
            }

            .col-md-10 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
    </style>
@endpush
