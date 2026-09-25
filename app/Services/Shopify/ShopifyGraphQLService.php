<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopifyGraphQLService
{
    protected string $storeDomain;
    protected string $apiVersion;
    protected string $accessToken;

    public function __construct()
    {
        $this->storeDomain = (string) config( 'shopify.store_domain' );

        $this->apiVersion = (string) config( 'shopify.api_version', '2026-07' );

        $this->accessToken = (string) config( 'shopify.access_token' );

        if ( empty($this->storeDomain) || empty($this->accessToken) ) {
            throw new RuntimeException(
                'Shopify credentials are not configured.'
            );
        }
    }

    public function execute( string $query, array $variables = [] ): array {

        $url = sprintf( 'https://%s/admin/api/%s/graphql.json', $this->storeDomain, $this->apiVersion );

        $response = Http::timeout(60)
            ->retry(
                3,
                1000,
                throw: false
            )
            ->withHeaders([ 'Content-Type' => 'application/json', 'X-Shopify-Access-Token' => $this->accessToken, ])
            ->post($url, [ 'query' => $query, 'variables' => $variables, ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Shopify HTTP error: ' .
                $response->status() .
                ' ' .
                $response->body()
            );
        }

        $body = $response->json();

        if (!empty($body['errors'])) {
            throw new RuntimeException(
                'Shopify GraphQL error: ' .
                json_encode(
                    $body['errors'],
                    JSON_PRETTY_PRINT
                )
            );
        }

        return $body['data'] ?? [];
    }


    /**
     * Execute a Shopify GraphQL query using store-specific credentials.
     *
     * This is used for multi-store authenticated operations where the
     * Shopify store and access token come from the authenticated session.
     */
    public function executeWithCredentials(
        string $storeDomain,
        string $accessToken,
        string $query,
        array $variables = []
    ): array {
        if (blank($storeDomain) || blank($accessToken)) {
            throw new RuntimeException(
                'Shopify store domain and access token are required.'
            );
        }

        $url = sprintf(
            'https://%s/admin/api/%s/graphql.json',
            $storeDomain,
            $this->apiVersion
        );

        $response = Http::timeout(60)
            ->retry(
                3,
                1000,
                throw: false
            )
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken,
            ])
            ->post($url, [
                'query' => $query,
                'variables' => $variables,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Shopify HTTP error: ' .
                $response->status() .
                ' ' .
                $response->body()
            );
        }

        $body = $response->json();

        if (! empty($body['errors'])) {
            throw new RuntimeException(
                'Shopify GraphQL error: ' .
                json_encode(
                    $body['errors'],
                    JSON_PRETTY_PRINT
                )
            );
        }

        return $body['data'] ?? [];
    }
}