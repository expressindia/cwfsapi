<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShopifyFulfillmentRegistrationService
{
    public function __construct(
        protected ShopifyGraphQLService $shopify
    ) {
    }

    /**
     * Get the Shopify token belonging to the
     * currently authenticated Shopify user.
     */
    protected function getShopifyToken(): ShopifyToken
    {
        $tokenId = session('shopify.token_id');

        if (blank($tokenId)) {
            throw new RuntimeException(
                'Shopify authentication session not found.'
            );
        }

        $token = ShopifyToken::find($tokenId);

        if (! $token) {
            throw new RuntimeException(
                'Shopify authentication token not found.'
            );
        }

        if (blank($token->shop_domain)) {
            throw new RuntimeException(
                'Shopify store domain is missing.'
            );
        }

        if (blank($token->access_token)) {
            throw new RuntimeException(
                'Shopify access token is missing.'
            );
        }

        return $token;
    }

    /**
     * Get credentials for the currently authenticated Shopify store.
     */
    protected function credentials(): array
    {
        $token = $this->getShopifyToken();

        return [
            'shop_domain' => $token->shop_domain,
            'access_token' => $token->access_token,
        ];
    }

    /**
     * Get Shopify fulfillment service status.
     *
     * This checks the currently authenticated store.
     */
    public function getStatus(): array
    {
        $credentials = $this->credentials();

        $query = <<<'GRAPHQL'
query GetFulfillmentServices {
    shop {
        name
        fulfillmentServices {
            id
            serviceName
            handle
            callbackUrl
            trackingSupport
            inventoryManagement
            requiresShippingMethod
            location {
                id
                name
            }
        }
    }
}
GRAPHQL;

        $data = $this->shopify->executeWithCredentials(
            $credentials['shop_domain'],
            $credentials['access_token'],
            $query
        );

        $serviceName = config(
            'shopify.fulfillment.service_name',
            'FSWarehouse'
        );

        $services = $data['shop']['fulfillmentServices'] ?? [];

        $registeredService = null;

        foreach ($services as $service) {
            if (
                ($service['serviceName'] ?? null) === $serviceName
            ) {
                $registeredService = $service;

                break;
            }
        }

        return [
            'connected' => true,

            'shop_name' => $data['shop']['name'] ?? null,

            'shop_domain' => $credentials['shop_domain'],

            'service_name' => $serviceName,

            'service' => $registeredService,

            'services' => $services,
        ];
    }

    /**
     * Register FSWarehouse with Shopify.
     *
     * This method is idempotent:
     *
     * - If FSWarehouse already exists, it will not create another one.
     * - If it does not exist, Shopify will create it.
     * - The fulfillment service ID and location ID are saved
     *   against the authenticated Shopify token.
     */
    public function register(): array
    {
        $credentials = $this->credentials();

        $serviceName = config(
            'shopify.fulfillment.service_name',
            'FSWarehouse'
        );

        $callbackUrl = config(
            'shopify.fulfillment.callback_url'
        );

        if (blank($callbackUrl)) {
            throw new RuntimeException(
                'Shopify fulfillment callback URL is not configured.'
            );
        }

        /*
         * First check whether FSWarehouse already exists.
         */
        $existing = $this->getStatus();

        if (! empty($existing['service'])) {
            $service = $existing['service'];

            /*
             * Save the existing fulfillment service ID
             * and location ID to our database.
             */
            $token = $this->getShopifyToken();

            $token->update([
                'fulfillment_service_id' => $service['id'] ?? null,

                'fulfillment_location_id' =>
                    $service['location']['id'] ?? null,
            ]);

            Log::info(
                'Shopify fulfillment service already exists.',
                [
                    'shop_domain' => $credentials['shop_domain'],

                    'fulfillment_service_id' =>
                        $service['id'] ?? null,

                    'fulfillment_location_id' =>
                        $service['location']['id'] ?? null,
                ]
            );

            return [
                'created' => false,

                'service' => $service,
            ];
        }

        /*
         * FSWarehouse does not exist.
         *
         * Create it.
         */
        $mutation = <<<'GRAPHQL'
mutation FulfillmentServiceCreate(
    $name: String!
    $callbackUrl: URL!
) {
    fulfillmentServiceCreate(
        name: $name
        callbackUrl: $callbackUrl
        trackingSupport: true
        inventoryManagement: false
        requiresShippingMethod: true
    ) {
        fulfillmentService {
            id
            serviceName
            handle
            callbackUrl
            trackingSupport
            inventoryManagement
            requiresShippingMethod
            location {
                id
                name
            }
        }

        userErrors {
            field
            message
        }
    }
}
GRAPHQL;

        $data = $this->shopify->executeWithCredentials(
            $credentials['shop_domain'],
            $credentials['access_token'],
            $mutation,
            [
                'name' => $serviceName,

                'callbackUrl' => $callbackUrl,
            ]
        );

        $result = $data['fulfillmentServiceCreate'] ?? null;

        if (! $result) {
            throw new RuntimeException(
                'Shopify did not return a fulfillment service creation result.'
            );
        }

        /*
         * Handle Shopify user errors.
         */
        if (! empty($result['userErrors'])) {
            Log::error(
                'Shopify fulfillment service registration failed.',
                [
                    'shop_domain' => $credentials['shop_domain'],

                    'user_errors' => $result['userErrors'],
                ]
            );

            $messages = collect($result['userErrors'])
                ->map(function ($error) {
                    $field = ! empty($error['field'])
                        ? implode('.', (array) $error['field']) . ': '
                        : '';

                    return $field . (
                        $error['message']
                        ?? 'Unknown Shopify error.'
                    );
                })
                ->implode(' ');

            throw new RuntimeException(
                'Shopify fulfillment service registration failed: ' .
                $messages
            );
        }

        /*
         * Get the newly created service.
         */
        $service = $result['fulfillmentService'] ?? null;

        if (! $service) {
            throw new RuntimeException(
                'Shopify did not return the created fulfillment service.'
            );
        }

        /*
         * Save Shopify service and location IDs.
         */
        $token = $this->getShopifyToken();

        $token->update([
            'fulfillment_service_id' =>
                $service['id'] ?? null,

            'fulfillment_location_id' =>
                $service['location']['id'] ?? null,
        ]);

        /*
         * Log only IDs and store domain.
         *
         * Never log the access token.
         */
        Log::info(
            'Shopify fulfillment service registered successfully.',
            [
                'shop_domain' => $credentials['shop_domain'],

                'fulfillment_service_id' =>
                    $service['id'] ?? null,

                'fulfillment_location_id' =>
                    $service['location']['id'] ?? null,

                'service_name' =>
                    $service['serviceName'] ?? null,

                'location_name' =>
                    $service['location']['name'] ?? null,
            ]
        );

        return [
            'created' => true,

            'service' => $service,
        ];
    }
}