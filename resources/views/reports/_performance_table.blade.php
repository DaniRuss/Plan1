@php
    $periodLabel = \App\Models\EdrReport::periodLabel($period ?? ($report->reporting_period ?? ''));
    $summary = app(\App\Services\EdrPerformanceCalculator::class)->summary($performanceData);
    $isCollectedReport = collect($performanceData)->pluck('reporting_department')->filter()->unique()->count() > 1;
    $completeness = collect($performanceData)->pluck('report_completeness')->filter()->first();
@endphp

@if($completeness)
    <div class="alert alert-{{ $completeness['status'] === 'Complete' ? 'success' : 'warning' }} py-2">
        <strong>{{ $completeness['status'] }}</strong> · Expected: {{ $completeness['expected'] }} · Submitted: {{ $completeness['submitted'] }} · Approved: {{ $completeness['approved'] }} · Planning coverage: {{ \App\Support\NumberFormatter::format($completeness['planning_coverage'],3) }}% · Reporting coverage: {{ \App\Support\NumberFormatter::format($completeness['reporting_coverage'],3) }}%
        @if($completeness['status'] === 'Provisional')
            <div class="small">Achievement uses final-approved reporting periods only.</div>
        @endif
    </div>
@endif

<style>
    .performance-report-scroll {
        max-height: calc(100vh - 240px);
        min-height: 260px;
        overflow: auto;
        position: relative;
    }

    .performance-comparison-table thead th {
        position: sticky;
        top: 0;
        z-index: 5;
        background: #13714c;
        color: #fff;
        box-shadow: inset 0 -1px 0 rgba(255, 255, 255, .35), 0 2px 4px rgba(0, 0, 0, .16);
    }

    @media (max-width: 767.98px) {
        .performance-report-scroll {
            max-height: calc(100vh - 190px);
        }
    }

    @media print {
        .performance-report-scroll {
            max-height: none !important;
            overflow: visible !important;
        }

        .performance-comparison-table thead th {
            position: static;
        }
    }
</style>

