<?php

namespace App\\Helpers;

class FormatHelper
{
    /**
     * Format displayed numeric values consistently in Indonesian notation.
     * All displayed numbers use exactly two decimal places.
     */
    public static function indo($value, int $decimals = 2, bool $prefixRp = false): string
    {
        $formatted = number_format((float) ($value ?? 0), 2, ',', '.');

        return $prefixRp ? 'Rp ' . $formatted : $formatted;
    }

    public static function parse($value): float
    {
        if ($value === null || trim((string) $value) === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value);

        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
