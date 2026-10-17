@php
    $record = $objective ?? null;
    $months = [
        'july' => 'July', 'august' => 'August', 'september' => 'September',
        'october' => 'October', 'november' => 'November', 'december' => 'December',
        'january' => 'January', 'february' => 'February', 'march' => 'March',
        'april' => 'April', 'may' => 'May', 'june' => 'June',
    ];
    $quarters = ['q1' => 'Q1', 'q2' => 'Q2', 'q3' => 'Q3', 'q4' => 'Q4'];
@endphp

<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title">Annual Target Distribution (July–June)</h3>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label for="target_distribution">How targets accumulate</label>
            <select name="target_distribution" id="target_distribution" class="form-control">
                <option value="sum" {{ old('target_distribution', $record->target_distribution ?? 'checkpoint') === 'sum' ? 'selected' : '' }}>Flow / Sum — quarter equals the sum of its months</option>
                <option value="checkpoint" {{ old('target_distribution', $record->target_distribution ?? 'checkpoint') === 'checkpoint' ? 'selected' : '' }}>Cumulative / Checkpoint — quarter equals its final month</option>
                <option value="manual" {{ old('target_distribution', $record->target_distribution ?? 'checkpoint') === 'manual' ? 'selected' : '' }}>Manual — quarter targets are entered separately</option>
            </select>
            <small class="text-muted">Use Sum for transported tons, revenue, trips, kilometres, and other period totals. Use Checkpoint for percentages, availability, speed, and cumulative project progress.</small>
        </div>

        <div class="form-group">
            <label for="weekly_plan_mode">How the monthly plan becomes a weekly plan</label>
            <select name="weekly_plan_mode" id="weekly_plan_mode" class="form-control">
                <option value="distribute" {{ old('weekly_plan_mode', $record->weekly_plan_mode ?? 'distribute') === 'distribute' ? 'selected' : '' }}>
                    Distribute the monthly plan equally across the weeks in that month
                </option>
                <option value="repeat" {{ old('weekly_plan_mode', $record->weekly_plan_mode ?? 'distribute') === 'repeat' ? 'selected' : '' }}>
                    Repeat the full monthly plan in every week of that month
                </option>
            </select>
            <small class="text-muted">This setting applies to this objective or sub-objective only. Repeated weekly plans are fixed during performance entry.</small>
        </div>

        @php
            $quarterGroups = [
                'q1' => ['july' => 'July', 'august' => 'August', 'september' => 'September'],
                'q2' => ['october' => 'October', 'november' => 'November', 'december' => 'December'],
                'q3' => ['january' => 'January', 'february' => 'February', 'march' => 'March'],
                'q4' => ['april' => 'April', 'may' => 'May', 'june' => 'June'],
            ];
        @endphp
        <div class="row mx-n2">
            @foreach($quarterGroups as $quarter => $quarterMonths)
                <div class="col-xl-3 col-md-6 px-2 mb-3">
                    <div class="border rounded h-100 p-3 bg-light">
                        <h6 class="text-info font-weight-bold border-bottom pb-2 mb-3">{{ strtoupper($quarter) }}</h6>
                        <div class="row mx-n1">
                            @foreach($quarterMonths as $key => $label)
                                <div class="col-4 px-1">
                                    <label class="small mb-1" for="{{ $key }}_target">{{ $label }}</label>
                                    <input type="number" step="any" min="0" name="{{ $key }}_target" id="{{ $key }}_target"
                                        class="form-control form-control-sm target-month"
                                        data-original-number="{{ old($key . '_target', $record?->{$key . '_target'}) }}" value="{{ \App\Support\NumberFormatter::format(old($key . '_target', $record?->{$key . '_target'}),3,'.','') }}">
                                </div>
                            @endforeach
                        </div>
                        <div class="form-group mb-0 mt-3">
                            <label class="small font-weight-bold mb-1" for="{{ $quarter }}_target">{{ strtoupper($quarter) }} Target</label>
                            <input type="number" step="any" min="0" name="{{ $quarter }}_target" id="{{ $quarter }}_target"
                                class="form-control font-weight-bold quarter-target"
                                data-original-number="{{ old($quarter . '_target', $record?->{$quarter . '_target'}) }}" value="{{ \App\Support\NumberFormatter::format(old($quarter . '_target', $record?->{$quarter . '_target'}),3,'.','') }}">
                            <small class="quarter-calculation-note text-muted"></small>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
