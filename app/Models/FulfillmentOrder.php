<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfillmentOrder extends Model
{
    protected $fillable = [
    'shopify_order_id',
    'shopify_order_name',
    'shopify_fulfillment_order_id',
    'shopify_fulfillment_id',
    'fullscript_order_id',
    'status',
    'fullscript_response',
    'tracking_data',
];

    protected $casts = [
        'fullscript_response' => 'array',
        'tracking_data' => 'array',
    ];
}