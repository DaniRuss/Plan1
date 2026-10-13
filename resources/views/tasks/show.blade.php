@extends('layouts.app')

@section('title', 'Task Details')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <!-- Task Information -->
            <div class="col-md-4">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title">General Info</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm">
                            <tr>
                                <th class="pl-3">Status</th>
                                <td>
                                    <span
                                        class="badge badge-{{ $task->status == 'completed' ? 'success' : ($task->status == 'in_progress' ? 'info' : 'secondary') }}">
                                        {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="pl-3">Completion</th>
                                <td>
                                    <div class="progress progress-xs mt-2">
                                        <div class="progress-bar bg-primary"
                                            style="width: {{ $task->completion_percentage }}%"></div>
                                    </div>
                                    <small>{{ number_format($task->completion_percentage, 1) }}%</small>
                                </td>
                            </tr>
                            <tr>
                                <th class="pl-3">Assigned To</th>
                                <td>{{ $task->assignedTo->first_name }} {{ $task->assignedTo->last_name }}</td>
                            </tr>
                            <tr>
                                <th class="pl-3">Assigned By</th>
                                <td>{{ $task->assignedBy->first_name ?? 'System' }} {{ $task->assignedBy->last_name ?? '' }}
                                </td>
                            </tr>
                            <tr>
                                <th class="pl-3">Due Date</th>
                                <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('M d, Y') : 'N/A' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">Strategic Alignment</h3>
                    </div>
                    <div class="card-body">
                        <strong><i class="fas fa-bullseye mr-1"></i> Objective</strong>
                        <p class="text-muted">{{ $task->planItem->strategicObjective->title }}</p>
                        <hr>
                        <strong><i class="fas fa-calendar mr-1"></i> Plan Year</strong>
                        <p class="text-muted">{{ $task->planItem->plan->year }}</p>
                    </div>
                </div>
            </div>

            <!-- Task Content & Reports -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex p-0">
                        <h3 class="card-title p-3">{{ $task->title }}</h3>
                        <ul class="nav nav-pills ml-auto p-2">
                            <li class="nav-item"><a class="nav-link active" href="#details"
                                    data-toggle="tab">Description</a></li>
                            <li class="nav-item"><a class="nav-link" href="#timeline" data-toggle="tab">Reports &
                                    Evidence</a></li>
                            @if(auth()->user()->employee_id == $task->assigned_to && $task->status != 'completed')
                                <li class="nav-item"><a class="nav-link bg-success ml-2" href="#submit-report"
                                        data-toggle="tab">Submit Progress</a></li>
                            @endif
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Description Tab -->
                            <div class="tab-pane active" id="details">
                                <p class="lead">{{ $task->description ?: 'No description provided.' }}</p>
                            </div>

                            <!-- Timeline Tab -->
                            <div class="tab-pane" id="timeline">
                                <div class="timeline timeline-inverse">
                                    @forelse($task->reports()->latest()->get() as $report)
                                        <div>
                                            <i
                                                class="fas {{ $report->status == 'approved' ? 'fa-check bg-success' : ($report->status == 'rejected' ? 'fa-times bg-danger' : 'fa-clock bg-warning') }}"></i>
                                            <div class="timeline-item">
                                                <span class="time"><i class="far fa-clock"></i>
                                                    {{ $report->created_at->diffForHumans() }}</span>
                                                <h3 class="timeline-header">
                                                    <strong>{{ $report->employee->first_name }}</strong> reported
                                                    {{ $report->progress_percentage }}% progress
                                                    for <strong>{{ $report->reporting_period }}</strong>
                                                </h3>
                                                <div class="timeline-body">
                                                    <p><strong>Narrative:</strong> {{ $report->narrative }}</p>
                                                    @if($report->challenges)
                                                        <p><strong>Challenges:</strong> {{ $report->challenges }}</p>
                                                    @endif

                                                    <div class="mt-3">
                                                        <strong>Evidence:</strong><br>
                                                        @foreach($report->evidence as $evidence)
                                                            <a href="{{ Storage::url($evidence->file_path) }}" target="_blank"
                                                                class="btn btn-xs btn-outline-info mr-2">
                                                                <i class="fas fa-paperclip"></i> {{ $evidence->file_name }}
                                                            </a>
                                                        @endforeach
                                                    </div>

                                                    @if($report->status == 'approved' && $report->review_comments)
                                                        <div class="alert alert-light mt-3 py-2">
                                                            <strong>Supervisor Feedback:</strong><br>
                                                            {{ $report->review_comments }}
                                                        </div>
                                                    @endif
                                                </div>

                                                @if($report->status == 'submitted' && (auth()->user()->hasRole('dept_head') || auth()->user()->hasRole('supervisor')))
                                                    <div class="timeline-footer">
                                                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal"
                                                            data-target="#approveModal{{ $report->id }}">
                                                            Approve
                                                        </button>
                                                    </div>

                                                    <!-- Approval Modal -->
                                                    <div class="modal fade" id="approveModal{{ $report->id }}" tabindex="-1"
                                                        role="dialog">
                                                        <div class="modal-dialog" role="document">
                                                            <form action="{{ route('tasks.report.approve', $report) }}"
                                                                method="POST">
                                                                @csrf
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Approve Progress Report</h5>
                                                                        <button type="button" class="close"
                                                                            data-dismiss="modal"><span>&times;</span></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p>You are approving
                                                                            <strong>{{ $report->progress_percentage }}%</strong>
                                                                            completion for this task.</p>
                                                                        <div class="form-group">
                                                                            <label>Comments (Optional)</label>
                                                                            <textarea name="review_comments" class="form-control"
                                                                                rows="3"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-success">Approve &
                                                                            Update Progress</button>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div>
                                            <i class="fas fa-info bg-gray"></i>
                                            <div class="timeline-item">
                                                <h3 class="timeline-header no-border">No reports submitted yet.</h3>
                                            </div>
                                        </div>
                                    @endforelse
                                    <div>
                                        <i class="far fa-clock bg-gray"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Report Tab -->
                            @if(auth()->user()->employee_id == $task->assigned_to && $task->status != 'completed')
                                <div class="tab-pane" id="submit-report">
                                    <form action="{{ route('tasks.report', $task) }}" method="POST"
                                        enctype="multipart/form-data">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="reporting_period">Reporting Period</label>
                                                    <select name="reporting_period" class="form-control" required>
                                                        <option value="july">July</option>
                                                        <option value="august">August</option>
                                                        <option value="september">September</option>
                                                        <option value="october">October</option>
                                                        <option value="november">November</option>
                                                        <option value="december">December</option>
                                                        <option value="january">January</option>
                                                        <option value="february">February</option>
                                                        <option value="march">March</option>
                                                        <option value="april">April</option>
                                                        <option value="may">May</option>
                                                        <option value="june">June</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="progress_percentage">Current Completion %</label>
                                                    <input type="number" name="progress_percentage" class="form-control"
                                                        min="{{ $task->completion_percentage }}" max="100"
                                                        value="{{ $task->completion_percentage }}" required>
                                                    <small class="text-muted">Must be greater than previous progress
                                                        ({{ $task->completion_percentage }}%)</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="narrative">Accomplishments / Narrative</label>
                                            <textarea name="narrative" class="form-control" rows="3" required
                                                placeholder="What have you completed?"></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label for="challenges">Challenges / Roadblocks (Optional)</label>
                                            <textarea name="challenges" class="form-control" rows="2"></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label for="evidence">Evidence Files (Mandatory)</label>
                                            <div class="custom-file">
                                                <input type="file" name="evidence[]" class="custom-file-input" id="evidence"
                                                    multiple required>
                                                <label class="custom-file-label" for="evidence">Choose files...</label>
                                            </div>
                                            <small class="text-muted">Upload copies of documents, photos, or spreadsheets
                                                proving completion.</small>
                                        </div>

                                        <div class="text-right">
                                            <button type="submit" class="btn btn-success px-4">Submit Report & Evidence</button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            // Handle file input label
            $(".custom-file-input").on("change", function () {
                var fileName = $(this).val().split("\\").pop();
                var files = $(this)[0].files;
                if (files.length > 1) {
                    $(this).siblings(".custom-file-label").addClass("selected").html(files.length + " files selected");
                } else {
                    $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
                }
            });
        });
    </script>
@endpush