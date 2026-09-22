<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'entity_id',
        'code',
        'name',
        'base_unit_id',
        'item_type',
        'sku',
        'barcode',
        'minimum_stock',
        'manage_stock',
        'is_active',
    ];

    protected $casts = [
        'minimum_stock' => 'decimal:3',
        'manage_stock' => 'boolean',
        'is_active' => 'boolean',
    ];
}
