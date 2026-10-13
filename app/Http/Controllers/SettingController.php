<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\AuditLog;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group');
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'performance_exceptional_min' => 'nullable|numeric|min:0|max:100',
            'performance_high_min' => 'nullable|numeric|min:0|max:100|lt:performance_exceptional_min',
            'performance_satisfactory_min' => 'nullable|numeric|min:0|max:100|lt:performance_high_min',
            'performance_needs_improvement_min' => 'nullable|numeric|min:0|max:100|lt:performance_satisfactory_min',
            'weekly_submission_due_days' => 'nullable|integer|min:0|max:14',
            'weekly_submission_due_time' => 'nullable|date_format:H:i',
            'period_weight_distribution_mode' => 'nullable|in:manual,proportional',
        ]);
        $data = $request->except(['_token', '_method', 'logo', 'favicon']);

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'group_')) continue;
            Setting::set($key, $value, $request->input("group_{$key}", 'general'));
        }

        // Handle Logo Upload
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding', 'public');
            Setting::set('site_logo', $path, 'branding', 'file');
        }

        // Handle Favicon
        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('branding', 'public');
            Setting::set('site_favicon', $path, 'branding', 'file');
        }

        AuditLog::log('updated', null, "Updated system appearance settings");

        return back()->with('success', 'Settings updated successfully.');
    }

    public function reset()
    {
        Setting::truncate();
        Setting::clearCache();
        return back()->with('success', 'Settings reset to defaults.');
    }
}
