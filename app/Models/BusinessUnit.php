<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessUnit extends Model
{
    protected $table = 'business_units';

    protected $fillable = [
        'entity_id',
        'code',
        'name',
        'business_type',
        'hpp_method',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
