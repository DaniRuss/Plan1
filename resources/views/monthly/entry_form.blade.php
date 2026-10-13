@extends('layouts.app')

@section('title', 'Monthly Report Entry — ' . ucfirst($month) . ' ' . $year)

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Monthly Entry: {{ ucfirst($month) }} {{ $year }}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('monthly.dashboard') }}">Monthly</a></li>
                    <li class="breadcrumb-item active">Entry</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        @if($closure)
            <div class="alert alert-warning d-flex align-items-center">
                <i class="fas fa-lock fa-2x mr-3"></i>
                <div>
                    <strong>Month Locked.</strong> This period has been certified and is now read-only.
                    Certified on {{ $closure->certified_at->format('d M Y') }}.
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt mr-2 text-info"></i>
                    {{ $plan->department->name }} — {{ ucfirst($month) }} {{ $year }} Actuals
                </h3>
                <div class="card-tools">
                    <span class="badge badge-info">Monthly Reporting Department</span>
                </div>
            </div>

            <form action="{{ route('monthly.entry.store', [$year, $month]) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="department_id" value="{{ $deptId ?? $plan->department_id }}">
                <div class="card-body border-bottom bg-light">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div class="mb-2">
                            <div class="small text-muted">Workflow Status</div>
                            <span class="badge badge-{{ $workflowState['badge'] }} px-3 py-2">{{ $workflowState['label'] }}</span>
                            @if($workflowState['note'])
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-comment-dots mr-1"></i>{{ $workflowState['note'] }}
                                </div>
                            @endif
                        </div>
                        <div class="text-right">
                            @if($workflowActions['submit_to_directorate'])
                                <button type="submit" formaction="{{ route('monthly.workflow', ['year' => $year, 'month' => $month]) }}" name="action" value="submit_to_directorate" class="btn btn-success btn-sm">
                                    <i class="fas fa-paper-plane mr-1"></i> Submit to Directorate
                                </button>
                            @endif
                        </div>
                    </div>
                    @unless($canEditWorkflow)
                        <div class="alert alert-warning mb-0 mt-3 py-2">
                            This monthly entry is read-only for your current role. Use the review workflow actions to continue.
                        </div>
                    @endunless
                </div>
                <fieldset {{ (!$canEditWorkflow || $closure) ? 'disabled' : '' }}>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered table-sm mb-0" style="font-size: 0.9rem;">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:60px">Code</th>
                                <th>Objective / Activity</th>
                                <th style="width:70px">Unit</th>
                                <th style="width:110px">Month Target</th>
                                <th style="width:140px">Actual Value</th>
                                <th style="width:200px">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($planItems as $item)
                                @php
                                    $entry = $existing[$item->id] ?? null;
                                    $objective = $item->strategicObjective;
                                    $isCalculatedParent = $objective
                                        && $objective->children()->exists()
                                        && $objective->summary_type !== 'manual_override';
                                    $isPlanned = !is_null($item->$monthColumn);
                                    $displayTarget = $calculatedParentTargets[$item->id]
                                        ?? ($item->$monthColumn ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ $item->strategicObjective->code ?? 'N/A' }}</td>
                                    <td>{{ $item->strategicObjective->description ?? 'N/A' }}</td>
                                    <td>{{ $item->strategicObjective->unit ?? 'N/A' }}</td>
                                    <td class="text-right font-weight-bold">
                                        {{ $isPlanned || $isCalculatedParent ? number_format($displayTarget, 2) : 'N/A' }}
                                    </td>
                                    <td>
                                        <input type="number" step="any" min="0"
                                            name="entries[{{ $item->id }}][actual_value]"
                                            class="form-control form-control-sm text-right"
                                            value="{{ old('entries.' . $item->id . '.actual_value', $entry?->actual_value) }}"
                                            placeholder="{{ !$isPlanned ? 'Not planned this month' : ($isCalculatedParent ? 'Calculated from sub-objectives' : '') }}"
                                            {{ ($closure || $isCalculatedParent || !$isPlanned) ? 'readonly' : '' }}>
                                        @if($isCalculatedParent)
                                            <small class="text-muted">Calculated from sub-objectives</small>
                                        @elseif(!$isPlanned)
                                            <small class="text-muted">No performance entry required this month</small>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="text"
                                            name="entries[{{ $item->id }}][notes]"
                                            class="form-control form-control-sm"
                                            placeholder="Optional notes"
                                            value="{{ old('entries.' . $item->id . '.notes', $entry?->notes) }}"
                                            {{ $closure ? 'disabled' : '' }}>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted p-4">
                                        No plan items found for this period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Performance Remarks Table -->
                <div class="card-footer bg-light p-4 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="font-weight-bold mb-0 text-primary">
                            <i class="fas fa-comment-alt mr-1"></i> Department Performance Remarks (Optional)
                        </h5>
                        @if(!$closure)
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addRemarkRow">
                            <i class="fas fa-plus mr-1"></i> Add Remark
                        </button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white" id="remarksTable">
                            <thead class="bg-light small">
                                <tr>
                                    <th width="200">Remark Type</th>
                                    <th>Observation / Narration</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($performanceRemarks as $index => $remark)
                                    <tr>
                                        <td>
                                            <select name="remarks[{{ $index }}][type]" class="form-control form-control-sm" {{ $closure ? 'disabled' : '' }}>
                                                <option value="achievement" {{ $remark->remark_type == 'achievement' ? 'selected' : '' }}>Key Achievement</option>
                                                <option value="challenge" {{ $remark->remark_type == 'challenge' ? 'selected' : '' }}>Key Challenge</option>
                                                <option value="measure" {{ $remark->remark_type == 'measure' ? 'selected' : '' }}>Corrective Measure</option>
                                                <option value="general" {{ $remark->remark_type == 'general' ? 'selected' : '' }}>General Note</option>
                                            </select>
                                        </td>
                                        <td>
                                            <textarea name="remarks[{{ $index }}][text]" class="form-control form-control-sm" rows="1" {{ $closure ? 'disabled' : '' }}>{{ $remark->remark_text }}</textarea>
                                        </td>
                                        <td class="text-center">
                                            @if(!$closure)
                                            <button type="button" class="btn btn-xs btn-danger remove-remark-row"><i class="fas fa-times"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td>
                                            <select name="remarks[0][type]" class="form-control form-control-sm" {{ $closure ? 'disabled' : '' }}>
                                                <option value="achievement">Key Achievement</option>
                                                <option value="challenge">Key Challenge</option>
                                                <option value="measure">Corrective Measure</option>
                                                <option value="general">General Note</option>
                                            </select>
                                        </td>
                                        <td>
                                            <textarea name="remarks[0][text]" class="form-control form-control-sm" rows="1" placeholder="Enter remark here..." {{ $closure ? 'disabled' : '' }}></textarea>
                                        </td>
                                        <td class="text-center">
                                            @if(!$closure)
                                            <button type="button" class="btn btn-xs btn-danger remove-remark-row"><i class="fas fa-times"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                    <div class="card-body border-top bg-light">
                        <label class="font-weight-bold"><i class="fas fa-paperclip mr-1"></i> Supporting Evidence</label>
                        <input type="file" name="attachments[]" class="form-control-file" multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png">
                        <small class="form-text text-muted">Up to 10 files, maximum 10 MB each. Evidence is stored privately.</small>
                        @include('performance._attachments', ['attachments' => $attachments])
                    </div>
                </fieldset>

                @if(!$closure && $canEditWorkflow)
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="{{ route('monthly.dashboard', ['department_id' => $deptId ?? $plan->department_id, 'year' => $year]) }}" class="btn btn-light">
                        <i class="fas fa-arrow-left mr-1"></i> Back
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> {{ $workflowState['key'] === 'returned_to_department_chief' ? 'Save Corrected Monthly Performance' : 'Submit Monthly Report' }}
                    </button>
                </div>
                @endif
            </form>
        </div>

        {{-- Navigate between months --}}
        <div class="d-flex justify-content-between mt-3">
            @php
                $monthIdx = array_search($month, $months);
                $prevMonth = $monthIdx > 0 ? $months[$monthIdx - 1] : null;
                $nextMonth = $monthIdx < count($months) - 1 ? $months[$monthIdx + 1] : null;
            @endphp
            @if($prevMonth)
                <a href="{{ route('monthly.entry.form', ['year' => $year, 'month' => $prevMonth, 'department_id' => $deptId ?? $plan->department_id]) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-chevron-left mr-1"></i> {{ ucfirst($prevMonth) }}
                </a>
            @else
                <span></span>
            @endif
            @if($nextMonth)
                <a href="{{ route('monthly.entry.form', ['year' => $year, 'month' => $nextMonth, 'department_id' => $deptId ?? $plan->department_id]) }}" class="btn btn-outline-secondary">
                    {{ ucfirst($nextMonth) }} <i class="fas fa-chevron-right ml-1"></i>
                </a>
            @endif
        </div>

    </div>
</section>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let remarkIndex = {{ count($performanceRemarks) > 0 ? count($performanceRemarks) : 1 }};
    $('#addRemarkRow').on('click', function() {
        let html = `<tr>
            <td>
                <select name="remarks[${remarkIndex}][type]" class="form-control form-control-sm">
                    <option value="achievement">Key Achievement</option>
                    <option value="challenge">Key Challenge</option>
                    <option value="measure">Corrective Measure</option>
                    <option value="general">General Note</option>
                </select>
            </td>
            <td>
                <textarea name="remarks[${remarkIndex}][text]" class="form-control form-control-sm" rows="1" placeholder="Enter remark here..."></textarea>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-xs btn-danger remove-remark-row"><i class="fas fa-times"></i></button>
            </td>
        </tr>`;
        $('#remarksTable tbody').append(html);
        remarkIndex++;
    });

    $(document).on('click', '.remove-remark-row', function() {
        if ($('#remarksTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            $(this).closest('tr').find('textarea').val('');
            $(this).closest('tr').find('select').val('achievement');
        }
    });
});
</script>
@endpush
