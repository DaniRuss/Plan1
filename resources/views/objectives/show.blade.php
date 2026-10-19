@extends('layouts.app')
@section('page_header_hidden', '1')
@section('title', 'Objective '.$objective->code)
@push('styles')
<style>
    .objective-facts dt {font-size:.8rem;color:#657586;margin-bottom:.3rem}
    .objective-facts dd {font-size:.95rem;color:#17344b;margin-bottom:1.2rem}
    .objective-facts small {font-size:.75rem;margin-top:.25rem}
</style>
@endpush

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Objective Details: {{ $objective->code }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('objectives.index') }}">Objectives</a></li>
                        <li class="breadcrumb-item active">View</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @php
                $frequencyLabels = ['inherit' => 'Use department default', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
            @endphp
            <div class="card card-body mb-3">
                <h2 class="h6 font-weight-bold">Reporting schedule</h2>
                <p class="mb-1"><strong>Effective frequency:</strong> {{ ucfirst($objective->effectiveReportingFrequency()) }}</p>
                <small class="text-muted">{{ $objective->reporting_frequency ? 'Configured specifically for this objective.' : 'Inherited from the owning department’s reporting schedule.' }}</small>
                @if($objective->pending_reporting_frequency)
                    <p class="mt-2">Awaiting approval: {{ $frequencyLabels[$objective->pending_reporting_frequency] }}. Reason: {{ $objective->frequency_change_reason }}</p>
                    @if(auth()->user()->isPlanReportHeadOffice() || auth()->user()->isSystemAdmin())
                        <form method="POST" action="{{ route('objectives.frequency.decide', $objective) }}">@csrf
                            <button name="decision" value="approve" class="btn btn-success btn-sm">Approve frequency change</button>
                            <button name="decision" value="reject" class="btn btn-outline-danger btn-sm">Reject</button>
                        </form>
                    @endif
                @elseif($objective->status === 'approved' && auth()->user()->canManageObjectiveFrequency())
                    <form method="POST" action="{{ route('objectives.frequency.request', $objective) }}" class="mt-3">@csrf
                        <label for="new-frequency">Request reporting frequency change</label>
                        <select id="new-frequency" name="reporting_frequency" class="form-control mb-2">@foreach($frequencyLabels as $value => $label)<option value="{{ $value }}" @selected(($objective->reporting_frequency ?? 'inherit') === $value)>{{ $label }}</option>@endforeach</select>
                        <label for="frequency-reason">Change reason</label><textarea id="frequency-reason" name="change_reason" required minlength="5" maxlength="2000" class="form-control mb-2">{{ old('change_reason') }}</textarea>
                        @error('change_reason')<div class="text-danger">{{ $message }}</div>@enderror
                        <button class="btn btn-primary btn-sm">Send for reapproval</button>
                    </form>
                @endif
            </div>
            @php
                $fieldLabels = [
                    'code' => 'Code', 'kpi_code' => 'KPI code', 'description' => 'Description', 'reporting_frequency' => 'Reporting Frequency',
                    'weight' => 'Weight', 'unit' => 'Unit', 'baseline_value' => 'Baseline value',
                    'baseline_year' => 'Baseline year', 'annual_target' => 'Annual target',
                    'target_distribution' => 'Target distribution', 'weekly_plan_mode' => 'Weekly plan mode',
                    'target_year' => 'Target year', 'parent_id' => 'Main objective',
                    'performers' => 'Performing departments', 'lead_department_id' => 'Lead department',
                    'is_active' => 'Active status', 'summary_type' => 'Summary type',
                    'data_source' => 'Data source', 'goal_direction' => 'Goal direction',
                    'is_reference_only' => 'Reference-only status',
                    'is_calculated_weight' => 'Calculated weight status',
                    'include_in_parent_rollup' => 'Main-objective roll-up inclusion',
                ];
                $formatRevisionValue = static function ($value) {
                    if (is_bool($value)) return $value ? 'Yes' : 'No';
                    if (is_array($value)) return count($value) ? implode(', ', $value) : 'None';
                    if ($value === null || $value === '') return 'Not set';
                    return (string) $value;
                };
            @endphp

            @if($objective->last_change_reason || $revisionHistory->isNotEmpty())
                <div class="card border-warning shadow-sm">
                    <div class="card-header bg-warning text-dark d-flex align-items-center justify-content-between">
                        <h3 class="card-title font-weight-bold mb-0">
                            <i class="fas fa-history mr-2"></i>Reviewer Changes &amp; Comments
                        </h3>
                        <span class="badge badge-dark">Revision {{ $objective->revision_number }}</span>
                    </div>
                    <div class="card-body">
                        @if($objective->last_change_reason)
                            <div class="alert alert-warning mb-3">
                                <div class="font-weight-bold mb-1">Latest reviewer comment</div>
                                <div>{{ $objective->last_change_reason }}</div>
                                <small class="d-block mt-2 text-muted">
                                    Changed by {{ optional($objective->lastEditor)->name ?? 'Reviewer' }}
                                    @if($objective->last_edited_at)
                                        on {{ $objective->last_edited_at->format('d M Y, h:i A') }}
                                    @endif
                                    @if($objective->last_edited_stage)
                                        during {{ str_replace('_', ' ', $objective->last_edited_stage) }}
                                    @endif
                                </small>
                            </div>
                        @endif

                        @forelse($revisionHistory as $revisionLog)
                            @php
                                $revision = $revisionLog->revision_details;
                                $changes = $revision['changes'] ?? [];
                            @endphp
                            <div class="border rounded mb-3 overflow-hidden">
                                <div class="bg-light px-3 py-2 d-flex flex-wrap justify-content-between">
                                    <strong>
                                        Revision {{ $revision['revision'] ?? '?' }} —
                                        {{ $revision['editor']['name'] ?? optional($revisionLog->user)->name ?? 'Reviewer' }}
                                    </strong>
                                    <small class="text-muted">{{ $revisionLog->created_at->format('d M Y, h:i A') }}</small>
                                </div>
                                <div class="p-3">
                                    @if(!empty($revision['reason']))
                                        <p class="mb-3"><strong>Comment:</strong> {{ $revision['reason'] }}</p>
                                    @endif
                                    @if(count($changes))
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="thead-light">
                                                    <tr><th>Field changed</th><th>Previous value</th><th>New value</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($changes as $field => $change)
                                                        <tr>
                                                            <th>{{ $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field)) }}</th>
                                                            <td class="text-danger">{{ $formatRevisionValue($change['before'] ?? null) }}</td>
                                                            <td class="text-success font-weight-bold">{{ $formatRevisionValue($change['after'] ?? null) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <span class="text-muted">No tracked field values were changed.</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No detailed revision history is available yet.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Objective overview</h3>
                </div>
                <div class="card-body">
                    @include('objectives.partials.detail-summary')
                </div>
                <div class="card-footer">
                    @php
                        $objectiveEditor = auth()->user();
                        $canEditObjective = $objectiveEditor->isSystemAdmin()
                            || ($objectiveEditor->hasPermission('manage_objectives') && in_array($objective->status, ['draft', 'returned'], true))
                            || ($objectiveEditor->isPlanReportDirectorate() && $objective->status === 'submitted_to_plan_report_directorate')
                            || ($objectiveEditor->isPlanReportHeadOffice() && $objective->status === 'submitted');
                    @endphp
                    @can('manage_objectives')
                        @if(in_array($objective->status, ['draft', 'returned'], true))
                            <a href="{{ route('objectives.create', ['parent_id' => $objective->id]) }}" class="btn btn-primary">Add Sub-objective</a>
                        @endif
                    @endcan
                    @if($canEditObjective)
                        <a href="{{ route('objectives.edit', $objective->id) }}" class="btn btn-warning">Edit</a>
                    @endif
                    @can('manage_objectives')
                    @if(in_array($objective->status, ['draft', 'returned'], true))
                    <form action="{{ route('objectives.destroy', $objective->id) }}" method="POST" class="d-inline"
                        data-confirm="Delete this objective? This action cannot be undone." data-confirm-title="Delete objective" data-confirm-text="Delete" data-confirm-class="btn-danger">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                    @endif
                    @endcan
                </div>
            </div>

            @if($objective->children->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Direct sub-objectives and activities</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Description</th>
                                    <th>Weight</th>
                                    <th>Annual target</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($objective->children as $child)
                                    <tr>
                                        <td><a href="{{ route('objectives.show', $child->id) }}">{{ $child->code }}</a></td>
                                        <td>{{ $child->description }}</td>
                                        <td>{{ \App\Support\NumberFormatter::format((float) $child->weight,3) }}%</td>
                                        <td>{{ $child->annual_target === null ? 'Not set' : \App\Support\NumberFormatter::format((float) $child->annual_target,3) }} @if($child->annual_target !== null){{ $child->unit }}@endif</td><td>@include('objectives.partials.status', ['objective' => $child])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
