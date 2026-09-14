<?php

namespace App\Services\Shopify;

use App\Models\FulfillmentOrder;
use App\Services\Fullscript\FullscriptFulfillmentService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShopifyFulfillmentService
{
    public function __construct(
        protected ShopifyGraphQLService $shopify,
        protected FullscriptFulfillmentService $fullscript
    ) {
    }

    public function processFulfillmentRequest(
        string $fulfillmentOrderId
    ): array {

        $query = <<<'GRAPHQL'
query GetFulfillmentOrder($id: ID!) {

    fulfillmentOrder(id: $id) {

        id

        status

        requestStatus

        assignedLocation {
            location {
                id
                name
            }
        }

        lineItems(first: 100) {

            edges {

                node {

                    id

                    remainingQuantity

                    lineItem {

                        id

                        sku

                        name

                        quantity

                    }

                }

            }

        }

        order {

            id

            name

            shippingAddress {

                firstName
                lastName
                company
                address1
                address2
                city
                province
                provinceCode
                zip
                country
                countryCode
                phone

            }

            shippingLines(first: 10) {

                edges {

                    node {

                        title

                    }

                }

            }

        }

    }

}
GRAPHQL;

        $data =
            $this->shopify->execute(
                $query,
                [
                    'id' =>
                        $fulfillmentOrderId
                ]
            );

        $fulfillmentOrder =
            $data['fulfillmentOrder']
            ?? null;

        if (!$fulfillmentOrder) {

            throw new RuntimeException(
                'Shopify fulfillment order not found: ' .
                $fulfillmentOrderId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check fulfillment location
        |--------------------------------------------------------------------------
        */

        $location =
            $fulfillmentOrder[
                'assignedLocation'
            ]['location']
            ?? null;

        if (!$location) {

            throw new RuntimeException(
                'Shopify fulfillment order has no assigned location.'
            );
        }

        $expectedLocationId =
            config(
                'shopify.fulfillment.location_id'
            );

        $expectedServiceName =
            config(
                'shopify.fulfillment.service_name',
                'FS-Warehouse'
            );

        if (
            filled($expectedLocationId)
            &&
            $location['id'] !==
            $expectedLocationId
        ) {

            throw new RuntimeException(
                'Fulfillment order is not assigned to the configured FS-Warehouse location.'
            );
        }

        if (
            blank($expectedLocationId)
            &&
            $location['name'] !==
            $expectedServiceName
        ) {

            throw new RuntimeException(
                'Fulfillment order location "' .
                ($location['name'] ?? '') .
                '" does not match "' .
                $expectedServiceName .
                '".'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Build line items
        |--------------------------------------------------------------------------
        */

        $lineItems = [];

        foreach (
            $fulfillmentOrder['lineItems']['edges']
            ?? []
            as $edge
        ) {

            $node =
                $edge['node']
                ?? [];

            $remainingQuantity =
                (int) (
                    $node['remainingQuantity']
                    ?? 0
                );

            if ($remainingQuantity <= 0) {
                continue;
            }

            $lineItem =
                $node['lineItem']
                ?? [];

            $sku =
                $lineItem['sku']
                ?? null;

            if (blank($sku)) {

                Log::warning(
                    'Skipping Shopify fulfillment line without SKU.',
                    [
                        'fulfillment_order_id' =>
                            $fulfillmentOrderId,
                        'line_item_id' =>
                            $lineItem['id']
                            ?? null,
                    ]
                );

                continue;
            }

            $lineItems[] = [

                'sku' =>
                    (string) $sku,

                'quantity' =>
                    $remainingQuantity,

            ];
        }

        if (empty($lineItems)) {

            throw new RuntimeException(
                'No valid remaining SKU line items found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shipping address
        |--------------------------------------------------------------------------
        */

        $shopifyAddress =
            $fulfillmentOrder['order'][
                'shippingAddress'
            ]
            ?? null;

        if (!$shopifyAddress) {

            throw new RuntimeException(
                'Shopify order has no shipping address.'
            );
        }

        if (
            blank(
                $shopifyAddress['phone']
                ?? null
            )
        ) {

            throw new RuntimeException(
                'Customer phone number is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shipping method
        |--------------------------------------------------------------------------
        */

        $shippingMethod = 'Standard';

        $shippingLines =
            $fulfillmentOrder['order'][
                'shippingLines'
            ]['edges']
            ?? [];

        if (!empty($shippingLines)) {

            $title =
                $shippingLines[0]['node']['title']
                ?? null;

            if (filled($title)) {
                $shippingMethod = $title;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Build Fullscript data
        |--------------------------------------------------------------------------
        */

        $orderData = [

            'shipping_method' =>
                $shippingMethod,

            'line_items' =>
                $lineItems,

            'shipping_address' => [

                'firstname' =>
                    $shopifyAddress['firstName']
                    ?? '',

                'lastname' =>
                    $shopifyAddress['lastName']
                    ?? '',

                'address1' =>
                    $shopifyAddress['address1']
                    ?? '',

                'address2' =>
                    $shopifyAddress['address2']
                    ?? '',

                'city' =>
                    $shopifyAddress['city']
                    ?? '',

                'state' =>
                    $shopifyAddress['province']
                    ?? '',

                'zipcode' =>
                    $shopifyAddress['zip']
                    ?? '',

                'country' =>
                    $shopifyAddress['country']
                    ?? '',

                'phone' =>
                    $shopifyAddress['phone']
                    ?? '',

            ],

        ];

        /*
        |--------------------------------------------------------------------------
        | Send order to Fullscript
        |--------------------------------------------------------------------------
        */

        $fullscriptResult =
            $this->fullscript->createOrder(
                $orderData,
                $fulfillmentOrderId
            );

        /*
        |--------------------------------------------------------------------------
        | Save Shopify ↔ Fullscript relationship
        |--------------------------------------------------------------------------
        */

        $fullscriptResponse =
            $fullscriptResult['response']
            ?? [];

        $fullscriptOrderId =
            $fullscriptResponse['order']['id']
            ?? $fullscriptResponse[
                'fulfillment_order'
            ]['id']
            ?? $fullscriptResponse['id']
            ?? null;

        FulfillmentOrder::updateOrCreate(

            [
                'shopify_fulfillment_order_id' =>
                    $fulfillmentOrderId,
            ],

            [

                'shopify_order_id' =>
                    $fulfillmentOrder['order']['id']
                    ?? null,

                'shopify_order_name' =>
                    $fulfillmentOrder['order']['name']
                    ?? null,

                'fullscript_order_id' =>
                    $fullscriptOrderId,

                'status' =>
                    'submitted',

                'fullscript_response' =>
                    $fullscriptResult,

            ]
        );

        return $fullscriptResult;
    }
}