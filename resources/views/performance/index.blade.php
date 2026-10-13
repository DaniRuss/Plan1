@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Performance Tracking</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Performance</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- Tabs container -->
            <ul class="nav nav-tabs mb-3" id="performanceTypeTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $type === 'monthly' ? 'active' : '' }}" href="{{ route('performance.index', ['type' => 'monthly']) }}">
                        <i class="fas fa-calendar-alt mr-1"></i> Monthly Plans
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $type === 'weekly' ? 'active' : '' }}" href="{{ route('performance.index', ['type' => 'weekly']) }}">
                        <i class="fas fa-calendar-week mr-1"></i> Weekly Plans
                    </a>
                </li>
            </ul>

            <div class="card">
                <div class="card-header bg-white">
                    <h3 class="card-title">Select {{ ucfirst($type) }} Plan to Track Performance</h3>
                </div>
                <div class="card-body">
                    @if($plans->isEmpty())
                        <div class="alert alert-info border-0 shadow-sm">
                            <i class="fas fa-info-circle mr-1"></i> No approved {{ $type }} plans available for tracking.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Plan</th>
                                        <th>Department</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th style="width: 250px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($plans as $plan)
                                        <tr>
                                            <td class="align-middle font-weight-bold">{{ $plan->title }}</td>
                                            <td class="align-middle">{{ $plan->department->name }}</td>
                                            <td class="align-middle">{{ $plan->year }} EFY</td>
                                            <td class="align-middle">
                                                <span class="badge badge-success px-2 py-1">{{ ucfirst($plan->status) }}</span>
                                            </td>
                                            <td class="align-middle">
                                                @if($type === 'monthly')
                                                    <a href="{{ route('performance.edit', [$plan->id, 'july']) }}"
                                                        class="btn btn-sm btn-primary shadow-sm mr-1">
                                                        <i class="fas fa-edit mr-1"></i> Enter Data
                                                    </a>
                                                    <a href="{{ route('performance.report', [$plan->id, 'july']) }}"
                                                        class="btn btn-sm btn-info shadow-sm text-white">
                                                        <i class="fas fa-chart-bar mr-1"></i> Report
                                                    </a>
                                                @else
                                                    <!-- Weekly departments jump to the specific weekly dashboard for their entries -->
                                                    <a href="{{ route('performance.report', [$plan->id, 'july']) }}"
                                                        class="btn btn-sm btn-info shadow-sm text-white mr-1">
                                                        <i class="fas fa-chart-bar mr-1"></i> View Report
                                                    </a>
                                                    <a href="{{ route('weekly.dashboard', ['year' => $plan->year, 'department_id' => $plan->department_id]) }}"
                                                        class="btn btn-sm btn-secondary shadow-sm">
                                                        <i class="fas fa-external-link-alt mr-1"></i> Weekly Hub
                                                    </a>
                                                @endif
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
    </section>
@endsection