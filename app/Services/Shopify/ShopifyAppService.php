<?php

namespace App\Services\Shopify;

use Shopify\App\ShopifyApp;

class ShopifyAppService
{
    protected ShopifyApp $shopify;

    public function __construct()
    {
        $this->shopify = new ShopifyApp(
            clientId: (string) config('shopify.client_id'),
            clientSecret: (string) config('shopify.client_secret'),
        );
    }

    public function getApp(): ShopifyApp
    {
        return $this->shopify;
    }

    /**
     * Convert Laravel request into Shopify request format.
     */
    public function toShopifyRequest($request): array
    {
        return [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'url' => $request->fullUrl(),
            'body' => $request->getContent(),
        ];
    }
}