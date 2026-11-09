@php
    $rollup = $record['rollup'];
    $points = static fn($value) => rtrim(rtrim(number_format((float)$value, 4, '.', ','), '0'), '.');
@endphp
<div class="pc-composite-score">
    <span class="pc-kicker">WEIGHTED ACTIVITY SCORE</span>
    <dl class="pc-values">
        <div><dt>Points earned{{ !$record['complete'] ? ' (provisional)' : '' }}</dt><dd>{{ $rollup['score'] === null ? 'Awaiting results' : $points($rollup['score']) }}</dd></div>
        <div><dt>Available points</dt><dd>{{ $points($rollup['weight']) }}</dd></div>
    </dl>
    <strong class="pc-achievement {{ !$record['complete'] ? 'pc-incomplete' : '' }}">{{ $record['achievement'] === null ? 'Awaiting complete evidence' : number_format($record['achievement'], 2).'%' }}</strong>
    <span class="pc-value-caption">Weighted achievement · {{ $rollup['completed'] }} / {{ $rollup['expected'] }} activities have complete results</span>
    <p class="pc-composite-note">Activity achievement × activity weight, summed once. This is a scorecard result, not a directly measured {{ $record['kpi'] ?: 'objective' }} actual.</p>
    @if($record['sourceRow'])<p class="pc-composite-note">Joint activity results retain the workbook’s performer assignments. They are not independent departmental scores.</p>@endif
    @foreach($rollup['warnings'] as $warning)<p class="pc-composite-warning">{{ $warning }}</p>@endforeach
    <details class="pc-component-details">
        <summary>Activity breakdown ({{ $rollup['expected'] }} activities)</summary>
        <div class="pc-component-list">
        @foreach($rollup['components'] as $component)
            <article class="pc-component" data-component-code="{{ $component['code'] }}">
                <strong>{{ $component['code'] }} · {{ $component['description'] }}</strong>
                <span class="pc-value-caption">{{ $component['unit'] ?: 'Unit unspecified' }} · {{ $component['department'] }}</span>
                <dl>
                    @foreach(['target' => 'Target', 'actual' => 'Actual', 'weight' => 'Weight', 'achievement' => 'Achievement', 'contribution' => 'Weighted points'] as $field => $label)
                    <div><dt>{{ $label }}</dt><dd>{{ $component[$field] === null ? 'Not reported' : (in_array($field,['weight','contribution'],true) ? $points($component[$field]) : \App\Support\NumberFormatter::format($component[$field], 2)).($field === 'achievement' ? '%' : '') }}</dd></div>
                    @endforeach
                </dl>
                <span class="pc-value-caption">{{ $component['source'] }}@if($component['sourceRow']) · Source row {{ $component['sourceRow'] }}@endif</span>
                @if(!$component['componentComplete'])<p class="pc-composite-warning">{{ $component['reason'] ?: 'Awaiting complete activity results.' }}</p>@endif
            </article>
        @endforeach
        </div>
    </details>
</div>
