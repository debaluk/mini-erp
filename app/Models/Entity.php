<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
