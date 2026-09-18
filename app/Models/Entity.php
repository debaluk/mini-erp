<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Entity extends Model
{
    protected $table = 'entities';

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'email',
        'is_active',
        'npwp',
        'nib',
        'logo_path',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $entity): void {
            if (!empty($entity->code)) {
                return;
            }

            do {
                $code = now()->format('Ym') . strtoupper(Str::random(6));
            } while (static::where('code', $code)->exists());

            $entity->code = $code;
        });
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
