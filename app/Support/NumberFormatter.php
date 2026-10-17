<?php

namespace App\Support;

final class NumberFormatter
{
    /** Format display values only; stored numbers and calculations retain precision. */
    public static function format($value, int $maximumDecimals = 3, string $decimalSeparator = '.', string $thousandsSeparator = ','): string
    {
        if ($value === null || $value === '') return '';
        if (!is_numeric($value)) return (string) $value;
        $precision = min(3, max(0, $maximumDecimals));
        $rounded = round((float) $value, $precision, PHP_ROUND_HALF_UP);
        if ($rounded == 0) $rounded = 0;
        $formatted = number_format($rounded, $precision, $decimalSeparator, $thousandsSeparator);
        return $precision ? rtrim(rtrim($formatted, '0'), $decimalSeparator) : $formatted;
    }
}
