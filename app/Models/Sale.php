<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $table = 'sales';

    protected $fillable = [
        'entity_id',
        'business_unit_id',
        'customer_id',
        'user_id',
        'shift_id',
        'invoice_no',
        'sale_date',
        'due_date',
        'subtotal',
        'discount',
        'total',
        'memo',
        'status',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'business_unit_id' => 'integer',
        'customer_id' => 'integer',
        'user_id' => 'integer',
        'shift_id' => 'integer',
        'sale_date' => 'datetime',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
