@extends('layouts.app')
@section('page_header_hidden', '1')
@section('title','Performance Comparison')
@section('breadcrumb')<li class="breadcrumb-item active">Performance Comparison</li>@endsection
@section('content')
@php($c = $comparison)
<div class="container-fluid py-3 comparison-workspace">
 <section class="comparison-banner mb-3"><small>PERFORMANCE INTELLIGENCE</small><h1 class="h3 mt-2">Performance Comparison</h1><p class="mb-0">Compare KPI achievement across years, periods, departments, or against targets.</p></section>
 <form method="GET" action="{{ route('performance.comparison') }}" class="card card-body mb-3" id="comparison-filters">
  <div class="row">
   <div class="col-md-4 col-xl-3 form-group"><label for="comparison-method">Comparison method</label><select id="comparison-method" name="comparison_method" class="form-control">
    @foreach(['year'=>'Year versus year','period'=>'Period versus period','department'=>'Department versus department','target'=>'Actual versus target'] as $key=>$label)
     @if($key !== 'department' || $c['organizationWide'])<option value="{{ $key }}" @selected($c['method']===$key)>{{ $label }}</option>@endif
    @endforeach
   </select></div>
   <div class="col-md-4 col-xl-3 form-group"><label for="current-year">Current Plan Year</label><select id="current-year" name="performance_year" class="form-control">@foreach($c['years']->push($c['year'])->unique()->sortDesc() as $year)<option value="{{ $year }}" @selected($c['year']===$year)>{{ $year }}</option>@endforeach</select></div>
   <div class="col-md-4 col-xl-3 form-group" data-method="year"><label for="reference-year">Reference plan year</label><select id="reference-year" name="reference_year" class="form-control">@foreach($c['years']->push($c['referenceYear'])->unique()->sortDesc() as $year)<option value="{{ $year }}" @selected($c['referenceYear']===$year)>{{ $year }}</option>@endforeach</select></div>
   <div class="col-md-4 col-xl-3 form-group"><label for="current-period">Current period</label><select id="current-period" name="performance_period" class="form-control">@foreach($c['periods'] as $key=>$label)<option value="{{ $key }}" @selected($c['period']===$key)>{{ $label }}</option>@endforeach</select></div>
   <div class="col-md-4 col-xl-3 form-group" data-method="period"><label for="reference-period">Reference period</label><select id="reference-period" name="reference_period" class="form-control">@foreach($c['periods'] as $key=>$label)<option value="{{ $key }}" @selected($c['referencePeriod']===$key)>{{ $label }}</option>@endforeach</select></div>
   @if($c['organizationWide'])
   <div class="col-md-4 col-xl-3 form-group"><label for="current-department">Department</label><select id="current-department" name="performance_department" class="form-control"><option value="">All authorized departments</option>@foreach($c['departments'] as $department)<option value="{{ $department->id }}" @selected($c['department']==$department->id)>{{ $department->name }}</option>@endforeach</select></div>
   <div class="col-md-4 col-xl-3 form-group" data-method="department"><label for="reference-department">Reference department</label><select id="reference-department" name="reference_department" class="form-control"><option value="">Choose a department</option>@foreach($c['departments'] as $department)<option value="{{ $department->id }}" @selected($c['referenceDepartment']==$department->id)>{{ $department->name }}</option>@endforeach</select></div>
   @endif
   <div class="col-md-4 col-xl-3 form-group"><label for="frequency">Reporting frequency</label><select id="frequency" name="reporting_frequency" class="form-control">@foreach(['all'=>'All frequencies','daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $key=>$label)<option value="{{ $key }}" @selected($c['frequency']===$key)>{{ $label }}</option>@endforeach</select></div>
   <div class="col-md-4 col-xl-3 form-group"><label for="result-scope">Review status</label><select id="result-scope" name="result_scope" class="form-control"><option value="official" @selected($c['scope']==='official')>Final reviewed results</option>@if(!$c['finalOnly'])<option value="reported" @selected($c['scope']==='reported')>All reported results (provisional)</option>@endif</select></div>
   <div class="col-md-6 col-xl-6 form-group"><label for="kpi">KPI / Activity</label><select id="kpi" name="performance_kpi" class="form-control"><option value="">All KPIs</option>@if($c['kpi'] && !$c['kpis']->contains($c['kpi']))<option selected value="{{ $c['kpi'] }}">{{ $c['kpi'] }} (no matching results)</option>@endif @foreach($c['kpis'] as $kpi)<option value="{{ $kpi }}" @selected($c['kpi']===$kpi)>{{ $kpi }}</option>@endforeach</select></div>
   <div class="col-md-3 form-group"><label for="movement">Performance movement</label><select id="movement" name="movement" class="form-control">@foreach(['all'=>'All results','improved'=>'Improved / above target','declined'=>'Declined / below target','unchanged'=>'Unchanged / on target','unmatched'=>'Missing comparison'] as $key=>$label)<option value="{{ $key }}" @selected($c['movement']===$key)>{{ $label }}</option>@endforeach</select></div>
   <div class="col-md-3 form-group"><label for="sort">Sort results</label><select id="sort" name="comparison_sort" class="form-control">@foreach(['change'=>'Largest change first','achievement'=>'Highest achievement first','name'=>'KPI name'] as $key=>$label)<option value="{{ $key }}" @selected($c['sort']===$key)>{{ $label }}</option>@endforeach</select></div>
  </div>
  <div><button class="btn btn-primary"><i class="fas fa-filter mr-1"></i>Apply Filters</button><a class="btn btn-outline-secondary ml-2" href="{{ route('performance.comparison') }}">Reset</a></div>
 </form>
 @if($c['scope']==='reported')<div class="alert alert-warning"><strong>Provisional comparison.</strong> These results include drafts and reports awaiting review. They are not official approved performance.</div>@endif
 <div class="card card-body mb-3"><strong>Current: {{ $c['currentLabel'] }}</strong><span class="text-muted">Reference: {{ $c['referenceLabel'] }}</span><small class="mt-2 text-muted">Year, period, and department comparisons cap achievement at 100%. Actual-versus-target comparisons show uncapped achievement so overachievement remains visible. All methods account for whether higher or lower values are better. Summaries compare only matched KPIs with the same name, unit, and achievement direction. Year and department methods use common reporting periods; period comparisons use the two selected periods. Multiple records are averaged by KPI and period, not added across different units. This is a comparison of achievement, not the weighted executive scorecard. Daily work uses its weekly reporting summaries.</small></div>
 <div class="row mb-3">@foreach(['Current matched achievement'=>$c['currentAverage'],'Reference matched achievement'=>$c['referenceAverage'],'Change (percentage points)'=>$c['delta']] as $label=>$value)<div class="col-md-4 mb-2"><div class="card card-body h-100"><small class="text-muted">{{ $label }}</small><strong class="h4 mb-0">{{ $value === null ? 'N/A' : $value }}{{ $value === null ? '' : ($loop->last ? ' pp' : '%') }}</strong></div></div>@endforeach</div>
 @if($c['rows']->isNotEmpty())
 <div class="card card-body mb-3"><h2 class="h6 font-weight-bold">Same-Period Trend / KPI Comparison</h2><p class="small text-muted">{{ $c['matched'] }} matched KPIs; {{ $c['unmatched'] }} without both sides. Chart shows the first 12 filtered results; the table includes all results.</p><div style="height:360px"><canvas id="comparison-kpis"></canvas></div></div>
 <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="thead-light"><tr><th>KPI / Activity</th><th>Unit</th><th>Current achievement</th><th>Reference achievement</th><th>Change</th><th>Movement</th>@if($c['method']==='target')<th>Actual / target</th>@endif</tr></thead><tbody>
 @foreach($c['rows'] as $row)<tr><th scope="row" style="min-width:240px">{{ $row['objective'] }}</th><td>{{ $row['unit'] ?: 'Not set' }}</td><td>{{ $row['current'] === null ? 'N/A' : $row['current'].'%' }}<small class="d-block text-muted">{{ $row['currentRecords'] }} records</small></td><td>{{ $row['reference'] === null ? 'N/A' : $row['reference'].'%' }}</td><td>{{ $row['delta'] === null ? 'N/A' : ($row['delta']>0?'+':'').$row['delta'].' pp' }}</td><td>{{ ['improved'=>'Improved / above target','declined'=>'Declined / below target','unchanged'=>'Unchanged / on target','unmatched'=>'Missing comparison'][$row['direction']] }}</td>@if($c['method']==='target')<td>{{ $row['actual'] === null ? 'Multiple periods — see achievement' : \App\Support\NumberFormatter::format($row['actual'],3).' / '.\App\Support\NumberFormatter::format($row['target'],3) }}</td>@endif</tr>@endforeach
 </tbody></table></div></div>
 @else<div class="card card-body text-center py-5"><strong>No performance matches these filters.</strong><span class="text-muted">Try another year, period, frequency, or KPI.@if($c['scope']==='official' && !$c['finalOnly']) To inspect work awaiting approval, select “All reported results (provisional)”.@endif Missing results are not treated as zero.</span></div>@endif
</div>
@endsection
@push('styles')<style>.comparison-banner{padding:26px;border-radius:16px;color:#fff;background:linear-gradient(120deg,#173b54,#078f83)}.comparison-workspace label{font-size:.8rem;font-weight:600;color:#526675}.comparison-workspace td,.comparison-workspace th{vertical-align:middle}.comparison-workspace .card{border-radius:12px}</style>@endpush
@push('scripts')
<script>
(() => {
 const method = document.getElementById('comparison-method');
 const update = () => document.querySelectorAll('[data-method]').forEach(group => { const active = group.dataset.method === method.value; group.hidden = !active; group.querySelectorAll('select').forEach(input => input.disabled = !active); });
 method.addEventListener('change',update); update();
})();
</script>
@if($c['rows']->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>(()=>{const rows=@json($c['rows']->take(12)->values());new Chart(document.getElementById('comparison-kpis'),{type:'bar',data:{labels:rows.map(r=>r.objective),datasets:[{label:'Current achievement (%)',data:rows.map(r=>r.current),backgroundColor:'#078f83'},{label:'Reference achievement (%)',data:rows.map(r=>r.reference),backgroundColor:'#9baab6'}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,scales:{x:{beginAtZero:true,suggestedMax:100}},plugins:{legend:{position:'bottom'}}}})})();</script>
@endif
@endpush
