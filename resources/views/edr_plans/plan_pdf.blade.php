<!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:22px}body{font-family:DejaVu Sans,sans-serif;color:#24343f;font-size:9px}h1{text-align:center;color:#123d57;margin-bottom:3px}h2{color:#087f77;border-bottom:2px solid #087f77;padding-bottom:4px;margin-top:18px}.meta{text-align:center;color:#667780;margin-bottom:20px}.section-text{white-space:pre-line;line-height:1.6;text-align:justify}.empty{color:#999;font-style:italic}table{width:100%;border-collapse:collapse;font-size:7px}th{background:#173b54;color:#fff;padding:4px}td{border:1px solid #cfd9df;padding:3px;text-align:center}td.desc{text-align:left;width:170px}.q{background:#e5f5f3;font-weight:bold}.summary{font-size:8px}.summary td{padding:5px}.summary .objective{text-align:left}.summary .level-1{padding-left:14px}.summary .level-2{padding-left:25px;color:#536773}.footer{margin-top:14px;color:#6d7b84}.page-break{page-break-before:always}
</style></head><body>
<h1>EDR PLAN</h1><div class="meta"><strong>{{ $plan->department->name }}</strong> · {{ $plan->year }} EFY · {{ $selectedObjective ? 'Objective '.$selectedObjective->code : 'Complete Approved Plan' }}</div>
@if(!$selectedObjective)
@php($n=$narrative)
@foreach([
 ['1. Introduction','introduction'],['2. Past Year’s Performance, Strengths, Weaknesses and Future Focus Areas','past_performance'],['3. Strategic Issues (Vision, Mission, Values and Goals)','strategic_issues']
] as [$heading,$field])<h2>{{ $heading }}</h2><div class="section-text {{ blank($n?->$field) ? 'empty' : '' }}">{{ $n?->$field ?: 'Narration has not yet been prepared.' }}</div>@endforeach
@endif
<h2>{{ $selectedObjective ? 'Objective and Detailed Activities' : '4. Objectives and Detailed Activities' }}</h2>
<p>The following approved objectives and detailed activities are generated automatically from this plan.</p>
<table class="summary"><thead><tr><th>Code</th><th>Objective / Detailed Activity</th><th>Measurement</th><th>Weight</th><th>Implementing Bodies</th></tr></thead><tbody>
@forelse($items->sortBy(fn($item)=>$item->strategicObjective?->code) as $summaryItem)
 @php($summaryObjective=$summaryItem->strategicObjective)
 @php($summaryLevel=$summaryObjective?->getHierarchyLevel() ?? 0)
 @php($summaryBodies=collect($summaryItem->performers??[])->map(fn($id)=>$departmentNames[$id]??null)->filter()->implode(', '))
 <tr><td>{{ $summaryObjective?->code }}</td><td class="objective level-{{ min(2,$summaryLevel) }}">{{ $summaryObjective?->description }}</td><td>{{ $summaryItem->unit?:$summaryObjective?->unit }}</td><td>{{ $summaryItem->weight?:$summaryObjective?->weight }}%</td><td>{{ $summaryBodies?:$plan->department->name }}</td></tr>
@empty<tr><td colspan="5">No approved objectives or detailed activities.</td></tr>@endforelse
</tbody></table>
@if(!$selectedObjective)
@foreach([['5. Implementation Strategies','implementation_strategies'],['6. Monitoring and Evaluation System','monitoring_evaluation'],['7. Challenges and Possible Solutions','challenges_solutions']] as [$heading,$field])<h2>{{ $heading }}</h2><div class="section-text {{ blank($n?->$field) ? 'empty' : '' }}">{{ $n?->$field ?: 'Narration has not yet been prepared.' }}</div>@endforeach
@endif
<h2 class="page-break">{{ $selectedObjective ? 'Action Plan Schedule' : '8. Action Plan' }}</h2>
<table><thead><tr><th>Code</th><th>Activity / KPI</th><th>Measurement</th><th>Baseline</th><th>Weight</th><th>Annual</th>@foreach($months as $m)<th>{{ strtoupper(substr($m,0,3)) }}</th>@endforeach<th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Implementing bodies</th></tr></thead><tbody>
@forelse($items as $item) @php($o=$item->strategicObjective) @php($bodies=collect($item->performers ?? [])->map(fn($id)=>$departmentNames[$id] ?? null)->filter()->implode(', '))<tr><td>{{ $o?->code }}</td><td class="desc">{{ $o?->description }}</td><td>{{ $item->unit ?: $o?->unit }}</td><td>{{ $item->baseline_value ?? $o?->baseline_value ?? '-' }}</td><td>{{ $item->weight ?: $o?->weight }}%</td><td>{{ $item->annual_target }}</td>@foreach($months as $m)<td>{{ $item->{$m.'_target'} ?? '-' }}</td>@endforeach<td class="q">{{ $item->q1_target ?? '-' }}</td><td class="q">{{ $item->q2_target ?? '-' }}</td><td class="q">{{ $item->q3_target ?? '-' }}</td><td class="q">{{ $item->q4_target ?? '-' }}</td><td>{{ $bodies ?: $plan->department->name }}</td></tr>@empty<tr><td colspan="23">No approved action-plan items.</td></tr>@endforelse
</tbody></table><div class="footer">Organization narration prepared by: {{ $narrative?->preparer?->name ?? 'Not recorded' }} · Generated {{ now()->format('d M Y H:i') }}</div></body></html>
