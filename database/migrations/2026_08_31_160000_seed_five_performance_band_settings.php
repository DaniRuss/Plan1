<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'performance_exceptional_min' => 95, 'performance_exceptional_label' => 'Exceptional (Very Good) Performance', 'performance_exceptional_short_label' => 'Exceptional', 'performance_exceptional_color' => '#00B050', 'performance_exceptional_text_color' => '#FFFFFF',
            'performance_high_min' => 90, 'performance_high_label' => 'High Performance', 'performance_high_short_label' => 'High', 'performance_high_color' => '#92D050', 'performance_high_text_color' => '#1F1F1F',
            'performance_satisfactory_min' => 85, 'performance_satisfactory_label' => 'Satisfactory (Medium) Performance', 'performance_satisfactory_short_label' => 'Satisfactory', 'performance_satisfactory_color' => '#FFC000', 'performance_satisfactory_text_color' => '#1F1F1F',
            'performance_needs_improvement_min' => 75, 'performance_needs_improvement_label' => 'Needs Improvement (Low Performance)', 'performance_needs_improvement_short_label' => 'Needs Improvement', 'performance_needs_improvement_color' => '#FF0000', 'performance_needs_improvement_text_color' => '#FFFFFF',
            'performance_unsatisfactory_label' => 'Unsatisfactory (Very Low) Performance', 'performance_unsatisfactory_short_label' => 'Unsatisfactory', 'performance_unsatisfactory_color' => '#C00000', 'performance_unsatisfactory_text_color' => '#FFFFFF',
        ];
        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'group' => 'performance', 'type' => str_contains($key, 'color') ? 'color' : 'text', 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'performance_exceptional_min', 'performance_exceptional_label', 'performance_exceptional_short_label', 'performance_exceptional_color', 'performance_exceptional_text_color',
            'performance_high_min', 'performance_high_label', 'performance_high_short_label', 'performance_high_color', 'performance_high_text_color',
            'performance_satisfactory_min', 'performance_satisfactory_label', 'performance_satisfactory_short_label', 'performance_satisfactory_color', 'performance_satisfactory_text_color',
            'performance_needs_improvement_min', 'performance_needs_improvement_label', 'performance_needs_improvement_short_label', 'performance_needs_improvement_color', 'performance_needs_improvement_text_color',
            'performance_unsatisfactory_label', 'performance_unsatisfactory_short_label', 'performance_unsatisfactory_color', 'performance_unsatisfactory_text_color',
        ])->delete();
    }
};
