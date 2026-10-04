<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    private static array $days = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    private static array $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public static function formatIndo($date): string
    {
        if (!$date) return '-';
        $c = Carbon::parse($date);
        return sprintf('%02d %s %d', $c->day, self::$months[$c->month], $c->year);
    }

    public static function formatIndoFull($date): string
    {
        if (!$date) return '-';
        $c = Carbon::parse($date);
        $dayName = self::$days[$c->format('l')] ?? '';
        return sprintf('%s, %02d %s %d', $dayName, $c->day, self::$months[$c->month], $c->year);
    }

    public static function formatIndoDateTime($date, bool $showTimezone = true): string
    {
        if (!$date) return '-';
        $c = Carbon::parse($date);
        $formatted = sprintf('%02d %s %d %s', $c->day, self::$months[$c->month], $c->year, $c->format('H:i'));
        return $showTimezone ? $formatted . ' WIB' : $formatted;
    }

    public static function formatCompact($date = null): string
    {
        return Carbon::parse($date ?? now())->format('Ymd');
    }

    public static function formatShort($date): string
    {
        if (!$date) return '-';
        return Carbon::parse($date)->format('d/m/Y');
    }
}
