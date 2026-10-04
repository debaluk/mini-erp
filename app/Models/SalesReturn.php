<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    protected $table = 'sales_returns';

    protected $fillable = [
        'entity_id',
        'business_unit_id',
        'sale_id',
        'customer_id',
        'warehouse_id',
        'user_id',
        'return_no',
        'return_date',
        'total',
        'reason',
        'status',
    ];

    protected $casts = [
        'return_date' => 'datetime',
        'total'       => 'decimal:2',
    ];

    /**
     * Relasi ke Entitas
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Relasi ke Unit Bisnis Multi-Tenant
     */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Relasi ke Invoice Penjualan Asal
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Relasi ke Pelanggan
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relasi ke Gudang Penerima Stok Retur
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relasi ke User Operator / Kasir
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Rincian Item Barang yang Diretur
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class, 'sales_return_id');
    }
}
