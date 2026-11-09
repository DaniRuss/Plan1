@php($editAttribution = $editedObjective->approvedEditAttribution)
@if($editAttribution)
    <span class="objective-edit-attribution d-block text-muted mt-2" data-objective-edit-meta="{{ $editedObjective->id }}">
        <i class="fas fa-user-edit mr-1" aria-hidden="true"></i>
        Edited after approval by <strong>{{ $editAttribution['editorName'] }}</strong>
        <span class="d-block">{{ $editAttribution['roleLabel'] }}@if($editAttribution['editedAt']) · <time datetime="{{ $editAttribution['editedAt']->toIso8601String() }}">{{ $editAttribution['editedAt']->format('d M Y, h:i A') }}</time>@endif</span>
    </span>
@endif
