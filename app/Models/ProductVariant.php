<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'fullscript_product_id',
        'fullscript_variant_id',
        'sku',
        'shopify_product_id',
        'shopify_product_status',
        'shopify_variant_id',
        'shopify_inventory_item_id',
        'fullscript_quantity',
        'shopify_quantity',
        'status',
        'sync_attempts',
        'last_error',
        'last_synced_at',
    ];

    protected $casts = [
        'fullscript_quantity' => 'integer',
        'shopify_quantity' => 'integer',
        'sync_attempts' => 'integer',
        'last_synced_at' => 'datetime',
    ];
}