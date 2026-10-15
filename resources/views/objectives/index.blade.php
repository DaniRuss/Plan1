@extends('layouts.app')
@section('title', 'Strategic Objectives')

@push('styles')
<style>
    .objective-allocation{padding:20px;border:1px solid #deebe9;border-radius:12px;background:#f5faf9}
    .allocation-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .allocation-metrics small,.allocation-metrics strong{display:block}
    .allocation-metrics small{color:#647582;margin-bottom:5px}
    .allocation-metrics strong{font-size:1.35rem;color:#17394b;font-variant-numeric:tabular-nums}
    .objective-hierarchy-table{font-size:.9rem;min-width:980px}
    .objective-hierarchy-table td,.objective-hierarchy-table th{padding:14px 12px;vertical-align:middle}
    .objective-hierarchy-table thead th{font-size:.78rem;color:#526775;background:#f2f6f8;white-space:nowrap}
    .objective-hierarchy-table .objective-code{color:#087f73;white-space:nowrap}
    .objective-description{color:#17394b;font-weight:600;display:block;margin-bottom:4px}
    .objective-number{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
    .objective-row-actions{display:flex;flex-wrap:wrap;gap:6px;min-width:170px}
    .objective-row-actions .btn{white-space:nowrap}
    @media(max-width:600px){.allocation-metrics{grid-template-columns:1fr}.objective-allocation{padding:16px}}
    .objective-kpi-owned { border-left: 5px solid #6f42c1 !important; background: #f5efff !important; }
    .objective-kpi-owned-row td { background: #f5efff !important; }
    .badge-kpi-owned { background: #6f42c1; color: #fff; font-weight: 700; }
    .badge-kpi-code { background: #e9ecef; color: #4b2a79; border: 1px solid #bca7d8; }

    .objectives-page-heading {
        align-items: center;
        row-gap: 1rem;
    }

    .objectives-action-bar {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .55rem;
        padding: .5rem;
        border: 1px solid #e3e9ef;
        border-radius: .85rem;
        background: rgba(255, 255, 255, .92);
        box-shadow: 0 8px 24px rgba(23, 43, 77, .08);
    }

    .objectives-action-bar .objective-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: .58rem .9rem;
        border: 1px solid #dbe3ea;
        border-radius: .6rem;
        background: #fff;
        color: #344054;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
        font-size: .88rem;
        font-weight: 600;
        line-height: 1.1;
        white-space: nowrap;
        transition: border-color .18s ease, box-shadow .18s ease, color .18s ease, transform .18s ease;
    }

    .objectives-action-bar .objective-action i {
        width: 1.1rem;
        margin-right: .45rem;
        font-size: 1rem;
        text-align: center;
    }

    .objectives-action-bar .objective-action:hover,
    .objectives-action-bar .objective-action:focus {
        border-color: #9eabb8;
        color: #101828;
        box-shadow: 0 5px 12px rgba(16, 24, 40, .12);
        text-decoration: none;
        transform: translateY(-2px);
    }

    .objectives-action-bar .objective-action:focus-visible {
        outline: 3px solid rgba(14, 148, 136, .2);
        outline-offset: 2px;
    }

    .objectives-action-bar .action-weights i { color: #0891b2; }
    .objectives-action-bar .action-pdf i { color: #e5484d; }
    .objectives-action-bar .action-template i { color: #16a34a; }

    .objectives-action-bar .action-narration {
        border-color: #c7e8e5;
        background: #edf9f7;
        color: #087f73;
    }

    .objectives-action-bar .action-primary {
        border-color: #087f73;
        background: linear-gradient(135deg, #0f9b8e 0%, #087f73 100%);
        color: #fff;
        box-shadow: 0 4px 10px rgba(8, 127, 115, .22);
    }

    .objectives-action-bar .action-primary:hover,
    .objectives-action-bar .action-primary:focus {
        border-color: #06695f;
        color: #fff;
        box-shadow: 0 7px 16px rgba(8, 127, 115, .28);
    }

    @media (max-width: 991.98px) {
        .objectives-action-bar { justify-content: flex-start; }
    }

    @media (max-width: 575.98px) {
        .objectives-action-bar .objective-action { flex: 1 1 100%; }
    }
</style>
@endpush

@section('content')
@php
    $objectiveEditor = auth()->user();
    $canEditObjective = static fn ($objective) =>
        $objectiveEditor->isSystemAdmin()
        || ($objectiveEditor->hasPermission('manage_objectives') && in_array($objective->status, ['draft', 'returned'], true))
        || ($objectiveEditor->isPlanReportDirectorate() && $objective->status === 'submitted_to_plan_report_directorate')
        || ($objectiveEditor->isPlanReportHeadOffice() && $objective->status === 'submitted');
@endphp
<div class="content-header">
    <div class="container-fluid">
         <div class="row mb-2 objectives-page-heading">
            <div class="col-lg-4">
                <h1 class="m-0">Strategic Objectives</h1>
                <p class="text-muted small mb-0">Expand an objective to review its activities and weight allocation. Open its details for targets, responsibilities, and reporting rules.</p>
            </div>
             <div class="col-lg-8">
                <!-- Actions -->
                <div class="objectives-action-bar" role="group" aria-label="Objective actions">
                     @if(auth()->user()->isPlanReportDepartmentExpert() || auth()->user()->isPlanReportDirectorate() || auth()->user()->isPlanReportHeadOffice() || auth()->user()->isSystemAdmin())
                     <a href="{{ route('period-weights.edit') }}" class="objective-action action-weights"><i class="fas fa-balance-scale" aria-hidden="true"></i> Period Weights</a>
                         <a href="{{ route('objectives.plan-narrative.edit') }}" class="objective-action action-narration"><i class="fas fa-pen-alt" aria-hidden="true"></i> Prepare Plan Narration</a>
                     @endif
                     <a href="{{ route('objectives.download-approved') }}" class="objective-action action-pdf"><i class="fas fa-file-pdf" aria-hidden="true"></i> Download Approved Objectives</a>
                     @can('import_objectives')
                         <a href="{{ route('objectives.import.form') }}" class="objective-action action-template"><i class="fas fa-file-import" aria-hidden="true"></i> Import Objectives</a>
                         <a href="{{ route('objectives.download-template') }}" class="objective-action action-template"><i class="fas fa-file-excel" aria-hidden="true"></i> Template</a>
                     @endcan
                     @can('manage_objectives')
                         <a href="{{ route('objectives.create') }}" class="objective-action action-primary"><i class="fas fa-plus" aria-hidden="true"></i> Add New</a>
                     @endcan
                </div>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        @if($objectives->isEmpty())
             <div class="alert alert-info">No objectives found. Start by creating one!</div>
        @else
        <div class="accordion" id="objectivesAccordion">
            @foreach($objectives as $mainObj)
            @php
                $mainIsYourKpi = $mainObj->isKpiForDepartment(auth()->user()->department_id);
            @endphp
            <div class="card mb-2 {{ $mainIsYourKpi ? 'objective-kpi-owned' : '' }}">
                <div class="card-header" id="heading{{ $mainObj->id }}">
                    <h5 class="mb-0">
                        <button class="btn btn-link btn-block text-left d-flex justify-content-between align-items-center" 
                                type="button" aria-expanded="false" aria-controls="collapse{{ $mainObj->id }}" data-toggle="collapse" 
                                data-target="#collapse{{ $mainObj->id }}">
                            <div>
                                <strong class="text-primary">{{ $mainObj->code }}</strong> - 
                                {{ $mainObj->description }}
                                @if($mainIsYourKpi)
                                    <span class="badge badge-kpi-owned ml-2"><i class="fas fa-bullseye mr-1"></i>Your KPI: {{ $mainObj->kpi_code }}</span>
                                @elseif($mainObj->kpi_code)
                                    <span class="badge badge-kpi-code ml-2">KPI: {{ $mainObj->kpi_code }}</span>
                                @endif
                                <span class="badge badge-info ml-2">{{ $mainObj->children->count() }} sub-objectives</span>
                                <small class="d-block text-muted mt-1">Owner: {{ $mainObj->department?->name ?? 'Not assigned' }} · Target year: {{ $mainObj->target_year ?? 'Not set' }} · Unit: {{ $mainObj->unit ?: 'Not set' }}</small>
                            </div>
                            <div>
                                <span class="badge badge-light border">Weight: {{ \App\Support\NumberFormatter::format($mainObj->weight) }}%</span>
                                @include('objectives.partials.status', ['objective' => $mainObj])
                                <i class="fas fa-chevron-down ml-2"></i>
                            </div>
                        </button>
                    </h5>
                </div>
                
                <div id="collapse{{ $mainObj->id }}" class="collapse" data-parent="#objectivesAccordion">
                    <div class="card-body">
                        @include('objectives.partials.weight-allocation')

                         @if($mainObj->children->count() > 0)
                         <div class="table-responsive"><table class="table table-hover objective-hierarchy-table">
                            <thead class="bg-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Sub-Objective / Activity</th>
                                    <th>Unit</th>
                                    <th class="text-right">Overall weight</th>
                                    <th class="text-right">Parent share</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mainObj->children as $child)
                                    @include('objectives.partials.hierarchy-row', ['rowObjective' => $child, 'rowParent' => $mainObj, 'depth' => 0])
                                @endforeach
                            </tbody>
                        </table></div>
                        @else
                            <p class="text-muted text-center">No sub-objectives yet.</p>
                        @endif
                        
                        <div class="mt-3">
                            <a href="{{ route('objectives.show', $mainObj->id) }}" class="btn btn-outline-info btn-sm">View Details</a>
                            @if($mainObj->status === 'approved')<a href="{{ route('objectives.download', $mainObj) }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-file-pdf mr-1"></i>Download Objective</a>@endif
                            @if($canEditObjective($mainObj))
                                <a href="{{ route('objectives.edit', $mainObj->id) }}" class="btn btn-outline-primary btn-sm">Edit Main</a>
                            @endif
                            @can('manage_objectives')
                                @if($mainObj->status === 'draft' || $mainObj->status === 'returned')
                                <a href="{{ route('objectives.create', ['parent_id' => $mainObj->id]) }}" class="btn btn-outline-success btn-sm">Add Sub-Objective</a>
                                @endif
                                
                                @if($mainObj->status === 'draft' || $mainObj->status === 'returned')
                                <form action="{{ route('objectives.submit', $mainObj->id) }}" method="POST" class="d-inline ml-2">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm" data-confirm="Submit this objective for approval?" data-confirm-title="Submit objective" data-confirm-text="Submit">Submit</button>
                                </form>
                                @endif
                                
                                

                                @if($mainObj->status === 'draft' || $mainObj->status === 'returned')
                                    <form action="{{ route('objectives.destroy', $mainObj->id) }}" method="POST" class="d-inline float-right">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="Delete this objective? This action cannot be undone." data-confirm-title="Delete objective" data-confirm-text="Delete" data-confirm-class="btn-danger">Delete</button>
                                    </form>
                                @endif
                            @endcan

                            @if(auth()->user()->isPlanReportDepartmentExpert() && in_array($mainObj->status, ['submitted_to_plan_report_directorate', 'submitted'], true))
                                <form action="{{ route('objectives.recall-request', $mainObj) }}" method="POST" class="d-inline ml-2">
                                    @csrf
                                    <input type="text" name="recall_reason" required maxlength="2000" placeholder="Why do you need to change the plan?" class="form-control form-control-sm d-inline-block" style="width:260px">
                                    <button type="submit" class="btn btn-warning btn-sm">Request Recall for Editing</button>
                                </form>
                            @endif

                            @if(
                                ($mainObj->status === 'recall_requested_from_plan_report_directorate' && auth()->user()->isPlanReportDirectorate())
                                || ($mainObj->status === 'recall_requested_from_head_office' && auth()->user()->isPlanReportHeadOffice())
                                || (str_starts_with($mainObj->status, 'recall_requested_') && auth()->user()->isSystemAdmin())
                            )
                                <div class="mt-2">
                                    <div class="alert alert-warning py-2 mb-2"><strong>Recall reason:</strong> {{ $mainObj->recall_reason }}</div>
                                    <form action="{{ route('objectives.recall-approve', $mainObj) }}" method="POST" class="d-inline">@csrf
                                        <button class="btn btn-success btn-sm">Approve Recall</button>
                                    </form>
                                    <form action="{{ route('objectives.recall-reject', $mainObj) }}" method="POST" class="d-inline ml-2">@csrf
                                        <input type="text" name="decision_reason" required maxlength="2000" placeholder="Reason for rejecting recall" class="form-control form-control-sm d-inline-block" style="width:230px">
                                        <button class="btn btn-danger btn-sm">Reject Recall</button>
                                    </form>
                                </div>
                            @endif

                            @if($mainObj->status === 'submitted_to_plan_report_directorate' && (auth()->user()->isPlanReportDirectorate() || auth()->user()->isSystemAdmin()))
                                <form action="{{ route('objectives.directorate-forward', $mainObj->id) }}" method="POST" class="d-inline ml-2">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success btn-sm" data-confirm="Review and send this objective to the Head of Plan and Report?" data-confirm-title="Forward objective" data-confirm-text="Review and send" data-confirm-class="btn-success">Review and Send to Head</button>
                                </form>
                                <form action="{{ route('objectives.directorate-return', $mainObj->id) }}" method="POST" class="d-inline ml-2">
                                    @csrf
                                    <input type="text" name="rejection_comment" required maxlength="2000" placeholder="Required return reason" class="form-control form-control-sm d-inline-block" style="width:220px">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Return to Expert</button>
                                </form>
                            @endif

                            @if($mainObj->status === 'submitted' && (auth()->user()->isPlanReportHeadOffice() || auth()->user()->isHeadOffice() || auth()->user()->isSystemAdmin()))
                                <form action="{{ route('objectives.approve', $mainObj->id) }}" method="POST" class="d-inline ml-2">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success btn-sm" data-confirm="Approve this objective?" data-confirm-title="Approve objective" data-confirm-text="Approve" data-confirm-class="btn-success">Approve</button>
                                </form>
                                <button type="button" class="btn btn-outline-danger btn-sm ml-2" data-toggle="modal" data-target="#rejectModal{{ $mainObj->id }}">Return</button>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal{{ $mainObj->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('objectives.reject', $mainObj->id) }}" method="POST">
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Return Objective</h5>
                                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label>Reason for return / Comments <span class="text-danger">*</span></label>
                                                        <textarea name="rejection_comment" class="form-control" required rows="3"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Return to Department</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
        <div class="mt-3">{{ $objectives->withQueryString()->links() }}</div>
    </div>
</section>
@endsection
