@php
    $rowClass = $depth === 0 ? 'root' : ($depth === 1 ? 'child' : 'activity');
@endphp
@include('edr_plans._objective_pdf_row', ['objective' => $objective, 'class' => $rowClass])
@foreach($objective->children as $child)
    @include('edr_plans._objective_pdf_branch', ['objective' => $child, 'depth' => $depth + 1])
@endforeach
