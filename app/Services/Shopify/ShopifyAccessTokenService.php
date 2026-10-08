<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use RuntimeException;

class ShopifyAccessTokenService
{
    /**
     * Get the Shopify access token for the configured store.
     *
     * Both development and production stores use the
     * shopify_tokens database table.
     */
    public function getAccessToken(): string
    {
        $shopDomain = config('shopify.store_domain');

        if (blank($shopDomain)) {
            throw new RuntimeException(
                'Shopify store domain is not configured.'
            );
        }

        $shopifyToken = ShopifyToken::query()
            ->where('shop_domain', $shopDomain)
            ->latest('id')
            ->first();

        if (! $shopifyToken) {
            throw new RuntimeException(
                "No Shopify access token found for store [{$shopDomain}]."
            );
        }

        if (blank($shopifyToken->access_token)) {
            throw new RuntimeException(
                "Shopify access token is empty for store [{$shopDomain}]."
            );
        }

        return $shopifyToken->access_token;
    }

    /**
     * Get the Shopify token record for the configured store.
     */
    public function getToken(): ShopifyToken
    {
        $shopDomain = config('shopify.store_domain');

        if (blank($shopDomain)) {
            throw new RuntimeException(
                'Shopify store domain is not configured.'
            );
        }

        $shopifyToken = ShopifyToken::query()
            ->where('shop_domain', $shopDomain)
            ->latest('id')
            ->first();

        if (! $shopifyToken) {
            throw new RuntimeException(
                "No Shopify token found for store [{$shopDomain}]."
            );
        }

        return $shopifyToken;
    }
}