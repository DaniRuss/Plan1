@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Enter Performance Data: {{ ucfirst($period) }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('performance.index') }}">Performance</a></li>
                        <li class="breadcrumb-item active">Enter Data</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ $plan->title }} - {{ $plan->department->name }}</h3>
                    <div class="card-tools">
                        <div class="btn-group">
                            @foreach(['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'] as $month)
                                <a href="{{ route('performance.edit', [$plan->id, $month]) }}"
                                    class="btn btn-sm {{ $period == $month ? 'btn-primary' : 'btn-default' }}">
                                    {{ ucfirst(substr($month, 0, 3)) }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <form action="{{ route('performance.update', [$plan->id, $period]) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <th style="width: 100px">Code</th>
                                    <th>Objective</th>
                                    <th style="width: 120px">Target ({{ ucfirst($period) }})</th>
                                    <th style="width: 150px">Actual Value</th>
                                    <th style="width: 100px">Progress %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($plan->items as $item)
                                    @php
                                        $objective = $item->strategicObjective;
                                        $isCalculatedParent = $objective->children()->exists()
                                            && $objective->summary_type !== 'manual_override';
                                        $periodTarget = $item->{strtolower($period) . '_target'};
                                        $isPlanned = $isCalculatedParent || !is_null($periodTarget);
                                        $target = $isCalculatedParent && $objective->summary_type === 'weighted'
                                            ? (float) ($objective->weight ?: 100)
                                            : $periodTarget;
                                        $existing = $performances->get($item->id);
                                        $actual = $existing ? $existing->actual_value : null;
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $item->strategicObjective->code }}</strong></td>
                                        <td>{{ \Illuminate\Support\Str::limit($item->strategicObjective->description, 60) }}
                                        </td>
                                        <td class="text-right">{{ $isPlanned ? \App\Support\NumberFormatter::format((float)$target,3) : 'N/A' }}</td>
                                        <td>
                                            <input type="number" step="any" min="0" name="performances[{{ $item->id }}]"
                                                data-original-number="{{ $actual }}" value="{{ \App\Support\NumberFormatter::format($actual,3,'.','') }}" class="form-control form-control-sm text-right"
                                                placeholder="{{ $isCalculatedParent ? 'Calculated from sub-objectives' : 'Enter actual' }}"
                                                {{ ($isCalculatedParent || !$isPlanned) ? 'readonly' : '' }}>
                                            @if($isCalculatedParent)
                                                <small class="text-muted">Calculated from sub-objectives</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($actual !== null && $isPlanned)
                                                @php
                                                    $rawProgress = app(\App\Services\EdrPerformanceCalculator::class)->rawAchievement((float)$actual, (float)$target, $objective->goal_direction ?? 'increase');
                                                    $progress = min(100, $rawProgress);
                                                    $status = app(\App\Services\EdrPerformanceCalculator::class)->performanceStatus($rawProgress);
                                                    $badge = $status === 'Overachieved' ? 'info' : ($status === 'Achieved' ? 'success' : 'warning');
                                                @endphp
                                                <span class="badge badge-{{ $badge }}">{{ \App\Support\NumberFormatter::format($progress,3) }}% · {{ $status }}</span>
                                                @if($rawProgress > 100)<small class="d-block text-info">Raw {{ \App\Support\NumberFormatter::format($rawProgress,3) }}%</small>@endif
                                            @elseif(!$isPlanned)
                                                <span class="badge badge-secondary">Not Planned (N/A)</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- Performance Remarks Table -->
                        <div class="card-footer bg-light p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="font-weight-bold mb-0 text-primary">
                                    <i class="fas fa-comment-alt mr-1"></i> Department Performance Remarks (Optional)
                                </h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addRemarkRow">
                                    <i class="fas fa-plus mr-1"></i> Add Remark
                                </button>
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
                                                    <select name="remarks[{{ $index }}][type]" class="form-control form-control-sm">
                                                        <option value="achievement" {{ $remark->remark_type == 'achievement' ? 'selected' : '' }}>Key Achievement</option>
                                                        <option value="challenge" {{ $remark->remark_type == 'challenge' ? 'selected' : '' }}>Key Challenge</option>
                                                        <option value="measure" {{ $remark->remark_type == 'measure' ? 'selected' : '' }}>Corrective Measure</option>
                                                        <option value="general" {{ $remark->remark_type == 'general' ? 'selected' : '' }}>General Note</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <textarea name="remarks[{{ $index }}][text]" class="form-control form-control-sm" rows="1">{{ $remark->remark_text }}</textarea>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-danger remove-remark-row"><i class="fas fa-times"></i></button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td>
                                                    <select name="remarks[0][type]" class="form-control form-control-sm">
                                                        <option value="achievement">Key Achievement</option>
                                                        <option value="challenge">Key Challenge</option>
                                                        <option value="measure">Corrective Measure</option>
                                                        <option value="general">General Note</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <textarea name="remarks[0][text]" class="form-control form-control-sm" rows="1" placeholder="Enter remark here..."></textarea>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-danger remove-remark-row"><i class="fas fa-times"></i></button>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Performance Data
                            </button>
                            <a href="{{ route('performance.index') }}" class="btn btn-default">Cancel</a>
                        </div>
                    </form>
                </div>
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
