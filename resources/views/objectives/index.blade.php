@extends('layouts.app')

@push('styles')
<style>
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
                                type="button" data-toggle="collapse" 
                                data-target="#collapse{{ $mainObj->id }}">
                            <div>
                                <strong class="text-primary">{{ $mainObj->code }}</strong> - 
                                {{ Str::limit($mainObj->description, 60) }}
                                @if($mainIsYourKpi)
                                    <span class="badge badge-kpi-owned ml-2"><i class="fas fa-bullseye mr-1"></i>Your KPI: {{ $mainObj->kpi_code }}</span>
                                @elseif($mainObj->kpi_code)
                                    <span class="badge badge-kpi-code ml-2">KPI: {{ $mainObj->kpi_code }}</span>
                                @endif
                                <span class="badge badge-info ml-2">{{ $mainObj->children->count() }} sub-objectives</span>
                            </div>
                            <div>
                                <span class="badge badge-light border">Weight: {{ $mainObj->weight }}%</span>
                                @if($mainObj->status === 'approved')
                                    <span class="badge badge-success ml-2">Approved</span>
                                @elseif($mainObj->status === 'returned')
                                    <span class="badge badge-danger ml-2" title="{{ $mainObj->rejection_comment }}">Returned</span>
                                @elseif($mainObj->status === 'submitted')
                                    <span class="badge badge-info ml-2">Sent to Head of Plan &amp; Report</span>
                                @elseif($mainObj->status === 'submitted_to_plan_report_directorate')
                                    <span class="badge badge-info ml-2">Sent to Plan &amp; Report Directorate</span>
                                @elseif(str_starts_with($mainObj->status, 'recall_requested_'))
                                    <span class="badge badge-warning ml-2">Recall Approval Pending</span>
                                @else
                                    <span class="badge badge-secondary ml-2">Draft</span>
                                @endif
                                <i class="fas fa-chevron-down ml-2"></i>
                            </div>
                        </button>
                    </h5>
                </div>
                
                <div id="collapse{{ $mainObj->id }}" class="collapse" data-parent="#objectivesAccordion">
                    <div class="card-body">
                         <!-- Weight Visual -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="small text-muted mb-0">Weight Allocation (Total: {{ $mainObj->weight }}%)</label>
                                <div class="progress" style="height: 15px;">
                                    @php 
                                        $alloc = $mainObj->getAllocatedWeight();
                                        $pct = $mainObj->weight > 0 ? ($alloc / $mainObj->weight) * 100 : 0;
                                    @endphp
                                    <div class="progress-bar {{ $pct > 100 ? 'bg-danger' : 'bg-success' }}" role="progressbar" style="width: {{ $pct }}%">
                                        {{ number_format($alloc, 2) }}% Used
                                    </div>
                                </div>
                                <small class="text-muted">Remaining to allocate: {{ number_format($mainObj->getRemainingWeight(), 2) }}%</small>
                            </div>
                        </div>

                         @if($mainObj->children->count() > 0)
                         <table class="table table-sm table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Sub-Objective / Activity</th>
                                    <th>Unit</th>
                                    <th>Weight</th>
                                    <th>% of Parent</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mainObj->children as $child)
                                @php
                                    $childIsYourKpi = $child->isKpiForDepartment(auth()->user()->department_id);
                                @endphp
                                <tr class="{{ $childIsYourKpi ? 'objective-kpi-owned-row' : '' }}">
                                    <td>{{ $child->code }}</td>
                                    <td>
                                        {{ $child->description }}
                                        @if($childIsYourKpi)
                                            <span class="badge badge-kpi-owned ml-2"><i class="fas fa-bullseye mr-1"></i>Your KPI: {{ $child->kpi_code }}</span>
                                        @elseif($child->kpi_code)
                                            <span class="badge badge-kpi-code ml-2">KPI: {{ $child->kpi_code }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $child->unit }}</td>
                                    <td>{{ $child->weight }}%</td>
                                    <td>{{ $mainObj->weight > 0 ? number_format(($child->weight / $mainObj->weight)*100, 1) : 0 }}%</td>
                                    <td>
                                        @if($child->status === 'approved')
                                            <span class="badge badge-success badge-sm">Approved</span>
                                        @elseif($child->status === 'returned')
                                            <span class="badge badge-danger badge-sm" title="{{ $child->rejection_comment }}">Returned</span>
                                        @elseif($child->status === 'submitted')
                                            <span class="badge badge-info badge-sm">Submitted</span>
                                        @else
                                            <span class="badge badge-secondary badge-sm">Draft</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('objectives.show', $child->id) }}" class="btn btn-info"><i class="fas fa-eye"></i></a>
                                            @if($child->status === 'approved')<a href="{{ route('objectives.download', $child) }}" class="btn btn-danger" title="Download this objective"><i class="fas fa-file-pdf"></i></a>@endif
                                            @if($canEditObjective($child))
                                                <a href="{{ route('objectives.edit', $child->id) }}" class="btn btn-warning"><i class="fas fa-edit"></i></a>
                                            @endif
                                            @can('manage_objectives')
                                                @if($child->status === 'draft' || $child->status === 'returned')
                                                <a href="{{ route('objectives.create', ['parent_id' => $child->id]) }}" class="btn btn-success" title="Add Activity"><i class="fas fa-plus"></i></a>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @if($child->children->count() > 0)
                                    @foreach($child->children as $grandchild)
                                    @php
                                        $grandchildIsYourKpi = $grandchild->isKpiForDepartment(auth()->user()->department_id);
                                    @endphp
                                    <tr class="{{ $grandchildIsYourKpi ? 'objective-kpi-owned-row' : 'bg-light' }}">
                                        <td class="pl-4 border-left border-info text-muted">↳ {{ $grandchild->code }}</td>
                                        <td class="pl-4 text-muted">
                                            {{ $grandchild->description }}
                                            @if($grandchildIsYourKpi)
                                                <span class="badge badge-kpi-owned ml-2"><i class="fas fa-bullseye mr-1"></i>Your KPI: {{ $grandchild->kpi_code }}</span>
                                            @elseif($grandchild->kpi_code)
                                                <span class="badge badge-kpi-code ml-2">KPI: {{ $grandchild->kpi_code }}</span>
                                            @endif
                                        </td>
                                        <td><small>{{ $grandchild->unit }}</small></td>
                                        <td><small>{{ $grandchild->weight }}%</small></td>
                                        <td><small>{{ $child->weight > 0 ? number_format(($grandchild->weight / $child->weight)*100, 1) : 0 }}%</small></td>
                                        <td>
                                            @if($grandchild->status === 'approved')
                                                <span class="badge badge-success badge-sm">Approved</span>
                                            @elseif($grandchild->status === 'returned')
                                                <span class="badge badge-danger badge-sm" title="{{ $grandchild->rejection_comment }}">Returned</span>
                                            @elseif($grandchild->status === 'submitted')
                                                <span class="badge badge-info badge-sm">Submitted</span>
                                            @else
                                                <span class="badge badge-secondary badge-sm">Draft</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('objectives.show', $grandchild->id) }}" class="btn btn-info"><i class="fas fa-eye"></i></a>
                                                @if($grandchild->status === 'approved')<a href="{{ route('objectives.download', $grandchild) }}" class="btn btn-danger" title="Download this activity"><i class="fas fa-file-pdf"></i></a>@endif
                                                @if($canEditObjective($grandchild))
                                                    <a href="{{ route('objectives.edit', $grandchild->id) }}" class="btn btn-warning"><i class="fas fa-edit"></i></a>
                                                @endif
                                                @can('manage_objectives')
                                                    @if(in_array($grandchild->status, ['draft', 'returned'], true))
                                                        <a href="{{ route('objectives.create', ['parent_id' => $grandchild->id]) }}" class="btn btn-success" title="Add detail activity"><i class="fas fa-plus"></i></a>
                                                    @endif
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                    @foreach($grandchild->children as $detail)
                                    <tr class="bg-light">
                                        <td class="pl-5 border-left border-secondary text-muted">{{ $detail->code }}</td>
                                        <td class="pl-5 text-muted">{{ $detail->description }} @if($detail->is_reference_only)<span class="badge badge-light border ml-1">Unweighted detail</span>@endif</td>
                                        <td><small>{{ $detail->unit }}</small></td>
                                        <td><small>{{ $detail->weight }}%</small></td>
                                        <td><small>{{ $grandchild->weight > 0 ? number_format(($detail->weight / $grandchild->weight) * 100, 1) : 0 }}%</small></td>
                                        <td><span class="badge badge-{{ $detail->status === 'approved' ? 'success' : ($detail->status === 'returned' ? 'danger' : 'secondary') }} badge-sm">{{ ucfirst($detail->status ?? 'draft') }}</span></td>
                                        <td><div class="btn-group btn-group-sm"><a href="{{ route('objectives.show', $detail) }}" class="btn btn-info"><i class="fas fa-eye"></i></a>@if($canEditObjective($detail))<a href="{{ route('objectives.edit', $detail) }}" class="btn btn-warning"><i class="fas fa-edit"></i></a>@endif</div></td>
                                    </tr>
                                    @endforeach
                                    @endforeach
                                @endif
                                @endforeach
                            </tbody>
                        </table>
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
    </div>
</section>
@endsection
