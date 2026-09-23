<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'entity_id',
        'business_unit_id',
        'warehouse_id',
        'product_id',
        'unit_id',
        'transaction_qty',
        'conversion_factor',
        'movement_type',
        'qty',
        'unit_cost',
        'reference_type',
        'reference_id',
        'occurred_at',
        'created_by',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'business_unit_id' => 'integer',
        'warehouse_id' => 'integer',
        'product_id' => 'integer',
        'unit_id' => 'integer',
        'transaction_qty' => 'decimal:9',
        'conversion_factor' => 'decimal:9',
        'qty' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'reference_id' => 'integer',
        'occurred_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
