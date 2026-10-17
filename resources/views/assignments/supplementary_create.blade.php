@extends('layouts.app')
@section('content')
<div class="content-header"><div class="container-fluid"><h1 class="m-0">Create Supplementary Sub-Objective</h1></div></div>
<section class="content"><div class="container-fluid"><div class="card card-primary">
 <div class="card-header"><h3 class="card-title">Objective Details</h3></div>
 <form method="POST" action="{{ route('assignments.supplementary.store', $assignment) }}">@csrf
  <div class="card-body">
   @php $parent=$assignment->planItem->strategicObjective; $plan=$assignment->planItem->plan; @endphp
   <div class="alert" style="background:#f3e8ff;border-left:4px solid #7c3aed"><strong>Department-added supplementary objective.</strong> Experts can report against it after Directorate approval, but it does not affect the main-objective calculation or overall performance score.</div>
   <div class="row">
    <div class="col-md-6"><div class="form-group"><label>Code</label><input class="form-control" value="Generated automatically under {{ $parent->code }}" readonly></div></div>
    <div class="col-md-6"><div class="form-group"><label>Performance Year</label><input class="form-control" value="{{ $plan->year }} EFY" readonly></div></div>
   </div>
   <div class="form-group"><label>Main Objective</label><input class="form-control" value="{{ $parent->code }} - {{ $parent->description }}" readonly><small class="text-muted">This supplementary sub-objective will be created directly under this main objective.</small></div>
   <div class="form-group"><label for="description" class="required">Description (Objective/Activity)</label><textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>@error('description')<span class="invalid-feedback">{{ $message }}</span>@enderror</div>
   <div class="row">
    @php $measurementUnits=['Percentage (%)','Number','Tons','Tons (in 1000)','Kilograms','Kilometer','Kilometers (in 1000)','Kilometers per Hour','Hours','Days','Months','TEU','Wagon','Passenger','Passengers (in 1000)','Set','Birr','Birr (Million)'];$selectedUnit=old('unit',$parent->unit); @endphp
    <div class="col-md-4"><div class="form-group"><label for="unit" class="required">Unit of Measurement</label><select name="unit" id="unit" class="form-control select2 @error('unit') is-invalid @enderror" data-tags="true" required><option value="">Select or type a unit</option>@if($selectedUnit&&!in_array($selectedUnit,$measurementUnits,true))<option value="{{ $selectedUnit }}" selected>{{ $selectedUnit }}</option>@endif @foreach($measurementUnits as $measurementUnit)<option value="{{ $measurementUnit }}" {{ $selectedUnit===$measurementUnit?'selected':'' }}>{{ $measurementUnit }}</option>@endforeach</select>@error('unit')<span class="invalid-feedback">{{ $message }}</span>@enderror</div></div>
    <div class="col-md-4"><div class="form-group"><label for="goal_direction" class="required">Goal Direction</label><select name="goal_direction" id="goal_direction" class="form-control" required><option value="increase" {{ old('goal_direction')==='increase'?'selected':'' }}>Increase (Higher is Better)</option><option value="decrease" {{ old('goal_direction')==='decrease'?'selected':'' }}>Decrease (Lower is Better)</option></select></div></div>
    <div class="col-md-4"><div class="form-group"><label for="annual_target">Annual Target</label><input type="number" step="any" min="0" name="annual_target" id="annual_target" class="form-control @error('annual_target') is-invalid @enderror" data-original-number="{{ old('annual_target') }}" value="{{ \App\Support\NumberFormatter::format(old('annual_target'),3,'.','') }}">@error('annual_target')<span class="invalid-feedback">{{ $message }}</span>@enderror</div></div>
   </div>
   @include('objectives._target_schedule')
  </div>
  <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Supplementary Sub-Objective</button><a href="{{ route('assignments.index') }}" class="btn btn-default float-right">Cancel</a></div>
 </form>
</div></div></section>
@endsection
@push('scripts')
<script>
function recalculateQuarterTargets(){var mode=$('#target_distribution').val(),groups=[[['july','august','september'],'q1_target'],[['october','november','december'],'q2_target'],[['january','february','march'],'q3_target'],[['april','may','june'],'q4_target']];groups.forEach(function(g){var months=g[0],input=$('#'+g[1]);input.prop('readonly',mode!=='manual').toggleClass('bg-light',mode!=='manual');if(mode==='sum'){var total=0;months.forEach(function(m){total+=parseFloat($('#'+m+'_target').val())||0});input.val(total).siblings('.quarter-calculation-note').text('Sum of the three months')}else if(mode==='checkpoint'){input.val($('#'+months[2]+'_target').val()).siblings('.quarter-calculation-note').text('Copied from the final month')}else{input.siblings('.quarter-calculation-note').text('Enter the quarter target manually')}})}
$(document).on('input change','.target-month, #target_distribution',recalculateQuarterTargets);$(document).ready(recalculateQuarterTargets);
</script>
@endpush
