@extends('layouts.app')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <h1 class="m-0">Create Strategic Objective</h1>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Objective Details</h3>
            </div>
            <form action="{{ route('objectives.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="code" class="required">Code</label>
                                <input type="text" name="code" id="code" class="form-control" placeholder="Automatic Sequential Number" required value="{{ old('code', $suggestedCode ?? '') }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="kpi_code">KPI Code (Optional)</label>
                                <input type="text" name="kpi_code" id="kpi_code"
                                    class="form-control @error('kpi_code') is-invalid @enderror"
                                    value="{{ old('kpi_code') }}" placeholder="e.g. K1">
                                <small class="form-text text-muted">Use K1 through K27 when this objective contributes to the KPI Performance Board.</small>
                                @error('kpi_code')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="lead_department_id" class="required">Lead / Accountable Department</label>
                                <select name="lead_department_id" id="lead_department_id" class="form-control @error('lead_department_id') is-invalid @enderror" required>
                                    <option value="">-- Select the department whose result becomes authoritative --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ (string) old('lead_department_id', $defaultLeadDepartmentId) === (string) $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Automatically included as a performer. If additional performers are selected, this becomes a shared KPI and the lead department's approved result is counted once in the company score.</small>
                                @error('lead_department_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Additional / Contributing Performers (Optional)</label>
                                <div class="row form-control p-3 h-auto @error('performers') is-invalid @enderror" style="max-height: 250px; overflow-y: auto; background-color: #f8f9fa;">
                                    @foreach($departments as $dept)
                                    <div class="col-md-4 mb-2">
                                        <div class="custom-control custom-checkbox bg-white border rounded p-2 shadow-sm">
                                            <input class="custom-control-input" type="checkbox" name="performers[]" id="performer_{{ $dept->id }}" value="{{ $dept->id }}" {{ in_array((int) $dept->id, array_map('intval', old('performers', $defaultPerformerIds)), true) ? 'checked' : '' }}>
                                            <label for="performer_{{ $dept->id }}" class="custom-control-label font-weight-normal" style="cursor: pointer;">{{ $dept->name }}</label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <small class="text-muted">The lead department is selected automatically. Choose one or more additional departments only when this is a shared KPI.</small>
                                @error('performers')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description" class="required">Description (Objective/Activity)</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="parent_id">Main Objective</label>
                                <select name="parent_id" id="parent_id" class="form-control select2">
                                    <option value="">-- Main Objective (Top Level) --</option>
                                    @foreach($objectives as $obj)
                                        <option value="{{ $obj->id }}" 
                                            data-remaining="{{ $obj->getRemainingWeight() }}"
                                            data-total="{{ $obj->weight }}"
                                            data-allocated="{{ $obj->getAllocatedWeight() }}"
                                            data-calculated="{{ $obj->is_calculated_weight ? 1 : 0 }}"
                                            data-code="{{ $obj->code }}"
                                            data-unit="{{ $obj->unit }}"
                                            data-lead="{{ $obj->lead_department_id ?: (($obj->performers ?? [])[0] ?? '') }}"
                                            data-performers='@json(array_values(array_map('intval', $obj->performers ?? [])))'
                                            {{ ($parent && $parent->id == $obj->id) ? 'selected' : '' }}>
                                            {{ $obj->code }} - {{ Str::limit($obj->description, 50) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                             <div class="form-group">
                                <label for="weight" id="weightLabel" class="required">Weight (Percentage Points)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" name="weight" id="weight" class="form-control @error('weight') is-invalid @enderror" value="{{ old('weight') }}" required placeholder="e.g. 5.00">
                                    <div class="input-group-append">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    @error('weight')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                                <small id="weightInfo" class="form-text text-muted">
                                    Enter total weight for this strategic objective.
                                </small>
                                <div id="progressContainer" class="progress mt-2 d-none" style="height: 10px;">
                                    <div id="progressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="goal_direction" class="required">Goal Direction</label>
                                <select name="goal_direction" id="goal_direction" class="form-control" required>
                                    <option value="increase" {{ old('goal_direction') == 'increase' ? 'selected' : '' }}>Increase (Higher is Better)</option>
                                    <option value="decrease" {{ old('goal_direction') == 'decrease' ? 'selected' : '' }}>Decrease (Lower is Better)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="unit" class="required">Unit of Measurement</label>
                                @php
                                    $measurementUnits = ['Percentage (%)', 'Number', 'Tons', 'Tons (in 1000)', 'Kilograms', 'Kilometer', 'Kilometers (in 1000)', 'Kilometers per Hour', 'Hours', 'Days', 'Months', 'TEU', 'Wagon', 'Passenger', 'Passengers (in 1000)', 'Set', 'Birr', 'Birr (Million)'];
                                    $selectedUnit = old('unit', $parent?->unit);
                                @endphp
                                <select name="unit" id="unit" class="form-control select2" data-tags="true" required onchange="handleUnitChange()">
                                    <option value="">Select or type a unit</option>
                                    @if($selectedUnit && !in_array($selectedUnit, $measurementUnits, true))
                                        <option value="{{ $selectedUnit }}" selected>{{ $selectedUnit }}</option>
                                    @endif
                                    @foreach($measurementUnits as $measurementUnit)
                                        <option value="{{ $measurementUnit }}" {{ $selectedUnit === $measurementUnit ? 'selected' : '' }}>{{ $measurementUnit }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                         <div class="col-md-3">
                            <div class="form-group">
                                <label for="annual_target">Annual Target</label>
                                <input type="number" step="any" name="annual_target" id="annual_target" class="form-control" oninput="validateAnnualTarget()">
                                <small id="targetError" class="text-danger d-none">Percentage cannot exceed 100%</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="baseline_value">Baseline Value</label>
                                <input type="number" step="any" name="baseline_value" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="baseline_year">Baseline Year</label>
                                <input type="text" name="baseline_year" class="form-control" placeholder="e.g. 2017">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="target_year" class="required">Performance Year</label>
                                <select name="target_year" id="target_year" class="form-control @error('target_year') is-invalid @enderror" required>
                                    <option value="">-- Select Year --</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ (string) old('target_year', $parent?->target_year) === (string) $year ? 'selected' : '' }}>{{ $year }} EFY</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">After approval, this objective becomes available for reporting in this year.</small>
                                @error('target_year')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    @include('objectives._target_schedule')

                        <div class="form-group">
 @if(auth()->user()->canManageObjectiveFrequency())
 <label for="reporting_frequency">Reporting Frequency</label>
 <select name="reporting_frequency" id="reporting_frequency" class="form-control">
 @foreach(['' => 'Use department default', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
 <option value="{{ $value }}" @selected((old('reporting_frequency', null) ?? '') === $value)>{{ $label }}</option>
 @endforeach
 </select><small class="text-muted">A blank setting inherits the performer department's default.</small>
 @error('reporting_frequency')<div class="text-danger">{{ $message }}</div>@enderror
 @endif
 </div>
 <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="is_calculated_weight" value="0">
                                <input type="checkbox" class="custom-control-input" id="is_calculated_weight" name="is_calculated_weight" value="1" {{ old('is_calculated_weight') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_calculated_weight">Calculate this objective's weight from its direct sub-objectives</label>
                            </div>
                            <small class="text-muted">Matches main-objective rows in the official action plan where Weight is the sum of sub-objective weights.</small>
                        </div>

                        <div class="col-md-12 mt-3" id="parentRollupOption" style="{{ old('parent_id', $parent?->id) ? '' : 'display:none' }}">
                          <div class="form-group mb-0">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="include_in_parent_rollup" value="0">
                                <input type="checkbox" class="custom-control-input" id="include_in_parent_rollup" name="include_in_parent_rollup" value="1" {{ old('include_in_parent_rollup', true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="include_in_parent_rollup">Include this sub-objective in its main objective's calculation</label>
                            </div>
                            <small class="text-muted">Uncheck this when the sub-objective should be reported separately but must not affect the main-objective result.</small>
                          </div>
                        </div>
                        <div class="col-md-6">
                            <label for="summary_type">How sub-objective results roll up</label>
                            <select name="summary_type" id="summary_type" class="form-control">
                                @foreach(['sum' => 'Sum sub-objective results', 'weighted' => 'Weighted achievement', 'average' => 'Average sub-objective results', 'manual_override' => 'Manual main-objective result'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('summary_type', 'weighted') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    

                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save Objective</button>
                    <a href="{{ route('objectives.index') }}" class="btn btn-default float-right">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    function syncLeadPerformer() {
        const leadId = String($('#lead_department_id').val() || '');
        $('input[name="performers[]"]').each(function () {
            const isLead = leadId !== '' && String(this.value) === leadId;
            $(this).closest('.custom-control').toggleClass('border-primary bg-light', isLead);
            if (isLead) this.checked = true;
        });
    }

    $('#lead_department_id').on('change', syncLeadPerformer);
    $('input[name="performers[]"]').on('change', function () {
        if (String(this.value) === String($('#lead_department_id').val()) && !this.checked) this.checked = true;
    });
    syncLeadPerformer();
    // Parent Department Change — same pattern as employees/create
    $('#parent_department_id').on('change', function() {
        const parentId = $(this).val();
        const $childSelect = $('#department_id');

        $childSelect.empty().append('<option value="">-- Loading Units... --</option>').prop('disabled', true);

        if (!parentId) {
            $childSelect.empty().append('<option value="">-- Select Parent First --</option>');
            return;
        }

        $.get('/api/departments/' + parentId + '/children', function(data) {
            $childSelect.empty().append('<option value="">-- Select Unit / Branch (Optional) --</option>');
            if (data.length > 0) {
                data.forEach(function(unit) {
                    $childSelect.append('<option value="' + unit.id + '">' + unit.name + '</option>');
                });
            }
            $childSelect.prop('disabled', false);
        });
    });

    // Objective Parent change -> update code
    $('#parent_id').on('change', function() {
        inheritParentPerformers();
        updateWeightInfo();
        updateCodePrefix();
    });

    $('#weight').on('input', function() {
        updateWeightInfo();
    });

    $('#is_calculated_weight').on('change', updateCalculatedWeightState);

    updateCalculatedWeightState();
    updateWeightInfo();
});

function inheritParentPerformers() {
    var option = $('#parent_id').find(':selected');
    if (!option.val()) return;
    var performers = [];
    try { performers = JSON.parse(option.attr('data-performers') || '[]').map(String); } catch (e) { performers = []; }
    var lead = String(option.attr('data-lead') || performers[0] || '');
    $('input[name="performers[]"]').each(function () {
        this.checked = performers.includes(String(this.value));
    });
    $('#lead_department_id').val(lead).trigger('change');
}

function updateCalculatedWeightState() {
    var calculated = $('#is_calculated_weight').is(':checked');
    var weight = $('#weight');
    weight.prop('required', !calculated).prop('readonly', calculated);
    $('#weightLabel').toggleClass('required', !calculated);

    if (calculated) {
        weight.val('').attr('placeholder', 'Automatically calculated from direct sub-objectives');
        $('#weightInfo').html('Weight will start at 0 and automatically become the sum of direct sub-objective weights.');
    } else {
        weight.attr('placeholder', 'e.g. 5.00');
    }
}

function updateCodePrefix() {
    var parentId = $('#parent_id').val();
    $('#parentRollupOption').toggle(!!parentId);
    var codeInput = $('#code');
    codeInput.val('Loading...');
    var url = parentId ? '/api/objectives/next-code/' + parentId : '/api/objectives/next-code';
    $.get(url, function(data) {
        codeInput.val(data.code);
    }).fail(function() {
        codeInput.val('');
    });
}

function updateWeightInfo() {
    var option = $('#parent_id').find(':selected');
    var val = $('#parent_id').val();
    var inputWeight = parseFloat($('#weight').val()) || 0;
    var infoText = 'Enter total weight for this strategic objective.';
    var progressBar = $('#progressBar');
    var progressContainer = $('#progressContainer');

    if (val) {
        var parentIsCalculated = String(option.data('calculated')) === '1';
        var allocated = parseFloat(option.data('allocated')) || 0;
        if (parentIsCalculated) {
            var newTotal = allocated + inputWeight;
            infoText = '<b>Automatic parent weight.</b> Current direct-child total: ' + allocated.toFixed(2)
                + '%. After saving this child: <b>' + newTotal.toFixed(2) + '%</b>.';
            $('#weight').removeClass('is-invalid');
            progressContainer.addClass('d-none');
            $('#weightInfo').html(infoText);
            return;
        }
        var remaining = parseFloat(option.data('remaining'));
        var total = parseFloat(option.data('total'));
        allocated = total - remaining;
        infoText = 'Parent Allocation: ' + total + '%. Allocated: ' + allocated.toFixed(2) + '%. <b>Remaining: ' + remaining.toFixed(2) + '%</b>.';
        if (inputWeight > remaining) {
            infoText += ' <span class="text-danger font-weight-bold ml-2"><i class="fas fa-exclamation-circle"></i> Exceeds remaining!</span>';
            $('#weight').addClass('is-invalid');
            progressBar.removeClass('bg-success').addClass('bg-danger');
        } else {
            $('#weight').removeClass('is-invalid');
            progressBar.removeClass('bg-danger').addClass('bg-success');
        }
        progressContainer.removeClass('d-none');
        var percentUsed = total > 0 ? ((allocated + inputWeight) / total) * 100 : 0;
        progressBar.css('width', percentUsed + '%');
    } else {
        progressContainer.addClass('d-none');
        $('#weight').removeClass('is-invalid');
    }
    if (!$('#weight').hasClass('is-invalid')) {
        $('#weightInfo').html(infoText);
    }
}

function handleUnitChange() { validateAnnualTarget(); }

function recalculateQuarterTargets() {
    var mode = $('#target_distribution').val();
    var groups = [
        [['july','august','september'], 'q1_target'], [['october','november','december'], 'q2_target'],
        [['january','february','march'], 'q3_target'], [['april','may','june'], 'q4_target']
    ];
    groups.forEach(function (group) {
        var months = group[0];
        var quarter = group[1];
        var input = $('#' + quarter);
        input.prop('readonly', mode !== 'manual').toggleClass('bg-light', mode !== 'manual');
        if (mode === 'sum') {
            var total = 0;
            months.forEach(function (month) { total += parseFloat($('[name="' + month + '_target"]').val()) || 0; });
            input.val(total).siblings('.quarter-calculation-note').text('Sum of the three months');
        } else if (mode === 'checkpoint') {
            input.val($('[name="' + months[2] + '_target"]').val()).siblings('.quarter-calculation-note').text('Copied from ' + months[2].charAt(0).toUpperCase() + months[2].slice(1));
        } else {
            input.siblings('.quarter-calculation-note').text('Enter the quarter target manually');
        }
    });
}

$(document).on('input change', '.target-month, #target_distribution', recalculateQuarterTargets);
$(document).ready(recalculateQuarterTargets);

function validateAnnualTarget() {
    var unit = $('#unit').val();
    var target = parseFloat($('#annual_target').val());
    if (unit === 'Percentage (%)' && target > 100) {
        $('#targetError').removeClass('d-none');
        $('#annual_target').addClass('is-invalid');
    } else {
        $('#targetError').addClass('d-none');
        $('#annual_target').removeClass('is-invalid');
    }
}
</script>
@endpush
