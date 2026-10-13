<!doctype html>
<html><head><meta charset="utf-8"><style>
@page{margin:18px}body{font-family:DejaVu Sans,sans-serif;color:#24343f;font-size:7px}h1{text-align:center;color:#123d57;margin:0 0 3px}.meta{text-align:center;color:#667780;margin-bottom:14px}.notice{padding:7px 10px;margin-bottom:10px;background:#e9f6f4;border-left:4px solid #087f77;font-size:8px}table{width:100%;border-collapse:collapse}th{background:#173b54;color:#fff;padding:4px 2px}td{border:1px solid #cfd9df;padding:3px 2px;text-align:center}td.desc{text-align:left;width:150px}.dept{background:#dff1ee;font-weight:bold;text-align:left;font-size:8px}.q{background:#e9f4f8;font-weight:bold}.footer{margin-top:10px;color:#6d7b84}
</style></head><body>
<h1>APPROVED ORGANIZATION EDR PLANS</h1>
<div class="meta">{{ $year }} EFY - {{ $plans->count() }} approved departmental plan(s)</div>
<div class="notice"><strong>Approved plans only:</strong> this export contains planning baselines, weights, targets, schedules and implementing bodies. It contains no narration and no performance results.</div>
<table><thead><tr><th>Code</th><th>Planned Activity / KPI</th><th>Department</th><th>Unit</th><th>Planning Baseline</th><th>Approved Weight</th><th>Annual Plan</th>@foreach(['Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun'] as $m)<th>{{ $m }} Plan</th>@endforeach<th>Q1 Plan</th><th>Q2 Plan</th><th>Q3 Plan</th><th>Q4 Plan</th><th>Implementing Bodies</th></tr></thead><tbody>
@foreach($plans as $plan)
 <tr><td colspan="24" class="dept">{{ $plan->department->name }} - {{ $plan->title }}</td></tr>
 @foreach($plan->items->sortBy(fn($item)=>$item->strategicObjective?->code) as $item)
  @php($objective=$item->strategicObjective)
  @php($bodies=collect($item->performers??[])->map(fn($id)=>$departmentNames[$id]??null)->filter()->implode(', '))
  <tr><td>{{ $objective?->code }}</td><td class="desc">{{ $objective?->description }}</td><td>{{ $plan->department->name }}</td><td>{{ $item->unit?:$objective?->unit }}</td><td>{{ $item->baseline_value??$objective?->baseline_value??'-' }}</td><td>{{ $item->weight?:$objective?->weight }}%</td><td>{{ $item->annual_target??'-' }}</td>@foreach(['july','august','september','october','november','december','january','february','march','april','may','june'] as $month)<td>{{ $item->{$month.'_target'}??'-' }}</td>@endforeach<td class="q">{{ $item->q1_target??'-' }}</td><td class="q">{{ $item->q2_target??'-' }}</td><td class="q">{{ $item->q3_target??'-' }}</td><td class="q">{{ $item->q4_target??'-' }}</td><td>{{ $bodies?:$plan->department->name }}</td></tr>
 @endforeach
@endforeach
</tbody></table><div class="footer">Generated from approved plans on {{ now()->format('d M Y H:i') }}</div>
</body></html>
