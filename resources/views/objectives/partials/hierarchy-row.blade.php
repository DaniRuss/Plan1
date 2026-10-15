@php
    $ownedKpi = $rowObjective->isKpiForDepartment(auth()->user()->department_id);
    $parentWeight = (float) $rowParent->weight;
@endphp
<tr class="{{ $ownedKpi ? 'objective-kpi-owned-row' : '' }}">
    <th scope="row" class="objective-code">{{ $rowObjective->code }}</th>
    <td style="padding-left: {{ 16 + $depth * 18 }}px">
        <a class="objective-description" href="{{ route('objectives.show', $rowObjective) }}">{{ $rowObjective->description }}</a>
        <small class="d-block text-muted">{{ $depth === 0 ? 'Sub-objective' : 'Activity / detail' }} · Parent {{ $rowParent->code }}
            @if($rowObjective->is_reference_only) · Reference only @endif
        </small>
        @if($rowObjective->kpi_code)<span class="badge {{ $ownedKpi ? 'badge-kpi-owned' : 'badge-kpi-code' }} mt-1">{{ $ownedKpi ? 'Your KPI' : 'KPI' }}: {{ $rowObjective->kpi_code }}</span>@endif
    </td>
    <td>{{ $rowObjective->unit ?: 'Not set' }}</td>
    <td class="objective-number">{{ \App\Support\NumberFormatter::format((float) $rowObjective->weight,3) }}%</td>
    <td class="objective-number">{{ $parentWeight > 0 ? \App\Support\NumberFormatter::format($rowObjective->weight / $parentWeight * 100,3).'%' : 'Not applicable' }}</td>
    <td>@include('objectives.partials.status', ['objective' => $rowObjective])</td>
    <td><div class="objective-row-actions">
        <a href="{{ route('objectives.show', $rowObjective) }}" class="btn btn-sm btn-outline-info"><i class="fas fa-eye mr-1" aria-hidden="true"></i>Details</a>
        @if($rowObjective->status === 'approved')<a href="{{ route('objectives.download', $rowObjective) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>PDF</a>@endif
        @if($canEditObjective($rowObjective))<a href="{{ route('objectives.edit', $rowObjective) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit mr-1" aria-hidden="true"></i>Edit</a>@endif
        @can('manage_objectives')
            @if($depth < 2 && in_array($rowObjective->status, ['draft', 'returned'], true))<a href="{{ route('objectives.create', ['parent_id' => $rowObjective->id]) }}" class="btn btn-sm btn-outline-success"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Add {{ $depth === 0 ? 'activity' : 'detail' }}</a>@endif
        @endcan
    </div></td>
</tr>
@foreach($rowObjective->children as $nested)
    @include('objectives.partials.hierarchy-row', ['rowObjective' => $nested, 'rowParent' => $rowObjective, 'depth' => $depth + 1])
@endforeach
