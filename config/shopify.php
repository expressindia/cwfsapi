<?php

/*
|--------------------------------------------------------------------------
| Shopify Integration
|--------------------------------------------------------------------------
*/

return [

    'shop' => env('SHOPIFY_SHOP'),

    'store_domain' => env('SHOPIFY_STORE_DOMAIN'),

    'client_id' => env('SHOPIFY_API_KEY'),

    'client_secret' => env('SHOPIFY_API_SECRET'),

    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),

    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),

    /*
    |--------------------------------------------------------------------------
    | Standalone Shopify OAuth
    |--------------------------------------------------------------------------
    */

    'scopes' => env('SHOPIFY_SCOPES'),

    'redirect_uri' => env( 'SHOPIFY_REDIRECT_URI', 'https://snd.cwfullscriptapi.com/shopify/callback' ),

    /*
    |--------------------------------------------------------------------------
    | Shopify Webhook
    |--------------------------------------------------------------------------
    */

    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Inventory location
    |--------------------------------------------------------------------------
    */

    'inventory_location_id' => env('SHOPIFY_INVENTORY_LOCATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Shopify Fulfillment Service
    |--------------------------------------------------------------------------
    */

    'fulfillment' => [
        'service_name' => env( 'SHOPIFY_FULFILLMENT_SERVICE_NAME', 'FSWarehouse' ),
        'callback_url' => env('SHOPIFY_FULFILLMENT_CALLBACK_URL'),
        'location_id' => env('SHOPIFY_FULFILLMENT_LOCATION_ID'),

    ],

];