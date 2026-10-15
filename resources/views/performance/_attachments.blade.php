@if($attachments->isNotEmpty())
<div class="card border-info mt-3">
    <div class="card-header bg-light font-weight-bold">
        <i class="fas fa-paperclip mr-1"></i> Supporting Evidence
    </div>
    <div class="list-group list-group-flush">
        @foreach($attachments as $attachment)
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
               href="{{ route('performance-attachments.download', $attachment) }}">
                <span><i class="far fa-file mr-2 text-info"></i>{{ $attachment->original_name }}</span>
                <small class="text-muted">{{ $attachment->uploader?->name }} &middot; {{ \App\Support\NumberFormatter::format(($attachment->file_size ?? 0) / 1024,3) }} KB</small>
            </a>
        @endforeach
    </div>
</div>
@endif
