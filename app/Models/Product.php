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
        'category_id',
        'base_unit_id',
        'item_type',
        'type',
        'sku',
        'barcode',
        'cost_price',
        'selling_price',
        'minimum_stock',
        'manage_stock',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:4',
        'selling_price' => 'decimal:4',
        'minimum_stock' => 'decimal:3',
        'manage_stock' => 'boolean',
        'is_active' => 'boolean',
    ];
}
