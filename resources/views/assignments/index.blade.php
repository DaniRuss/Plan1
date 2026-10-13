@extends('layouts.app')
@section('content')
<style>
 .supplementary-objective{background:#f3e8ff!important;border-left:5px solid #7c3aed}
 .assigned-kpi{background:#f5efff!important;border-left:5px solid #6f42c1}
 .assigned-kpi-badge{background:#6f42c1;color:#fff;font-weight:700}
</style>
<div class="container-fluid">
 <div class="mb-4"><h1 class="h3 mb-1">Assigned Objectives</h1><p class="text-muted">Chief approval, Directorate assignment, and objectives released for reporting. Approving a main objective also approves and assigns its pending sub-objectives to the selected Directorate.</p></div>
 @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <div class="card shadow-sm">
  <div class="card-header bg-white font-weight-bold">
   @if(auth()->user()->isDepartmentChief()) Department Chief Queue
   @elseif(auth()->user()->isDepartmentDirectorate()) My Directorate Queue
   @elseif(auth()->user()->isDepartmentExpert()) Approved Objectives Ready for Reporting
   @elseif(auth()->user()->isPlanReportHeadOffice()) Returned and Department-Added Objectives
   @else Objective Assignment Monitoring @endif
  </div>
  <div class="card-body p-0 table-responsive"><table class="table table-bordered table-hover mb-0">
   <thead class="thead-light"><tr><th>Department</th><th>Code</th><th>Objective / Sub-objective</th><th>Target</th><th>Status</th><th>Directorate</th><th style="min-width:280px">Actions</th></tr></thead>
   <tbody>
   @forelse($assignments as $assignment)
    @php
     $item=$assignment->planItem; $objective=$item->strategicObjective;
     $statusLabels=['pending_chief_approval'=>['warning','Pending Department Chief'],'pending_directorate_approval'=>['info','Pending Directorate'],'returned_to_head_office'=>['danger','Returned to Head Office'],'returned_to_chief'=>['warning','Returned to Chief'],'approved_for_experts'=>['success','Approved for Experts']];
     [$badge,$label]=$statusLabels[$assignment->status]??['secondary',ucwords(str_replace('_',' ',$assignment->status))];
     $isAssignedKpi=$objective->isKpiForDepartment(auth()->user()->department_id);
    @endphp
    <tr class="{{ $objective->is_supplementary?'supplementary-objective':($isAssignedKpi?'assigned-kpi':'') }}">
     <td>{{ $assignment->performer->name??$item->plan->department->name }}</td><td><strong>{{ $objective->code }}</strong></td>
     <td>{{ $objective->description }}
      @if($isAssignedKpi)<span class="badge assigned-kpi-badge ml-2"><i class="fas fa-bullseye mr-1"></i>Your KPI: {{ $objective->kpi_code }}</span>
      @elseif($objective->kpi_code)<span class="badge badge-light border ml-2">KPI: {{ $objective->kpi_code }}</span>@endif
      @if($objective->is_supplementary)<span class="badge ml-2" style="background:#7c3aed;color:white">Department Added</span><div class="small text-muted">Excluded from parent rollup and overall score</div>@endif
      @if($assignment->rejection_reason)<div class="small text-danger mt-1"><strong>Return reason:</strong> {{ $assignment->rejection_reason }}</div>@endif
     </td>
     <td>{{ $item->annual_target+0 }} {{ $objective->unit }}</td><td><span class="badge badge-{{ $badge }}">{{ $label }}</span></td><td>{{ $assignment->assignedDirectorate->name??'—' }}</td>
     <td>
      @if(auth()->user()->isDepartmentChief()&&in_array($assignment->status,['pending_chief_approval','returned_to_chief']))
       @if(isset($chiefApprovalParents[$assignment->id]))
        <div class="small text-muted mb-2">Will be approved and assigned with objective {{ $chiefApprovalParents[$assignment->id] }}.</div>
       @else
       <form method="POST" action="{{ route('assignments.chief-approve',$assignment) }}" class="mb-2">@csrf
        <div class="input-group input-group-sm"><select name="directorate_user_id" class="form-control" required><option value="">Select Directorate user</option>@foreach($directorates as $directorate)<option value="{{ $directorate->id }}">{{ $directorate->name }}</option>@endforeach</select><div class="input-group-append"><button class="btn btn-success">Approve &amp; Assign to Directorate</button></div></div>
       </form>
       @endif
       @if($assignment->status==='pending_chief_approval')<form method="POST" action="{{ route('assignments.chief-return',$assignment) }}" class="input-group input-group-sm mb-2">@csrf<input name="reason" class="form-control" placeholder="Required return reason" required><div class="input-group-append"><button class="btn btn-outline-danger">Return to Head Office</button></div></form>@endif
      @endif
      @if(auth()->user()->isDepartmentChief()&&$objective->getHierarchyLevel()<3)<a class="btn btn-sm btn-outline-primary" href="{{ route('assignments.supplementary.create',$assignment) }}"><i class="fas fa-plus"></i> Add Supplementary Sub-objective</a>@endif
      @if(auth()->user()->isDepartmentDirectorate()&&$assignment->status==='pending_directorate_approval')
       <form method="POST" action="{{ route('assignments.directorate-approve',$assignment) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve for Experts</button></form>
       <form method="POST" action="{{ route('assignments.directorate-return',$assignment) }}" class="input-group input-group-sm mt-2">@csrf<input name="reason" class="form-control" placeholder="Required return reason" required><div class="input-group-append"><button class="btn btn-outline-warning">Return to Chief</button></div></form>
      @endif
      @if((auth()->user()->isPlanReportHeadOffice()||auth()->user()->isSystemAdmin())&&$assignment->status==='returned_to_head_office')<form method="POST" action="{{ route('assignments.head-office-resend',$assignment) }}">@csrf<button class="btn btn-sm btn-primary">Resend to Department Chief</button></form>@endif
      @if(auth()->user()->isDepartmentExpert()&&$assignment->status==='approved_for_experts')
       @php $reportRoute=match($assignment->performer->reporting_type??'weekly'){'daily'=>route('daily.dashboard',['department_id'=>$assignment->performer_id,'year'=>$item->plan->year]),'monthly'=>route('monthly.dashboard',['department_id'=>$assignment->performer_id,'year'=>$item->plan->year]),default=>route('weekly.dashboard',['department_id'=>$assignment->performer_id,'year'=>$item->plan->year])}; @endphp
       <a href="{{ $reportRoute }}" class="btn btn-sm btn-primary">Go to Reporting</a>
      @endif
     </td>
    </tr>
   @empty<tr><td colspan="7" class="text-center text-muted py-5">No objective assignments are currently available for your role.</td></tr>@endforelse
   </tbody>
  </table></div>
 </div>
</div>
@endsection
