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
        /*
         * These credentials are optional now.
         *
         * Multi-store operations use executeWithCredentials()
         * with credentials retrieved from the authenticated
         * Shopify store.
         */
        $this->storeDomain = (string) config(
            'shopify.store_domain'
        );

        $this->apiVersion = (string) config(
            'shopify.api_version',
            '2026-07'
        );

        $this->accessToken = (string) config(
            'shopify.access_token'
        );
    }

    /**
     * Execute a GraphQL request using the default Shopify
     * credentials from .env.
     *
     * This is kept for existing single-store functionality.
     */
    public function execute(
        string $query,
        array $variables = []
    ): array {
        if (
            blank($this->storeDomain) ||
            blank($this->accessToken)
        ) {
            throw new RuntimeException(
                'Default Shopify credentials are not configured.'
            );
        }

        return $this->sendRequest(
            $this->storeDomain,
            $this->accessToken,
            $query,
            $variables
        );
    }

    /**
     * Execute a GraphQL request using credentials belonging
     * to a specific authenticated Shopify store.
     *
     * This is the method used by the multi-store OAuth flow.
     */
    public function executeWithCredentials(
        string $storeDomain,
        string $accessToken,
        string $query,
        array $variables = []
    ): array {
        if (blank($storeDomain)) {
            throw new RuntimeException(
                'Shopify store domain is required.'
            );
        }

        if (blank($accessToken)) {
            throw new RuntimeException(
                'Shopify access token is required.'
            );
        }

        return $this->sendRequest(
            $storeDomain,
            $accessToken,
            $query,
            $variables
        );
    }

    /**
     * Send the actual Shopify GraphQL request.
     */
    protected function sendRequest(
        string $storeDomain,
        string $accessToken,
        string $query,
        array $variables = []
    ): array {
        $url = sprintf(
            'https://%s/admin/api/%s/graphql.json',
            $storeDomain,
            $this->apiVersion
        );

        /*
         * Do not send an empty "variables" object.
         *
         * Shopify can reject:
         *
         * "variables": []
         *
         * when the query does not define variables.
         */
        $payload = [
            'query' => $query,
        ];

        if (! empty($variables)) {
            $payload['variables'] = $variables;
        }

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
            ->post(
                $url,
                $payload
            );

        /*
         * Handle HTTP-level errors.
         */
        if (! $response->successful()) {
            throw new RuntimeException(
                'Shopify HTTP error: ' .
                $response->status() .
                ' ' .
                $response->body()
            );
        }

        $body = $response->json();

        /*
         * Handle GraphQL-level errors.
         */
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