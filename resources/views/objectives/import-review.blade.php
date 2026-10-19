@extends('layouts.app')
@section('page_header_hidden', '1')

@section('content')
<div class="content-header"><div class="container-fluid"><h1>Review objective import</h1></div></div>
<section class="content"><div class="container-fluid">
    <div class="alert alert-info">{{ $batch['filename'] }} | {{ $batch['target_year'] }} EFY | {{ count($batch['rows']) }} rows. This is a preview; no objectives have been saved. Confirmed rows become drafts.</div>
    @if($errors->any())
        <div class="alert alert-danger"><strong>Import was not saved.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if($duplicates->count())
        <div class="alert alert-warning">Duplicate source codes: {{ $duplicates->keys()->implode(', ') }}. Correct the codes below or skip those rows.</div>
    @endif
    <p>Existing departments are matched by name and preselected in workbook order; the first match is the lead. Review unmatched names and each row's KPI direction, weekly plan method, and accumulation rule.</p>
    <form method="POST" action="{{ route('objectives.import.commit', $token) }}">@csrf
        <div class="card">
            <div class="card-body p-0" style="overflow:auto;max-height:70vh">
                <table class="table table-sm table-bordered table-striped mb-0" style="min-width:1550px">
                    <thead class="thead-dark" style="position:sticky;top:0;z-index:2"><tr><th>Excel row</th><th>Action / Code</th><th>KPI / Activity</th><th>File performers / Departments</th><th>Weight</th><th>Unit</th><th>Baseline</th><th>Annual</th><th>Monthly / quarterly targets</th><th>Direction</th><th>Reporting Frequency</th><th>Weekly plan</th><th>Accumulation</th></tr></thead>
                    <tbody>
                    @foreach($batch['rows'] as $i => $row)
                        @php
                            $match = $performerMatches[$i];
                            $defaults = $importDefaults[$i];
                            $selectedPerformers = array_map('intval', old("rows.$i.performers", $defaults['performers']));
                            $leadDepartmentId = old("rows.$i.department_id", $defaults['lead']);
                            $displayWeight = old("rows.$i.weight", $row['weight'] ?? $defaults['calculated_weight'] ?? 0);
                        @endphp
                        <tr class="{{ $duplicates->has($row['code']) || $existing->has($row['code']) || !$row['code'] || $row['weight'] === null || $match['unmatched'] ? 'table-warning' : '' }}">
                            <td>{{ $row['source_row'] }}</td>
                            <td><select name="rows[{{ $i }}][action]" class="form-control form-control-sm mb-1"><option value="import">Import draft</option><option value="skip" @selected(old("rows.$i.action") === 'skip')>Skip</option></select><input name="rows[{{ $i }}][code]" class="form-control form-control-sm" value="{{ old("rows.$i.code", $row['code']) }}" style="min-width:75px">@if($existing->has($row['code']))<small class="text-danger">Already exists: {{ $existing[$row['code']]->status }}</small>@endif</td>
                            <td style="min-width:280px">{{ $row['description'] }}</td>
                            <td style="min-width:220px">
                                <small class="d-block">{{ $row['performer_text'] ?: 'Blank in workbook' }}</small>
                                <label class="small mb-0">Lead / accountable</label>
                                <select name="rows[{{ $i }}][department_id]" class="form-control form-control-sm"><option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) $leadDepartmentId === (string) $department->id)>{{ $department->name }}</option>@endforeach</select>
                                <label class="small mb-0">Performers (Ctrl-click for multiple)</label>
                                <select name="rows[{{ $i }}][performers][]" class="form-control form-control-sm" multiple size="3">@foreach($departments as $department)<option value="{{ $department->id }}" @selected(in_array((int) $department->id, $selectedPerformers, true))>{{ $department->name }}</option>@endforeach</select>
                                @if($match['unmatched'])
                                    <small class="text-danger d-block">Not matched: {{ implode(', ', $match['unmatched']) }}</small>
                                    <label class="small"><input type="checkbox" name="rows[{{ $i }}][confirm_unmatched]" value="1" @checked(old("rows.$i.confirm_unmatched"))> I reviewed these names</label>
                                @elseif($match['ids'])
                                    <small class="text-success">{{ count($match['ids']) }} department(s) matched</small>
                                @endif
                            </td>
                            <td><input name="rows[{{ $i }}][weight]" type="number" step="any" min="0" class="form-control form-control-sm" data-original-number="{{ $displayWeight }}" value="{{ \App\Support\NumberFormatter::format($displayWeight,3,'.','') }}" style="min-width:75px" @readonly($row['weight'] === null)>@if($row['weight'] === null && $defaults['has_children'])<small class="text-info d-block">Calculated from direct children</small>@elseif($row['weight'] === null)<small class="text-muted d-block">Unweighted detail; excluded from score</small>@endif</td>
                            <td>{{ $row['unit'] ?: '-' }}</td><td>{{ \App\Support\NumberFormatter::format($row['baseline_value'] ?? 'N/A') }}</td><td>{{ \App\Support\NumberFormatter::format($row['annual_target'] ?? 'N/A') }}</td>
                            <td style="min-width:220px"><details><summary>View targets</summary><div class="small">@foreach(\App\Services\ObjectiveWorkbookImport::PERIOD_COLUMNS as $field => $column){{ str_replace('_target','',$field) }}: {{ $row[$field] === null ? 'N/A' : $row[$field] }}<br>@endforeach</div></details></td>
                            <td><select name="rows[{{ $i }}][goal_direction]" class="form-control form-control-sm"><option value="increase">Higher is better</option><option value="decrease" @selected(old("rows.$i.goal_direction") === 'decrease')>Lower is better</option></select></td>
                            <td><select name="rows[{{ $i }}][reporting_frequency]" class="form-control form-control-sm">@foreach(['inherit' => 'Use department default', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)<option value="{{ $value }}" @selected((old("rows.$i.reporting_frequency", $row['reporting_frequency'] ?? 'inherit')) === $value)>{{ $label }}</option>@endforeach</select></td>
 <td><select name="rows[{{ $i }}][weekly_plan_mode]" class="form-control form-control-sm"><option value="distribute">Distribute</option><option value="repeat" @selected(old("rows.$i.weekly_plan_mode") === 'repeat')>Repeat</option></select></td>
                            <td><select name="rows[{{ $i }}][target_distribution]" class="form-control form-control-sm"><option value="checkpoint">Checkpoint</option><option value="sum" @selected(old("rows.$i.target_distribution") === 'sum')>Sum</option><option value="manual" @selected(old("rows.$i.target_distribution") === 'manual')>Manual</option></select></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer"><button class="btn btn-primary">Validate and save drafts</button><a href="{{ route('objectives.import.form') }}" class="btn btn-secondary ml-2">Cancel</a></div>
        </div>
    </form>
</div></section>
@endsection
