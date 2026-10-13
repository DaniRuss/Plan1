<tr @if($objective->is_supplementary) style="background-color:#f3e8ff;border-left:4px solid #7c3aed" @endif>
    <td style="padding-left: {{ $padding }}px">
        <strong>{{ $objective->code }}</strong>
    </td>
    <td>
        {{ \Illuminate\Support\Str::limit($objective->description, 100) }}
        @if($objective->is_supplementary)
            <span class="badge ml-2" style="background:#7c3aed;color:#fff">Department Added</span>
        @endif
    </td>
    <td>{{ $objective->weight + 0 }}%</td> <!-- +0 removes trailing zeros -->
    <td>{{ $objective->baseline_value }} ({{ $objective->baseline_year }})</td>
    <td>{{ $objective->annual_target }} {{ $objective->unit }}</td>
    <td>
        @if($objective->department)
            {{ $objective->department->code ?? $objective->department->name }}
        @else
            -
        @endif
        @if($objective->performers && count($objective->performers) > 0)
            <small class="d-block text-muted">
                Observed: {{ count($objective->performers) }}
            </small>
        @endif
    </td>
    <td>
        <div class="btn-group">
            <a href="{{ route('objectives.show', $objective->id) }}" class="btn btn-xs btn-info"><i
                    class="fas fa-eye"></i></a>
            <a href="{{ route('objectives.edit', $objective->id) }}" class="btn btn-xs btn-warning"><i
                    class="fas fa-edit"></i></a>
            <form action="{{ route('objectives.destroy', $objective->id) }}" method="POST" class="d-inline"
                data-confirm="Delete objective {{ $objective->code }}? This action cannot be undone." data-confirm-title="Delete objective" data-confirm-text="Delete" data-confirm-class="btn-danger">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
            </form>
        </div>
    </td>
</tr>

@if($objective->children)
    @foreach($objective->children as $child)
        @include('objectives.partials.objective_row', ['objective' => $child, 'padding' => $padding + 20])
    @endforeach
@endif
