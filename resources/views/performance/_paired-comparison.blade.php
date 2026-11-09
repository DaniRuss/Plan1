@extends('layouts.app')
@section('page_header_hidden','1')
@section('title','Performance Comparison')
@section('content')
@php
    $b=$c['builder']; $rows=$c['pairedRows'];
    $statusLabels=$b['type']==='target'?['improved'=>'Above target','declined'=>'Below target','unchanged'=>'On target']:['improved'=>'Improved','declined'=>'Declined','unchanged'=>'Unchanged'];
    $number=static fn($v)=>$v===null?'Not reported':\App\Support\NumberFormatter::format($v,2);
    $percent=static fn($v)=>$v===null?'Awaiting result':number_format($v,2).'%';
    $chartRows=$rows->take(12)->map(fn($r)=>['objective'=>($r['reference']['kpi']??$r['current']['kpi']??'').' · '.($r['reference']['description']??$r['current']['description']),'reference'=>$r['reference']['achievement']??null,'current'=>($r['current']['complete']??false)?$r['current']['achievement']:null])->values();
    $chartHeight=max(260,min(640,$chartRows->count()*54+120));
    $exportRecord=static function($r,$rowType='indicator',$parentKpi=null) use($c,$b){
        $out=['rowType'=>$rowType,'parentKpi'=>$parentKpi,'comparison'=>$b['type'],'referenceYear'=>$c['referenceLabel'],'currentYear'=>$c['currentLabel'],'referencePeriod'=>$b['referencePeriod'],'currentPeriod'=>$b['currentPeriod']];
        foreach(['reference','current'] as $side){
            foreach(['code','kpi','description','unit','department','target','actual','achievement','rawAchievement','complete','source','sourceRow','weight','contribution'] as $field)$out[$side.ucfirst($field)]=$r[$side][$field]??null;
            $out[$side.'MeasureType']=$r[$side]['measureType']??'direct';
            $out[$side.'WeightedScore']=$r[$side]['rollup']['score']??null;
            $out[$side.'AvailablePoints']=$r[$side]['rollup']['weight']??null;
            $out[$side.'CompletedActivities']=$r[$side]['rollup']['completed']??null;
            $out[$side.'ExpectedActivities']=$r[$side]['rollup']['expected']??null;
        }
        return $out+\Illuminate\Support\Arr::only($r,['delta','actualChange','targetChange','status','reason']);
    };
    $exportRows=$rows->flatMap(function($r) use($exportRecord){
        $out=[$exportRecord($r)];
        foreach($r['componentPairs']??[] as $component)$out[]=$exportRecord($component,'component',$r['reference']['kpi']??$r['current']['kpi']??null);
        return $out;
    });
