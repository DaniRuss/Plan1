@extends('layouts.app')

@section('title', 'Daily Performance Entry')

@section('content')
@php
    $performanceScale = \App\Models\Setting::getPerformanceScale();
    $referenceBg = \App\Models\Setting::get('performance_reference_bg', '#cce5ff');
    $referenceText = \App\Models\Setting::get('performance_reference_text', '#004085');
@endphp
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <div>
                        <a href="{{ route('daily.dashboard', ['department_id' => $dept->id, 'year' => $year]) }}" class="btn btn-outline-secondary btn-sm mr-2">
                            <i class="fas fa-arrow-left mr-1"></i> Back
                        </a>
                        <h5 class="d-inline-block mb-0 font-weight-bold">
                            <i class="fas fa-calendar-day mr-2 text-warning"></i> 
                            Daily Entry: {{ ucfirst($month) }} {{ $year }}, Week {{ $week }} 
                            <small class="text-muted ml-2">({{ $weekInfo['start'] }} - {{ $weekInfo['end'] }})</small>
                        </h5>
                    </div>
                    <div class="d-flex align-items-center">
                        @if($workflowActions['submit_to_directorate'])
                            <form action="{{ route('weekly.workflow', ['year' => $year, 'month' => $month, 'week' => $week]) }}" method="POST" class="mr-2">
                                @csrf
                                <input type="hidden" name="department_id" value="{{ $dept->id }}"><input type="hidden" name="frequency" value="daily">
                                <input type="hidden" name="action" value="submit_to_directorate">
                                <button type="submit" class="btn btn-success btn-sm font-weight-bold shadow-sm">
                                    <i class="fas fa-paper-plane mr-1"></i> Submit to Directorate
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('weekly.report', ['year' => $year, 'month' => $month, 'week' => $week, 'department_id' => $dept->id, 'frequency' => 'daily']) }}" class="btn btn-info btn-sm mr-2 font-weight-bold shadow-sm">
                            <i class="fas fa-chart-bar mr-1"></i> View Report
                        </a>
                        <span class="badge badge-info p-2">{{ $dept->name }}</span>
                    </div>
                </div>

                <div class="card-body border-bottom bg-light">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div class="mb-2">
                            <div class="small text-muted">Workflow Status</div>
                            <span class="badge badge-{{ $workflowState['badge'] }} px-3 py-2">{{ $workflowState['label'] }}</span>
                            @if($workflowState['note'])
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-comment-dots mr-1"></i>{{ $workflowState['note'] }}
                                </div>
                            @endif
                        </div>
                        <div class="text-right">
                            @if(
                                $workflowActions['return_to_expert']
                                || $workflowActions['submit_to_chief']
                                || $workflowActions['return_to_directorate']
                                || $workflowActions['approve_by_chief']
                                || $workflowActions['return_to_department_chief']
                                || $workflowActions['submit_to_plan_report_directorate']
                                || $workflowActions['submit_to_head_office']
                                || $workflowActions['return_to_plan_report_department']
                                || $workflowActions['return_to_plan_report_directorate']
                                || $workflowActions['approve_by_head_office']
                                || $workflowActions['mark_reviewed_no_correction']
                            )
                                <form action="{{ route('weekly.workflow', ['year' => $year, 'month' => $month, 'week' => $week]) }}" method="POST" class="d-inline-block">
                                    @csrf
                                    <input type="hidden" name="department_id" value="{{ $dept->id }}"><input type="hidden" name="frequency" value="daily">
                                    <div class="form-group mb-2">
                                        <input type="text" name="workflow_note" class="form-control form-control-sm" placeholder="Optional review note">
                                    </div>
                                    @if($workflowActions['return_to_expert'])
                                        <button type="submit" name="action" value="return_to_expert" class="btn btn-outline-warning btn-sm mr-1">Return to Expert</button>
                                    @endif
                                    @if($workflowActions['submit_to_chief'])
                                        <button type="submit" name="action" value="submit_to_chief" class="btn btn-primary btn-sm mr-1">Approve and Send to Chief</button>
                                    @endif
                                    @if($workflowActions['return_to_directorate'])
                                        <button type="submit" name="action" value="return_to_directorate" class="btn btn-outline-warning btn-sm mr-1">Return to Directorate</button>
                                    @endif
                                    @if($workflowActions['approve_by_chief'])
                                        <button type="submit" name="action" value="approve_by_chief" class="btn btn-success btn-sm mr-1">{{ $workflowState['key'] === 'returned_to_department_chief' ? 'Resend Corrected Performance to PR Expert' : 'Approve and Send to Plan & Report' }}</button>
                                    @endif
                                    @if($workflowActions['return_to_department_chief'])
                                        <button type="submit" name="action" value="return_to_department_chief" class="btn btn-outline-warning btn-sm mr-1">Return to Chief for Correction</button>
                                    @endif
                                    @if($workflowActions['mark_reviewed_no_correction'])
                                        <button type="submit" name="action" value="mark_reviewed_no_correction" class="btn btn-success btn-sm mr-1">Reviewed — No Correction Required</button>
                                    @endif
                                    @if($workflowActions['submit_to_head_office'])
                                        <button type="submit" name="action" value="submit_to_head_office" class="btn btn-primary btn-sm mr-1">Accept and Send to Head of Plan &amp; Report</button>
                                    @endif
                                    @if($workflowActions['submit_to_plan_report_directorate'])
                                        <button type="submit" name="action" value="submit_to_plan_report_directorate" class="btn btn-primary btn-sm mr-1">Request Correction Through Directorate</button>
                                    @endif
                                    @if($workflowActions['return_to_plan_report_department'])
                                        <button type="submit" name="action" value="return_to_plan_report_department" class="btn btn-outline-warning btn-sm mr-1">Return to Plan & Report</button>
                                    @endif
                                    @if($workflowActions['return_to_plan_report_directorate'])
                                        <button type="submit" name="action" value="return_to_plan_report_directorate" class="btn btn-outline-warning btn-sm mr-1">Return to Plan &amp; Report Directorate</button>
                                    @endif
                                    @if($workflowActions['approve_by_head_office'])
                                        <button type="submit" name="action" value="approve_by_head_office" class="btn btn-success btn-sm">Final Approve Performance</button>
                                    @endif
                                </form>
                            @endif
                        </div>
                    </div>
                    @unless($canEditWorkflow)
                        <div class="alert alert-warning mb-0 mt-3 py-2">
                            This daily entry is read-only for your current role. The linked weekly workflow is waiting for review actions.
                        </div>
                    @endunless
                </div>
                
                <form action="{{ route('daily.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="week" value="{{ $week }}">
                    <input type="hidden" name="department_id" value="{{ $dept->id }}"><input type="hidden" name="frequency" value="daily">
                    <fieldset {{ !$canEditWorkflow ? 'disabled' : '' }}>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm mb-0">
                                <thead class="bg-dark text-white text-center">
                                    <tr>
                                        <th rowspan="2" class="align-middle" style="width: 50px;">SN</th>
                                        <th rowspan="2" class="align-middle text-left" style="min-width: 250px;">Objective / Plan Item</th>
                                        <th rowspan="2" class="align-middle bg-info text-white" style="width: 100px;">Weekly Plan</th>
                                        <th colspan="7" class="text-center bg-secondary border-bottom-0">Daily Actual Values</th>
                                        <th rowspan="2" class="align-middle bg-primary" style="width: 80px;">Week Total</th>
                                        <th rowspan="2" class="align-middle bg-dark text-white" style="width: 70px;">%</th>
                                    </tr>
                                    <tr>
                                        @foreach($weekInfo['days'] as $d => $day)
                                            <th style="width: 80px;" class="bg-secondary border-top-0">
                                                <span class="d-block">{{ $day['day_name'] }}</span>
                                                <span class="badge {{ $day['is_in_month'] ? 'badge-light' : 'badge-dark opacity-50' }}" style="font-size: 0.7rem;">
                                                    {{ $day['day_num'] }}
                                                </span>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $index => $item)
                                        @php
                                            $itemData = $dailyData->get($item->id, collect());
                                            $weeklyEntry = $weeklyEntries->get($item->id);
                                            $monthTarget = (float)($item->{$month . '_target'} ?? 0);
                                            $isPlanned = !is_null($item->{$month . '_target'});
                                            $defaultWeeklyPlan = $item->strategicObjective
                                                ? $item->strategicObjective->weeklyPlanFromMonthlyTarget($monthTarget, $numWeeks)
                                                : ($numWeeks > 0 ? $monthTarget / $numWeeks : 0);
                                            $repeatsMonthlyPlan = optional($item->strategicObjective)->weekly_plan_mode === 'repeat';
                                            $currentPlan = $weeklyEntry && !is_null($weeklyEntry->planned_value) ? (float)$weeklyEntry->planned_value : $defaultWeeklyPlan;
                                            $isCalculatedParent = optional($item->strategicObjective)->children()->exists()
                                                && optional($item->strategicObjective)->summary_type
                                                && optional($item->strategicObjective)->summary_type !== 'manual_override';
                                            $totalActual = $isCalculatedParent && $weeklyEntry
                                                ? (float) $weeklyEntry->actual_value
                                                : (float) $itemData->sum('actual_value');
                                            $percentage = $weeklyEntry
                                                ? (float) $weeklyEntry->percentage
                                                : ($isPlanned && $item->strategicObjective ? $item->strategicObjective->calculateWeeklyAchievement((float)$totalActual, (float)$currentPlan) : 0);
                                        @endphp
                                        <tr data-item="{{ $item->id }}" data-direction="{{ optional($item->strategicObjective)->goal_direction ?? 'increase' }}">
                                            <td class="text-center align-middle">{{ $index + 1 }}</td>
                                            <td class="align-middle">
                                                <div class="font-weight-bold text-wrap">{{ optional($item->strategicObjective)->description }}</div>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted">{{ $item->unit }}</small>
                                                    <small class="text-info">Month Target: {{ $isPlanned ? \App\Support\NumberFormatter::format($monthTarget,3) : 'N/A' }}</small>
                                                </div>
                                            </td>
                                            <td class="p-1 align-middle bg-light-info">
                                                <input type="number" step="any" 
                                                       name="weekly_plans[{{ $item->id }}]" 
                                                       class="form-control form-control-sm text-center font-weight-bold row-plan-input" 
                                                       data-original-number="{{ $currentPlan }}" value="{{ \App\Support\NumberFormatter::format($currentPlan,3,'.','') }}"
                                                       readonly
                                                       title="{{ $repeatsMonthlyPlan ? 'Monthly plan repeated in every week' : 'Monthly plan distributed across weeks' }}"
                                                       placeholder="0.00">
                                            </td>
                                            @foreach($weekInfo['days'] as $d => $day)
                                                @php
                                                    $val = $itemData->firstWhere('day_of_week', $d);
                                                    $isInMonth = $day['is_in_month'];
                                                @endphp
                                                <td class="p-1 {{ !$isInMonth ? 'bg-light' : '' }}">
                                                    <input type="number" step="any" 
                                                           name="actuals[{{ $item->id }}][{{ $d }}]" 
                                                           class="form-control form-control-sm text-center day-input {{ !$isInMonth ? 'opacity-50' : '' }}" 
                                                       data-item="{{ $item->id }}"
                                                       data-original-number="{{ $val ? $val->actual_value : '' }}" value="{{ \App\Support\NumberFormatter::format($val ? $val->actual_value : '',3,'.','') }}"
                                                       {{ (!$isInMonth || $isCalculatedParent || !$isPlanned) ? 'readonly tabindex=-1' : '' }}
                                                       placeholder="{{ !$isInMonth ? '-' : '' }}">
                                                </td>
                                            @endforeach
                                            <td class="text-center align-middle bg-light font-weight-bold">
                                                <span id="total-{{ $item->id }}" class="item-total">
                                                    {{ \App\Support\NumberFormatter::format($totalActual,3, '.', '') }}
                                                </span>
                                            </td>
                                            @php
                                                $band = \App\Models\Setting::getPerformanceBand($percentage);
                                            @endphp
                                            <td class="text-center align-middle font-weight-bold percentage-display" style="color: {{ $band['color'] }};">
                                                <span id="percent-{{ $item->id }}">
                                                    {{ \App\Support\NumberFormatter::format($percentage,3) }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-right py-3">
                        @if($canEditWorkflow)
                            <button type="submit" class="btn btn-warning px-5 font-weight-bold shadow-sm">
                                <i class="fas fa-save mr-1"></i> {{ $workflowState['key'] === 'returned_to_department_chief' ? 'Save Corrected Daily Performance' : 'Save Daily Data & Update Weekly' }}
                            </button>
                        @endif
                    </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .bg-light-info { background-color: #e7f3ff; }
    .text-wrap { white-space: normal !important; }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        const performanceScale = @json($performanceScale);

                    function colorForPercentage(percentage) {
                        for (const band of performanceScale) {
                            const min = parseFloat(band.min);
                            if (percentage >= min) {
                                return band.color;
                            }
                        }
                        return performanceScale[performanceScale.length - 1].color;
                    }

        function updateRowCalculation(itemId) {
            let totalActual = 0;
            $(`input.day-input[data-item="${itemId}"]`).each(function() {
                const val = parseFloat($(this).val()) || 0;
                totalActual += val;
            });
            
            const planValue = parseFloat($(`input[name="weekly_plans[${itemId}]"]`).val()) || 0;
            const direction = $(`tr[data-item="${itemId}"]`).data('direction') || 'increase';
            const percentage = direction === 'decrease'
                ? (totalActual <= 0 ? 100 : (planValue / totalActual) * 100)
                : (planValue === 0 ? (totalActual >= 0 ? 100 : 0) : (totalActual / planValue) * 100);
            
            $(`#total-${itemId}`).text(formatNumber(totalActual));
            const percentDisplay = $(`#percent-${itemId}`);
            percentDisplay.text(formatNumber(percentage) + '%');
            
            const displayTd = percentDisplay.closest('td');
            displayTd.css('color', colorForPercentage(percentage));
        }

        $('.day-input, .row-plan-input').on('input', function() {
            const itemId = $(this).closest('tr').data('item');
            updateRowCalculation(itemId);
        });
    });
</script>
@endpush
@endsection
