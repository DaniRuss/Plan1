<?php

namespace App\Services;

use App\Models\Setting;

class PerformanceLevel
{
    /**
     * Corporate performance scale from "Performance level.docx".
     * Boundaries are deliberately exhaustive while preserving the document's
     * wording: "above 90" and "above 85" are strict lower bounds.
     */
    public function for(?float $achievement): array
    {
        if ($achievement === null) {
            return [
                'key' => 'not_applicable',
                'label' => 'Not Applicable',
                'shortLabel' => 'N/A',
                'colorName' => 'Grey',
                'hex' => '#6C757D',
                'textHex' => '#FFFFFF',
            ];
        }

        try {
            $band = Setting::getPerformanceBand($achievement);
        } catch (\Throwable) {
            // Keep calculators usable in isolated/unit contexts where Laravel's
            // settings table is intentionally unavailable.
            $band = match (true) {
                $achievement >= 95 => ['key' => 'exceptional', 'label' => 'Exceptional (Very Good) Performance', 'short_label' => 'Exceptional', 'color_name' => 'Green', 'color' => '#00B050', 'text_color' => '#FFFFFF'],
                $achievement >= 90 => ['key' => 'high', 'label' => 'High Performance', 'short_label' => 'High', 'color_name' => 'Light Green', 'color' => '#92D050', 'text_color' => '#1F1F1F'],
                $achievement >= 85 => ['key' => 'satisfactory', 'label' => 'Satisfactory (Medium) Performance', 'short_label' => 'Satisfactory', 'color_name' => 'Amber', 'color' => '#FFC000', 'text_color' => '#1F1F1F'],
                $achievement >= 75 => ['key' => 'needs_improvement', 'label' => 'Needs Improvement (Low Performance)', 'short_label' => 'Needs Improvement', 'color_name' => 'Red', 'color' => '#FF0000', 'text_color' => '#FFFFFF'],
                default => ['key' => 'unsatisfactory', 'label' => 'Unsatisfactory (Very Low) Performance', 'short_label' => 'Unsatisfactory', 'color_name' => 'Dark Red', 'color' => '#C00000', 'text_color' => '#FFFFFF'],
            };
        }
        return $this->level($band['key'], $band['label'], $band['short_label'], $band['color_name'], $band['color'], $band['text_color']);
    }

    private function level(
        string $key,
        string $label,
        string $shortLabel,
        string $colorName,
        string $hex,
        string $textHex = '#FFFFFF'
    ): array {
        return compact('key', 'label', 'shortLabel', 'colorName', 'hex', 'textHex');
    }
}
