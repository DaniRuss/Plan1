@php
    $b=$c['builder'];
@endphp
@if($b['type']==='department')
@include('performance._department-comparison-controls',['c'=>$c])
@else
<form method="GET" action="{{ route('performance.comparison') }}" class="pc-panel pc-filters pc-builder" id="comparison-filters">
    <input type="hidden" name="comparison_method" value="historical">
    <input type="hidden" name="comparison_view" value="{{ $b['view'] }}">
    <div class="pc-builder-heading"><div><span class="pc-kicker">{{ $b['view']==='master'?'BUILD YOUR COMPARISON':'FOCUSED COMPARISON' }}</span><h2>{{ $b['view']==='master'?'What would you like to compare?':$b['types'][$b['type']] }}</h2></div>
        @if($b['view']==='master')<div><label for="pc-comparison-type">Comparison type</label><select name="comparison_type" id="pc-comparison-type" class="form-control" data-builder-refresh="type">@foreach($b['types'] as $key=>$label)<option value="{{ $key }}" @selected($key===$b['type'])>{{ $label }}</option>@endforeach</select></div>@else<input type="hidden" name="comparison_type" value="{{ $b['type'] }}"><a href="{{ route('performance.comparison',array_merge(request()->except('comparison_view'),['comparison_view'=>'master','comparison_type'=>$b['type']])) }}" class="pc-text-link">Open full Master workspace →</a>@endif
    </div>
    <div class="pc-builder-sides">
        @foreach(['reference'=>'Reference','current'=>'Comparison'] as $side=>$label)
        @php
            $source=$b[$side.'Source']; $entity=$b[$side.'Entity']; $options=$b[$side.'Options'];
            $entityOptions=$b['type']==='kpi'?$options->where('kpi','!=',''):$options;
            $periodOptions=$source['kind']==='history'?['annual'=>'Full fiscal year · imported annual']: $b['periods'];
            unset($periodOptions['all']);
        @endphp
        <fieldset class="pc-builder-side {{ $side }}"><legend><span>{{ $side==='reference'?'01':'02' }}</span>{{ $b['type']==='target' && $side==='reference'?'Planned benchmark':$label }}</legend>
            @if($b['type']==='target' && $side==='reference')<div class="pc-target-benchmark"><i class="fas fa-bullseye"></i><strong>100%</strong><p>The selected activity’s own target is the benchmark. Actual and target use the same unit and achievement direction.</p></div>
            <input type="hidden" name="reference_source" value="{{ $b['currentSource']['key'] }}"><input type="hidden" name="reference_period" value="{{ $b['currentPeriod'] }}">
            @else
            <label for="pc-{{ $side }}-source">{{ $label }} year &amp; source</label><select class="form-control" id="pc-{{ $side }}-source" name="{{ $side }}_source" data-builder-refresh="source">@foreach($b['sources'] as $s)<option value="{{ $s['key'] }}" @selected($source['key']===$s['key'])>{{ $s['label'] }} · {{ $s['kind']==='history'?'Imported annual':'IPRPMS records' }}</option>@endforeach</select>
            @if($side==='reference' && $b['type']==='department')
            <label>Reporting period</label><p class="pc-builder-linked">{{ $b['periods'][$b['currentPeriod']]??'Choose on Comparison' }} · Same period on both sides</p><input type="hidden" name="reference_period" value="{{ $b['currentPeriod'] }}">
            @else
            <label for="pc-{{ $side }}-period">Reporting period</label><select id="pc-{{ $side }}-period" class="form-control" name="{{ $side }}_period">@if(!$b[$side.'Period'])<option value="">Choose a period</option>@endif @foreach($periodOptions as $key=>$text)<option value="{{ $key }}" @selected($b[$side.'Period']===$key)>{{ $text }}</option>@endforeach</select>
            @endif
            @if($b['wide'])
                @if($side==='reference' && $b['type']==='period')<label>Department</label><p class="pc-builder-linked">{{ $b['departments']->firstWhere('id',$b['currentDepartment'])?->name??'Organization / accountable result' }} · Same department on both sides</p><input type="hidden" name="reference_department" value="{{ $b['currentDepartment'] }}">
                @else<label for="pc-{{ $side }}-department">Department</label><select id="pc-{{ $side }}-department" name="{{ $side }}_department" class="form-control" data-builder-refresh="department"><option value="">Organization / accountable result</option>@foreach($b['departments'] as $d)<option value="{{ $d->id }}" @selected($b[$side.'Department']==$d->id)>{{ $d->name }}</option>@endforeach</select>@endif
            @endif
            @if(in_array($b['type'],['kpi','objective','target'],true))<label for="pc-{{ $side }}-entity">{{ $b['type']==='kpi'?'KPI indicator':'Main objective, sub-objective or activity' }}</label><select id="pc-{{ $side }}-entity" name="{{ $side }}_entity" class="form-control" @if($side==='reference') data-builder-match="1" @endif><option value="">{{ $b['type']==='target'?'All activities':($side==='current'?'Automatically find the corresponding indicator':'Choose an indicator') }}</option>@foreach($entityOptions as $o)<option value="{{ $o['id'] }}" @selected($entity===$o['id'])>{{ $o['kpi']?$o['kpi'].' · ':'' }}{{ $o['code'] }} · {{ $o['description'] }}</option>@endforeach</select><small class="pc-builder-help">{{ $side==='current'?'Matching uses established KPI codes or existing relationships—not wording alone.':'Full wording and source values remain visible in the result.' }}</small>@endif
            @endif
        </fieldset>
        @endforeach
    </div>
    @if($b['view']==='master')
    <details class="pc-more-filters" @if(($c['movement']??'all')!=='all'||($c['sort']??'code')!=='code'||$b['frequency']!=='all'||$b['scope']!=='official'||request('search')) open @endif><summary>Shared filters &amp; result settings</summary><div class="pc-builder-settings">
        <div><label for="pc-search">Find in results</label><input class="form-control" id="pc-search" name="search" value="{{ request('search') }}" placeholder="K7, activity or department" maxlength="200"></div>
        <div><label for="pc-frequency">Reporting frequency</label><select id="pc-frequency" class="form-control" name="reporting_frequency" data-builder-refresh="frequency">@foreach(['all'=>'All frequencies','daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $key=>$label)<option value="{{ $key }}" @selected($b['frequency']===$key)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="pc-reporting-scope">Review status</label><select id="pc-reporting-scope" class="form-control" name="result_scope"><option value="official" @selected($b['scope']==='official')>Final reviewed results</option>@if(!$b['finalOnly'])<option value="reported" @selected($b['scope']==='reported')>Include reports awaiting review</option>@endif</select></div>
        <div><label for="pc-movement">Movement / readiness</label><select class="form-control" id="pc-movement" name="movement">@foreach(['all'=>'All results','improved'=>'Improved / above target','declined'=>'Declined / below target','unchanged'=>'Unchanged / on target','unmatched'=>'Missing or not comparable'] as $key=>$label)<option value="{{ $key }}" @selected(($c['movement']??'all')===$key)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="pc-sort">Sort results</label><select class="form-control" id="pc-sort" name="comparison_sort">@foreach(['code'=>'Activity code','change'=>'Largest change','achievement'=>'Comparison achievement','name'=>'Activity name'] as $key=>$label)<option value="{{ $key }}" @selected(($c['sort']??'code')===$key)>{{ $label }}</option>@endforeach</select></div>
        @if(isset($c['mainObjectives']))<div><label for="pc-main">Historical main objective</label><select id="pc-main" class="form-control" name="main_objective"><option value="">All main objectives</option>@foreach($c['mainObjectives'] as $code)<option value="{{ $code }}" @selected(($c['mainObjective']??'')===$code)>{{ $code }}</option>@endforeach</select></div><div><label for="pc-mapping">KPI relationship</label><select id="pc-mapping" class="form-control" name="mapping_type">@foreach(['all'=>'All relationships','unmapped'=>'Not linked','direct'=>'Same KPI','renamed'=>'Renamed equivalent','split'=>'Split','merged'=>'Merged','unit_changed'=>'Unit changed','definition_changed'=>'Definition changed','not_comparable'=>'Not comparable','retired'=>'Retired'] as $key=>$label)<option value="{{ $key }}" @selected(($c['mappingType']??'all')===$key)>{{ $label }}</option>@endforeach</select></div>@endif
    </div></details>
    @else
    @foreach(['reporting_frequency'=>$b['frequency'],'result_scope'=>$b['scope'],'movement'=>$c['movement']??'all','comparison_sort'=>$c['sort']??'code','search'=>request('search')] as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
    <p class="pc-builder-help">{{ $b['scope']==='official'?'Final reviewed evidence':'Provisional reporting evidence' }} · {{ $b['frequency']==='all'?'All frequencies':ucfirst($b['frequency']).' reporting' }}. Shared filters are managed in the Master workspace.</p>
    @endif
    <div class="pc-builder-actions"><div><button class="btn pc-primary" type="submit"><i class="fas fa-exchange-alt mr-1"></i> Compare selections</button><button class="btn btn-light" type="button" id="pc-swap">⇄ Swap sides</button><button class="btn btn-light" type="submit" name="configure" value="1" id="pc-load-options">Refresh selectors</button><a href="{{ route('performance.comparison',['comparison_method'=>'historical']) }}" class="btn btn-light">Reset</a></div><small id="pc-builder-status" aria-live="polite">Changing a source or department refreshes its available indicators. Annual sources stay annual; incomplete results are clearly labelled.</small></div>
</form>
@push('scripts')<script src="{{ asset('js/comparison-workbench.js') }}?v={{ filemtime(public_path('js/comparison-workbench.js')) }}"></script>@endpush
@endif
