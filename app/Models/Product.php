<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'price_thb' => 'integer',
        'sort_order' => 'integer',
        'limited_qty' => 'integer',
        'is_public' => 'boolean',
        'coming_soon' => 'boolean',
    ];
}
