<?php

use App\Helpers\FormatHelper;
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

if (! class_exists('FormatIndo')) {
    class_alias(FormatHelper::class, 'FormatIndo');
}

if (! function_exists('format_id_number')) {
    function format_id_number($value, int $decimals = 2): string
    {
        return FormatHelper::indo($value, $decimals);
    }
}

if (! function_exists('parse_id_number')) {
    function parse_id_number($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return FormatHelper::parse($value);
    }
}
