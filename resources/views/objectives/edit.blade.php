@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Strategic Objective</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('objectives.index') }}">Objectives</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">Edit Objective: {{ $objective->code }}</h3>
                </div>
                <form action="{{ route('objectives.update', $objective->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">Objective Code</label>
                                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                        value="{{ old('code', $objective->code) }}" required readonly>
                                    @error('code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark">KPI Code (Optional)</label>
                                    <input type="text" name="kpi_code"
                                        class="form-control @error('kpi_code') is-invalid @enderror"
                                        value="{{ old('kpi_code', $objective->kpi_code) }}">
                                    @error('kpi_code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="weight" id="weightLabel">Weight (Percentage Points) <span
                                            id="weightRequired" class="text-danger">*</span></label>
                                    <input type="number" step="any" name="weight" id="weight"
                                        class="form-control @error('weight') is-invalid @enderror"
                                        data-original-number="{{ old('weight', $objective->weight) }}" value="{{ \App\Support\NumberFormatter::format(old('weight', $objective->weight),3,'.','') }}" required min="0">
                                    @error('weight') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                rows="3" required>{{ old('description', $objective->description) }}</textarea>
                            @error('description') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="parent_id">Main Objective</label>
                                    <select name="parent_id" id="parent_id"
                                        class="form-control select2 @error('parent_id') is-invalid @enderror">
                                        <option value="">None (Top Level)</option>
                                        @foreach($objectives as $obj)
                                            <option value="{{ $obj->id }}" {{ old('parent_id', $objective->parent_id) == $obj->id ? 'selected' : '' }}>{{ $obj->code }} -
                                                {{ \Illuminate\Support\Str::limit($obj->description, 30) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('parent_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="goal_direction" class="required">Goal Direction</label>
                                    <select name="goal_direction" id="goal_direction" class="form-control" required>
                                        <option value="increase" {{ old('goal_direction', $objective->goal_direction) == 'increase' ? 'selected' : '' }}>Increase (Higher is Better)</option>
                                        <option value="decrease" {{ old('goal_direction', $objective->goal_direction) == 'decrease' ? 'selected' : '' }}>Decrease (Lower is Better)</option>
                                    </select>
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
                                                <input class="custom-control-input" type="checkbox" name="performers[]" id="performer_{{ $dept->id }}" value="{{ $dept->id }}" {{ (collect(old('performers', $objective->performers))->contains($dept->id)) ? 'checked' : '' }}>
                                                <label for="performer_{{ $dept->id }}" class="custom-control-label font-weight-normal" style="cursor: pointer;">{{ $dept->name }}</label>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    <small class="text-muted">The lead department is selected automatically. Choose additional departments only when this is a shared KPI.</small>
                                    @error('performers')
                                        <span class="invalid-feedback d-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="unit" class="required">Unit of Measurement <span
                                            class="text-danger">*</span></label>
                                    @php
                                        $measurementUnits = ['Percentage (%)', 'Number', 'Tons', 'Tons (in 1000)', 'Kilograms', 'Kilometer', 'Kilometers (in 1000)', 'Kilometers per Hour', 'Hours', 'Days', 'Months', 'TEU', 'Wagon', 'Passenger', 'Passengers (in 1000)', 'Set', 'Birr', 'Birr (Million)'];
                                        $selectedUnit = old('unit', $objective->unit);
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
                                    <input type="number" step="any" name="annual_target" id="annual_target"
                                        class="form-control @error('annual_target') is-invalid @enderror"
                                        data-original-number="{{ old('annual_target', $objective->annual_target) }}" value="{{ \App\Support\NumberFormatter::format(old('annual_target', $objective->annual_target),3,'.','') }}"
                                        oninput="validateAnnualTarget()">
                                    @error('annual_target') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    <small id="targetError" class="text-danger d-none">Percentage cannot exceed 100%</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="baseline_value">Baseline Value</label>
                                    <input type="number" step="any" name="baseline_value" class="form-control"
                                        data-original-number="{{ old('baseline_value', $objective->baseline_value) }}" value="{{ \App\Support\NumberFormatter::format(old('baseline_value', $objective->baseline_value),3,'.','') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="baseline_year">Baseline Year</label>
                                    <input type="text" name="baseline_year" class="form-control"
                                        value="{{ old('baseline_year', $objective->baseline_year) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="lead_department_id" class="required">Lead / Accountable Department</label>
                                    <select name="lead_department_id" id="lead_department_id" class="form-control @error('lead_department_id') is-invalid @enderror" required>
                                        <option value="">-- Select accountable department --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" {{ old('lead_department_id', $objective->lead_department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Automatically included as a performer. When additional performers are selected, this department supplies the one authoritative company result.</small>
                                    @error('lead_department_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4"><div class="form-group">
                                <label for="target_year" class="required">Performance Year</label>
                                <select name="target_year" id="target_year" class="form-control @error('target_year') is-invalid @enderror" required>
                                    <option value="">-- Select Year --</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ (string) old('target_year', $objective->target_year) === (string) $year ? 'selected' : '' }}>{{ $year }} EFY</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Approval publishes this objective directly to reporting for this year.</small>
                                @error('target_year')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div></div>
                        </div>

                        @include('objectives._target_schedule', ['objective' => $objective])

                        <div class="form-group">
 @if(auth()->user()->canManageObjectiveFrequency())
 <label for="reporting_frequency">Reporting Frequency</label>
 <select name="reporting_frequency" id="reporting_frequency" class="form-control">
 @foreach(['' => 'Use department default', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
 <option value="{{ $value }}" @selected((old('reporting_frequency', $objective->reporting_frequency) ?? '') === $value)>{{ $label }}</option>
 @endforeach
 </select><small class="text-muted">A blank setting inherits the performer department's default.</small>
 @error('reporting_frequency')<div class="text-danger">{{ $message }}</div>@enderror
 @endif
 </div>
 <div class="row mt-3">
                            <div class="col-md-6"><div class="custom-control custom-switch">
                                <input type="hidden" name="is_calculated_weight" value="0">
                                <input type="checkbox" class="custom-control-input" id="is_calculated_weight" name="is_calculated_weight" value="1" {{ old('is_calculated_weight', $objective->is_calculated_weight) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_calculated_weight">Calculate weight from direct sub-objectives</label>
                            </div></div>
                            <div class="col-md-6"><label for="summary_type">How sub-objective results roll up</label>
                                <select name="summary_type" id="summary_type" class="form-control">
                                    @foreach(['sum' => 'Sum sub-objective results', 'weighted' => 'Weighted achievement', 'average' => 'Average sub-objective results', 'manual_override' => 'Manual main-objective result'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('summary_type', $objective->summary_type) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group mt-3" id="parentRollupOption" style="{{ old('parent_id', $objective->parent_id) ? '' : 'display:none' }}">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="include_in_parent_rollup" value="0">
                                <input type="checkbox" class="custom-control-input" id="include_in_parent_rollup" name="include_in_parent_rollup" value="1" {{ old('include_in_parent_rollup', $objective->include_in_parent_rollup ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="include_in_parent_rollup">Include this sub-objective in its main objective's calculation</label>
                            </div>
                            <small class="text-muted">Uncheck this to keep reporting the sub-objective without affecting the main-objective result.</small>
                        </div>


                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active"
                                    value="1" {{ old('is_active', $objective->is_active) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">Active</label>
                            </div>
                        </div>



                    </div>
                    <div class="card-footer">
                        @if(
                            (auth()->user()->isPlanReportDirectorate() && $objective->status === 'submitted_to_plan_report_directorate')
                            || (auth()->user()->isPlanReportHeadOffice() && $objective->status === 'submitted')
                        )
                            <div class="form-group">
                                <label for="review_change_reason">Reason for reviewer changes <span class="text-danger">*</span></label>
                                <textarea id="review_change_reason" name="review_change_reason" rows="2"
                                    class="form-control @error('review_change_reason') is-invalid @enderror"
                                    required minlength="5" maxlength="2000"
                                    placeholder="Briefly explain what was corrected and why.">{{ old('review_change_reason') }}</textarea>
                                @error('review_change_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="form-text text-muted">This explanation is saved in the permanent revision history and sent to the plan owner.</small>
                            </div>
                        @endif
                        <button type="submit" class="btn btn-primary">Update Objective</button>
                        <a href="{{ route('objectives.index') }}" class="btn btn-default float-right">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        function handleUnitChange() {
            validateAnnualTarget();
        }

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

        function validateAnnualTarget() {
            let unit = $('#unit').val();
            let target = parseFloat($('#annual_target').val());
            let errorEl = $('#targetError');
            let inputEl = $('#annual_target');

            if (unit === 'Percentage (%)' && target > 100) {
                errorEl.removeClass('d-none');
                inputEl.addClass('is-invalid');
            } else {
                errorEl.addClass('d-none');
                inputEl.removeClass('is-invalid');
            }
        }

        $(document).ready(function () {
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
            validateAnnualTarget();
            recalculateQuarterTargets();

            function updateCalculatedWeightState() {
                var calculated = $('#is_calculated_weight').is(':checked');
                $('#weight').prop('required', !calculated).prop('readonly', calculated);
                $('#weightRequired').toggle(!calculated);
            }
            $('#is_calculated_weight').on('change', updateCalculatedWeightState);
            updateCalculatedWeightState();

            $('#parent_id').on('change', function () {
                $('#parentRollupOption').toggle(!!$(this).val());
            });

            // Handle department hierarchy AJAX
            $('#parent_department_id').on('change', function() {
                const parentId = $(this).val();
                const $childSelect = $('#department_id');

                $childSelect.empty().append('<option value="">-- Loading Units... --</option>').prop('disabled', true);

                if (!parentId) {
                    $childSelect.empty().append('<option value="">-- Select Unit / Branch (Optional) --</option>');
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
        });
        $(document).on('input change', '.target-month, #target_distribution', recalculateQuarterTargets);
    </script>
@endpush
