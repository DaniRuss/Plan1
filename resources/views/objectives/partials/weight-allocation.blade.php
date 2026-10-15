@php
    $allocated = (float) $mainObj->children->sum('weight');
    $total = (float) $mainObj->weight;
    $percentage = $total > 0 ? $allocated / $total * 100 : 0;
    $excess = max(0, $allocated - $total);
@endphp
<section class="objective-allocation mb-4" aria-label="Weight allocation for objective {{ $mainObj->code }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h3 class="h6 font-weight-bold mb-1">Sub-objective weight allocation</h3><span class="badge badge-{{ $excess > 0 ? 'danger' : ($allocated < $total ? 'warning' : 'success') }}">{{ $excess > 0 ? 'Over-allocated' : ($allocated < $total ? 'Allocation incomplete' : 'Fully allocated') }}</span></div>
    <div class="allocation-metrics"><div><small>Parent objective weight</small><strong>{{ \App\Support\NumberFormatter::format($total,3) }}%</strong></div><div><small>Allocated to direct sub-objectives</small><strong>{{ \App\Support\NumberFormatter::format($allocated,3) }}%</strong></div><div><small>{{ $excess > 0 ? 'Excess allocation' : 'Available to allocate' }}</small><strong>{{ \App\Support\NumberFormatter::format($excess > 0 ? $excess : max(0, $total - $allocated),3) }}%</strong></div></div>
    <div class="progress my-3" style="height:10px"><div class="progress-bar {{ $excess > 0 ? 'bg-danger' : 'bg-success' }}" role="progressbar" aria-label="Allocated share of parent weight" aria-valuenow="{{ min(100, max(0, $percentage)) }}" aria-valuemin="0" aria-valuemax="100" style="width:{{ min(100, max(0, $percentage)) }}%"></div></div>
    <p class="small text-muted mb-0">@if($total > 0){{ \App\Support\NumberFormatter::format($percentage,3) }}% of the parent’s weight is allocated.@else The parent has no weight; an allocation share cannot be calculated.@endif Overall weight contributes to the total score; parent share compares each row with its immediate parent. Only direct sub-objectives are counted in this allocation.</p>
</section>
