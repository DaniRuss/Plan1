@php
    $exportLayout=app(\App\Services\ApprovedPlanExport::class);
    $periodColumns=\App\Services\ApprovedPlanExport::PERIODS;
@endphp
<table><thead><tr><th>Code</th><th>Planned Activity / KPI</th><th>Unit</th><th>Planning Baseline</th><th>Approved Weight</th><th>Annual Plan</th>
@foreach($periodColumns as $period=>$label)<th @if(str_starts_with($period,'q')) class="quarter-heading" @endif>{{ $label }} Plan</th>@endforeach
<th>Implementing Departments</th></tr></thead><tbody>
@forelse($exportLayout->items($schedulePlans, $scheduleItems ?? null) as $scheduleItem)
@php
    $schedulePlan=$scheduleItem->plan;
    $scheduleObjective=$scheduleItem->strategicObjective;
    $implementers=$exportLayout->implementers($scheduleItem,$departmentNames,$schedulePlan->department_id);
@endphp
<tr><td>{{ $scheduleObjective?->code }}</td><td class="desc">{{ $scheduleObjective?->description }}</td><td>{{ $scheduleItem->unit ?: $scheduleObjective?->unit }}</td><td>{{ \App\Support\NumberFormatter::format($scheduleItem->baseline_value ?? $scheduleObjective?->baseline_value ?? '-') }}</td><td>{{ \App\Support\NumberFormatter::format($scheduleItem->weight ?: $scheduleObjective?->weight) }}%</td><td>{{ \App\Support\NumberFormatter::format($scheduleItem->annual_target ?? '-') }}</td>
@foreach($periodColumns as $period=>$label)<td @if(str_starts_with($period,'q')) class="q" @endif>{{ \App\Support\NumberFormatter::format($scheduleItem->{$period.'_target'} ?? '-') }}</td>@endforeach
<td class="implementers">@forelse($implementers as $name)<div>@if($loop->first)<strong>{{ $name }}</strong>@else{{ $name }}@endif</div>@empty{{ $schedulePlan->department->name }}@endforelse</td></tr>
@empty<tr><td colspan="23">No approved action-plan items.</td></tr>@endforelse
</tbody></table>
