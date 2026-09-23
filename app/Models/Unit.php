<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $table = 'units';

    protected $fillable = [
        'entity_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function productsUsingAsBaseUnit(): HasMany
    {
        return $this->hasMany(Product::class, 'base_unit_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
