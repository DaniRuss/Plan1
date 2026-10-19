@extends('layouts.app')
@section('page_header_hidden', '1')

@section('title', 'Monthly Performance Report')

@section('content')
@if($sharedWorkspace ?? false)
<div class="container-fluid pt-3">
@if($sharedSubmission)
@include('weekly._shared-review')
@else
<div class="alert alert-info">This period uses shared reporting. <a href="{{ route('shared-reporting.index',['department_id'=>$plan->department_id,'year'=>$year,'month'=>$month,'frequency'=>'monthly','week'=>0]) }}">Open Shared Reporting</a> to prepare and submit this report.</div>
@endif
</div>
@endif

    @php
        $performanceScale = \App\Models\Setting::getPerformanceScale();
        $referenceBg = \App\Models\Setting::get('performance_reference_bg', '#cce5ff');
        $referenceText = \App\Models\Setting::get('performance_reference_text', '#004085');
    @endphp
    <div class="content-header p-2">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded border shadow-sm mb-3">
                <div class="d-flex align-items-center">
                    <a href="{{ route('monthly.dashboard', ['department_id' => $deptId ?? $plan->department_id, 'year' => $year]) }}" class="btn btn-outline-secondary btn-sm mr-3">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h5 class="m-0 font-weight-bold text-dark border-left pl-3">
                        📋 {{ ucfirst($month) }} {{ $year }} Performance Report
                    </h5>
                </div>
                <div class="card-tools d-flex align-items-center">
                    @if($closure)
                        <div class="badge badge-success px-3 py-2 mr-2">
                            <i class="fas fa-lock mr-1"></i> CERTIFIED & LOCKED
                        </div>
                    @else
                        @if(auth()->user()->isSystemAdmin() || auth()->user()->isPlanReportHeadOffice())
                        <form action="{{ route('monthly.certify') }}" method="POST" class="d-inline-block mr-2"
                            data-confirm="Certify and lock this month? Data entry will be frozen." data-confirm-title="Certify and lock month" data-confirm-text="Certify and lock" data-confirm-class="btn-warning">
                            @csrf
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="month" value="{{ $month }}">
                            <input type="hidden" name="department_id" value="{{ $plan->department_id }}">
                            <button type="submit" class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm">
                                <i class="fas fa-certificate mr-1"></i> CERTIFY & CLOSE
                            </button>
                        </form>
                        @else
                            <span class="badge badge-info px-3 py-2 mr-2"><i class="fas fa-lock-open mr-1"></i>OPEN PERIOD</span>
                        @endif
                    @endif
                    <a href="{{ route('monthly.export', ['year' => $year, 'month' => $month, 'department_id' => $deptId ?? $plan->department_id]) }}"
                        class="btn btn-success btn-sm rounded-pill px-3 shadow-sm mr-2">
                        <i class="fas fa-file-excel mr-1"></i> Export to Excel
                    </a>
                    <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 shadow-sm"
                        onclick="window.print()">
                        <i class="fas fa-file-pdf mr-1"></i> Print PDF
                    </button>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3 no-print">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div class="mb-2">
                            <div class="text-muted small">Workflow Status</div>
                            <span class="badge badge-{{ $workflowState['badge'] }} px-3 py-2">{{ $workflowState['label'] }}</span>
                            @if($workflowState['note'])
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-comment-dots mr-1"></i>{{ $workflowState['note'] }}
                                </div>
                            @endif
                            @if($workflowState['submitted_by'])
                                <div class="small text-muted mt-2">
                                    Submitted by {{ $workflowState['submitted_by'] }}
                                    @if($workflowState['submitted_at'])
                                        on {{ $workflowState['submitted_at']->format('M d, Y H:i') }}
                                    @endif
                                </div>
                            @endif
                            @if($workflowState['reviewed_by'])
                                <div class="small text-muted">
                                    Last reviewed by {{ $workflowState['reviewed_by'] }}
                                    @if($workflowState['reviewed_at'])
                                        on {{ $workflowState['reviewed_at']->format('M d, Y H:i') }}
                                    @endif
                                </div>
                            @endif
                        </div>
                        @if(auth()->user()->isSystemAdmin() || auth()->user()->isPlanReportHeadOffice())
                        <form action="{{ route('monthly.reopen') }}" method="POST" class="d-inline-flex mr-2"
                            data-confirm="Reopen this certified month for a controlled revision?" data-confirm-title="Reopen monthly report" data-confirm-text="Reopen" data-confirm-class="btn-danger">
                            @csrf
                            <input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="department_id" value="{{ $plan->department_id }}">
                            <input type="text" name="reason" class="form-control form-control-sm mr-1" required minlength="5" placeholder="Required revision reason">
                            <button class="btn btn-outline-danger btn-sm"><i class="fas fa-lock-open mr-1"></i>Reopen</button>
                        </form>
                        @endif
                        <div class="text-right">
                            @if(
                                $workflowActions['submit_to_chief']
                                || $workflowActions['return_to_expert']
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
                                <form action="{{ route('monthly.workflow', ['year' => $year, 'month' => $month]) }}" method="POST" class="d-inline-block">
                                    @csrf
                                    <input type="hidden" name="department_id" value="{{ $deptId ?? $plan->department_id }}">
                                    <div class="form-group mb-2">
                                        <input type="text" name="workflow_note" class="form-control form-control-sm" placeholder="Optional review note for the next person">
                                    </div>
                                    <div>
                                        @if($workflowActions['return_to_expert'])
                                            <button type="submit" name="action" value="return_to_expert" class="btn btn-outline-warning btn-sm mr-2">Return to Expert</button>
                                        @endif
                                        @if($workflowActions['submit_to_chief'])
                                            <a href="{{ route('monthly.entry.form', ['year' => $year, 'month' => $month, 'department_id' => $deptId ?? $plan->department_id]) }}" class="btn btn-outline-primary btn-sm mr-2">Open Review Form</a>
                                            <button type="submit" name="action" value="submit_to_chief" class="btn btn-primary btn-sm">Approve and Send to Chief</button>
                                        @endif
                                        @if($workflowActions['return_to_directorate'])
                                            <button type="submit" name="action" value="return_to_directorate" class="btn btn-outline-warning btn-sm mr-2">Return to Directorate</button>
                                        @endif
                                        @if($workflowActions['approve_by_chief'])
                                            @if($workflowState['key'] === 'returned_to_department_chief')<a href="{{ route('monthly.entry.form', ['year'=>$year,'month'=>$month,'department_id'=>$deptId??$plan->department_id]) }}" class="btn btn-warning btn-sm mr-2"><i class="fas fa-edit mr-1"></i>Edit Returned Performance</a>@endif
                                            <button type="submit" name="action" value="approve_by_chief" class="btn btn-success btn-sm">{{ $workflowState['key'] === 'returned_to_department_chief' ? 'Resend Corrected Performance to PR Expert' : 'Approve and Send to Plan & Report Department' }}</button>
                                        @endif
                                        @if($workflowActions['return_to_department_chief'])
                                            <button type="submit" name="action" value="return_to_department_chief" class="btn btn-outline-warning btn-sm mr-2">Return to Department Chief for Correction</button>
                                        @endif
                                        @if($workflowActions['mark_reviewed_no_correction'])
                                            <button type="submit" name="action" value="mark_reviewed_no_correction" class="btn btn-success btn-sm mr-2">Reviewed — No Correction Required</button>
                                        @endif
                                        @if($workflowActions['submit_to_head_office'])
                                            <a href="{{ route('monthly.entry.form', ['year' => $year, 'month' => $month, 'department_id' => $deptId ?? $plan->department_id]) }}" class="btn btn-outline-primary btn-sm mr-2">Open Office Review Form</a>
                                            <button type="submit" name="action" value="submit_to_head_office" class="btn btn-primary btn-sm">Accept and Send to Head of Plan &amp; Report</button>
                                        @endif
                                        @if($workflowActions['submit_to_plan_report_directorate'])
                                            <button type="submit" name="action" value="submit_to_plan_report_directorate" class="btn btn-primary btn-sm">Request Correction Through Directorate</button>
                                        @endif
                                        @if($workflowActions['return_to_plan_report_department'])
                                            <button type="submit" name="action" value="return_to_plan_report_department" class="btn btn-outline-warning btn-sm mr-2">Return to Plan & Report Department</button>
                                        @endif
                                        @if($workflowActions['return_to_plan_report_directorate'])
                                            <button type="submit" name="action" value="return_to_plan_report_directorate" class="btn btn-outline-warning btn-sm mr-2">Return to Plan &amp; Report Directorate</button>
                                        @endif
                                        @if($workflowActions['approve_by_head_office'])
                                            <button type="submit" name="action" value="approve_by_head_office" class="btn btn-success btn-sm">Final Approve Performance</button>
                                        @endif
                                    </div>
                                </form>
                            @elseif($workflowActions['submit_to_directorate'])
                                <form action="{{ route('monthly.workflow', ['year' => $year, 'month' => $month]) }}" method="POST" class="d-inline-block">
                                    @csrf
                                    <input type="hidden" name="department_id" value="{{ $deptId ?? $plan->department_id }}">
                                    <button type="submit" name="action" value="submit_to_directorate" class="btn btn-success btn-sm">Submit to Directorate</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @include('performance._attachments', ['attachments' => $attachments])

            <div class="card border-{{ $completeness['status'] === 'Complete' ? 'success' : 'warning' }} shadow-sm mb-3">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div><strong>Reporting completeness</strong> <span class="badge badge-{{ $completeness['status'] === 'Complete' ? 'success' : 'warning' }}">{{ $completeness['status'] }}</span></div>
                        <div>Expected weeks: <strong>{{ $completeness['expected'] }}</strong></div>
                        <div>Submitted: <strong>{{ $completeness['submitted'] }}</strong></div>
                        <div>Approved: <strong>{{ $completeness['approved'] }}</strong></div>
                        <div>Planning coverage: <strong>{{ \App\Support\NumberFormatter::format($completeness['planning_coverage'],3) }}%</strong></div>
                        <div>Reporting coverage: <strong>{{ \App\Support\NumberFormatter::format($completeness['reporting_coverage'],3) }}%</strong></div>
                    </div>
                    @if($completeness['status'] === 'Provisional')
                        <div class="text-warning small mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>The achievement below uses final-approved weeks only and is provisional until every expected week is approved.</div>
                    @endif
                </div>
            </div>

            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-chart-bar mr-2 text-primary"></i>
                        {{ $plan->department->name }} - KPI Performance Matrix
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm m-0">
                            <thead class="bg-light text-center small font-weight-bold uppercase">
                                <tr>
                                    <th width="60" rowspan="2" class="align-middle">S.No</th>
                                    <th rowspan="2" class="align-middle text-left pl-3">Strategic Objectives / Activities
                                    </th>
                                    <th width="80" rowspan="2" class="align-middle">Unit</th>
                                    <th width="105" rowspan="2" class="align-middle">Period Weight</th>
                                    <th colspan="2" class="bg-primary text-white py-1">Target vs Performance</th>
                                    <th width="100" rowspan="2" class="align-middle text-info">% Perf.</th>
                                    <th width="100" rowspan="2" class="align-middle text-success">Weighted Score</th>
                                </tr>
                                <tr>
                                    <th width="120" class="sub-column py-1 font-weight-bold">Plan</th>
                                    <th width="120" class="sub-column py-1 font-weight-bold">Actual</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalWeightedScore = $objectiveRollups->sum('weighted_score');
                                    $totalPossibleWeight = $objectiveRollups->sum('weight');
                                @endphp
                                @foreach($objectives as $objective)
                                    @php
                                        $rollup = $objectiveRollups[$objective->id] ?? null;
                                        $rollupBand = \App\Models\Setting::getPerformanceBand($rollup['achievement'] ?? null);
                                    @endphp
                                    <tr class="bg-light font-weight-bold {{ $objective->is_reference_only ? 'italic' : '' }}">
                                        <td class="text-center">{{ $objective->code }}</td>
                                        <td class="text-primary">
                                            {{ strtoupper($objective->description) }}
                                            @if($objective->is_reference_only) <span
                                            class="badge badge-secondary ml-2">REFERENCE</span> @endif
                                            @if(!$objective->is_reference_only)<small class="d-block text-muted">Weighted child roll-up · {{ $rollup['planned_count'] ?? 0 }}/{{ $rollup['total_count'] ?? 0 }} activities planned</small>@endif
                                        </td>
                                        <td class="text-center small"><span class="badge badge-light border">Roll-up</span></td>
                                        <td class="text-center">{{ ($rollup['weight'] ?? 0) > 0 ? \App\Support\NumberFormatter::format($rollup['weight'],3) : 'N/A' }}</td>
                                        <td class="text-center text-muted">Roll-up</td>
                                        <td class="text-center text-primary">Roll-up</td>
                                        <td class="text-center" style="color:{{ $rollupBand['color'] }}">
                                            {{ is_null($rollup['achievement'] ?? null) ? 'N/A' : \App\Support\NumberFormatter::format($rollup['achievement'],3).'%' }}
                                            @if(($rollup['raw_achievement'] ?? 0) > 100)<small class="d-block text-info">Raw: {{ \App\Support\NumberFormatter::format($rollup['raw_achievement'],3) }}%</small>@endif
                                            @if(!is_null($rollup['achievement'] ?? null))<small class="d-block">{{ $rollup['status'] }}</small>@endif
                                        </td>
                                        <td class="text-center" style="color:{{ $rollupBand['color'] }}">{{ is_null($rollup['achievement'] ?? null) ? '-' : \App\Support\NumberFormatter::format($rollup['weighted_score'],3) }}</td>
                                    </tr>

                                    @foreach($objective->children as $child)
                                        @php
                                            $planItem = $planItems->get($child->id);
                                            $monthlyPlan = $planItem ? ($planItem->$monthColumn ?? 0) : 0;
                                            
                                            // Fallback: Use StrategicObjective weight if PlanItem weight is 0 or missing
                                            $baseWeight = ($planItem && $planItem->weight > 0) ? (float) $planItem->weight : (float) $child->weight;
                                            $weight = $planItem ? ($effectiveWeights[$planItem->id] ?? null) : null;
                                            $isPlanned = !is_null($weight);

                                            // Use helper method to aggregate both weekly and monthly actuals
                                            $monthlyActual = $child->is_reference_only ? 0 : ($planItem ? $planItem->getActualValueForPeriod($month) : 0);

                                            if ($child->is_reference_only) {
                                                $rawPercentage = 0;
                                                $percentage = 0;
                                                $weightedScore = 0;
                                            } else {
                                                $rawPercentage = $planItem ? $calculator->calculateRawTargetAchievement($planItem, $month) : 0;
                                                $percentage = $planItem ? $calculator->calculateTargetAchievement($planItem, $month) : 0;
                                                $weightedScore = (!$isPlanned || $child->exclude_from_overall_score) ? 0 : min(($percentage / 100) * $weight, $weight);
                                            }
                                        @endphp
                                        <tr class="{{ $child->is_reference_only ? 'italic' : '' }}" @if($child->is_reference_only) style="background-color: {{ $referenceBg }}; color: {{ $referenceText }};" @elseif($child->is_supplementary) style="background-color:#f3e8ff;border-left:4px solid #7c3aed" @endif>
                                            <td class="text-center font-weight-bold">{{ $child->code }}</td>
                                            <td class="pl-3 font-weight-bold">
                                                {{ $child->description }}
                                                <small class="d-block {{ ($child->goal_direction ?? 'increase') === 'decrease' ? 'text-warning' : 'text-success' }}">
                                                    <i class="fas {{ ($child->goal_direction ?? 'increase') === 'decrease' ? 'fa-arrow-down' : 'fa-arrow-up' }} mr-1"></i>{{ ($child->goal_direction ?? 'increase') === 'decrease' ? 'Lower is better' : 'Higher is better' }}
                                                </small>
                                                @if($child->is_supplementary)
                                                    <span class="badge ml-2" style="background:#7c3aed;color:#fff">Department Added</span>
                                                    <small class="d-block text-muted">Excluded from parent and overall score</small>
                                                @endif
                                            </td>
                                            <td class="text-center small">{{ $child->unit }}</td>
                                            <td class="text-center bg-light">
                                                @if(!$isPlanned)
                                                    <span class="badge badge-secondary">N/A</span>
                                                @elseif(abs($weight - $baseWeight) > 0.0001)
                                                    <small class="text-muted"><del>{{ \App\Support\NumberFormatter::format($baseWeight,3) }}</del></small>
                                                    <strong class="text-success ml-1">{{ \App\Support\NumberFormatter::format($weight,3) }}</strong>
                                                @else
                                                    {{ \App\Support\NumberFormatter::format($weight,3) }}
                                                @endif
                                            </td>
                                            <td class="text-right pr-3 font-weight-bold text-muted">
                                                {{ $child->isTemporal() ? $child->formatTemporal($monthlyPlan) : \App\Support\NumberFormatter::format($monthlyPlan,3) }}
                                            </td>
                                            <td class="text-right pr-3 font-weight-bold text-primary">
                                                @if($child->is_reference_only)
                                                    <span class="text-muted small">N/A (REF)</span>
                                                @else
                                                    {{ $child->isTemporal() ? $child->formatTemporal($monthlyActual) : \App\Support\NumberFormatter::format($monthlyActual,3) }}
                                                @endif
                                            </td>
                                            @php
                                                $childBand = \App\Models\Setting::getPerformanceBand($percentage);
                                            @endphp
                                            <td
                                                class="text-center font-weight-bold" style="color: {{ $child->is_reference_only ? '#6c757d' : $childBand['color'] }};">
                                                {{ $child->is_reference_only || !$isPlanned ? '-' : \App\Support\NumberFormatter::format($percentage,3) . '%' }}
                                                @if(!$child->is_reference_only && $isPlanned && $rawPercentage > 100)
                                                    <small class="d-block text-primary">
                                                        Raw: {{ \App\Support\NumberFormatter::format($rawPercentage,3) }}%
                                                        <span class="badge badge-info">Overachieved</span>
                                                    </small>
                                                @endif
                                            </td>
                                            <td class="text-center font-weight-bold" style="color: {{ $child->is_reference_only ? '#6c757d' : $childBand['color'] }};">
                                                {{ $child->is_reference_only || !$isPlanned ? '-' : \App\Support\NumberFormatter::format($weightedScore,3) }}
                                            </td>
                                        </tr>

                                        @foreach($child->children as $subChild)
                                            @php
                                                $subPlanItem = $planItems->get($subChild->id);
                                                $subMonthlyPlan = $subPlanItem ? ($subPlanItem->$monthColumn ?? 0) : 0;
                                                $subIsPlanned = $subPlanItem && !is_null($subPlanItem->$monthColumn);
                                                $subActual = $subPlanItem ? $subPlanItem->getActualValueForPeriod($month) : 0;
                                                $subPercentage = $subIsPlanned
                                                    ? $calculator->calculateTargetAchievement($subPlanItem, $month)
                                                    : 0;
                                                $subRawPercentage = $subIsPlanned
                                                    ? $calculator->calculateRawTargetAchievement($subPlanItem, $month)
                                                    : 0;
                                            @endphp
                                            <tr class="sub-activity-row bg-white small">
                                                <td class="text-right pr-2 text-muted">{{ $subChild->code }}</td>
                                                <td class="pl-4">
                                                    <i class="fas fa-level-up-alt fa-rotate-90 text-muted mr-1"></i>
                                                    {{ $subChild->description }}
                                                    <small class="d-block {{ ($subChild->goal_direction ?? 'increase') === 'decrease' ? 'text-warning' : 'text-success' }}">
                                                        <i class="fas {{ ($subChild->goal_direction ?? 'increase') === 'decrease' ? 'fa-arrow-down' : 'fa-arrow-up' }} mr-1"></i>{{ ($subChild->goal_direction ?? 'increase') === 'decrease' ? 'Lower is better' : 'Higher is better' }}
                                                    </small>
                                                </td>
                                                <td class="text-center">{{ $subChild->unit }}</td>
                                                <td class="text-center">-</td>
                                                <td class="text-right pr-3 text-muted">
                                                    {{ !$subIsPlanned ? 'N/A' : ($subChild->isTemporal() ? $subChild->formatTemporal($subMonthlyPlan) : \App\Support\NumberFormatter::format($subMonthlyPlan,3)) }}
                                                </td>
                                                <td class="text-right pr-3 text-primary">
                                                    {{ !$subIsPlanned ? 'N/A' : ($subChild->isTemporal() ? $subChild->formatTemporal($subActual) : \App\Support\NumberFormatter::format($subActual,3)) }}
                                                </td>
                                                @php
                                                    $subBand = \App\Models\Setting::getPerformanceBand($subPercentage);
                                                @endphp
                                                <td class="text-center" style="color: {{ $subBand['color'] }};">
                                                    {{ $subIsPlanned ? \App\Support\NumberFormatter::format($subPercentage,3) . '%' : 'N/A' }}
                                                    @if($subIsPlanned && $subRawPercentage > 100)
                                                        <small class="d-block text-primary">Raw: {{ \App\Support\NumberFormatter::format($subRawPercentage,3) }}% — Overachieved</small>
                                                    @endif
                                                </td>
                                                <td class="text-center text-muted">-</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </tbody>
                            @php
                                $overallPerformanceScore = $totalPossibleWeight > 0
                                    ? min(100, ($totalWeightedScore / $totalPossibleWeight) * 100)
                                    : 0;
                            @endphp
                            <tfoot class="bg-dark text-white font-weight-bold h5">
                                <tr>
                                    <td colspan="7" class="text-right py-3 pr-4">OVERALL PERFORMANCE SCORE (OUT OF 100%):
                                    </td>
                                    <td class="text-center py-3 bg-success">
                                        {{ \App\Support\NumberFormatter::format($overallPerformanceScore,3) }}%
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white font-weight-bold">
                            <i class="fas fa-info-circle mr-2 text-info"></i> Aggregation Summary
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-0">
                                This report aggregates data from Week 1 to Week {{ $weekCount ?? 4 }} of {{ ucfirst($month) }}.
                                Performance uses <code>Actual / Plan * 100</code>. Weighted score is
                                <code>(% Perf. / 100) * Weight</code>, and the overall score is
                                <code>sum(Weighted Score) / sum(Weight) * 100</code>. Performance is capped at 100%.
                                Activities without a plan are marked N/A. Effective period weights use the configured {{ \App\Models\Setting::get('period_weight_distribution_mode','manual') }} distribution method without changing approved annual weights.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-right pt-3">
                    @if($closure)
                        <div class="alert alert-success d-inline-block px-5 py-2 mb-0 shadow-sm border-0 font-weight-bold">
                            <i class="fas fa-lock mr-2"></i> THIS MONTH IS CERTIFIED & LOCKED
                        </div>
                    @else
                        <form action="{{ route('monthly.certify') }}" method="POST" class="d-inline-block"
                            data-confirm="Certify and lock this month? Data entry will be frozen." data-confirm-title="Certify and lock month" data-confirm-text="Certify and lock" data-confirm-class="btn-warning">
                            @csrf
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="month" value="{{ $month }}">
                            <input type="hidden" name="department_id" value="{{ $plan->department_id }}">
                            <button type="submit" class="btn btn-primary px-5 shadow-sm font-weight-bold">
                                <i class="fas fa-check-double mr-2"></i> CERTIFY & CLOSE MONTH
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @if($missingWeightItems->isNotEmpty())
    <div class="alert alert-warning mx-2"><strong>Weight Distribution Required.</strong> Official scoring is blocked for {{ $missingWeightItems->count() }} planned activity item(s) until an approved manual distribution is available. <a href="{{route('period-weights.edit',['department_id'=>$plan->department_id,'year'=>$year,'period'=>$month])}}" class="alert-link">Open period weights</a>.</div>
    @endif

    @push('styles')
        <style>
            .sub-column {
                font-size: 0.75rem;
                text-transform: uppercase;
            }

            .italic {
                font-style: italic;
            }

            .bg-warning-light {
                background-color: #fffde7 !important;
            }

            .bg-success-light {
                background-color: #f0fff4 !important;
            }

            @media print {

                .btn,
                .main-sidebar,
                .main-header {
                    display: none !important;
                }

                .content-wrapper {
                    margin-left: 0 !important;
                }
            }
        </style>
    @endpush
@endsection
