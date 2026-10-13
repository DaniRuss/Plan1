@extends('layouts.app')

@section('title', 'Data Validation & Quality Sentinel')

@section('content')
    <div class="content-header p-2">
        <div class="container-fluid">
            <div class="row align-items-center bg-white p-3 rounded border shadow-sm mb-4">
                <div class="col-md-6">
                    <h4 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-shield-alt text-danger mr-2"></i> Data Quality sentinel
                    </h4>
                    <p class="text-muted small m-0">Automated diagnostic system for the IPRPMS operational layer.</p>
                </div>
                <div class="col-md-6 text-right">
                    <form action="{{ route('quality.console') }}" method="GET" class="form-inline d-inline-block">
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

            <div class="row">
                {{-- Quality Metrics --}}
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 bg-primary text-white mb-4">
                        <div class="card-body text-center py-4">
                            <h6 class="text-uppercase small font-weight-bold opacity-75">Reporting Health Score</h6>
                            <div class="h1 font-weight-bold mb-0">{{ $stats['health_score'] }}%</div>
                            <div class="progress progress-xxs mt-2 bg-primary-dark">
                                <div class="progress-bar bg-white" style="width: {{ $stats['health_score'] }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body text-center py-4">
                            <h6 class="text-uppercase small font-weight-bold text-muted">Weekly Data Completion</h6>
                            <div class="h1 font-weight-bold mb-0 text-dark">{{ $stats['completion_rate'] }}%</div>
                            <div class="small text-success font-weight-bold mt-1">↗ 4% from last month</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body text-center py-4">
                            <h6 class="text-uppercase small font-weight-bold text-muted">Active Alerts</h6>
                            <div class="h1 font-weight-bold mb-0 text-danger">{{ count($alerts) }}</div>
                            <div class="small text-muted mt-1">Requires Immediate Attention</div>
                        </div>
                    </div>
                </div>

                {{-- Alerts List --}}
                <div class="col-md-12">
                    <div class="card shadow-sm border-0 overflow-hidden">
                        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-muted uppercase small">Active Diagnostic Alerts</h6>
                            <button class="btn btn-sm btn-outline-danger px-3 rounded-pill bold">
                                <i class="fas fa-paper-plane mr-1"></i> Notify All Departments
                            </button>
                        </div>
                        <div class="card-body p-0">
                            @if(empty($alerts))
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle text-success h1 mb-3"></i>
                                    <h5 class="font-weight-bold">Zero Quality Conflicts Found</h5>
                                    <p class="text-muted small">All operational data is consistent and complete.</p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover m-0">
                                        <thead class="bg-light small">
                                            <tr>
                                                <th width="100" class="pl-4">SEVERITY</th>
                                                <th width="150">CATEGORY</th>
                                                <th>DESCRIPTION</th>
                                                <th width="150" class="text-right pr-4">ACTION</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($alerts as $alert)
                                                <tr class="{{ $alert['type'] == 'critical' ? 'bg-light-danger' : '' }}">
                                                    <td class="pl-4 align-middle">
                                                        <span
                                                            class="badge badge-pill badge-{{ $alert['type'] == 'critical' ? 'danger' : 'warning' }} px-3">
                                                            {{ strtoupper($alert['type']) }}
                                                        </span>
                                                    </td>
                                                    <td class="align-middle font-weight-bold small text-muted">
                                                        {{ $alert['category'] }}
                                                    </td>
                                                    <td
                                                        class="align-middle border-left-{{ $alert['type'] == 'critical' ? 'danger' : 'warning' }} pl-3">
                                                        <div class="font-weight-bold text-dark">{{ $alert['message'] }}</div>
                                                    </td>
                                                    <td class="text-right pr-4 align-middle">
                                                        <button class="btn btn-xs btn-outline-dark px-3 rounded-pill">
                                                            Dismiss
                                                        </button>
                                                        <a href="{{ $alert['resolve_url'] }}"
                                                            class="btn btn-xs btn-primary px-3 rounded-pill ml-1">
                                                            Resolve
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            .bg-primary-dark {
                background-color: rgba(0, 0, 0, 0.1);
            }

            .progress-xxs {
                height: 6px;
                border-radius: 3px;
            }

            .bg-light-danger {
                background-color: #fff5f5 !important;
            }

            .border-left-danger {
                border-left: 4px solid #dc3545 !important;
            }

            .border-left-warning {
                border-left: 4px solid #ffc107 !important;
            }

            .btn-xs {
                padding: 4px 10px;
                font-size: 0.75rem;
                font-weight: bold;
            }
        </style>
    @endpush
@endsection