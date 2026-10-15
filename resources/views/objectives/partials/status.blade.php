@php
    $statuses = [
        'draft' => ['secondary', 'Draft'],
        'returned' => ['danger', 'Returned for revision'],
        'submitted_to_plan_report_directorate' => ['info', 'Awaiting Plan & Report Directorate review'],
        'submitted' => ['info', 'Awaiting Plan & Report Head Office approval'],
        'approved' => ['success', 'Approved'],
    ];
    [$tone, $label] = $statuses[$objective->status] ?? ['warning', str_starts_with((string) $objective->status, 'recall_requested_') ? 'Recall approval pending' : ucwords(str_replace('_', ' ', (string) $objective->status))];
@endphp
<span class="badge badge-{{ $tone }}" @if($objective->rejection_comment) title="{{ $objective->rejection_comment }}" @endif>{{ $label }}</span>
