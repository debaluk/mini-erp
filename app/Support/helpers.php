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
