@extends('layouts.app')

@section('title', 'Department Performance Scorecard')

@section('content')
<div class="content-header p-2">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded border shadow-sm mb-4">
            <div>
                <h4 class="m-0 font-weight-bold text-dark">
                    🏆 Executive Performance Leaderboard
                </h4>
                <p class="text-muted small m-0">Real-time ranking of departments based on weighted monthly performance.</p>
            </div>
            <div class="text-right d-flex align-items-center">
                <form action="{{ route('scorecard.leaderboard') }}" method="GET" class="form-inline">
                    <select name="month" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        @foreach($months as $m)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                    <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                        @for($y = 2017; $y <= 2026; $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </form>
            </div>
        </div>

        {{-- Podium Section --}}
        <div class="row mb-5 justify-content-center align-items-end podium-container">
            @php 
                $top3 = array_slice($leaderboard, 0, 3);
                // Reorder for visual podium: 2nd, 1st, 3rd
                $podium = [];
                if (isset($top3[1])) $podium[] = $top3[1]; // Silver
                if (isset($top3[0])) $podium[] = $top3[0]; // Gold
                if (isset($top3[2])) $podium[] = $top3[2]; // Bronze
            @endphp

            @foreach($podium as $entry)
                <div class="col-md-3 text-center px-2">
                    <div class="podium-card {{ $entry['rank'] == 1 ? 'gold' : ($entry['rank'] == 2 ? 'silver' : 'bronze') }} shadow-lg">
                        <div class="rank-badge">{{ $entry['rank'] }}</div>
                        <div class="dept-avatar bg-white rounded-circle shadow-sm mx-auto mb-3">
                            <i class="fas fa-building text-{{ $entry['rank'] == 1 ? 'warning' : 'secondary' }} h2 mt-3"></i>
                        </div>
                        <h6 class="font-weight-bold mb-1">{{ $entry['department']->name }}</h6>
                        <div class="score-display h3 font-weight-bold mb-0">
                            {{ number_format($entry['score'], 1) }}%
                        </div>
                        <div class="trend-indicator mt-2">
                            @if($entry['trend'] == 'up')
                                <span class="badge badge-success px-2 rounded-pill"><i class="fas fa-arrow-up mr-1"></i> UP</span>
                            @elseif($entry['trend'] == 'down')
                                <span class="badge badge-danger px-2 rounded-pill"><i class="fas fa-arrow-down mr-1"></i> DOWN</span>
                            @else
                                <span class="badge badge-light px-2 rounded-pill border">STABLE</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Detailed Leaderboard --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="m-0 font-weight-bold text-muted">
                    <i class="fas fa-list-ol mr-2"></i> All Departments Ranking
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0" id="leaderboardTable">
                        <thead class="bg-light small font-weight-bold">
                            <tr>
                                <th width="80" class="text-center">RANK</th>
                                <th class="pl-4">DEPARTMENT</th>
                                <th class="text-center">CORE OBJECTIVES</th>
                                <th class="text-center">AGGREGATED SCORE</th>
                                <th class="text-center">TREND</th>
                                <th class="text-right pr-4">DETAILS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($leaderboard as $entry)
                                <tr class="{{ $entry['is_top'] ? 'bg-light-info' : '' }}">
                                    <td class="text-center align-middle">
                                        <span class="rank-circle {{ $entry['rank'] <= 3 ? 'top-rank' : '' }}">
                                            {{ $entry['rank'] }}
                                        </span>
                                    </td>
                                    <td class="pl-4 align-middle font-weight-bold">
                                        {{ $entry['department']->name }}
                                        @if($entry['rank'] == 1)
                                            <i class="fas fa-crown text-warning ml-1"></i>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-light px-3 border rounded-pill small">{{ $entry['objective_count'] }} Active Objectives</span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="d-inline-block">
                                            <div class="h5 m-0 font-weight-bold {{ $entry['score'] >= 90 ? 'text-success' : ($entry['score'] >= 75 ? 'text-primary' : 'text-danger') }}">
                                                {{ number_format($entry['score'], 2) }}%
                                            </div>
                                            <div class="progress progress-xxs mt-1" style="width: 80px;">
                                                <div class="progress-bar {{ $entry['score'] >= 90 ? 'bg-success' : ($entry['score'] >= 75 ? 'bg-primary' : 'bg-danger') }}" 
                                                     style="width: {{ $entry['score'] }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        @if($entry['trend'] == 'up')
                                            <i class="fas fa-chevron-up text-success h5 m-0"></i>
                                        @elseif($entry['trend'] == 'down')
                                            <i class="fas fa-chevron-down text-danger h5 m-0"></i>
                                        @else
                                            <i class="fas fa-minus text-muted h5 m-0"></i>
                                        @endif
                                    </td>
                                    <td class="text-right pr-4 align-middle">
                                        <a href="{{ route('monthly.report', [$year, $month]) }}?department_id={{ $entry['department']->id }}" 
                                           class="btn btn-outline-secondary btn-sm px-3 rounded-pill hover-primary">
                                            Open Report <i class="fas fa-external-link-alt ml-1 small"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .podium-card { background: white; border-radius: 15px; padding: 30px 20px; position: relative; transition: all 0.3s; }
    .podium-card:hover { transform: translateY(-10px); }
    .podium-card.gold { border-top: 5px solid #FFD700; height: 320px; z-index: 10; transform: scale(1.1); margin-bottom: 20px; }
    .podium-card.gold .rank-badge { background: #FFD700; }
    .podium-card.silver { border-top: 5px solid #C0C0C0; height: 280px; }
    .podium-card.silver .rank-badge { background: #C0C0C0; }
    .podium-card.bronze { border-top: 5px solid #CD7F32; height: 250px; }
    .podium-card.bronze .rank-badge { background: #CD7F32; }
    
    .rank-badge { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px; border-radius: 50%; color: white; line-height: 30px; font-weight: bold; }
    .dept-avatar { width: 70px; height: 70px; }
    
    .rank-circle { width: 35px; height: 35px; border-radius: 50%; background: #f8f9fa; display: inline-block; line-height: 35px; font-weight: bold; color: #6c757d; border: 1px solid #dee2e6; }
    .rank-circle.top-rank { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
    
    .bg-light-info { background-color: #f0f7ff !important; }
    .hover-primary:hover { background-color: #007bff; color: white; border-color: #007bff; }
    
    .progress-xxs { height: 4px; border-radius: 2px; }
    .podium-container { margin-top: 20px; }
</style>
@endpush
@endsection
