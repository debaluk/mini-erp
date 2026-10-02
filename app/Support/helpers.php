<?php

use App\Models\Entity;

if (! function_exists('current_entity')) {
    function current_entity(): ?Entity
    {
        return once(function (): ?Entity {
            $user = auth()->user();

            if ($user?->entity_id) {
                return Entity::query()->find($user->entity_id);
            }

            return Entity::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        });
    }
}

if (! function_exists('format_id_number')) {
    /**
     * Format angka dengan standar Indonesia:
     * 1 -> 1
     * 1.5 -> 1,5
     * 1000 -> 1.000
     * 1000.5 -> 1.000,5
     */
    function format_id_number($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format(
            (float) $value,
            $decimals,
            ',',
            '.'
        );
    }
}

if (! function_exists('parse_id_number')) {
    /**
     * Parse input angka Indonesia:
     * 10 -> 10
     * 10,50 -> 10.50
     * 10.000 -> 10000
     * 10.000,50 -> 10000.50
     */
    function parse_id_number($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
