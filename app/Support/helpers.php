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
if (! function_exists('decimal_input')) {
    function decimal_input($value, int $precision = 6): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;
        $formatted = number_format($number, $precision, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
