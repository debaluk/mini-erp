<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $table = 'sales_return_items';

    protected $fillable = [
        'sales_return_id',
        'sale_item_id',
        'product_id',
        'unit_id',
        'qty',
        'conversion_factor',
        'base_qty',
        'unit_price',
        'return_value',
        'hpp_unit',
        'hpp_total',
        'condition',
    ];

    protected $casts = [
        'qty'               => 'decimal:3',
        'conversion_factor' => 'decimal:9',
        'base_qty'          => 'decimal:9',
        'unit_price'        => 'decimal:4',
        'return_value'      => 'decimal:2',
        'hpp_unit'          => 'decimal:4',
        'hpp_total'         => 'decimal:2',
    ];

    /**
     * Relasi ke Header Sales Return
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    /**
     * Relasi ke Baris Item Penjualan Asal
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    /**
     * Relasi ke Master Katalog Produk
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relasi ke Satuan Barang (UOM)
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
