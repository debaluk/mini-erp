<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $table = 'sale_items';

    protected $fillable = [
        'sale_id',
        'product_id',
        'unit_id',
        'qty',
        'conversion_factor',
        'base_qty',
        'unit_price',
        'base_unit_cost',
        'discount',
        'total',
        'hpp_unit',
        'hpp_total',
    ];

    protected $casts = [
        'sale_id' => 'integer',
        'product_id' => 'integer',
        'unit_id' => 'integer',
        'qty' => 'decimal:3',
        'conversion_factor' => 'decimal:9',
        'base_qty' => 'decimal:9',
        'unit_price' => 'decimal:4',
        'base_unit_cost' => 'decimal:4',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'hpp_unit' => 'decimal:4',
        'hpp_total' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
