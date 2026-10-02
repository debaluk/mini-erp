<?php

namespace App\Helpers;

class FormatHelper
{
    public static function indo($value, int $decimals = 0, bool $prefixRp = false): string
    {
        if ($value === null || $value === '') {
            return $prefixRp ? 'Rp 0' : '0';
        }

        $formatted = number_format((float) $value, $decimals, ',', '.');

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
