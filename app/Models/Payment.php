<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'entity_id',
        'business_unit_id',
        'sale_id',
        'user_id',
        'payment_date',
        'method',
        'amount',
        'paid_amount',
        'change_amount',
        'reference',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'business_unit_id' => 'integer',
        'sale_id' => 'integer',
        'user_id' => 'integer',
        'payment_date' => 'datetime',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
