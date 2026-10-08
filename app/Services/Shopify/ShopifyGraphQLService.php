<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopifyGraphQLService
{
    protected string $storeDomain;

    protected string $apiVersion;

    public function __construct(
        protected ShopifyAccessTokenService $accessTokenService
    ) {
        $this->storeDomain = (string) config(
            'shopify.store_domain'
        );

        $this->apiVersion = (string) config(
            'shopify.api_version',
            '2026-07'
        );
    }

    /**
     * Execute a GraphQL request using the Shopify access token
     * stored in the database.
     *
     * Both development and production use the same
     * shopify_tokens database table.
     */
    public function execute(
        string $query,
        array $variables = []
    ): array {
        if (blank($this->storeDomain)) {
            throw new RuntimeException(
                'Shopify store domain is not configured.'
            );
        }

        $accessToken = $this->accessTokenService->getAccessToken();

        return $this->sendRequest(
            $this->storeDomain,
            $accessToken,
            $query,
            $variables
        );
    }

    /**
     * Execute a GraphQL request using explicitly supplied credentials.
     *
     * Kept for existing functionality that supplies a specific
     * store domain and access token.
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