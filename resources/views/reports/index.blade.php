@extends('layouts.app')

@section('title', 'Reports Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Reports</li>
@endsection

@section('content')
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h3 class="text-dark font-weight-bold mb-1"><i class="fas fa-file-contract mr-2 text-info"></i> Performance Reports</h3>
            <p class="text-muted mb-0">Manage periodic department evaluations with automated performance synchronization.</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="{{ route('projects.report') }}" class="btn btn-info shadow mr-2">
                <i class="fas fa-project-diagram mr-1"></i> Project Report
            </a>
            @if(auth()->user()->isPlanReportDepartmentExpert() || auth()->user()->isSystemAdmin())<a href="{{ route('reports.create') }}" class="btn btn-primary shadow"><i class="fas fa-plus-circle mr-1"></i> Draft New Report</a>@endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase small font-weight-bold">
                        <tr>
                            <th class="px-4 py-3">Reporting Period</th>
                            <th>Department</th>
                            <th class="text-center">Achievement</th>
                            <th>Drafted By</th>
                            <th>Status</th>
                            <th class="text-right px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-weight-bold text-dark">{{ \App\Models\EdrReport::periodLabel($report->reporting_period) }} {{ $report->year }}</div>
                                    <div class="small text-muted">Created {{ $report->created_at->format('M d, Y') }}</div>
                                </td>
                                <td>
                                    <span class="text-dark font-weight-600">{{ $report->department_label }}</span>
                                </td>
                                <td class="text-center">
                                    @if($report->achievement !== null)
                                        @php
                                            $color = $report->achievement >= 90 ? 'success' : ($report->achievement >= 75 ? 'warning' : 'danger');
                                        @endphp
                                        <div class="progress progress-xs mb-1" style="height: 4px; width: 80px; margin: 0 auto;">
                                            <div class="progress-bar bg-{{ $color }}" style="width: {{ $report->achievement }}%"></div>
                                        </div>
                                        <span class="badge badge-{{ $color }} shadow-sm px-2">{{ round($report->achievement, 1) }}%</span>
                                    @else
                                        <span class="text-muted small">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle p-1 mr-2"><i class="fas fa-user text-xs"></i></div>
                                        {{ $report->submittedBy->name ?? 'System' }}
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $statusBadge = match ($report->status) {
                                            'draft' => 'badge-secondary',
                                            'submitted', 'submitted_to_plan_report_directorate', 'submitted_to_plan_report_head' => 'badge-warning',
                                            'approved' => 'badge-success',
                                            'rejected' => 'badge-danger',
                                            default => 'badge-info'
                                        };
                                    @endphp
                                    <span class="badge {{ $statusBadge }} badge-pill px-3 py-1 text-uppercase"
                                        style="font-size: 0.65rem; letter-spacing: 0.5px;">{{ $report->status }}</span>
                                </td>
                                <td class="text-right px-4">
                                    <div class="btn-group">
                                        <a href="{{ route('reports.show', $report) }}" class="btn btn-sm btn-outline-info"
                                            title="View Details" data-toggle="tooltip">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @php($canEdit=(auth()->user()->isSystemAdmin())||(auth()->user()->isPlanReportDepartmentExpert()&&$report->status==='draft')||(auth()->user()->isPlanReportDirectorate()&&$report->status==='submitted_to_plan_report_directorate')||(auth()->user()->isPlanReportHeadOffice()&&$report->status==='submitted_to_plan_report_head'))
                                        @if($canEdit)
                                            <a href="{{ route('reports.edit', $report) }}" class="btn btn-sm btn-outline-warning"
                                                title="Edit Narrative" data-toggle="tooltip">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif
                                        @if(auth()->user()->isPlanReportDepartmentExpert() && $report->status==='draft')
                                            <form method="POST" action="{{ route('reports.submit',$report) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success" title="Send to Directorate"><i class="fas fa-paper-plane"></i></button></form>
                                        @elseif(auth()->user()->isPlanReportDirectorate() && $report->status==='submitted_to_plan_report_directorate')
                                            <form method="POST" action="{{ route('reports.forward-head',$report) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success" title="Send to Head"><i class="fas fa-share"></i></button></form>
                                        @elseif(auth()->user()->isPlanReportHeadOffice() && $report->status==='submitted_to_plan_report_head')
                                            <form method="POST" action="{{ route('reports.approve',$report) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success" title="Save and approve"><i class="fas fa-check"></i></button></form>
                                        @endif
                                        <a href="{{ route('reports.pdf', $report) }}" class="btn btn-sm btn-outline-danger"
                                            title="Export PDF" data-toggle="tooltip">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="mb-3 opacity-25">
                                        <i class="fas fa-folder-open fa-4x text-muted"></i>
                                    </div>
                                    <h5 class="text-muted">No reports found.</h5>
                                    <p class="text-muted small">Start by drafting your first performance report.</p>
                                    <a href="{{ route('reports.create') }}" class="btn btn-primary btn-sm mt-2">Create New Report</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($reports->hasPages())
        <div class="card-footer bg-white border-0">
            {{ $reports->links() }}
        </div>
        @endif
    </div>
@endsection
