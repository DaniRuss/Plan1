<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type'];

    /** @var array<string, mixed>|null */
    protected static ?array $resolvedValues = null;

    public static function getPerformanceScale(): array
    {
        return [
            [
                'key' => 'exceptional', 'min' => (float) self::get('performance_exceptional_min', 95),
                'label' => self::get('performance_exceptional_label', 'Exceptional (Very Good) Performance'),
                'short_label' => self::get('performance_exceptional_short_label', 'Exceptional'),
                'color_name' => 'Green', 'color' => self::get('performance_exceptional_color', '#00B050'),
                'text_color' => self::get('performance_exceptional_text_color', '#FFFFFF'),
            ],
            [
                'key' => 'high', 'min' => (float) self::get('performance_high_min', 90),
                'label' => self::get('performance_high_label', 'High Performance'),
                'short_label' => self::get('performance_high_short_label', 'High'),
                'color_name' => 'Light Green', 'color' => self::get('performance_high_color', '#92D050'),
                'text_color' => self::get('performance_high_text_color', '#1F1F1F'),
            ],
            [
                'key' => 'satisfactory', 'min' => (float) self::get('performance_satisfactory_min', 85),
                'label' => self::get('performance_satisfactory_label', 'Satisfactory (Medium) Performance'),
                'short_label' => self::get('performance_satisfactory_short_label', 'Satisfactory'),
                'color_name' => 'Amber', 'color' => self::get('performance_satisfactory_color', '#FFC000'),
                'text_color' => self::get('performance_satisfactory_text_color', '#1F1F1F'),
            ],
            [
                'key' => 'needs_improvement', 'min' => (float) self::get('performance_needs_improvement_min', 75),
                'label' => self::get('performance_needs_improvement_label', 'Needs Improvement (Low Performance)'),
                'short_label' => self::get('performance_needs_improvement_short_label', 'Needs Improvement'),
                'color_name' => 'Red', 'color' => self::get('performance_needs_improvement_color', '#FF0000'),
                'text_color' => self::get('performance_needs_improvement_text_color', '#FFFFFF'),
            ],
            [
                'key' => 'unsatisfactory', 'min' => 0.0,
                'label' => self::get('performance_unsatisfactory_label', 'Unsatisfactory (Very Low) Performance'),
                'short_label' => self::get('performance_unsatisfactory_short_label', 'Unsatisfactory'),
                'color_name' => 'Dark Red', 'color' => self::get('performance_unsatisfactory_color', '#C00000'),
                'text_color' => self::get('performance_unsatisfactory_text_color', '#FFFFFF'),
            ],
        ];
    }

    public static function getPerformanceBand(?float $percentage): array
    {
        $scale = self::getPerformanceScale();

        if ($percentage === null) return ['key' => 'not_applicable', 'min' => 0, 'label' => 'Not Applicable', 'short_label' => 'N/A', 'color_name' => 'Grey', 'color' => '#6C757D', 'text_color' => '#FFFFFF'];

        foreach ($scale as $band) {
            if ($percentage >= $band['min']) return $band;
        }
        return $scale[array_key_last($scale)];
    }

    public static function formatPerformanceBandRange(array $band): string
    {
        $min = rtrim(rtrim(number_format((float) $band['min'], 2, '.', ''), '0'), '.');
        $scale = self::getPerformanceScale();
        $index = array_search($band['key'], array_column($scale, 'key'), true);
        if ($index === 0) return '≥ ' . $min . '%';
        $upper = rtrim(rtrim(number_format((float) $scale[$index - 1]['min'], 2, '.', ''), '0'), '.');
        return $min . '% – < ' . $upper . '%';
    }

    /**
     * Get a setting value by key.
     */
    public static function get($key, $default = null)
    {
        if (self::$resolvedValues === null) {
            self::$resolvedValues = Cache::rememberForever(
                'settings.all',
                fn () => self::query()->pluck('value', 'key')->all()
            );
        }

        return array_key_exists($key, self::$resolvedValues)
            ? self::$resolvedValues[$key]
            : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set($key, $value, $group = 'general', $type = 'text')
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'type' => $type]
        );

        self::$resolvedValues = null;
        Cache::forget('settings.all');
        Cache::forget("setting.{$key}");

        return $setting;
    }

    /**
     * Clear all settings cache.
     */
    public static function clearCache()
    {
        self::$resolvedValues = null;
        Cache::flush();
    }
}
