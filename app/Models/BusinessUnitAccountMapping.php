<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessUnitAccountMapping extends Model
{
    protected $fillable = ['entity_id', 'business_unit_id', 'account_id', 'mapping_key'];
}