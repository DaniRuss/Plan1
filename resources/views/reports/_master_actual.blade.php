@if(!$cell['planned'])
    <span class="badge badge-secondary">N/A</span>
@elseif(!$cell['reported'])
    <span class="badge badge-light border">{{ $cell['status'] }}</span>
@else
    <div class="text-primary">{{ $cell['is_rollup'] ? 'Roll-up' : \App\Support\NumberFormatter::format($cell['actual'],3) }}</div>
    @if($cell['scorecard_achievement'] !== null)
        <div class="score-chip" style="background:{{ $cell['level']['hex'] }};color:{{ $cell['level']['textHex'] }}">{{ \App\Support\NumberFormatter::format($cell['scorecard_achievement'],3) }}%</div>
        @if($cell['raw_achievement'] > 100)<small class="d-block text-info">Raw {{ \App\Support\NumberFormatter::format($cell['raw_achievement'],3) }}%</small>@endif
    @endif
    @if(($pdf ?? false) && $cell['complete'])<div class="small">{{ $cell['status'] }}</div>@endif
    @if(!$cell['complete'])
        <div class="text-muted small">Provisional</div>
        <div class="text-muted small">{{ \App\Support\NumberFormatter::format($cell['coverage'],3) }}% covered ({{ $cell['completed'] }}/{{ $cell['expected'] }})</div>
    @endif
    @if(($pdf ?? false) && !$cell['is_rollup'] && abs((float)$cell['effective_weight'] - (float)$row['weight']) > 0.0001)
        <div class="small">Eff. wt {{ \App\Support\NumberFormatter::format($cell['effective_weight'],3) }}</div>
    @endif
@endif
