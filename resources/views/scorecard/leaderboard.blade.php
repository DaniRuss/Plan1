@extends('layouts.app')
@section('title', 'Department Performance Scorecard')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
        <div><h1 class="h3">Department Performance Scorecard</h1><p class="text-muted">Weighted monthly achievement from final reviewed results.</p></div>
        <form method="GET" action="{{ route('scorecard.leaderboard') }}" class="form-inline">
            <label for="scorecard-month" class="mr-2">Month</label><select id="scorecard-month" name="month" class="form-control mr-3" onchange="this.form.submit()">@foreach($months as $m)<option value="{{ $m }}" @selected($month === $m)>{{ ucfirst($m) }}</option>@endforeach</select>
            <label for="scorecard-year" class="mr-2">Plan year</label><select id="scorecard-year" name="year" class="form-control" onchange="this.form.submit()">@if(!$years->contains($year))<option value="{{ $year }}" selected>{{ $year }}</option>@endif @foreach($years as $y)<option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>@endforeach</select>
        </form>
    </div>
    <div class="alert alert-info">Scores are normalized to 100% using applicable KPI weights. Departments are ranked only when all planned scoring items have reviewed results and approved weights. Missing results are not zero scores. Equal scores share a rank; trends compare the previous fiscal month.</div>
    @php($ranked = collect($leaderboard)->whereNotNull('rank'))
    @if($ranked->isEmpty())<div class="card card-body mb-3"><strong>No departments are ready to rank for {{ ucfirst($month) }} {{ $year }}.</strong><span class="text-muted">Review the status and coverage below to see what is missing.</span></div>
    @else
    <div class="row mb-3">@foreach($ranked->take(3) as $entry)<div class="col-md-4 mb-2"><div class="card card-body border-top border-success h-100"><small class="text-muted">Rank {{ $entry['rank'] }}</small><h2 class="h5 mt-2">{{ $entry['department']->name }}</h2><strong class="h3 text-success">{{ \App\Support\NumberFormatter::format($entry['score'],3) }}%</strong></div></div>@endforeach</div>
    @endif
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="thead-light"><tr><th>Rank</th><th>Department</th><th>Plan items</th><th>Reviewed coverage</th><th>Score</th><th>Previous month</th><th>Trend</th><th>Details</th></tr></thead><tbody>
    @forelse($leaderboard as $entry)
    <tr><td>{{ $entry['rank'] ?? 'Not ranked' }}</td><th scope="row">{{ $entry['department']->name }}</th><td>{{ $entry['objective_count'] }}</td><td>{{ \App\Support\NumberFormatter::format($entry['coverage']) }}%<small class="d-block text-muted">{{ $entry['reviewed_count'] }} scoring items reviewed</small></td>
    <td><strong>{{ $entry['score'] === null ? 'N/A' : \App\Support\NumberFormatter::format($entry['score'],3).'%' }}</strong><small class="d-block text-muted">{{ $entry['state'] }}</small></td>
    <td>{{ $entry['previous_score'] === null ? 'N/A' : \App\Support\NumberFormatter::format($entry['previous_score'],3).'%' }}</td>
    <td>{{ ['up'=>'Improved','down'=>'Declined','stable'=>'Unchanged','unavailable'=>'No comparison'][$entry['trend']] }}</td>
    <td>@if($entry['objective_count'] > 0 && auth()->user()->hasPermission('view_performance'))
        @if($entry['department']->reporting_type === 'weekly' && auth()->user()->canAccessReportingModule('weekly'))<a class="btn btn-sm btn-outline-primary" href="{{ route('weekly.dashboard', ['year'=>$year,'department_id'=>$entry['department']->id]) }}">Weekly reports</a>
        @elseif($entry['department']->reporting_type === 'daily' && auth()->user()->canAccessReportingModule('daily'))<a class="btn btn-sm btn-outline-primary" href="{{ route('daily.dashboard', ['year'=>$year,'department_id'=>$entry['department']->id]) }}">Daily reports</a>
        @elseif(auth()->user()->canAccessReportingModule('monthly'))<a class="btn btn-sm btn-outline-primary" href="{{ route('monthly.report', [$year, $month, 'department_id'=>$entry['department']->id]) }}">Monthly report</a>
        @else<span class="text-muted">Not available</span>@endif
    @else<span class="text-muted">Not available</span>@endif</td></tr>
    @empty<tr><td colspan="8" class="text-muted text-center py-4">No active departments are available in your authorized scope.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
