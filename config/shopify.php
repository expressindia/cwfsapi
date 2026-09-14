<?php

/*
|--------------------------------------------------------------------------
| Existing Shopify integration
|--------------------------------------------------------------------------
| This project intentionally does not implement Shopify OAuth. Populate
| these values from your existing Shopify CLI/custom-app authentication flow,
| or replace this config's consumers with your established token provider.
*/

return [
    'shop' => env('SHOPIFY_SHOP'),
    'store_domain' => env('SHOPIFY_STORE_DOMAIN'),
    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
    'api_version' => env( 'SHOPIFY_API_VERSION', '2026-07' ),

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
    'inventory_location_id' => env( 'SHOPIFY_INVENTORY_LOCATION_ID' ),


    /*
    |--------------------------------------------------------------------------
    | Shopify Fulfillment Service
    |--------------------------------------------------------------------------
    */
    


    'fulfillment' => [

        'service_name' => env(
            'SHOPIFY_FULFILLMENT_SERVICE_NAME',
            'FS-Warehouse'
        ),
        'callback_url' => env(
            'SHOPIFY_FULFILLMENT_CALLBACK_URL'
        ),

        'location_id' => env(
            'SHOPIFY_FULFILLMENT_LOCATION_ID'
        ),

    ],


];
