@extends('layouts.app')

@section('title', 'System Settings & Customization')

@section('breadcrumb')
    <li class="breadcrumb-item active">Settings</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-3">
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">Customization</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="nav nav-pills flex-column" id="settingsTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="appearance-tab" data-toggle="pill" href="#appearance" role="tab">
                                <i class="fas fa-palette mr-2"></i> Appearance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="sidebar-tab" data-toggle="pill" href="#sidebar_custom" role="tab">
                                <i class="fas fa-columns mr-2"></i> Sidebar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="branding-tab" data-toggle="pill" href="#branding" role="tab">
                                <i class="fas fa-trademark mr-2"></i> Logo & Branding
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="performance-scale-tab" data-toggle="pill" href="#performance-scale" role="tab">
                                <i class="fas fa-traffic-light mr-2"></i> Performance Colors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="advanced-tab" data-toggle="pill" href="#advanced" role="tab">
                                <i class="fas fa-code mr-2"></i> Advanced (CSS)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="workflow-tab" data-toggle="pill" href="#workflow" role="tab">
                                <i class="fas fa-hourglass-half mr-2"></i> Workflow
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-body">
                    <form action="{{ route('settings.reset') }}" method="POST" data-confirm="Reset all UI customizations to their defaults?" data-confirm-title="Reset settings" data-confirm-text="Reset" data-confirm-class="btn-danger">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-block btn-sm">
                            <i class="fas fa-undo mr-2"></i> Reset to Defaults
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-body settings-scroll-area">
                        <div class="tab-content">
                            <!-- Appearance Tab -->
                            <div class="tab-pane fade show active" id="appearance" role="tabpanel">
                                <h5 class="mb-4 font-weight-bold border-bottom pb-2 text-primary">UI Theme & Colors</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Navbar Color</label>
                                            <input type="color" name="navbar_color" class="form-control color-picker"
                                                value="{{ \App\Models\Setting::get('navbar_color', '#ffffff') }}">
                                            <input type="hidden" name="group_navbar_color" value="appearance">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Accent Color (Primary)</label>
                                            <input type="color" name="primary_color" class="form-control color-picker"
                                                value="{{ \App\Models\Setting::get('primary_color', '#007bff') }}">
                                            <input type="hidden" name="group_primary_color" value="appearance">
                                        </div>
                                    </div>
                                    <div class="col-md-12 mt-3">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="dark_mode"
                                                name="dark_mode" value="1"
                                                {{ \App\Models\Setting::get('dark_mode') ? 'checked' : '' }}>
                                            <input type="hidden" name="group_dark_mode" value="appearance">
                                            <label class="custom-control-label" for="dark_mode">Enable Dark Mode
                                                (Global)</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sidebar Tab -->
                            <div class="tab-pane fade" id="sidebar_custom" role="tabpanel">
                                <h5 class="mb-4 font-weight-bold border-bottom pb-2 text-primary">Sidebar Customization</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Background Color</label>
                                            <input type="color" name="sidebar_bg" class="form-control color-picker"
                                                id="sidebar_bg_picker"
                                                value="{{ \App\Models\Setting::get('sidebar_bg', '#343a40') }}">
                                            <input type="hidden" name="group_sidebar_bg" value="sidebar">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Text Color</label>
                                            <input type="color" name="sidebar_text" class="form-control color-picker"
                                                id="sidebar_text_picker"
                                                value="{{ \App\Models\Setting::get('sidebar_text', '#c2c7d0') }}">
                                            <input type="hidden" name="group_sidebar_text" value="sidebar">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Active Item Color</label>
                                            <input type="color" name="sidebar_active" class="form-control color-picker"
                                                value="{{ \App\Models\Setting::get('sidebar_active', '#007bff') }}">
                                            <input type="hidden" name="group_sidebar_active" value="sidebar">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Hover Item Color</label>
                                            <input type="color" name="sidebar_hover" class="form-control color-picker"
                                                value="{{ \App\Models\Setting::get('sidebar_hover', '#494e53') }}">
                                            <input type="hidden" name="group_sidebar_hover" value="sidebar">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Branding Tab -->
                            <div class="tab-pane fade" id="branding" role="tabpanel">
                                <h5 class="mb-4 font-weight-bold border-bottom pb-2 text-primary">Logo & Branding</h5>
                                <div class="form-group">
                                    <label>Current Logo</label>
                                    <div class="mb-3 p-3 bg-light text-center rounded border"
                                        style="min-height: 100px; display: flex; align-items: center; justify-content: center;">
                                        @if(\App\Models\Setting::get('site_logo'))
                                            <img src="{{ asset('storage/' . \App\Models\Setting::get('site_logo')) }}"
                                                style="width: 150px; height: 50px; object-fit: contain;" id="logo-preview">
                                        @else
                                            <img src="{{ asset('vendor/adminlte/dist/img/AdminLTELogo.png') }}"
                                                style="max-height: 80px;" id="logo-preview">
                                        @endif
                                    </div>
                                    <label class="btn btn-outline-primary btn-block">
                                        Upload Horizontal Logo (150x50) <input type="file" name="logo" hidden
                                            onchange="previewImage(this, 'logo-preview')">
                                    </label>
                                    <small class="text-muted d-block mt-1 text-center">Recommended: 150x50 pixels,
                                        Transparent PNG.</small>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-md-9">
                                        <div class="form-group">
                                            <label>Full System Name</label>
                                            <input type="text" name="site_name" class="form-control"
                                                value="{{ \App\Models\Setting::get('site_name', 'IPRPMS') }}">
                                            <input type="hidden" name="group_site_name" value="branding">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Abbreviation</label>
                                            <input type="text" name="site_abbv" class="form-control"
                                                value="{{ \App\Models\Setting::get('site_abbv', 'IPRPMS') }}">
                                            <input type="hidden" name="group_site_abbv" value="branding">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="performance-scale" role="tabpanel">
                                <h5 class="mb-4 font-weight-bold border-bottom pb-2 text-primary">Performance Color Reference</h5>
                                <p class="text-muted">These inclusive lower boundaries and colors are used everywhere: weekly, daily, monthly, EDR, PDF, and Excel reports. Maximum boundaries are derived automatically so ranges cannot overlap.</p>
                                @php
                                    $bandFields = [
                                        'exceptional' => ['Green', 95, 'Exceptional (Very Good) Performance', '#00B050'],
                                        'high' => ['Light Green', 90, 'High Performance', '#92D050'],
                                        'satisfactory' => ['Amber', 85, 'Satisfactory (Medium) Performance', '#FFC000'],
                                        'needs_improvement' => ['Red', 75, 'Needs Improvement (Low Performance)', '#FF0000'],
                                        'unsatisfactory' => ['Dark Red', 0, 'Unsatisfactory (Very Low) Performance', '#C00000'],
                                    ];
                                @endphp
                                @foreach($bandFields as $key => [$colorName, $defaultMin, $defaultLabel, $defaultColor])
                                    <div class="row border rounded mx-0 mb-3 pt-3" style="border-left:6px solid {{ \App\Models\Setting::get('performance_'.$key.'_color', $defaultColor) }} !important">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Minimum (%)</label>
                                                @if($key === 'unsatisfactory')
                                                    <input class="form-control" value="0" readonly>
                                                @else
                                                    <input type="number" step="any" min="0" max="100" name="performance_{{ $key }}_min" class="form-control" required data-original-number="{{ \App\Models\Setting::get('performance_'.$key.'_min', $defaultMin) }}" value="{{ \App\Support\NumberFormatter::format(\App\Models\Setting::get('performance_'.$key.'_min', $defaultMin),3,'.','') }}">
                                                    <input type="hidden" name="group_performance_{{ $key }}_min" value="performance">
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-5"><div class="form-group"><label>{{ $colorName }} Label</label><input type="text" name="performance_{{ $key }}_label" class="form-control" value="{{ \App\Models\Setting::get('performance_'.$key.'_label', $defaultLabel) }}"><input type="hidden" name="group_performance_{{ $key }}_label" value="performance"></div></div>
                                        <div class="col-md-3"><div class="form-group"><label>{{ $colorName }} Color</label><input type="color" name="performance_{{ $key }}_color" class="form-control color-picker" value="{{ \App\Models\Setting::get('performance_'.$key.'_color', $defaultColor) }}"><input type="hidden" name="group_performance_{{ $key }}_color" value="performance"></div></div>
                                        <div class="col-md-2 d-flex align-items-center"><strong>{{ \App\Models\Setting::formatPerformanceBandRange(collect(\App\Models\Setting::getPerformanceScale())->firstWhere('key', $key)) }}</strong></div>
                                    </div>
                                @endforeach
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Reference Row Background</label>
                                            <input type="color" name="performance_reference_bg" class="form-control color-picker"
                                                value="{{ \App\Models\Setting::get('performance_reference_bg', '#cce5ff') }}">
                                            <input type="hidden" name="group_performance_reference_bg" value="performance">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Reference Row Text</label>
                                            <input type="color" name="performance_reference_text" class="form-control color-picker"
                                                value="{{ \App\Models\Setting::get('performance_reference_text', '#004085') }}">
                                            <input type="hidden" name="group_performance_reference_text" value="performance">
                                        </div>
                                    </div>
                                </div>

                                @php
                                    $performanceScale = \App\Models\Setting::getPerformanceScale();
                                @endphp
                                <div class="alert alert-light border d-flex flex-wrap align-items-center mb-0">
                                    @foreach($performanceScale as $band)
                                        <span class="mr-4 mb-2">
                                            <span style="display:inline-block; min-width:34px; padding:4px 10px; border-radius:6px; color:#fff; background:{{ $band['color'] }};">■</span>
                                            {{ \App\Models\Setting::formatPerformanceBandRange($band) }} — {{ $band['label'] }}
                                        </span>
                                    @endforeach
                                    <span class="mb-2">
                                        <span style="background:{{ \App\Models\Setting::get('performance_reference_bg', '#cce5ff') }}; color:{{ \App\Models\Setting::get('performance_reference_text', '#004085') }}; padding:2px 8px; border-radius:3px;">■</span>
                                        Reference / Breakdown Row
                                    </span>
                                </div>
                            </div>

                            <!-- Advanced Tab -->
                            <div class="tab-pane fade" id="advanced" role="tabpanel">
                                <h5 class="mb-4 font-weight-bold border-bottom pb-2 text-primary">Advanced Styling</h5>
                                <div class="form-group">
                                    <label>Custom CSS (Applied globally)</label>
                                    <textarea name="custom_css" class="form-control font-italic" rows="10"
                                        placeholder="/* Enter your CSS here */">{{ \App\Models\Setting::get('custom_css') }}</textarea>
                                    <input type="hidden" name="group_custom_css" value="general">
                                    <p class="text-sm text-muted mt-2">Format: standard CSS. Be careful, this can break your
                                        UI layout.</p>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="workflow" role="tabpanel">
                                <h5 class="mb-4 font-weight-bold border-bottom pb-2 text-primary">Workflow Submission Window</h5>
                                <p class="text-muted">Control the interval when department chiefs are allowed to forward objective performances to the plan and report department.</p>
                                <div class="alert alert-info py-2">
                                    Weekly deadlines measure timeliness only. A late weekly report remains open for backdated submission.
                                </div>
                                <div class="row">
                                    <div class="col-md-12"><div class="form-group"><label>Period Weight Distribution Mode</label><select name="period_weight_distribution_mode" class="form-control"><option value="manual" @selected(\App\Models\Setting::get('period_weight_distribution_mode','manual')==='manual')>Manual expert allocation</option><option value="proportional" @selected(\App\Models\Setting::get('period_weight_distribution_mode','manual')==='proportional')>Automatic proportional normalization</option></select><input type="hidden" name="group_period_weight_distribution_mode" value="workflow"><small class="text-muted">Restricted system setting. Manual mode never overwrites annual weights.</small></div></div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Weekly Deadline — Days After Friday</label>
                                            <input type="number" min="0" max="14" name="weekly_submission_due_days" class="form-control"
                                                data-original-number="{{ \App\Models\Setting::get('weekly_submission_due_days', 3) }}" value="{{ \App\Support\NumberFormatter::format(\App\Models\Setting::get('weekly_submission_due_days', 3),3,'.','') }}">
                                            <input type="hidden" name="group_weekly_submission_due_days" value="workflow">
                                            <small class="text-muted">Default 3 means the following Monday.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Weekly Deadline Time</label>
                                            <input type="time" name="weekly_submission_due_time" class="form-control"
                                                value="{{ \App\Models\Setting::get('weekly_submission_due_time', '17:00') }}">
                                            <input type="hidden" name="group_weekly_submission_due_time" value="workflow">
                                            <small class="text-muted">Uses Africa/Addis_Ababa time.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Submission Window Start</label>
                                            <input type="datetime-local" name="performance_submission_window_start" class="form-control"
                                                value="{{ \App\Models\Setting::get('performance_submission_window_start') ? \Carbon\Carbon::parse(\App\Models\Setting::get('performance_submission_window_start'))->format('Y-m-d\TH:i') : '' }}">
                                            <input type="hidden" name="group_performance_submission_window_start" value="workflow">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Submission Window End</label>
                                            <input type="datetime-local" name="performance_submission_window_end" class="form-control"
                                                value="{{ \App\Models\Setting::get('performance_submission_window_end') ? \Carbon\Carbon::parse(\App\Models\Setting::get('performance_submission_window_end'))->format('Y-m-d\TH:i') : '' }}">
                                            <input type="hidden" name="group_performance_submission_window_end" value="workflow">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-right bg-white border-top">
                        <button type="submit" class="btn btn-primary px-5 shadow-sm font-weight-bold">
                            <i class="fas fa-save mr-2"></i> Save System Customizations
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function previewImage(input, previewId) {
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        $('#' + previewId).attr('src', e.target.result);
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }

            $(document).ready(function () {
                // Live Preview for Sidebar
                $('#sidebar_bg_picker').on('input', function () {
                    $('.main-sidebar').css('background-color', $(this).val());
                });
                $('#sidebar_text_picker').on('input', function () {
                    $('.nav-sidebar .nav-link, .brand-text').css('color', $(this).val());
                });
            });
        </script>
    @endpush
    @push('styles')
        <style>
            .settings-scroll-area { max-height: calc(100vh - 205px); overflow-y: auto; overflow-x: hidden; scrollbar-gutter: stable; }
            #settingsTab { max-height: calc(100vh - 260px); overflow-y: auto; }
            @media (max-width: 767.98px) { .settings-scroll-area, #settingsTab { max-height: none; overflow: visible; } }
        </style>
    @endpush
@endsection
