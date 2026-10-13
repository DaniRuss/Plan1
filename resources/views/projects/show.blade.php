@extends('layouts.app')

@section('title', 'Project Dashboard: ' . $project->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Project Tracking</a></li>
    <li class="breadcrumb-item active">Project Details</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Project Summary Card -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold"><i class="fas fa-info-circle mr-2"></i> Project Info</h5>
                    <a href="{{ route('projects.edit', $project) }}" class="btn btn-sm btn-light rounded-pill">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
                <div class="card-body">
                    <h4 class="text-primary font-weight-bold mb-3">{{ $project->name }}</h4>
                    <p class="text-muted mb-4">{{ $project->description ?: 'No description provided.' }}</p>

                    <div class="mb-3">
                        <label class="small font-weight-bold text-uppercase text-muted d-block">Department</label>
                        <span class="badge badge-info px-3 py-2 rounded-pill">{{ $project->department->name }}</span>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="small font-weight-bold text-uppercase text-muted d-block">Start Date</label>
                            <span>{{ $project->start_date ? $project->start_date->format('M d, Y') : 'N/A' }}</span>
                        </div>
                        <div class="col-6">
                            <label class="small font-weight-bold text-uppercase text-muted d-block">End Date</label>
                            <span>{{ $project->end_date ? $project->end_date->format('M d, Y') : 'N/A' }}</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small font-weight-bold text-uppercase text-muted d-block">Total Budget</label>
                        <h5 class="font-weight-bold">{{ number_format($project->total_budget, 2) }} {{ $project->currency }}</h5>
                    </div>

                    <div class="mb-0">
                        <label class="small font-weight-bold text-uppercase text-muted d-block">Status</label>
                        @php
                            $statusColors = [
                                'planned' => 'secondary',
                                'in_progress' => 'primary',
                                'completed' => 'success',
                                'on_hold' => 'warning',
                                'delayed' => 'danger'
                            ];
                            $color = $statusColors[$project->status] ?? 'secondary';
                        @endphp
                        <span class="badge badge-{{ $color }} px-3 py-2 rounded-pill text-capitalize">
                            {{ str_replace('_', ' ', $project->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Progress Card -->
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-4">
                    <label class="small font-weight-bold text-uppercase text-muted d-block mb-3">Overall Completion</label>
                    <div class="progress mb-3" style="height: 25px; border-radius: 15px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                             role="progressbar" style="width: {{ $project->completion_percentage }}%;" 
                             aria-valuenow="{{ $project->completion_percentage }}" aria-valuemin="0" aria-valuemax="100">
                            {{ $project->completion_percentage }}%
                        </div>
                    </div>
                    <p class="small text-muted mb-0">
                        @if($project->milestones->count() > 0)
                            Based on {{ $project->milestones->count() }} weighted milestones.
                        @else
                            Manually set project progress.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Milestones Area -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-tasks mr-2 text-primary"></i> Project Milestones</h5>
                    <button class="btn btn-primary btn-sm rounded-pill px-3" data-toggle="modal" data-target="#addMilestoneModal">
                        <i class="fas fa-plus"></i> Add Milestone
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 pl-4">Milestone</th>
                                    <th class="border-0">Due Date</th>
                                    <th class="border-0">Weight</th>
                                    <th class="border-0">Progress</th>
                                    <th class="border-0">Status</th>
                                    <th class="border-0 pr-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($project->milestones as $milestone)
                                    <tr>
                                        <td class="pl-4">
                                            <div class="font-weight-bold">{{ $milestone->title }}</div>
                                            <small class="text-muted d-block">{{ Str::limit($milestone->description, 50) }}</small>
                                        </td>
                                        <td>{{ $milestone->due_date->format('M d, Y') }}</td>
                                        <td>{{ $milestone->weight }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 mr-2" style="height: 6px;">
                                                    <div class="progress-bar bg-info" style="width: {{ $milestone->completion_percentage }}%"></div>
                                                </div>
                                                <small>{{ $milestone->completion_percentage }}%</small>
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $mStatusColors = [
                                                    'pending' => 'secondary',
                                                    'in_progress' => 'info',
                                                    'completed' => 'success',
                                                    'delayed' => 'danger'
                                                ];
                                                $mColor = $mStatusColors[$milestone->status] ?? 'secondary';
                                            @endphp
                                            <span class="badge badge-pill badge-{{ $mColor }}">{{ ucfirst(str_replace('_', ' ', $milestone->status)) }}</span>
                                        </td>
                                        <td class="pr-4 text-right">
                                            <button class="btn btn-sm btn-outline-primary rounded-circle edit-milestone" 
                                                    data-id="{{ $milestone->id }}"
                                                    data-title="{{ $milestone->title }}"
                                                    data-desc="{{ $milestone->description }}"
                                                    data-date="{{ $milestone->due_date->format('Y-m-d') }}"
                                                    data-progress="{{ $milestone->completion_percentage }}"
                                                    data-status="{{ $milestone->status }}"
                                                    data-weight="{{ $milestone->weight }}"
                                                    data-toggle="modal" data-target="#editMilestoneModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="{{ route('milestones.destroy', $milestone) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" data-confirm="Delete this milestone?" data-confirm-title="Delete milestone" data-confirm-text="Delete" data-confirm-class="btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-stream fa-3x mb-3 d-block"></i>
                                                No milestones registered for this project yet.
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Milestone Modal -->
<div class="modal fade" id="addMilestoneModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('projects.milestones.store', $project) }}" method="POST">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold">Add Project Milestone</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Milestone Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Due Date</label>
                                <input type="date" name="due_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Weight (Relative Importance)</label>
                                <input type="number" name="weight" step="0.1" class="form-control" value="1.0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="delayed">Delayed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Completion %</label>
                                <input type="number" name="completion_percentage" class="form-control" value="0" min="0" max="100" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Create Milestone</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Milestone Modal -->
<div class="modal fade" id="editMilestoneModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="editMilestoneForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-navy text-white">
                    <h5 class="modal-title font-weight-bold">Edit Milestone</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Milestone Title</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Description</label>
                        <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Due Date</label>
                                <input type="date" name="due_date" id="edit_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Weight</label>
                                <input type="number" name="weight" id="edit_weight" step="0.1" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Status</label>
                                <select name="status" id="edit_status" class="form-control" required>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="delayed">Delayed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Completion %</label>
                                <input type="number" name="completion_percentage" id="edit_progress" class="form-control" min="0" max="100" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy rounded-pill px-4">Update Milestone</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('styles')
<style>
    .bg-navy { background-color: #001f3f; }
    .btn-outline-primary:hover { color: white; }
    .progress { background-color: #e9ecef; }
    .card { transition: transform 0.2s; }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        $('.edit-milestone').click(function() {
            const id = $(this).data('id');
            const title = $(this).data('title');
            const desc = $(this).data('desc');
            const date = $(this).data('date');
            const progress = $(this).data('progress');
            const status = $(this).data('status');
            const weight = $(this).data('weight');

            $('#editMilestoneForm').attr('action', '/milestones/' + id);
            $('#edit_title').val(title);
            $('#edit_desc').val(desc);
            $('#edit_date').val(date);
            $('#edit_progress').val(progress);
            $('#edit_status').val(status);
            $('#edit_weight').val(weight);
        });
    });
</script>
@endpush
