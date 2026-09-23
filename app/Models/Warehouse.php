<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $fillable = [
        'entity_id',
        'business_unit_id',
        'code',
        'name',
        'type',
        'address',
        'is_active',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'business_unit_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }
}
