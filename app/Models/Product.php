<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'entity_id' => 'integer',
        'category_id' => 'integer',
        'base_unit_id' => 'integer',
        'cost_price' => 'decimal:4',
        'selling_price' => 'decimal:4',
        'minimum_stock' => 'decimal:3',
        'manage_stock' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function businessUnits(): BelongsToMany
    {
        return $this->belongsToMany(BusinessUnit::class, 'product_business_units');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
