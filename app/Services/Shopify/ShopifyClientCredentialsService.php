<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopifyClientCredentialsService
{
    /**
     * Get a valid Shopify Client Credentials access token.
     */
    public function getAccessToken(): string
    {
        $shopDomain = config('shopify.shop_domain');
        $clientId = config('shopify.client_id');
        $clientSecret = config('shopify.client_secret');

        if (!$shopDomain) {
            throw new RuntimeException('Shopify store domain is not configured.');
        }

        if (!$clientId) {
            throw new RuntimeException('Shopify client ID is not configured.');
        }

        if (!$clientSecret) {
            throw new RuntimeException('Shopify client secret is not configured.');
        }

        $cacheKey = 'shopify.client_credentials_token.' . $shopDomain;

        return Cache::remember(
            $cacheKey,
            now()->addHours(23),
            function () use ($shopDomain, $clientId, $clientSecret) {
                $response = Http::asForm()
                    ->timeout(30)
                    ->post(
                        "https://{$shopDomain}/admin/oauth/access_token",
                        [
                            'grant_type' => 'client_credentials',
                            'client_id' => $clientId,
                            'client_secret' => $clientSecret,
                        ]
                    );

                if ($response->failed()) {
                    throw new RuntimeException(
                        'Unable to obtain Shopify Client Credentials token. ' .
                        'HTTP ' . $response->status() . ': ' .
                        $response->body()
                    );
                }

                $token = $response->json('access_token');

                if (!$token) {
                    throw new RuntimeException(
                        'Shopify Client Credentials response did not contain an access token.'
                    );
                }

                return $token;
            }
        );
    }

    /**
     * Clear the cached Shopify access token.
     */
    public function clearToken(): void
    {
        $shopDomain = config('shopify.shop_domain');

        Cache::forget(
            'shopify.client_credentials_token.' . $shopDomain
        );
    }
}