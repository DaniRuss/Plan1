@extends('layouts.app')

@section('title', 'Create Annual Plan')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Create Annual Plan</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('plans.index') }}">Plans</a></li>
                    <li class="breadcrumb-item active">Create</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
    <form action="{{ route('plans.store') }}" method="POST" id="planForm">
        @csrf
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cog mr-1"></i> Plan Settings</h3>
            </div>
             <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                         <div class="form-group">
                            <label for="year" class="required">Plan Year (EFY)</label>
                            <select class="form-control" id="year" name="year" required>
                                @foreach($years as $y)
                                    <option value="{{ $y }}" {{ $y == 2018 ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="department_id" class="required">Department</label>
                            <select class="form-control select2" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" 
                                        {{ (auth()->user()->department_id == $dept->id && !auth()->user()->isSystemAdmin()) ? 'selected' : '' }}>
                                        {{ $dept->name }} ({{ $dept->code ?? '' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table mr-1"></i> Planning Grid</h3>
                <div class="card-tools">
                   <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#activityModal">
                        <i class="fas fa-plus mr-1"></i> Add Objectives
                    </button>
                </div>
            </div>
            
            <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-sm table-head-fixed text-nowrap" id="planningGrid" style="font-size: 0.9rem;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 50px">Remove</th>
                            <th style="width: 80px">Code</th>
                            <th style="min-width: 300px">Objective / Activity</th>
                            <th style="width: 60px">Weight</th>
                            <th style="width: 80px">Unit</th>
                            <th style="width: 150px">Performers</th> <!-- Added Column -->
                            <th style="width: 80px">Target</th>
                            
                            <!-- Q1 -->
                            <th class="bg-primary-light text-center" style="width: 80px">Jul</th>
                            <th class="bg-primary-light text-center" style="width: 80px">Aug</th>
                            <th class="bg-primary-light text-center" style="width: 80px">Sep</th>
                            <th class="bg-primary text-center font-weight-bold" style="width: 80px">Q1</th>

                            <!-- Q2 -->
                            <th class="bg-info-light text-center" style="width: 80px">Oct</th>
                            <th class="bg-info-light text-center" style="width: 80px">Nov</th>
                            <th class="bg-info-light text-center" style="width: 80px">Dec</th>
                            <th class="bg-info text-center font-weight-bold" style="width: 80px">Q2</th>

                            <!-- Q3 -->
                            <th class="bg-warning-light text-center" style="width: 80px">Jan</th>
                            <th class="bg-warning-light text-center" style="width: 80px">Feb</th>
                            <th class="bg-warning-light text-center" style="width: 80px">Mar</th>
                            <th class="bg-warning text-center font-weight-bold" style="width: 80px">Q3</th>

                            <!-- Q4 -->
                            <th class="bg-danger-light text-center" style="width: 80px">Apr</th>
                            <th class="bg-danger-light text-center" style="width: 80px">May</th>
                            <th class="bg-danger-light text-center" style="width: 80px">Jun</th>
                            <th class="bg-danger text-center font-weight-bold" style="width: 80px">Q4</th>
                        </tr>
                    </thead>
                    <tbody id="gridBody">
                        <tr id="emptyRow">
                            <td colspan="22" class="text-center text-muted p-4">
                                No objectives added yet. Click "Add Objectives" to start planning.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary float-right">Create Plan</button>
            </div>
        </div>
    </form>
    </div>
</section>

<!-- Modal -->
<div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Strategic Objectives</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px"></th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Weight</th>
                            <th>Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $targetFields = [
                                'july_target', 'august_target', 'september_target', 'q1_target',
                                'october_target', 'november_target', 'december_target', 'q2_target',
                                'january_target', 'february_target', 'march_target', 'q3_target',
                                'april_target', 'may_target', 'june_target', 'q4_target',
                            ];
                        @endphp
                        @foreach($objectives as $obj)
                            <tr>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input obj-checkbox" id="obj_{{ $obj->id }}" 
                                            value="{{ $obj->id }}" 
                                            data-code="{{ $obj->code }}" 
                                            data-desc="{{ $obj->description }}"
                                            data-weight="{{ \App\Support\NumberFormatter::format($obj->weight) }}"
                                            data-unit="{{ $obj->unit }}"
                                            data-department="{{ $obj->department_id }}"
                                            data-performers="{{ json_encode($obj->performers ?? []) }}"
                                            data-annual-target="{{ $obj->annual_target ?? '' }}"
                                            data-targets="{{ json_encode($obj->only($targetFields)) }}">
                                        <label class="custom-control-label" for="obj_{{ $obj->id }}"></label>
                                    </div>
                                </td>
                                <td>{{ $obj->code }}</td>
                                <td>
                                    {{ $obj->description }}
                                    @if($obj->children->count() > 0)
                                        <br><small class="text-muted">Includes {{ $obj->children->count() }} sub-objectives</small>
                                    @endif
                                </td>
                                <td>{{ \App\Support\NumberFormatter::format($obj->weight) }}</td>
                                <td>{{ $obj->unit }}</td>
                            </tr>
                            @foreach($obj->children as $child)
                                <tr>
                                     <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input obj-checkbox" id="obj_{{ $child->id }}" 
                                                value="{{ $child->id }}" 
                                                data-code="{{ $child->code }}" 
                                                data-desc="{{ $child->description }}"
                                                data-weight="{{ \App\Support\NumberFormatter::format($child->weight) }}"
                                                data-unit="{{ $child->unit }}"
                                                data-department="{{ $child->department_id }}"
                                                data-performers="{{ json_encode($child->performers ?? []) }}"
                                                data-annual-target="{{ $child->annual_target ?? '' }}"
                                                data-targets="{{ json_encode($child->only($targetFields)) }}">
                                            <label class="custom-control-label" for="obj_{{ $child->id }}"></label>
                                        </div>
                                    </td>
                                    <td class="pl-4">{{ $child->code }}</td>
                                    <td class="pl-4">{{ $child->description }}</td>
                                    <td>{{ \App\Support\NumberFormatter::format($child->weight) }}</td>
                                    <td>{{ $child->unit }}</td>
                                </tr>
                                @foreach($child->children as $activity)
                                    <tr>
                                        <td class="text-center"><div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input obj-checkbox" id="obj_{{ $activity->id }}"
                                                value="{{ $activity->id }}" data-code="{{ $activity->code }}" data-desc="{{ $activity->description }}"
                                                data-weight="{{ \App\Support\NumberFormatter::format($activity->weight) }}" data-unit="{{ $activity->unit }}"
                                                data-department="{{ $activity->department_id }}" data-performers="{{ json_encode($activity->performers ?? []) }}"
                                                data-annual-target="{{ $activity->annual_target ?? '' }}"
                                                data-targets="{{ json_encode($activity->only($targetFields)) }}">
                                            <label class="custom-control-label" for="obj_{{ $activity->id }}"></label>
                                        </div></td>
                                        <td class="pl-5">{{ $activity->code }}</td><td class="pl-5">{{ $activity->description }}</td>
                                        <td>{{ \App\Support\NumberFormatter::format($activity->weight) }}</td><td>{{ $activity->unit }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="addSelectedObjectives()">Add Selected</button>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-primary-light { background-color: #e3f2fd; }
    .bg-info-light { background-color: #e0f7fa; }
    .bg-warning-light { background-color: #fff8e1; }
    .bg-danger-light { background-color: #fce4ec; }
    .plan-input { 
        width: 100%; 
        min-width: 70px; /* Force minimum width */
        border: 1px solid #ced4da; 
        padding: 4px 6px; 
        font-size: 0.9rem; 
        text-align: right; 
    }
    .plan-input:focus { border-color: #80bdff; outline: 0; }
    .q-total { background-color: transparent; border: none; font-weight: bold; text-align: right; width: 100%; }
</style>

<script>
    // Pass departments to JS
    const departments = @json($departments);
    const performerDepartments = @json($performerDepartments);
    const childDepartments = @json($childDepartments);
    const experts = @json($experts);
    const isChief = {{ auth()->user()->isDepartmentChief() || auth()->user()->isDeptHead() || auth()->user()->isDepartmentDirectorate() ? 'true' : 'false' }};
    const currentUserDeptId = {{ auth()->user()->department_id ?? 'null' }};

    $(document).ready(function() {
        $('#department_id').on('change', function() {
            filterObjectives();
        });
        
        // Initial filter if dept is pre-selected
        if($('#department_id').val()) {
            filterObjectives();
        }
    });

    function filterObjectives() {
        let deptId = parseInt($('#department_id').val());
        if (!deptId) return;

        $('.obj-checkbox').each(function() {
            let objDeptId = parseInt($(this).data('department'));
            let performers = $(this).data('performers') || [];
            
            // Show ONLY if it belongs to selected dept OR if dept is a performer
            let isVisible = (objDeptId === deptId) || 
                            performers.includes(deptId);

            if (isVisible) {
                $(this).closest('tr').show();
            } else {
                $(this).closest('tr').hide();
                $(this).prop('checked', false); // Uncheck hidden ones
            }
        });
    }

    function addSelectedObjectives() {
        $('#emptyRow').remove();
        $('.obj-checkbox:checked').each(function() {
            let id = $(this).val();
            if ($('#row_' + id).length === 0) { // Avoid duplicates
                let code = $(this).data('code');
                let desc = $(this).data('desc');
                let weight = $(this).data('weight');
                let unit = $(this).data('unit');
                let annualTarget = $(this).data('annual-target') || '';
                let targets = $(this).data('targets') || {};

                // Parse Performers
                let objPerformersStr = $(this).data('performers');
                let objPerformers = [];
                if (typeof objPerformersStr === 'string' || objPerformersStr instanceof String) {
                    try { objPerformers = JSON.parse(objPerformersStr); } catch(e) {}
                } else if (Array.isArray(objPerformersStr)) {
                    objPerformers = objPerformersStr;
                }
                
                let deptId = $(this).data('department');
                if (!objPerformers || objPerformers.length === 0) {
                    objPerformers = [deptId];
                }

                let performerDisplay = '';
                let hiddenInputs = '';

                // Logic for Dispatching:
                // If the current user is a Chief and their department is assigned as a performer
                let userDeptInPerformers = objPerformers.some(p => String(p) === String(currentUserDeptId));

                if (isChief && userDeptInPerformers && (childDepartments.length > 0 || experts.length > 0)) {
                    // Show a selection for child departments and experts
                    performerDisplay += `<div class="p-1" style="max-height: 120px; overflow-y: auto; text-align: left; border: 1px dashed #ccc; border-radius: 4px;">`;
                    
                    if (childDepartments.length > 0) {
                        performerDisplay += `<strong class="small text-muted">Units:</strong>`;
                        // Always include the main department (self)
                        performerDisplay += `
                            <div class="custom-control custom-checkbox small">
                                <input type="checkbox" name="items[${id}][performers][]" value="${currentUserDeptId}" 
                                    class="custom-control-input" id="perf_${id}_${currentUserDeptId}" checked>
                                <label class="custom-control-label" for="perf_${id}_${currentUserDeptId}">Your Dept</label>
                            </div>
                        `;
                        // Add children
                        childDepartments.forEach(child => {
                            performerDisplay += `
                                <div class="custom-control custom-checkbox small">
                                    <input type="checkbox" name="items[${id}][performers][]" value="${child.id}" 
                                        class="custom-control-input" id="perf_${id}_${child.id}">
                                    <label class="custom-control-label" for="perf_${id}_${child.id}">${child.name}</label>
                                </div>
                            `;
                        });
                    }

                    if (experts.length > 0) {
                        if (childDepartments.length > 0) performerDisplay += `<hr class="my-1">`;
                        performerDisplay += `<strong class="small text-muted">Experts:</strong>`;
                        experts.forEach(exp => {
                            performerDisplay += `
                                <div class="custom-control custom-checkbox small">
                                    <input type="checkbox" name="items[${id}][experts][]" value="${exp.id}" 
                                        class="custom-control-input" id="exp_${id}_${exp.id}">
                                    <label class="custom-control-label" for="exp_${id}_${exp.id}">${exp.name}</label>
                                </div>
                            `;
                        });
                    }
                    performerDisplay += `</div>`;
                } else {
                    // Standard readonly badges (Admin or Non-Chief)
                    performerDepartments.forEach(dept => {
                        let match = objPerformers.some(p => String(p) === String(dept.id));
                        if (match) {
                            performerDisplay += `<span class="badge badge-info mr-1">${dept.name}</span>`;
                            hiddenInputs += `<input type="hidden" name="items[${id}][performers][]" value="${dept.id}">`;
                        }
                    });
                    if (performerDisplay === '') {
                        performerDisplay = '<span class="text-muted small">None Assigned</span>';
                    }
                }

                let row = `
                    <tr id="row_${id}">
                        <td class="text-center"><button type="button" class="btn btn-xs btn-danger" onclick="$('#row_${id}').remove()"><i class="fas fa-times"></i></button></td>
                        <td>${code}</td>
                        <td title="${desc}">${desc.length > 50 ? desc.substring(0, 50) + '...' : desc}</td>
                        <td>${weight}</td>
                        <td>${unit}</td>

                        <!-- Performers Info -->
                        <td style="min-width: 150px; text-align: left; vertical-align: middle;">
                            ${hiddenInputs}
                            ${performerDisplay}
                        </td>

                        <!-- Annual Target: auto-filled from objective, readonly -->
                        <td>
                            <input type="number" step="any" min="0"
                                name="items[${id}][annual_target]"
                                class="plan-input annual-target"
                                value="${annualTarget}"
                                readonly
                                style="background:#f8f9fa; cursor:not-allowed;"
                                title="Auto-filled from objective">
                        </td>

                        <!-- Q1 Months: Jul, Aug, Sep. Sep auto-fills Q1 -->
                        <td><input type="number" step="any" min="0" name="items[${id}][july]" value="${targets.july_target ?? ''}" class="plan-input" oninput="validity.valid||(value='')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][august]" value="${targets.august_target ?? ''}" class="plan-input" oninput="validity.valid||(value='')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][september]" class="plan-input sep-input"
                                   value="${targets.september_target ?? ''}" oninput="validity.valid||(value=''); autoFillQuarter(this, 'q1_${id}')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][q1_target]" value="${targets.q1_target ?? ''}" class="plan-input q-total font-weight-bold" id="q1_${id}" readonly style="background:#e3f2fd; cursor:not-allowed;"></td>

                        <!-- Q2 Months: Oct, Nov, Dec. Dec auto-fills Q2 -->
                        <td><input type="number" step="any" min="0" name="items[${id}][october]" value="${targets.october_target ?? ''}" class="plan-input" oninput="validity.valid||(value='')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][november]" value="${targets.november_target ?? ''}" class="plan-input" oninput="validity.valid||(value='')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][december]"  class="plan-input dec-input"
                                   value="${targets.december_target ?? ''}" oninput="validity.valid||(value=''); autoFillQuarter(this, 'q2_${id}')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][q2_target]" value="${targets.q2_target ?? ''}" class="plan-input q-total font-weight-bold" id="q2_${id}" readonly style="background:#e0f7fa; cursor:not-allowed;"></td>

                        <!-- Q3 Months: Jan, Feb, Mar. Mar auto-fills Q3 -->
                        <td><input type="number" step="any" min="0" name="items[${id}][january]" value="${targets.january_target ?? ''}" class="plan-input" oninput="validity.valid||(value='')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][february]" value="${targets.february_target ?? ''}" class="plan-input" oninput="validity.valid||(value='')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][march]"     class="plan-input mar-input"
                                   value="${targets.march_target ?? ''}" oninput="validity.valid||(value=''); autoFillQuarter(this, 'q3_${id}')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][q3_target]" value="${targets.q3_target ?? ''}" class="plan-input q-total font-weight-bold" id="q3_${id}" readonly style="background:#fff8e1; cursor:not-allowed;"></td>

                        <!-- Q4 Months: Apr, May, Jun. Jun auto-fills Q4 -->
                        <td><input type="number" step="any" min="0" name="items[${id}][april]" value="${targets.april_target ?? ''}" class="plan-input" oninput="validity.valid||(value='')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][may]" value="${targets.may_target ?? ''}" class="plan-input" oninput="validity.valid||(value='')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][june]"      class="plan-input jun-input"
                                   value="${targets.june_target ?? ''}" oninput="validity.valid||(value=''); autoFillQuarter(this, 'q4_${id}')"></td>
                        <td><input type="number" step="any" min="0" name="items[${id}][q4_target]" value="${targets.q4_target ?? ''}" class="plan-input q-total font-weight-bold" id="q4_${id}" readonly style="background:#fce4ec; cursor:not-allowed;"></td>
                    </tr>
                `;
                $('#gridBody').append(row);
            }
        });
        $('#activityModal').modal('hide');
    }

    // Auto-fill the quarter field with the last-month value
    function autoFillQuarter(monthInput, quarterId) {
        var val = monthInput.value;
        var qField = document.getElementById(quarterId);
        if (qField) {
            qField.value = val;
        }
    }
</script>
@endsection
