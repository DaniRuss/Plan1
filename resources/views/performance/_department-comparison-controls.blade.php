@php $b=$c['builder']; @endphp
<form method="GET" action="{{ route('performance.comparison') }}" class="pc-panel pc-filters pc-builder pc-department-builder" id="comparison-filters">
    <input type="hidden" name="comparison_method" value="department">
    <input type="hidden" name="comparison_view" value="{{ $b['view'] }}">
    <div class="pc-builder-heading">
        <div><span class="pc-kicker">{{ $b['view']==='master'?'BUILD YOUR COMPARISON':'DEPARTMENT YEAR-OVER-YEAR' }}</span><h2>One department. Two fiscal years.</h2></div>
        @if($b['view']==='master')
        <div><label for="pc-comparison-type">Comparison type</label><select id="pc-comparison-type" name="comparison_type" class="form-control" data-builder-refresh="type">@foreach($b['types'] as $key=>$label)<option value="{{ $key }}" @selected($key==='department')>{{ $label }}</option>@endforeach</select></div>
        @else
        <input type="hidden" name="comparison_type" value="department"><a class="pc-text-link" href="{{ route('performance.comparison',array_merge(request()->query(),['comparison_view'=>'master','comparison_type'=>'department'])) }}">Open full Master workspace →</a>
        @endif
    </div>
    <div class="pc-department-self-filters">
        <div><label for="pc-current-department">Department</label><select id="pc-current-department" name="current_department" class="form-control" required>@if(!$b['currentDepartment'])<option value="">Choose a department</option>@endif @foreach($b['departments'] as $d)<option value="{{ $d->id }}" @selected($b['currentDepartment']===$d->id)>{{ $d->name }} · {{ $d->code }}</option>@endforeach</select></div>
        @foreach(['reference'=>'Reference year & source','current'=>'Comparison year & source'] as $side=>$label)
        <div><label for="pc-{{ $side }}-source">{{ $label }}</label><select id="pc-{{ $side }}-source" name="{{ $side }}_source" class="form-control" data-builder-refresh="source">@if(!$b[$side.'Source'])<option value="">No other fiscal year available</option>@endif @foreach($b['sources'] as $source)<option value="{{ $source['key'] }}" @selected(($b[$side.'Source']['key']??null)===$source['key'])>{{ $source['label'] }} · {{ $source['kind']==='history'?'Imported annual results':'IPRPMS annual results' }}</option>@endforeach</select></div>
        @endforeach
    </div>
    <div class="pc-builder-actions"><div><button class="btn pc-primary" type="submit"><i class="fas fa-chart-line mr-1" aria-hidden="true"></i> Compare annual performance</button><button class="btn btn-light" type="button" id="pc-swap" @if(!$b['referenceSource']) disabled @endif>⇄ Swap years</button><a class="btn btn-light" href="{{ route('performance.comparison',['comparison_type'=>'department','comparison_view'=>$b['view'],'current_source'=>$b['currentSource']['key'],'current_department'=>$b['currentDepartment']]) }}">Reset</a></div><small id="pc-builder-status" aria-live="polite">Annual only · the same department on both sides. Any available years can be selected; neither year is tied to today's date.</small></div>
</form>
@push('scripts')<script src="{{ asset('js/comparison-workbench.js') }}?v={{ filemtime(public_path('js/comparison-workbench.js')) }}"></script>@endpush
