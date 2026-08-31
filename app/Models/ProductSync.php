<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSync extends Model
{
    protected $fillable = [
        'fullscript_product_id',
        'shopify_product_id',
        'shopify_handle',
        'shopify_product_status',
        'status',
        'sync_attempts',
        'last_error',
        'last_synced_at',
    ];

    protected $casts = [
        'sync_attempts' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(
            ProductVariant::class,
            'fullscript_product_id',
            'fullscript_product_id'
        );
    }
}