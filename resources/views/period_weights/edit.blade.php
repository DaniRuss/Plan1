@extends('layouts.app')
@section('title','Period Weight Distribution')
@section('content')
<div class="container-fluid py-3">
 <div class="card border-0 shadow-sm"><div class="card-header bg-dark text-white"><h4 class="mb-1">Manual Period Weight Distribution</h4><small>Allocate unplanned weight before performance reporting. Original annual weights are never changed.</small></div>
 <div class="card-body">
  <form method="GET" class="form-row mb-4">
   <div class="col-md-4"><label>Department</label><select name="department_id" class="form-control" onchange="this.form.submit()">@foreach($departments as $department)<option value="{{$department->id}}" @selected($plan->department_id===$department->id)>{{$department->name}}</option>@endforeach</select></div>
   <div class="col-md-3"><label>Plan Year</label><select name="year" class="form-control" onchange="this.form.submit()">@foreach($years as $availableYear)<option value="{{$availableYear}}" @selected($year===$availableYear)>{{$availableYear}}</option>@endforeach</select></div>
   <div class="col-md-3"><label>Month / Quarter</label><select name="period" class="form-control" onchange="this.form.submit()">@foreach(['july','august','september','october','november','december','january','february','march','april','may','june','q1','q2','q3','q4'] as $availablePeriod)<option value="{{$availablePeriod}}" @selected($period===$availablePeriod)>{{strtoupper($availablePeriod)}}</option>@endforeach</select></div>
   <div class="col-md-2"><label>Status</label><div><span class="badge badge-{{$distribution->status==='approved'?'success':'info'}} p-2">{{strtoupper(str_replace('_',' ',$distribution->status))}}</span></div></div>
  </form>
  @if($errors->any())<div class="alert alert-danger">{{$errors->first()}}</div>@endif
  <form method="POST" action="{{route('period-weights.store')}}">@csrf<input type="hidden" name="department_id" value="{{$plan->department_id}}"><input type="hidden" name="year" value="{{$year}}"><input type="hidden" name="period" value="{{$period}}">
  @if($groups->isEmpty())
   <div class="alert alert-success mb-0"><i class="fas fa-check-circle mr-1"></i>No manual redistribution is required for {{strtoupper($period)}}. Every activity group is either fully planned or has no planned activity for this period.</div>
  @endif
  @foreach($groups as $groupKey=>$siblings)
   @php($target=$period.'_target') @php($planned=$siblings->filter(fn($item)=>$item->getAttribute($target)!==null)) @php($groupTotal=$siblings->sum(fn($item)=>app(\App\Services\PeriodWeightNormalizer::class)->baseWeight($item))) @php($plannedOriginal=$planned->sum(fn($item)=>app(\App\Services\PeriodWeightNormalizer::class)->baseWeight($item)))
   <div class="card card-outline card-info mb-3"><div class="card-header d-flex justify-content-between"><strong>Sibling Group {{$groupKey}}</strong><span>Total: {{\App\Support\NumberFormatter::format($groupTotal,3)}} · Planned original: {{\App\Support\NumberFormatter::format($plannedOriginal,3)}} · Available: {{\App\Support\NumberFormatter::format($groupTotal-$plannedOriginal,3)}}</span></div>
   <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="thead-light"><tr><th>Code</th><th>Activity</th><th>Period target</th><th>Original weight</th><th>Effective {{$period}} weight</th></tr></thead><tbody>
   @foreach($siblings as $item) @php($isPlanned=$item->getAttribute($target)!==null) @php($base=app(\App\Services\PeriodWeightNormalizer::class)->baseWeight($item)) @php($auto=$isPlanned&&$planned->count()===$siblings->count()?$base:($isPlanned&&$planned->count()===1?$groupTotal:null))
    <tr class="{{$isPlanned?'':'text-muted bg-light'}}"><td>{{$item->strategicObjective->code}}</td><td>{{$item->strategicObjective->description}}</td><td>{{$isPlanned?$item->getAttribute($target):'N/A'}}</td><td>{{\App\Support\NumberFormatter::format($base,3)}}</td><td>@if(!$isPlanned)<span class="badge badge-secondary">N/A</span>@elseif($auto!==null)<input class="form-control form-control-sm" value="{{\App\Support\NumberFormatter::format($auto,3,'.','')}}" disabled><input type="hidden" name="weights[{{$item->id}}]" value="{{$auto}}">@else<input type="number" step="any" min="0" max="{{$groupTotal}}" name="weights[{{$item->id}}]" data-original-number="{{ old('weights.'.$item->id,$existing->get($item->id)?->effective_weight) }}" value="{{ \App\Support\NumberFormatter::format(old('weights.'.$item->id,$existing->get($item->id)?->effective_weight),3,'.','') }}" class="form-control form-control-sm" required @disabled(!in_array($distribution->status,['draft','returned']))>@endif</td></tr>
   @endforeach</tbody></table></div>
   <div class="weight-balance border-top px-3 py-2 d-flex flex-wrap justify-content-end align-items-center" data-total="{{$groupTotal}}">
    <span class="mr-3"><strong>Assigned:</strong> <span class="assigned-weight">0.0000</span></span>
    <span class="mr-3"><strong>Remaining:</strong> <span class="remaining-weight">{{\App\Support\NumberFormatter::format($groupTotal,3)}}</span></span>
    <span class="badge balance-status badge-warning">Allocation incomplete</span>
   </div></div>
  @endforeach
  @if($groups->isNotEmpty() && (auth()->user()->isPlanReportDepartmentExpert() || auth()->user()->isSystemAdmin()) && in_array($distribution->status,['draft','returned']))<button name="action" value="save" class="btn btn-outline-primary">Save Draft</button><button name="action" value="submit" class="btn btn-success">Save & Send to Directorate</button>@endif
  </form>
  @if($distribution->exists)
   <form method="POST" action="{{route('period-weights.transition')}}" class="mt-3">@csrf<input type="hidden" name="distribution_id" value="{{$distribution->id}}"><input name="reason" class="form-control form-control-sm d-inline-block mr-2" style="width:300px" placeholder="Reason when returning/reopening">
   @if(auth()->user()->isPlanReportDirectorate()&&$distribution->status==='submitted_to_directorate')<button name="action" value="return" class="btn btn-warning">Return</button><button name="action" value="send_to_head" class="btn btn-success">Send to Head</button>@elseif(auth()->user()->isPlanReportHeadOffice()&&$distribution->status==='submitted_to_head')<button name="action" value="approve" class="btn btn-success">Approve & Lock</button>@elseif(auth()->user()->isPlanReportHeadOffice()&&$distribution->status==='approved')<button name="action" value="reopen" class="btn btn-danger">Reopen</button>@endif
   </form>
  @endif
 </div></div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tolerance = 0.0001;

    document.querySelectorAll('.weight-balance').forEach(function (summary) {
        const card = summary.closest('.card');
        const total = Number(summary.dataset.total || 0);
        const inputs = card.querySelectorAll('input[name^="weights["]');
        const assignedOutput = summary.querySelector('.assigned-weight');
        const remainingOutput = summary.querySelector('.remaining-weight');
        const status = summary.querySelector('.balance-status');

        function updateBalance() {
            let assigned = 0;
            inputs.forEach(function (input) {
                const value = Number(input.value);
                if (Number.isFinite(value)) assigned += value;
            });

            const remaining = total - assigned;
            const balanced = Math.abs(remaining) <= tolerance;
            assignedOutput.textContent = formatNumber(assigned);
            remainingOutput.textContent = formatNumber(balanced ? 0 : remaining);
            remainingOutput.classList.toggle('text-danger', !balanced);
            remainingOutput.classList.toggle('text-success', balanced);
            status.textContent = balanced ? 'Balanced — ready to submit' : (remaining < 0 ? 'Over-allocated' : 'Allocation incomplete');
            status.classList.toggle('badge-success', balanced);
            status.classList.toggle('badge-danger', remaining < -tolerance);
            status.classList.toggle('badge-warning', !balanced && remaining >= -tolerance);
        }

        inputs.forEach(function (input) { input.addEventListener('input', updateBalance); });
        updateBalance();
    });
});
</script>
@endpush
