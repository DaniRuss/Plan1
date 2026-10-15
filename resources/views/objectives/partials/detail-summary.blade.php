@php
    $displayNumber = static fn ($value) => $value === null || $value === '' ? 'Not set' : \App\Support\NumberFormatter::format($value);
    $distribution = [
        'sum' => ['Sum of period values', 'Monthly targets add up to quarter and annual targets.'],
        'checkpoint' => ['Last planned checkpoint', 'Quarter and annual targets use the last planned month in each period.'],
        'manual' => ['Separate period targets', 'Quarter targets are entered independently from monthly targets.'],
    ];
    $summary = ['sum' => 'Sum of included child results', 'average' => 'Average of included child results', 'weighted' => 'Weighted child achievement', 'manual_override' => 'Entered directly'];
    $months = ['july'=>'July','august'=>'August','september'=>'September','october'=>'October','november'=>'November','december'=>'December','january'=>'January','february'=>'February','march'=>'March','april'=>'April','may'=>'May','june'=>'June'];
@endphp
<div class="d-flex flex-wrap align-items-center mb-3" style="gap:.6rem">
    @include('objectives.partials.status', ['objective' => $objective])
    <span class="badge badge-light border">{{ $objective->parent_id ? 'Sub-objective / activity' : 'Main objective' }}</span>
    <span class="badge badge-light border">{{ $objective->is_active ? 'Active' : 'Inactive' }}</span>
    @if($objective->kpi_code)<span class="badge badge-info">KPI {{ $objective->kpi_code }}</span>@endif
</div>
<h2 class="h4 mb-3">{{ $objective->description }}</h2>
@if($objective->parent)<p><strong>Parent objective:</strong> <a href="{{ route('objectives.show', $objective->parent) }}">{{ $objective->parent->code }} — {{ $objective->parent->description }}</a></p>@endif
<dl class="row objective-facts mb-0">
    <div class="col-md-6 col-xl-3"><dt>Owning department</dt><dd>{{ $objective->department?->name ?? 'Not assigned' }}</dd></div>
    <div class="col-md-6 col-xl-3"><dt>Measurement unit</dt><dd>{{ $objective->unit ?: 'Not set' }}</dd></div>
    <div class="col-md-6 col-xl-3"><dt>Objective weight</dt><dd>{{ \App\Support\NumberFormatter::format((float) $objective->weight,3) }}%<small class="d-block text-muted">@if($objective->parent && (float) $objective->parent->weight > 0){{ \App\Support\NumberFormatter::format($objective->weight / $objective->parent->weight * 100,3) }}% of parent weight@else Share of the overall score @endif</small></dd></div>
    <div class="col-md-6 col-xl-3"><dt>Baseline</dt><dd>{{ $displayNumber($objective->baseline_value) }} @if($objective->baseline_year !== null)({{ $objective->baseline_year }})@endif</dd></div>
    <div class="col-md-6"><dt>Performing departments</dt><dd>{{ $performingDepartments->pluck('name')->join(', ') ?: 'Not assigned' }}</dd></div>
    <div class="col-md-6"><dt>Lead performing department</dt><dd>{{ $objective->leadDepartment?->name ?? 'Not assigned' }}</dd></div>
    <div class="col-md-6"><dt>Target year</dt><dd>{{ $objective->target_year ?? 'Not set' }}</dd></div>
    <div class="col-md-6"><dt>Annual target</dt><dd>{{ $displayNumber($objective->annual_target) }} @if($objective->annual_target !== null){{ $objective->unit }}@endif</dd></div>
</dl>
<hr>
<h3 class="h6 font-weight-bold">Measurement and reporting rules</h3>
<dl class="row objective-facts mb-0">
    <div class="col-md-6"><dt>Achievement direction</dt><dd>{{ $objective->goal_direction === 'decrease' ? 'Lower values indicate better performance' : 'Higher values indicate better performance' }}</dd></div>
    <div class="col-md-6"><dt>Target accumulation</dt><dd>{{ $distribution[$objective->target_distribution ?? 'checkpoint'][0] ?? 'Not set' }}<small class="d-block text-muted">{{ $distribution[$objective->target_distribution ?? 'checkpoint'][1] ?? '' }}</small></dd></div>
    <div class="col-md-6"><dt>Weekly planning</dt><dd>{{ $objective->weekly_plan_mode === 'repeat' ? 'Repeat the monthly target each week' : 'Distribute the monthly target across its weeks' }}</dd></div>
    <div class="col-md-6"><dt>Child-result aggregation</dt><dd>{{ $summary[$objective->summary_type ?? 'weighted'] ?? 'Not set' }}</dd></div>
    <div class="col-md-6"><dt>Included in parent calculation</dt><dd>{{ $objective->parent_id ? ($objective->include_in_parent_rollup ? 'Yes' : 'No') : 'Not applicable — main objective' }}</dd></div>
    <div class="col-md-6"><dt>Data source</dt><dd>{{ $objective->data_source ?: 'Not specified' }}</dd></div>
</dl>
@if($objective->is_reference_only || $objective->exclude_from_overall_score)<p class="alert alert-info mb-0">{{ $objective->is_reference_only ? 'This is a reference-only objective.' : 'This objective is excluded from the overall score.' }}</p>@endif
<hr>
<h3 class="h6 font-weight-bold">Planned targets: July–June</h3>
<p class="small text-muted">These are the objective’s stored targets. “Not set” means no target was entered; zero is an explicit target.</p>
<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="thead-light"><tr><th scope="col">Quarter</th><th scope="col">Month</th><th scope="col">Monthly target</th><th scope="col">Stored quarter target</th></tr></thead><tbody>
@foreach(array_chunk($months, 3, true) as $index => $quarterMonths)
    @foreach($quarterMonths as $month => $label)<tr>@if($loop->first)<th scope="rowgroup" rowspan="3">Q{{ $index + 1 }}</th>@endif<th scope="row">{{ $label }}</th><td>{{ $displayNumber($objective->{$month.'_target'}) }}</td>@if($loop->first)<td rowspan="3">{{ $displayNumber($objective->{'q'.($index + 1).'_target'}) }}</td>@endif</tr>@endforeach
@endforeach
</tbody></table></div>