<div class="table-responsive performance-report-scroll">
    <table class="table table-bordered table-sm text-center align-middle small performance-comparison-table shadow-sm">
        <thead style="background:#13714c;color:#fff">
            <tr>
                <th>S.No</th>
                <th class="text-left" style="min-width:300px">KPIs</th>
                <th>Weight</th>
                <th>Unit</th>
                <th>{{ $periodLabel }} Plan</th>
                <th>{{ $periodLabel }} Performance</th>
                <th>Raw %</th>
                <th>Scorecard %</th>
                <th>Status</th>
                <th>Performer</th>
                <th>Achievement Factor</th>
                <th>Weighted Score</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($performanceData as $data)
                @php
                    $level = $data['performance_level'];
                    $indent = max(0, ((int) $data['hierarchy_level'] - 1) * 18);
                    $rawAchievement = $data['raw_achievement'] ?? $data['quarter_comp'];
                    $scorecardAchievement = array_key_exists('scorecard_achievement', $data)
                        ? $data['scorecard_achievement']
                        : $data['quarter_comp'];
                    $performanceStatus = $data['performance_status'] ?? app(\App\Services\EdrPerformanceCalculator::class)->performanceStatus($data['is_planned'] ? $rawAchievement : null);
                @endphp
                <tr @if($data['has_children']) style="background:#e2f0d9;font-weight:700" @endif>
                    <td>{{ $data['code'] }}</td>
                    <td class="text-left" style="padding-left:{{ 6 + $indent }}px">
                        @if($isCollectedReport)
                            <span class="badge badge-secondary mr-1">{{ $data['reporting_department'] }}</span>
                        @endif
                        @if(($data['contributor_count'] ?? 0) > 1)
                            <span class="badge badge-info mr-1" title="Contributors: {{ implode(', ', $data['contributing_departments']) }}">Lead result · {{ $data['contributor_count'] }} departments</span>
                        @endif
                        {{ $data['objective'] }}
                    </td>
                    <td>
                        @if(!$data['is_planned'])
                            N/A
                        @elseif(abs($data['weight'] - $data['base_weight']) > 0.0001)
                            <small><del>{{ \App\Support\NumberFormatter::format($data['base_weight']) }}</del></small> {{ \App\Support\NumberFormatter::format($data['weight']) }}
                        @else
                            {{ \App\Support\NumberFormatter::format($data['weight']) }}
                        @endif
                    </td>
                    <td>{{ $data['measurement'] }}</td>
                    <td>{{ $data['has_children'] && $data['is_planned'] ? 'Roll-up' : ($data['is_planned'] ? \App\Support\NumberFormatter::format($data['quarter_plan'],3, '.', ',') : 'N/A') }}</td>
                    <td>{{ $data['has_children'] && $data['is_planned'] ? 'Roll-up' : ($data['is_planned'] ? \App\Support\NumberFormatter::format($data['quarter_perf'],3, '.', ',') : 'N/A') }}</td>
                    <td class="font-weight-bold">
                        {{ $data['is_planned'] ? \App\Support\NumberFormatter::format($rawAchievement,3) . '%' : 'N/A' }}
                    </td>
                    <td style="background:{{ $level['hex'] ?? $level['color'] ?? '#6C757D' }};color:{{ $level['textHex'] ?? $level['text_color'] ?? '#FFFFFF' }};font-weight:700" title="{{ $level['label'] }}">
                        @if(!$data['is_planned'])
                            N/A
                        @elseif(is_null($scorecardAchievement))
                            Operational
                        @else
                            {{ \App\Support\NumberFormatter::format($scorecardAchievement,3) }}%
                        @endif
                    </td>
                    <td><span class="badge {{ $performanceStatus === 'Overachieved' ? 'badge-info' : ($performanceStatus === 'Achieved' ? 'badge-success' : ($performanceStatus === 'Not Planned (N/A)' ? 'badge-secondary' : 'badge-warning')) }}">{{ $performanceStatus }}</span></td>
                    <td>{{ $data['performer'] ?: 'N/A' }}</td>
                    <td>{{ $data['is_planned'] ? \App\Support\NumberFormatter::format($data['score_factor'],3) : 'N/A' }}</td>
                    <td>{{ \App\Support\NumberFormatter::format($data['weighted_score'],3) }}</td>
                    <td>{{ $data['has_children'] ? \App\Support\NumberFormatter::format($data['weighted_score'],3) : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="13" class="py-4 text-muted text-center">Performance data is unavailable for this period. Ensure an approved plan exists.</td></tr>
            @endforelse
        </tbody>
        @if(count($performanceData) > 0)
            <tfoot style="background:#13714c;color:#fff;font-weight:700">
                <tr>
                    <td colspan="11" class="text-right">TOTAL EDR ACHIEVEMENT</td>
                    <td>{{ \App\Support\NumberFormatter::format($summary['weighted_score'],3) }}</td>
                    <td style="background:{{ $summary['performance_level']['hex'] ?? '#6C757D' }};color:{{ $summary['performance_level']['textHex'] ?? '#FFFFFF' }}">
                        {{ is_null($summary['achievement']) ? 'N/A' : \App\Support\NumberFormatter::format($summary['achievement'],3) . '%' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="11" class="text-right">PERFORMANCE LEVEL</td>
                    <td colspan="2" style="background:{{ $summary['performance_level']['hex'] ?? '#6C757D' }};color:{{ $summary['performance_level']['textHex'] ?? '#FFFFFF' }}">
                        {{ $summary['performance_level']['label'] }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

<div class="small mt-2 d-flex flex-wrap">
    @foreach([96, 92, 87, 80, 70] as $sample)
        @php $legend = app(\App\Services\PerformanceLevel::class)->for($sample); @endphp
        <span class="mr-3 mb-1 px-2 py-1" style="background:{{ $legend['hex'] }};color:{{ $legend['textHex'] }}">{{ $legend['shortLabel'] }}</span>
    @endforeach
</div>