@endphp
<div class="container-fluid pc-workspace py-3">
    <header class="pc-hero"><div class="pc-hero-copy"><span class="pc-eyebrow">IPRPMS · PERFORMANCE INTELLIGENCE</span><h1>{{ $b['view']==='master'?'Master Performance Comparison':$b['types'][$b['type']] }}</h1><p>Choose your Reference and Comparison evidence. Compare established indicators across selected fiscal years, periods or objectives.</p></div><div class="pc-focus-badge"><i class="fas fa-exchange-alt"></i><span>PAIRED EVIDENCE</span><strong>{{ $c['matched'] }}</strong><small>Valid like-for-like pairs · {{ $c['unmatched'] }} pending / context</small></div></header>
    @include('performance._comparison-navigation',['c'=>$c])
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('performance._comparison-builder',['c'=>$c])
    @if($c['selectionNotice'])<div class="pc-explainer"><i class="fas fa-info-circle"></i><p>{{ $c['selectionNotice'] }}</p></div>@endif
    @if($c['scope']==='reported')<div class="alert alert-warning"><strong>Provisional comparison.</strong> Reporting-period results may include work awaiting review. Annual comparisons always use final reviewed evidence.</div>@endif
    <div class="pc-source-grid">@foreach(['reference'=>'REFERENCE','current'=>'COMPARISON'] as $side=>$label)<section class="pc-source {{ $side==='reference'?'baseline':'current' }}"><div class="pc-source-icon"><i class="fas {{ $b[$side.'Source']['kind']==='history'?'fa-file-excel':'fa-chart-line' }}"></i></div><div><span class="pc-kicker">{{ $label }}</span><h2>{{ $c[$side.'Label'] }}</h2><p>{{ $c[$side.'Filename'] }}</p>@if($c[$side.'Selected'])<p><strong>{{ $c[$side.'Selected']['kpi'] }} {{ $c[$side.'Selected']['code'] }}</strong> · {{ $c[$side.'Selected']['description'] }}</p>@endif<div class="pc-source-meta"><span>{{ $b['periods'][$b[$side.'Period']]??'Select a reporting period' }}</span><span>{{ $b['departments']->firstWhere('id',$b[$side.'Department'])?->name ?? 'Accountable organization results' }}</span></div></div></section>@endforeach</div>
    <div class="pc-explainer"><i class="fas fa-info-circle"></i><p><strong>Results, not assumptions.</strong> Historical annual values remain annual. Partial progress and different indicators stay visible, but only complete compatible pairs receive a change verdict. Objective groups compare component indicators—not totals added across different measurement units.</p></div>
    <div class="pc-metrics"><section class="pc-metric"><span>Reference available achievement</span><strong>{{ $percent($c['referenceAvailable']) }}</strong><small>Available selected indicators · not automatically the matched cohort</small></section><section class="pc-metric"><span>Comparison available achievement</span><strong>{{ $percent($c['currentAvailable']) }}</strong><small>Progress may be partial · see each activity’s completeness</small></section><section class="pc-metric"><span>Like-for-like matched change</span><strong class="{{ $c['delta']===null?'pc-waiting':($c['delta']<0?'pc-down':'pc-up') }}">{{ $c['delta']===null?'Not ready yet':($c['delta']>0?'+':'').number_format($c['delta'],2).' pp' }}</strong><small>Matched Reference {{ $percent($c['referenceAverage']) }} → Comparison {{ $percent($c['currentAverage']) }}</small></section><section class="pc-metric"><span>Comparable evidence coverage</span><strong>{{ $c['matched'] }} <em>/ {{ $rows->count() }}</em></strong><small>{{ $c['unmatched'] }} rows pending, missing or context-only</small></section></div>
    @if($rows->isNotEmpty())
    <div class="pc-analysis-grid">
        <section class="pc-panel pc-chart-panel">
            <div class="pc-section-heading"><div><span class="pc-kicker">THE SAME EVIDENCE, SIDE BY SIDE</span><h2>{{ $b['type']==='kpi'?'KPI against KPI':($b['type']==='objective'?'Objective indicator comparison':'Selected performance comparison') }}</h2><p>First 12 rows. Full source wording and every result remain in the table.</p></div></div>
            <div id="pc-chart-fallback">@foreach($chartRows as $r)<div class="pc-bar-row"><div class="pc-bar-name">{{ $r['objective'] }}</div>@foreach(['reference'=>'baseline','current'=>'current'] as $side=>$class)<div class="pc-bar-track"><span class="{{ $class }}" style="width:{{ min(100,max(0,$r[$side]??0)) }}%"></span><small>{{ $percent($r[$side]) }}</small></div>@endforeach</div>@endforeach</div>
            <div id="pc-chart-canvas" hidden style="height:{{ $chartHeight }}px"><canvas id="comparison-kpis" data-chart-scrollable="1" aria-label="Selected reference and current KPI achievement"></canvas></div>
            <p class="pc-footnote">Blank bars are unavailable or incomplete—not zero. Different definitions are context, not a growth claim.</p>
        </section>
        <aside class="pc-panel pc-insights">
            <span class="pc-kicker">COMPARISON SIGNALS</span><h2>{{ $b['type']==='target'?'Against the plan':'What changed?' }}</h2>
            <div class="pc-signal-counts">@foreach($statusLabels as $key=>$label)<div><b>{{ $c['signals'][$key]??0 }}</b><span>{{ $label }}</span></div>@endforeach</div>
            <div class="pc-insight"><i class="fas fa-shield-alt"></i><div><strong>Each verdict has evidence.</strong><p>Codes and validated relationships identify counterparts; compatible definitions, units, directions and complete periods make a valid comparison. Existing draft or changed mappings do not become approved through this screen.</p></div></div>
            <div class="pc-readiness"><strong>Methodology</strong><p class="pc-footnote">Paired averages use exactly the same complete indicator cohort and equal weighting. They are not the weighted executive scorecard. Available-side averages are context and are not subtracted from different populations.</p></div>
        </aside>
    </div>
    @else<section class="pc-panel pc-empty"><i class="fas fa-search"></i><h2>Select available indicators to compare</h2><p>No authorized evidence matches both selections. Change the source, department or indicator, or reset the filters. No missing value is converted to zero.</p></section>@endif
    <section class="pc-panel pc-table-panel" id="pc-activities"><div class="pc-section-heading"><div><span class="pc-kicker">TRACE EVERY RESULT</span><h2>Indicator-by-indicator comparison</h2><p>{{ $rows->count() }} rows · original descriptions, targets, actuals and source evidence</p></div><div class="pc-table-actions"><select id="pc-page-size" class="form-control" aria-label="Rows per page"><option value="20">20 per page</option><option value="50">50 per page</option><option value="0">Show all</option></select><button type="button" id="pc-download" class="btn btn-outline-secondary">CSV</button></div></div><div class="table-responsive"><table id="pc-table" class="table pc-table"><thead><tr><th>Indicator relationship</th><th class="baseline-heading">{{ $c['referenceLabel'] }}<small>REFERENCE</small></th><th class="current-heading">{{ $c['currentLabel'] }}<small>COMPARISON</small></th><th>Change &amp; explanation</th></tr></thead><tbody>
    @forelse($rows as $r)<tr class="pc-data-row"><th class="pc-activity" scope="row"><div class="pc-codes"><span class="pc-kpi-code">{{ $r['reference']['kpi']??$r['current']['kpi']?:'Activity' }}</span><span>{{ $r['reference']['code']??'Not linked' }} ↔ {{ $r['current']['code']??'Not linked' }}</span></div><strong>{{ $r['reference']['description']??$r['current']['description'] }}</strong>@if($r['reference'] && $r['current'] && $r['reference']['description']!==$r['current']['description'])<small class="pc-current-name">Comparison wording: {{ $r['current']['description'] }}</small>@endif<details class="pc-evidence"><summary>Source &amp; calculation evidence</summary><div>@foreach(['reference'=>'Reference','current'=>'Comparison'] as $side=>$label)@if($r[$side])<p><strong>{{ $label }}:</strong> {{ $r[$side]['source'] }}@if($r[$side]['sourceRow']) · Source row {{ $r[$side]['sourceRow'] }} @endif<br>{{ $r[$side]['department'] }} · {{ $r[$side]['direction']==='decrease'?'Lower is better':'Higher is better' }}<br>Uncapped achievement: {{ $percent($r[$side]['rawAchievement']) }}<br>{{ $r[$side]['completed'] }} / {{ $r[$side]['expected'] }} planned periods complete.</p>@endif @endforeach</div></details></th>
    @foreach(['reference','current'] as $side)<td class="{{ $side==='reference'?'pc-baseline-cell':'' }}">@if($r[$side])
        @if(($r[$side]['measureType']??'direct')==='composite')
            @include('performance._composite-evidence',['record'=>$r[$side]])
        @else
            <dl class="pc-values"><div><dt>Target</dt><dd>{{ $number($r[$side]['target']) }}</dd></div><div><dt>Actual</dt><dd>{{ $number($r[$side]['actual']) }}</dd></div></dl><strong class="pc-achievement {{ !$r[$side]['complete']?'pc-incomplete':'' }}">{{ $percent($r[$side]['achievement']) }}</strong><span class="pc-value-caption">{{ $r[$side]['unit']?:'Unit unspecified' }} · {{ $r[$side]['complete']?'Complete selected period':'Incomplete / awaiting results' }}</span>
        @endif
        <div class="pc-meter {{ $side==='reference'?'baseline':'' }}"><span style="width:{{ min(100,max(0,$r[$side]['achievement']??0)) }}%"></span></div>@else<span class="pc-incomplete">No corresponding indicator</span>@endif</td>@endforeach
    <td class="pc-result-cell">
        <span class="pc-status {{ $r['delta']===null?'pending':$r['status'] }}">{{ $r['status']==='context'?'Context only':($r['delta']===null?'Not ready to compare':$statusLabels[$r['status']]) }}</span>
        @if($r['delta']!==null)
            <strong class="pc-delta {{ $r['delta']<0?'pc-down':'pc-up' }}">{{ $r['delta']>0?'+':'' }}{{ number_format($r['delta'],2) }} <small>pp</small></strong>
            <small class="pc-raw-change">{{ $b['type']==='target'?'Actual − target':'Actual change' }}: {{ $number($r['actualChange']) }} {{ $r['current']['unit'] }}</small>
            @if($b['type']!=='target')<small class="pc-raw-change">Target change: {{ $number($r['targetChange']) }}</small>@endif
        @endif
        <p>{{ $r['reason'] }}</p>
    </td></tr>@empty<tr><td colspan="4" class="pc-empty">No result rows for these selections.</td></tr>@endforelse
    </tbody></table></div><div class="pc-pagination"><span id="pc-page-caption"></span><div><button type="button" id="pc-prev" class="btn btn-light">Previous</button><button type="button" id="pc-next" class="btn btn-light">Next</button></div></div></section>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('css/performance-comparison.css') }}?v={{ filemtime(public_path('css/performance-comparison.css')) }}">@endpush
@push('scripts')
<script id="pc-export-data" type="application/json">@json($exportRows)</script><script src="{{ asset('js/performance-comparison.js') }}?v={{ filemtime(public_path('js/performance-comparison.js')) }}"></script>
@if($chartRows->isNotEmpty())<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script><script>(()=>{if(!window.Chart)return;const rows=@json($chartRows);document.getElementById('pc-chart-canvas').hidden=false;new Chart(document.getElementById('comparison-kpis'),{type:'bar',data:{labels:rows.map(r=>r.objective),datasets:[{label:@json($c['referenceLabel']),data:rows.map(r=>r.reference),backgroundColor:'#667fa5'},{label:@json($c['currentLabel']),data:rows.map(r=>r.current),backgroundColor:'#008878'}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,scales:{x:{beginAtZero:true,suggestedMax:100}},plugins:{legend:{position:'bottom'}}}});document.getElementById('pc-chart-fallback').hidden=true;})();</script>@endif
@endpush
