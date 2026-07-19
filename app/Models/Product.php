<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
