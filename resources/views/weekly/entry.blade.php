@extends('layouts.app')

@section('title', 'Weekly Performance Entry')

@section('content')
    @php
        $performanceScale = \App\Models\Setting::getPerformanceScale();
        $referenceBg = \App\Models\Setting::get('performance_reference_bg', '#cce5ff');
        $referenceText = \App\Models\Setting::get('performance_reference_text', '#004085');
    @endphp
    <div class="content-headerp-2">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded border shadow-sm mb-3">
                <div class="d-flex align-items-center">
                    <a href="{{ route('weekly.dashboard') }}" class="btn btn-outline-secondary btn-sm mr-2">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <div class="btn-group btn-group-sm mr-2" id="weekSelector">
                        @php
                            $weeksInMonth = \App\Services\CalendarHelper::getMonthWeeks($month, $year);
                        @endphp
                        @foreach($weeksInMonth as $w => $info)
                            <a href="{{ route('weekly.entry', ['year' => $year, 'month' => $month, 'week' => $w, 'department_id' => $deptId]) }}"
                                class="btn {{ $week == $w ? 'primary' : 'outline-primary' }} px-2"
                                title="{{ $info['label'] }}">
                                W{{ $w }}
                            </a>
                        @endforeach
                    </div>
                    <select class="form-control form-control-sm mr-2" style="width: 120px;"
                        onchange="location.href='/weekly/entry/{{ $year }}/' + this.value + '/{{ $week }}' + ({{ $deptId ? 'true' : 'false' }} ? '?department_id={{ $deptId }}' : '')">
                        @foreach(['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'] as $m)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                    <select class="form-control form-control-sm" style="width: 100px;"
                        onchange="location.href='/weekly/entry/' + this.value + '/{{ $month }}/{{ $week }}' + ({{ $deptId ? 'true' : 'false' }} ? '?department_id={{ $deptId }}' : '')">
                        @foreach(\App\Services\CalendarHelper::getAppYears() as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="card-tools">
                    @if($canEditWorkflow)
                        <button type="submit" form="weeklyForm" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm mr-2">
                            <i class="fas fa-save mr-1"></i> {{ $workflowState['key'] === 'returned_to_department_chief' ? 'Save Corrected Performance' : 'Save Draft' }}
                        </button>
                    @endif
                    @if($workflowActions['submit_to_directorate'])
                        <form action="{{ route('weekly.workflow', ['year' => $year, 'month' => $month, 'week' => $week]) }}" method="POST" class="d-inline mr-2">
                            @csrf
                            <input type="hidden" name="department_id" value="{{ $deptId }}">
                            <input type="hidden" name="action" value="submit_to_directorate">
                            <button type="submit" class="btn btn-success btn-sm rounded-pill px-4 shadow-sm">
                                <i class="fas fa-paper-plane mr-1"></i> Submit to Directorate
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('weekly.report', ['year' => $year, 'month' => $month, 'week' => $week, 'department_id' => $deptId]) }}"
                       class="btn btn-info btn-sm rounded-pill px-3 shadow-sm mr-1">
                        <i class="fas fa-chart-bar mr-1"></i> View Report
                    </a>
                    <a href="{{ route('weekly.export', ['year' => $year, 'month' => $month, 'week' => $week, 'department_id' => $deptId]) }}"
                       class="btn btn-success btn-sm rounded-pill px-3 shadow-sm">
                        <i class="fas fa-file-csv mr-1"></i> Export CSV
                    </a>
                </div>
            </div>

            <div class="period-info bg-white p-3 rounded border shadow-sm mb-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="m-0 font-weight-bold text-dark">
                            📅 Week {{ $week }} Performance Entry - {{ ucfirst($month) }} {{ $year }}
                            <small class="text-muted ml-2">({{ $weekInfo ? $weekInfo['start'] . ' – ' . $weekInfo['end'] : '' }})</small>
                        </h5>
                        <div class="small text-muted mt-1">Status: <span class="badge badge-{{ $workflowState['badge'] }}">{{ $workflowState['label'] }}</span> | Dept:
                            {{ $dept->name ?? 'N/A' }}</div>
                        @if($workflowState['note'])
                            <div class="small text-muted mt-2">
                                <i class="fas fa-comment-dots mr-1"></i>{{ $workflowState['note'] }}
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6 text-right">
                        <div class="d-inline-block mr-3 border-right pr-3">
                            <span class="text-muted small d-block">📊 Overall Progress:</span>
                            <span class="font-weight-bold text-primary h5 m-0">67%</span>
                        </div>
                        <div class="d-inline-block">
                            <span class="text-muted small d-block">⏰ Due:</span>
                            <span class="font-weight-bold text-danger h6 m-0">Today, 5:00 PM</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Section Toggle Bar --}}
            <div class="section-toggles mb-3 overflow-auto">
                <div class="btn-group w-100 shadow-sm">
                    @foreach($objectives as $idx => $objective)
                        <button type="button"
                            class="btn btn-light border section-btn py-2 {{ $idx == 0 ? 'active btn-primary text-white' : '' }}"
                            data-section="section-{{ $objective->id }}">
                            {{ $idx + 1 }}. {{ strtoupper(Str::limit($objective->description, 25)) }}
                        </button>
                    @endforeach
                </div>
            </div>

            <form action="{{ route('weekly.store') }}" method="POST" enctype="multipart/form-data" id="weeklyForm">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="week" value="{{ $week }}">
                <input type="hidden" name="department_id" value="{{ $deptId }}">

                <div class="card card-outline card-primary shadow-sm">
                    @if(!$canEditWorkflow)
                        <div class="alert alert-warning rounded-0 border-0 mb-0">
                            This weekly period is read-only for your current role. Use the workflow actions on the report page for the next step.
                        </div>
                    @endif
                    <fieldset {{ !$canEditWorkflow ? 'disabled' : '' }}>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm m-0" id="weeklyEntryTable">
                                <thead class="bg-light text-center small uppercase">
                                    <tr>
                                        <th width="60" class="py-2">S.No</th>
                                        <th class="py-2 text-left pl-3">ACTIVITIES</th>
                                        <th width="100" class="py-2">UNIT</th>
                                        <th width="120" class="bg-light py-2">MONTH PLAN</th>
                                        <th width="130" class="bg-info text-white border-0 py-2">
                                            {{ strtoupper(substr($month, 0, 3)) }} W{{ $week }} Plan</th>
                                        <th width="130" class="bg-primary text-white border-0 py-2">W{{ $week }} Perf.</th>
                                        <th width="100" class="py-2">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($objectives as $objective)
                                        <tr class="section-row bg-light" id="section-{{ $objective->id }}">
                                            <td class="text-center font-weight-bold">{{ $objective->code }}</td>
                                            <td colspan="6" class="font-weight-bold text-primary h6 m-0">
                                                {{ strtoupper($objective->description) }}</td>
                                        </tr>

                                        @foreach($objective->children as $child)
                                            @php
                                                $planItem = $planItems->get($child->id);
                                                $isCalculatedParent = $child->children->isNotEmpty() && $child->summary_type !== 'manual_override';
                                                $monthlyTarget = $planItem ? ($planItem->$monthColumn ?? 0) : 0;
                                                $isPlanned = $planItem && !is_null($planItem->$monthColumn);
                                                $numWeeks = count($weeksInMonth);
                                                $weeklyTarget = $child->weeklyPlanFromMonthlyTarget((float) $monthlyTarget, $numWeeks);
                                                $repeatsMonthlyPlan = $child->weekly_plan_mode === 'repeat';
                                                $entry = $entries->get($planItem?->id);
                                                $actual = $entry ? $entry->actual_value : 0;
                                                $currentPlan = $entry && !is_null($entry->planned_value) ? (float)$entry->planned_value : $weeklyTarget;
                                                $percentage = $isPlanned ? $child->calculateWeeklyAchievement((float)$actual, (float)$currentPlan) : 0;
                                                $cleanActual = $actual != 0 ? (float)$actual : '';
                                                $hasStations = Str::contains($child->description, ['Cargo', 'TEU', 'Container', 'RoRo', 'Nagad', 'Empty']);

                                                // Calculate remaining allowed plan
                                                $otherWeeksPlan = ($monthlyPlanSums->get($planItem?->id) ?? 0) - ($entry && !is_null($entry->planned_value) ? (float)$entry->planned_value : 0);
                                                $maxAllowedPlan = $repeatsMonthlyPlan ? null : $monthlyTarget - $otherWeeksPlan;
                                                $maxAllowedPlan = is_null($maxAllowedPlan) ? null : ($maxAllowedPlan > 0 ? round($maxAllowedPlan, 2) : 0);
                                            @endphp
                                            <tr class="activity-row section-{{ $objective->id }}-item {{ $child->is_reference_only ? 'italic' : '' }} {{ !$isPlanned ? 'unplanned-period-row' : '' }} template-{{ $child->template_type }}"
                                                @if($child->is_reference_only) style="background-color: {{ $referenceBg }}; color: {{ $referenceText }};" @endif
                                                data-section="section-{{ $objective->id }}" data-id="{{ $planItem?->id }}" data-objective="{{ $child->id }}" data-summary="{{ $isCalculatedParent ? ($child->summary_type ?? 'sum') : '' }}" data-direction="{{ $child->goal_direction ?? 'increase' }}">
                                                <td class="text-center font-weight-bold">{{ $child->code }}</td>
                                                <td class="pl-3">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span class="font-weight-bold {{ $child->is_reference_only ? 'text-dark' : '' }}">
                                                                {{ $child->description }}
                                                            </span>
                                                            @if($child->data_source)
                                                                <div class="small text-muted"><i class="fas fa-link mr-1"></i> Source: {{ $child->data_source }}</div>
                                                            @endif
                                                        </div>
                                                        @if(($hasStations || $child->data_source) && !$child->is_reference_only && !$isCalculatedParent)
                                                            <button type="button"
                                                                class="btn btn-xs btn-outline-info ml-2 station-matrix-btn"
                                                                data-activity="{{ $child->description }}" data-id="{{ $child->id }}"
                                                                data-stations="{{ json_encode($entry ? $entry->stationData->pluck('actual_value', 'station_name')->toArray() : []) }}">
                                                                <i class="fas fa-th mr-1"></i> Stations
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center small">{{ $child->unit }}</td>
                                                <td class="text-right text-muted">{{ $isPlanned ? number_format($monthlyTarget, 2) : 'N/A' }}</td>
                                                <td class="text-right bg-light px-2" style="width: 120px;">
                                                    <input type="number" step="any" @if(!is_null($maxAllowedPlan)) max="{{ $maxAllowedPlan }}" @endif name="entries[{{ $child->id }}][plan_value]"
                                                        class="form-control form-control-sm row-plan-input text-right font-weight-bold text-info border-0 rounded-0"
                                                        value="{{ !is_null($entry?->planned_value) ? (float)$entry->planned_value : round($weeklyTarget, 2) }}"
                                                        {{ $repeatsMonthlyPlan || $isCalculatedParent ? 'readonly' : '' }}
                                                        title="{{ $repeatsMonthlyPlan ? 'Monthly plan repeated in every week' : 'Monthly plan distributed across weeks' }}"
                                                        style="background-color: transparent;" placeholder="0">
                                                </td>
                                                <td class="p-0">
                                                    @if($isCalculatedParent)
                                                        <input type="number" step="any" name="entries[{{ $child->id }}][actual]"
                                                            class="form-control form-control-sm performance-input border-0 text-right font-weight-bold"
                                                            value="{{ $actual }}" readonly title="Calculated from sub-objectives">
                                                    @elseif($child->is_reference_only)
                                                        <div class="py-2 pr-3 text-right font-weight-bold bg-light">REF</div>
                                                    @elseif($child->template_type === 'machinery')
                                                        @php $meta = $entry?->metadata ?? []; @endphp
                                                        <div class="d-flex machinery-grid bg-white">
                                                            <input type="number" step="1" name="entries[{{ $child->id }}][metadata][qty]" 
                                                                class="form-control form-control-sm border-0 bg-transparent text-center machinery-qty" 
                                                                value="{{ $meta['qty'] ?? '' }}" placeholder="Qty">
                                                            <input type="number" step="0.5" name="entries[{{ $child->id }}][metadata][time]" 
                                                                class="form-control form-control-sm border-0 bg-transparent text-center machinery-time" 
                                                                value="{{ $meta['time'] ?? 10 }}" placeholder="Hrs">
                                                            <input type="number" step="1" name="entries[{{ $child->id }}][metadata][days]" 
                                                                class="form-control form-control-sm border-0 bg-transparent text-center machinery-days" 
                                                                value="{{ $meta['days'] ?? 7 }}" placeholder="Days">
                                                            <input type="text" name="entries[{{ $child->id }}][actual]" 
                                                                class="form-control form-control-sm border-0 bg-light text-right font-weight-bold machinery-actual" 
                                                                value="{{ $cleanActual }}" readonly>
                                                        </div>
                                                    @elseif($child->template_type === 'truck_haulage')
                                                        @php $meta = $entry?->metadata ?? []; @endphp
                                                        <div class="d-flex truck-grid bg-white">
                                                            <input type="number" step="1" name="entries[{{ $child->id }}][metadata][count]" 
                                                                class="form-control form-control-sm border-0 bg-transparent text-center truck-count" 
                                                                value="{{ $meta['count'] ?? '' }}" placeholder="Qty">
                                                            <input type="number" step="0.01" name="entries[{{ $child->id }}][metadata][cycle]" 
                                                                class="form-control form-control-sm border-0 bg-transparent text-center truck-cycle" 
                                                                value="{{ $meta['cycle'] ?? '' }}" placeholder="Cycle">
                                                            <input type="number" step="0.1" name="entries[{{ $child->id }}][metadata][tone]" 
                                                                class="form-control form-control-sm border-0 bg-transparent text-center truck-tone" 
                                                                value="{{ $meta['tone'] ?? 40 }}" placeholder="Tone">
                                                            <input type="text" name="entries[{{ $child->id }}][actual]" 
                                                                class="form-control form-control-sm border-0 bg-light text-right font-weight-bold truck-actual" 
                                                                value="{{ $cleanActual }}" readonly>
                                                        </div>
                                                    @elseif($child->isTemporal())
                                                        <input type="text"
                                                            name="entries[{{ $child->id }}][actual]"
                                                            class="form-control form-control-sm performance-input temporal-input border-0 rounded-0 h-100 py-2"
                                                            value="{{ $child->formatTemporal($actual) }}" data-plan="{{ $weeklyTarget }}"
                                                            data-unit="{{ $child->unit }}"
                                                            id="input-{{ $child->id }}" placeholder="00:00"
                                                            style="background-color: transparent; text-align: right; font-weight: bold;">
                                                    @else
                                                        <input type="number" step="any"
                                                            name="entries[{{ $child->id }}][actual]"
                                                            class="form-control form-control-sm performance-input border-0 rounded-0 h-100 py-2"
                                                            value="{{ $cleanActual }}" data-plan="{{ $weeklyTarget }}"
                                                            id="input-{{ $child->id }}" placeholder="0"
                                                            style="background-color: transparent; text-align: right; font-weight: bold;">
                                                    @endif
                                                </td>
                                                @php
                                                    $childBand = \App\Models\Setting::getPerformanceBand($percentage);
                                                @endphp
                                                <td
                                                    class="text-center percentage-display font-weight-bold" style="color: {{ $childBand['color'] }};">
                                                    {{ $child->is_reference_only ? '-' : number_format($percentage, 1) . '%' }}
                                                </td>
                                            </tr>
                                            {{-- Remarks Row for every activity --}}
                                            <tr class="remarks-row section-{{ $objective->id }}-item" data-section="section-{{ $objective->id }}">
                                                <td class="p-0 border-0"></td>
                                                <td colspan="6" class="p-0 border-top-0">
                                                    <div class="input-group input-group-sm">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text bg-white border-0 text-muted px-3"><i class="far fa-comment-dots mr-1"></i> REMARK:</span>
                                                        </div>
                                                        <input type="text" name="entries[{{ $child->id }}][notes]" class="form-control border-0 bg-light-soft italic" placeholder="Add operational notes or maintenance status..." value="{{ $entry?->notes }}">
                                                    </div>
                                                </td>
                                            </tr>

                                            @foreach($child->children as $subChild)
                                                @php
                                                    $subPlanItem = $planItems->get($subChild->id);
                                                    $subMonthlyTarget = $subPlanItem ? ($subPlanItem->$monthColumn ?? 0) : 0;
                                                    $subIsPlanned = $subPlanItem && !is_null($subPlanItem->$monthColumn);
                                                    $subWeeklyTarget = $subChild->weeklyPlanFromMonthlyTarget((float) $subMonthlyTarget, $numWeeks);
                                                    $subRepeatsMonthlyPlan = $subChild->weekly_plan_mode === 'repeat';
                                                    $subEntry = $entries->get($subPlanItem?->id);
                                                    $subActual = $subEntry ? $subEntry->actual_value : 0;
                                                    $subCurrentPlan = $subEntry && !is_null($subEntry->planned_value) ? (float)$subEntry->planned_value : $subWeeklyTarget;
                                                    $subPercentage = $subIsPlanned ? $subChild->calculateWeeklyAchievement((float)$subActual, (float)$subCurrentPlan) : 0;
                                                    $subCleanActual = $subActual != 0 ? (float)$subActual : '';

                                                    // Calculate remaining allowed plan
                                                    $subOtherWeeksPlan = ($monthlyPlanSums->get($subPlanItem?->id) ?? 0) - ($subEntry && !is_null($subEntry->planned_value) ? (float)$subEntry->planned_value : 0);
                                                    $subMaxAllowedPlan = $subRepeatsMonthlyPlan ? null : $subMonthlyTarget - $subOtherWeeksPlan;
                                                    $subMaxAllowedPlan = is_null($subMaxAllowedPlan) ? null : ($subMaxAllowedPlan > 0 ? round($subMaxAllowedPlan, 2) : 0);
                                                @endphp
                                                <tr class="sub-activity-row section-{{ $objective->id }}-item {{ $subChild->is_reference_only ? 'italic' : '' }} {{ !$subIsPlanned ? 'unplanned-period-row' : '' }}"
                                                    @if($subChild->is_reference_only) style="background-color: {{ $referenceBg }}; color: {{ $referenceText }};" @endif
                                                    data-section="section-{{ $objective->id }}" data-parent-objective="{{ $child->id }}" data-included="{{ $subChild->include_in_parent_rollup ? 1 : 0 }}" data-direction="{{ $subChild->goal_direction ?? 'increase' }}">
                                                    <td class="text-right pr-2"><small>{{ $subChild->code }}</small></td>
                                                    <td class="pl-4">
                                                        <i class="fas fa-level-up-alt fa-rotate-90 text-muted mr-1 small"></i>
                                                        <span class="{{ $subChild->is_reference_only ? 'font-weight-bold' : '' }}">
                                                            {{ $subChild->description }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center small">{{ $subChild->unit }}</td>
                                                    <td class="text-right text-muted small">{{ $subIsPlanned ? number_format($subMonthlyTarget, 2) : 'N/A' }}
                                                    </td>
                                                    <td class="text-right bg-light px-2" style="width: 100px;">
                                                        <input type="number" step="any" @if(!is_null($subMaxAllowedPlan)) max="{{ $subMaxAllowedPlan }}" @endif name="entries[{{ $subChild->id }}][plan_value]"
                                                            class="form-control form-control-sm row-plan-input text-right font-weight-bold text-info border-0 rounded-0 p-1 bg-transparent"
                                                            value="{{ !is_null($subEntry?->planned_value) ? (float)$subEntry->planned_value : round($subWeeklyTarget, 2) }}"
                                                            {{ $subRepeatsMonthlyPlan ? 'readonly' : '' }}
                                                            title="{{ $subRepeatsMonthlyPlan ? 'Monthly plan repeated in every week' : 'Monthly plan distributed across weeks' }}" placeholder="0">
                                                    </td>
                                                    <td class="p-0">
                                                        @if($subChild->is_reference_only)
                                                           <div class="py-1 pr-3 text-right bg-light small">REF</div>
                                                        @elseif($subChild->isTemporal())
                                                            <input type="text"
                                                                name="entries[{{ $subChild->id }}][actual]"
                                                                class="form-control form-control-sm performance-input temporal-input border-0 rounded-0 h-100 py-1"
                                                                value="{{ $subChild->formatTemporal($subActual) }}" data-plan="{{ $subWeeklyTarget }}"
                                                                data-unit="{{ $subChild->unit }}"
                                                                id="input-{{ $subChild->id }}"
                                                                placeholder="00:00"
                                                                style="background-color: transparent; text-align: right; font-size: 0.85rem;">
                                                        @else
                                                            <input type="number" step="any"
                                                                name="entries[{{ $subChild->id }}][actual]"
                                                                class="form-control form-control-sm performance-input border-0 rounded-0 h-100 py-1"
                                                                value="{{ $subCleanActual }}" data-plan="{{ $subWeeklyTarget }}"
                                                                id="input-{{ $subChild->id }}" placeholder="0"
                                                                style="background-color: transparent; text-align: right; font-size: 0.85rem;">
                                                        @endif
                                                    </td>
                                                    @php
                                                        $subBand = \App\Models\Setting::getPerformanceBand($subPercentage);
                                                    @endphp
                                                    <td
                                                        class="text-center percentage-display small" style="color: {{ $subBand['color'] }};">
                                                        {{ $subChild->is_reference_only ? '-' : number_format($subPercentage, 1) . '%' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Performance Remarks Table -->
                        <div class="p-4 border-top bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="font-weight-bold mb-0 text-primary">
                                    <i class="fas fa-comment-alt mr-1"></i> Department Performance Remarks (Optional)
                                </h6>
                                <button type="button" class="btn btn-xs btn-outline-primary" id="addRemarkRow">
                                    <i class="fas fa-plus mr-1"></i> Add Remark
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white" id="remarksTable">
                                    <thead class="bg-light small">
                                        <tr>
                                            <th width="200">Remark Type</th>
                                            <th>Observation / Narration</th>
                                            <th width="50"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($performanceRemarks as $index => $remark)
                                            <tr>
                                                <td>
                                                    <select name="remarks[{{ $index }}][type]" class="form-control form-control-sm">
                                                        <option value="achievement" {{ $remark->remark_type == 'achievement' ? 'selected' : '' }}>Key Achievement</option>
                                                        <option value="challenge" {{ $remark->remark_type == 'challenge' ? 'selected' : '' }}>Key Challenge</option>
                                                        <option value="measure" {{ $remark->remark_type == 'measure' ? 'selected' : '' }}>Corrective Measure</option>
                                                        <option value="general" {{ $remark->remark_type == 'general' ? 'selected' : '' }}>General Note</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <textarea name="remarks[{{ $index }}][text]" class="form-control form-control-sm" rows="1">{{ $remark->remark_text }}</textarea>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-danger remove-remark-row"><i class="fas fa-times"></i></button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td>
                                                    <select name="remarks[0][type]" class="form-control form-control-sm">
                                                        <option value="achievement">Key Achievement</option>
                                                        <option value="challenge">Key Challenge</option>
                                                        <option value="measure">Corrective Measure</option>
                                                        <option value="general">General Note</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <textarea name="remarks[0][text]" class="form-control form-control-sm" rows="1" placeholder="Enter remark here..."></textarea>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-danger remove-remark-row"><i class="fas fa-times"></i></button>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="card-body border-top bg-light">
                        <label class="font-weight-bold"><i class="fas fa-paperclip mr-1"></i> Supporting Evidence</label>
                        <input type="file" name="attachments[]" class="form-control-file" multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png">
                        <small class="form-text text-muted">Up to 10 files, maximum 10 MB each. Evidence is stored privately.</small>
                        @include('performance._attachments', ['attachments' => $attachments])
                    </div>
                    <div class="card-footer bg-white border-top">
                        <div class="row">
                            <div class="col-md-6">
                                <span class="text-muted"><i class="fas fa-info-circle mr-1"></i> Data automatically sums up
                                    toward Monthly and Scorecard reports.</span>
                            </div>
                            <div class="col-md-6 text-right">
                                @if($canEditWorkflow)
                                    <button type="submit" class="btn btn-primary px-5 shadow-sm font-weight-bold">
                                        <i class="fas fa-save mr-1"></i> SAVE ALL PERFORMANCE
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    </fieldset>
                </div>
            </form>
        </div>
        </section>

        {{-- Station Matrix Modal --}}
        <div class="modal fade shadow" id="stationModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content border-0">
                    <div class="modal-header bg-primary text-white py-2">
                        <h6 class="modal-title font-weight-bold" id="stationModalTitle">Station Breakdown</h6>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body p-0">
                        <table class="table table-sm table-striped m-0">
                            <thead class="bg-light small">
                                <tr>
                                    <th class="pl-3 py-2">STATION / ROUTE</th>
                                    <th width="150" class="text-right pr-3 py-2">PERFORMANCE</th>
                                </tr>
                            </thead>
                            <tbody id="stationTableBody">
                                {{-- To be populated via JS --}}
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer py-2 bg-light">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                            data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm px-4" id="saveStationsBtn">Apply Total to
                            Activity</button>
                    </div>
                </div>
            </div>
        </div>

        @push('styles')
            <style>
                .section-btn {
                    transition: all 0.2s;
                    font-size: 0.8rem;
                    border-radius: 0 !important;
                }

                .section-btn.active {
                    border-bottom: 3px solid #0056b3;
                }

                .activity-row {
                    transition: background 0.2s;
                }

                .activity-row:hover {
                    background-color: #f1f8ff;
                }

                .italic {
                    font-style: italic;
                }

                .border-info-left {
                    border-left: 3px solid #17a2b8 !important;
                }

                .percentage-display {
                    transition: all 0.3s;
                }

                #weeklyEntryTable thead th {
                    border-top: 0;
                }

                .section-toggles .btn-group {
                    flex-wrap: nowrap;
                }

                /* High Fidelity Styles */
                .machinery-grid input, .truck-grid input {
                    width: 33%;
                    font-size: 0.8rem;
                    padding: 0.25rem;
                }
                .machinery-grid .machinery-actual, .truck-grid .truck-actual {
                    width: 40%;
                }
                .bg-light-soft {
                    background-color: #f8f9fa !important;
                }
                .remarks-row {
                    background-color: #fff !important;
                }
                .remarks-row td {
                    border-top: none !important;
                }
                .activity-row {
                    border-bottom: 1px solid #dee2e6;
                }
                .template-revenue { border-left: 4px solid #28a745; }
                .template-machinery { border-left: 4px solid #17a2b8; }
                .template-truck_haulage { border-left: 4px solid #ffc107; }
            </style>
        @endpush

        @push('scripts')
            <script>
                $(document).ready(function () {
                    $('.unplanned-period-row :input').prop('disabled', true);
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

                    // Section Scrolling instead of filtering
                    $('.section-btn').on('click', function () {
                        $('.section-btn').removeClass('active btn-primary text-white').addClass('btn-light text-dark');
                        $(this).addClass('active btn-primary text-white');
                        let sectionId = $(this).data('section');
                        let target = $('#' + sectionId);
                        if (target.length) {
                            $('html, body').animate({
                                scrollTop: target.offset().top - 80 // Adjust for header height
                            }, 500);
                        }
                    });

                    // Real-time calculation for Standard inputs
                    $('.performance-input, .row-plan-input').on('input', function () {
                        calculateStandardRow($(this));
                    });

                    // Real-time calculation for Machinery
                    $('.machinery-qty, .machinery-time, .machinery-days').on('input', function() {
                        let row = $(this).closest('tr');
                        let qty = parseFloat(row.find('.machinery-qty').val()) || 0;
                        let time = parseFloat(row.find('.machinery-time').val()) || 0;
                        let days = parseFloat(row.find('.machinery-days').val()) || 0;
                        let actual = qty * time * days;
                        
                        row.find('.machinery-actual').val(actual.toFixed(2));
                        updatePercentage(row, actual);
                    });

                    // Real-time calculation for Trucking
                    $('.truck-count, .truck-cycle, .truck-tone').on('input', function() {
                        let row = $(this).closest('tr');
                        let count = parseFloat(row.find('.truck-count').val()) || 0;
                        let cycle = parseFloat(row.find('.truck-cycle').val()) || 0;
                        let tone = parseFloat(row.find('.truck-tone').val()) || 0;
                        let actual = count * cycle * tone; 
                        
                        row.find('.truck-actual').val(actual.toFixed(2));
                        updatePercentage(row, actual);
                    });

                    function timeToDecimal(t) {
                        if (!t) return 0;
                        let totalHours = 0;
                        
                        // Handle "X days"
                        if (t.includes('day')) {
                           let parts = t.split(',');
                           let dayPart = parts[0].match(/\d+/);
                           totalHours += (dayPart ? parseInt(dayPart[0]) : 0) * 24;
                           t = parts[1] ? parts[1].trim() : "00:00";
                        }

                        let parts = t.split(':');
                        if (parts.length < 2) return parseFloat(t) || 0;
                        return totalHours + parseInt(parts[0]) + (parseInt(parts[1]) / 60);
                    }

                    function calculateStandardRow(input) {
                        let row = input.closest('tr');
                        let actualInput = row.find('.performance-input');
                        let actual;
                        
                        if (actualInput.hasClass('temporal-input')) {
                            actual = timeToDecimal(actualInput.val());
                        } else {
                            actual = parseFloat(actualInput.val()) || 0;
                        }
                        updatePercentage(row, actual);
                    }

                    function updatePercentage(row, actual) {
                        // Crucially, get the value directly from the .row-plan-input
                        let planInput = row.find('.row-plan-input');
                        let plan = 0;
                        if (planInput.length > 0) {
                            plan = parseFloat(planInput.val()) || 0;
                        } else {
                            // fallback for old plan-hidden fields if they exist
                            plan = parseFloat(row.find('.row-plan-hidden').val()) || 0;
                        }
                        
                        const direction = row.data('direction') || 'increase';
                        let percentage = direction === 'decrease'
                            ? (actual <= 0 ? 100 : (plan / actual) * 100)
                            : (plan === 0 ? (actual >= 0 ? 100 : 0) : (actual / plan) * 100);

                        let display = row.find('.percentage-display');
                        display.text(percentage.toFixed(1) + '%');

                        display.css('color', colorForPercentage(percentage));
                        if (row.data('parent-objective')) recalculateParents();
                    }

                    function recalculateParents() {
                        $('tr[data-summary]').each(function () {
                            const parent = $(this);
                            const type = parent.attr('data-summary');
                            if (!type) return;
                            const children = $('tr[data-parent-objective="' + parent.data('objective') + '"][data-included="1"]').filter(':not(.unplanned-period-row)');
                            let plan = 0, actual = 0;
                            children.each(function () {
                                const child = $(this);
                                plan += parseFloat(child.find('.row-plan-input').val()) || 0;
                                const input = child.find('.performance-input');
                                actual += type === 'weighted'
                                    ? (parseFloat(child.find('.percentage-display').text()) || 0)
                                    : (input.hasClass('temporal-input') ? timeToDecimal(input.val()) : (parseFloat(input.val()) || 0));
                            });
                            if (type === 'average' && children.length) { plan /= children.length; actual /= children.length; }
                            if (type === 'weighted') { plan = 100; actual = children.length ? actual / children.length : 0; }
                            parent.find('.row-plan-input').val(plan.toFixed(2));
                            parent.find('.performance-input').val(actual.toFixed(2));
                            updatePercentage(parent, actual);
                        });
                    }
                    recalculateParents();

                    // Station Matrix Support
                    let activePlanItemId = null;
                    const defaultStations = [
                        'NAGAD STATIONS', 'INDODE STATION', 'MOJO STATION', 'ADAMA STATION',
                        'METEHARA STATION', 'DIRE DAWA STATION', 'AYSHA STATION', 'DEWDALE STATION'
                    ];

                    $('.station-matrix-btn').on('click', function () {
                        activePlanItemId = $(this).data('id');
                        let activity = $(this).data('activity');
                        let savedData = $(this).data('stations') || {};

                        $('#stationModalTitle').text('Breakdown for: ' + activity);
                        let html = '';

                        defaultStations.forEach(station => {
                            let val = savedData[station] || '';
                            html += `<tr>
                                        <td class="pl-4 py-2 font-weight-bold text-muted small">${station}</td>
                                        <td class="p-0">
                                            <input type="number" step="0.01" class="form-control form-control-sm station-input border-0 rounded-0 text-right pr-3 font-weight-bold" 
                                                   data-station="${station}" value="${val}" placeholder="0.00">
                                        </td>
                                    </tr>`;
                        });

                        $('#stationTableBody').html(html);
                        $('#stationModal').modal('show');
                    });

                    $('#saveStationsBtn').on('click', function () {
                        let total = 0;
                        let stationData = {};

                        $('.station-input').each(function () {
                            let val = parseFloat($(this).val()) || 0;
                            total += val;
                            stationData[$(this).data('station')] = val;
                        });

                        // Update the main performance input
                        let mainInput = $('#input-' + activePlanItemId);
                        mainInput.val(total.toFixed(2)).trigger('input');

                        // Store hidden inputs for station data
                        let container = mainInput.closest('td');
                        container.find('.station-hidden-fields').remove();
                        let hiddenFields = $('<div class="station-hidden-fields"></div>');

                        for (let [station, val] of Object.entries(stationData)) {
                            hiddenFields.append(`<input type="hidden" name="entries[${activePlanItemId}][stations][${station}]" value="${val}">`);
                        }
                        container.append(hiddenFields);

                        $('#stationModal').modal('hide');
                    });

                    // Remarks Table Dynamic Rows
                    let remarkIndex = {{ count($performanceRemarks) > 0 ? count($performanceRemarks) : 1 }};
                    $('#addRemarkRow').on('click', function() {
                        let html = `<tr>
                            <td>
                                <select name="remarks[${remarkIndex}][type]" class="form-control form-control-sm">
                                    <option value="achievement">Key Achievement</option>
                                    <option value="challenge">Key Challenge</option>
                                    <option value="measure">Corrective Measure</option>
                                    <option value="general">General Note</option>
                                </select>
                            </td>
                            <td>
                                <textarea name="remarks[${remarkIndex}][text]" class="form-control form-control-sm" rows="1" placeholder="Enter remark here..."></textarea>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-danger remove-remark-row"><i class="fas fa-times"></i></button>
                            </td>
                        </tr>`;
                        $('#remarksTable tbody').append(html);
                        remarkIndex++;
                    });

                    $(document).on('click', '.remove-remark-row', function() {
                        if ($('#remarksTable tbody tr').length > 1) {
                            $(this).closest('tr').remove();
                        } else {
                            $(this).closest('tr').find('textarea').val('');
                            $(this).closest('tr').find('select').val('achievement');
                        }
                    });
                });
            </script>
        @endpush
@endsection
