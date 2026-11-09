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
  <p class="text-muted">Keep every planned activity's original weight. Enter only its share of the unplanned weight. Effective weight = original weight + extra allocation. A parent with planned sub-objectives is planned through its children.</p>
  @foreach($groups as $groupKey=>$siblings)
   @php
    $target=$period.'_target';
    $resolver=app(\App\Services\PeriodWeightNormalizer::class);
    $planned=$siblings->filter(fn($item)=>$planning[$item->id] ?? false);
    $groupTotal=(float)$siblings->sum(fn($item)=>$resolver->baseWeight($item));
    $plannedOriginal=(float)$planned->sum(fn($item)=>$resolver->baseWeight($item));
    $available=round(max(0,$groupTotal-$plannedOriginal),10);
    $automatic=$planned->count()===1;
    $locked=!in_array($distribution->status,['draft','returned']);
    $parent=$siblings->first()->strategicObjective->parent;
   @endphp
   <div class="card card-outline card-info mb-3">
    <div class="card-header"><strong>{{ $parent ? $parent->code.' - '.$parent->description : 'Top-level objectives' }}</strong><div class="small mt-1">Group original: {{\App\Support\NumberFormatter::format($groupTotal,3)}} &middot; Planned original: {{\App\Support\NumberFormatter::format($plannedOriginal,3)}} &middot; <strong>Extra weight to distribute: {{\App\Support\NumberFormatter::format($available,3)}}</strong></div></div>
    <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="thead-light"><tr><th>Code</th><th>Activity</th><th>Period target / applicability</th><th>Original weight (kept)</th><th>Extra allocation</th><th>Effective {{ $period }} weight</th></tr></thead><tbody>
    @foreach($siblings as $item)
     @php
      $isPlanned=$planning[$item->id] ?? false;
      $base=$resolver->baseWeight($item);
      $saved=$existing->get($item->id)?->effective_weight;
      $extra=$automatic ? $available : old('additions.'.$item->id,$saved===null ? 0 : max(0,(float)$saved-$base));
      $effective=$locked && $saved!==null ? (float)$saved : $base+(float)$extra;
     @endphp
     <tr class="{{$isPlanned?'':'text-muted bg-light'}}" data-base="{{$base}}">
      <td>{{$item->strategicObjective->code}}</td><td>{{$item->strategicObjective->description}}</td>
      <td>@if(!$isPlanned)N/A @elseif($item->getAttribute($target)===null)<span class="badge badge-info">Planned through sub-objectives</span>@else{{\App\Support\NumberFormatter::format($item->getAttribute($target),3)}}@endif</td>
      <td>{{\App\Support\NumberFormatter::format($base,3)}}</td>
      <td>@if(!$isPlanned)<span class="badge badge-secondary">N/A</span>@elseif($automatic)<input type="hidden" class="extra-weight" value="{{$extra}}"><span>{{\App\Support\NumberFormatter::format($extra,3)}} (automatic)</span><input type="hidden" name="additions[{{$item->id}}]" value="0">@else<input type="number" step="any" min="0" max="{{$available}}" name="additions[{{$item->id}}]" data-original-number="{{$extra}}" value="{{\App\Support\NumberFormatter::format($extra,3,'.','')}}" class="form-control form-control-sm extra-weight" required @disabled($locked)>@endif</td>
      <td>@if($isPlanned)<strong class="effective-weight">{{\App\Support\NumberFormatter::format($effective,3)}}</strong>@else N/A @endif</td>
     </tr>
    @endforeach
    </tbody></table></div>
    <div class="weight-balance border-top px-3 py-2 d-flex flex-wrap justify-content-end align-items-center" data-total="{{$available}}" data-locked="{{$locked?'1':'0'}}">
     <span class="mr-3"><strong>Extra assigned:</strong> <span class="assigned-weight">0</span></span>
     <span class="mr-3"><strong>Extra remaining:</strong> <span class="remaining-weight">{{\App\Support\NumberFormatter::format($available,3)}}</span></span>
     <span class="badge balance-status badge-warning">Allocation incomplete</span>
    </div>
   </div>
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
        const inputs = card.querySelectorAll('.extra-weight');
        const assignedOutput = summary.querySelector('.assigned-weight');
        const remainingOutput = summary.querySelector('.remaining-weight');
        const status = summary.querySelector('.balance-status');

        function updateBalance() {
            let assigned = 0;
            inputs.forEach(function (input) {
                const value = Number(window.numberInputValue ? window.numberInputValue(input) : input.value);
                const row = input.closest('tr');
                const effective = row.querySelector('.effective-weight');
                if (effective && summary.dataset.locked !== '1') effective.textContent = formatNumber(Number(row.dataset.base) + (Number.isFinite(value) ? value : 0));
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
