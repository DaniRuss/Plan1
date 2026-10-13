@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Plan Details: {{ $plan->title }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('plans.index') }}">Plans</a></li>
                        <li class="breadcrumb-item active">View</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Status Bar -->
            <div class="callout callout-info">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Status:</strong>
                        @if($plan->status == 'draft') <span class="badge badge-secondary">Draft</span>
                        @elseif($plan->status == 'submitted') <span class="badge badge-info">Submitted</span>
                        @elseif($plan->status == 'approved') <span class="badge badge-success">Approved</span>
                        @elseif($plan->status == 'rejected') <span class="badge badge-danger">Rejected</span>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>Period:</strong> {{ $plan->year }} EFY ({{ ucfirst($plan->type) }})
                    </div>
                    <div class="col-md-3">
                        <strong>Department:</strong> {{ $plan->department->name }}
                    </div>
                    <div class="col-md-3 text-right">
                        @if($plan->status === 'approved')
                            <a href="{{ route('plans.download', $plan) }}" class="btn btn-danger btn-sm mb-1"><i class="fas fa-file-pdf"></i> Download EDR Plan</a>
                        @endif
                        @can('edit_plan', $plan)
                        <button type="submit" form="plan-form" class="btn btn-primary btn-sm mb-1">
                            <i class="fas fa-save"></i> 
                            @if($plan->status == 'draft' || $plan->status == 'rejected') Save Draft @else Update Targets @endif
                        </button>
                        @endcan

                        @if($plan->status == 'draft' || $plan->status == 'rejected')
                            <form action="{{ route('plans.submit', $plan->id) }}" method="POST" class="d-inline mb-1"
                                data-confirm="Submit this plan? No changes are allowed after submission." data-confirm-title="Submit plan" data-confirm-text="Submit">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-paper-plane"></i> Submit
                                </button>
                            </form>
                        @endif

                        @if($plan->status == 'submitted')
                            @can('approve_plan')
                                <form action="{{ route('plans.approve', $plan->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#rejectPlanModal">
                                    <i class="fas fa-undo"></i> Return
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>
                @if($plan->status == 'rejected' && $plan->rejection_comment)
                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="alert alert-warning mb-0">
                                <strong><i class="fas fa-exclamation-triangle mr-1"></i> Return Reason:</strong>
                                {{ $plan->rejection_comment }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Planning Grid</h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <form id="plan-form" action="{{ route('plans.updateItems', $plan->id) }}" method="POST">
                                @csrf
                                <table class="table table-bordered table-head-fixed text-nowrap table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 150px">Code</th>
                                            <th style="width: 400px">Objective / Activity</th>
                                            <th>Weight</th>
                                            <th>Unit</th>
                                            <th style="width: 150px">Performers</th>
                                            <th>Annual Target</th>
                                            <!-- Q1 -->
                                            <th class="bg-primary-light text-center" style="width: 70px">Jul</th>
                                            <th class="bg-primary-light text-center" style="width: 70px">Aug</th>
                                            <th class="bg-primary-light text-center" style="width: 70px">Sep</th>
                                            <th class="bg-primary text-center font-weight-bold text-white" style="width: 70px">Q1</th>

                                            <!-- Q2 -->
                                            <th class="bg-info-light text-center" style="width: 70px">Oct</th>
                                            <th class="bg-info-light text-center" style="width: 70px">Nov</th>
                                            <th class="bg-info-light text-center" style="width: 70px">Dec</th>
                                            <th class="bg-info text-center font-weight-bold text-white" style="width: 70px">Q2</th>

                                            <!-- Q3 -->
                                            <th class="bg-warning-light text-center" style="width: 70px">Jan</th>
                                            <th class="bg-warning-light text-center" style="width: 70px">Feb</th>
                                            <th class="bg-warning-light text-center" style="width: 70px">Mar</th>
                                            <th class="bg-warning text-center font-weight-bold text-white" style="width: 70px">Q3</th>

                                            <!-- Q4 -->
                                            <th class="bg-danger-light text-center" style="width: 70px">Apr</th>
                                            <th class="bg-danger-light text-center" style="width: 70px">May</th>
                                            <th class="bg-danger-light text-center" style="width: 70px">Jun</th>
                                            <th class="bg-danger text-center font-weight-bold text-white" style="width: 70px">Q4</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($objectives as $objective)
                                            @include('plans.partials.item_row', ['objective' => $objective, 'padding' => 0])
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center">No strategic objectives found to plan
                                                    against.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('styles')
        <style>
            .bg-primary-light { background-color: #e3f2fd !important; }
            .bg-info-light { background-color: #e0f7fa !important; }
            .bg-warning-light { background-color: #fff8e1 !important; }
            .bg-danger-light { background-color: #fce4ec !important; }
            .plan-input { 
                width: 100%; 
                min-width: 60px;
                border: 1px solid #ced4da; 
                padding: 4px; 
                font-size: 0.85rem; 
                text-align: right; 
                background: transparent;
            }
            .plan-input:focus { border-color: #80bdff; outline: 0; background: #fff; }
            .q-total { border: none; font-weight: bold; cursor: not-allowed; }
            .form-control:disabled, .form-control[readonly] { background-color: transparent !important; opacity: 1; }
        </style>
    @endpush

    @push('scripts')
        <script>
            // Auto-fill the quarter field with the last-month value
            function autoFillQuarter(monthInput, quarterId) {
                var val = monthInput.value;
                var qField = document.getElementById(quarterId);
                if (qField) {
                    qField.value = val;
                }
            }
        </script>
    @endpush

    <!-- Reject Plan Modal -->
    <div class="modal fade" id="rejectPlanModal" tabindex="-1" role="dialog" aria-labelledby="rejectPlanModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{ route('plans.reject', $plan->id) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="rejectPlanModalLabel">Return Plan for Correction</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="rejection_comment" class="required">Please provide a reason for returning this plan:</label>
                            <textarea name="rejection_comment" id="rejection_comment" class="form-control" rows="4" required placeholder="Describe what needs to be fixed..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Confirm Return</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
