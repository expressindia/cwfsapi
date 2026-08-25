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
    'store_domain' => env('SHOPIFY_STORE_DOMAIN'),
    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
];
