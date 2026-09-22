<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $fillable = [
        'entity_id',
        'business_unit_id',
        'code',
        'name',
        'type',
        'address',
        'is_active',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'business_unit_id' => 'integer',
        'is_active' => 'boolean',
    ];
}
