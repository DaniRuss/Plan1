@php
    $context = \Illuminate\Support\Arr::only(request()->query(), ['performance_department','reporting_frequency','result_scope','performance_kpi','movement','comparison_sort','search']);
    if (($context['comparison_sort'] ?? null) === 'code') unset($context['comparison_sort']);
    if (isset($c['historicalYear'])) unset($context['performance_kpi']);
    $context['performance_year'] = $c['year'];
    $active=$c['activeView']??($c['method']==='historical'?'master':$c['method']);
    $modes = ['master'=>['Master overview','fas fa-th-large'], 'year'=>['Year over year','fas fa-calendar-alt'], 'period'=>['Period over period','fas fa-exchange-alt'], 'department'=>['Departments','fas fa-building'], 'objective'=>['Objectives','fas fa-sitemap'], 'kpi'=>['KPI vs KPI','fas fa-chart-bar'], 'target'=>['Actual vs target','fas fa-bullseye']];
@endphp
<nav class="pc-modes" aria-label="Comparison views">
    @foreach($modes as $method => [$label,$icon])
        @if($method !== 'department' || $c['organizationWide'])
            @php
                $query=$context+['current_source'=>$c['builder']['currentSource']['key']??('plan:'.$c['year'])];
                $query['comparison_method']=$method==='master'?'historical':$method; $query['comparison_view']=$method;
                $query['comparison_type']=$method==='master'?($c['builder']['type']??(in_array($c['method'],['year','period','department','target'],true)?$c['method']:'year')):$method;
                if(isset($c['builder'])) {
                    $query['current_department']=$c['builder']['currentDepartment'];
                    if($method!=='department') $query['reference_department']=$method==='period'?$c['builder']['currentDepartment']:$c['builder']['referenceDepartment'];
                    if($c['builder']['referenceSource']) $query['reference_source']=$c['builder']['referenceSource']['key'];
                }
                if($method==='master' && isset($c['builder'])) foreach(['Source'=>'source','Period'=>'period','Department'=>'department','Entity'=>'entity'] as $suffix=>$key) foreach(['reference','current'] as $side) $query[$side.'_'.$key]=$suffix==='Source'?($c['builder'][$side.$suffix]['key']??null):$c['builder'][$side.$suffix];
                elseif($method==='year' && isset($c['builder']['referenceSource']) && $c['builder']['referenceSource']['key']!==$query['current_source']) $query['reference_source']=$c['builder']['referenceSource']['key'];
                if($method==='year' && isset($c['builder']) && ($c['builder']['referenceSource']['gregorian']??null)===$c['builder']['currentSource']['gregorian']) unset($query['reference_source']);
                if($method==='department' || ($method==='master' && ($c['workspace']??null)==='departments')) {
                    unset($query['reference_department']);
                    if(($c['builder']['referenceSource']['gregorian']??null)===($c['builder']['currentSource']['gregorian']??null)) unset($query['reference_source']);
                    foreach(['performance_department','reporting_frequency','result_scope','performance_kpi','movement','comparison_sort','search'] as $key) unset($query[$key]);
                }
                if($method==='period' && isset($c['builder'])) {
                    if($c['builder']['type']==='period') foreach(['current','reference'] as $side) $query[$side.'_period']=$c['builder'][$side.'Period'];
                    elseif(($c['builder']['referenceSource']['kind']??null)==='history' || $c['builder']['currentSource']['kind']==='history') $query['current_period']=$query['reference_period']='annual';
                }
            @endphp
            <a @if($active===$method) aria-current="page" @endif class="{{ $active===$method?'active':'' }}" href="{{ route('performance.comparison',$query) }}"><i class="{{ $icon }}" aria-hidden="true"></i>{{ $label }}</a>
        @endif
    @endforeach
</nav>
