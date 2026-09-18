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
        /*
        |--------------------------------------------------------------------------
        | Get Shopify Fulfillment Order
        |--------------------------------------------------------------------------
        */

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
                            sku
                            remainingQuantity
                            totalQuantity
                            productTitle
                            variantTitle
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

        $data = $this->shopify->execute(
            $query,
            [
                'id' => $fulfillmentOrderId,
            ]
        );

        Log::info(
            'Shopify fulfillment order GraphQL response.',
            [
                'fulfillment_order_id' => $fulfillmentOrderId,
                'data' => $data,
            ]
        );

        $fulfillmentOrder =
            $data['fulfillmentOrder'] ?? null;

        if (! $fulfillmentOrder) {
            throw new RuntimeException(
                'Shopify fulfillment order not found: ' .
                $fulfillmentOrderId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check Fulfillment Location
        |--------------------------------------------------------------------------
        */

        $location =
            $fulfillmentOrder['assignedLocation']['location']
            ?? null;

        if (! $location) {
            throw new RuntimeException(
                'Shopify fulfillment order has no assigned location.'
            );
        }

        $expectedLocationId = config(
            'shopify.fulfillment.location_id'
        );

        $expectedServiceName = config(
            'shopify.fulfillment.service_name',
            'FS-Warehouse'
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Location ID
        |--------------------------------------------------------------------------
        */

        if (
            filled($expectedLocationId)
            &&
            ($location['id'] ?? null) !== $expectedLocationId
        ) {
            throw new RuntimeException(
                'Fulfillment order is not assigned to the configured ' .
                'FS-Warehouse location.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Location Name
        |--------------------------------------------------------------------------
        */

        if (
            blank($expectedLocationId)
            &&
            ($location['name'] ?? null) !== $expectedServiceName
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
        | Build Fullscript Line Items
        |--------------------------------------------------------------------------
        */

        $lineItems = [];

        foreach (
            $fulfillmentOrder['lineItems']['edges'] ?? []
            as $edge
        ) {
            $node = $edge['node'] ?? [];

            $remainingQuantity = (int) (
                $node['remainingQuantity'] ?? 0
            );

            $sku = $node['sku'] ?? null;

            Log::info(
                'Shopify fulfillment line item received.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrderId,

                    'line_item_id' =>
                        $node['id'] ?? null,

                    'sku' =>
                        $sku,

                    'remaining_quantity' =>
                        $remainingQuantity,

                    'total_quantity' =>
                        $node['totalQuantity'] ?? null,

                    'product_title' =>
                        $node['productTitle'] ?? null,

                    'variant_title' =>
                        $node['variantTitle'] ?? null,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Skip Fully Fulfilled Items
            |--------------------------------------------------------------------------
            */

            if ($remainingQuantity <= 0) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Skip Items Without SKU
            |--------------------------------------------------------------------------
            */

            if (blank($sku)) {
                Log::warning(
                    'Skipping Shopify fulfillment line without SKU.',
                    [
                        'fulfillment_order_id' =>
                            $fulfillmentOrderId,

                        'line_item_id' =>
                            $node['id'] ?? null,

                        'product_title' =>
                            $node['productTitle'] ?? null,

                        'variant_title' =>
                            $node['variantTitle'] ?? null,
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Add Fullscript Line Item
            |--------------------------------------------------------------------------
            */

            $lineItems[] = [
                'sku' => (string) $sku,
                'quantity' => $remainingQuantity,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Make Sure We Have At Least One Valid Item
        |--------------------------------------------------------------------------
        */

        if (empty($lineItems)) {
            throw new RuntimeException(
                'No valid remaining SKU line items found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Log Final Line Items
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Shopify fulfillment line items prepared for Fullscript.',
            [
                'fulfillment_order_id' =>
                    $fulfillmentOrderId,

                'line_items' =>
                    $lineItems,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Shipping Address
        |--------------------------------------------------------------------------
        */

        $shopifyAddress =
            $fulfillmentOrder['order']['shippingAddress']
            ?? null;

        if (! $shopifyAddress) {
            throw new RuntimeException(
                'Shopify order has no shipping address.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Customer Phone
        |--------------------------------------------------------------------------
        */

        if (
            blank(
                $shopifyAddress['phone'] ?? null
            )
        ) {
            throw new RuntimeException(
                'Customer phone number is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shipping Method
        |--------------------------------------------------------------------------
        */

        $shippingMethod = 'Standard';

        $shippingLines =
            $fulfillmentOrder['order']['shippingLines']['edges']
            ?? [];

        if (! empty($shippingLines)) {
            $title =
                $shippingLines[0]['node']['title']
                ?? null;

            if (filled($title)) {
                $shippingMethod = $title;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Build Fullscript Order Data
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
                    $shopifyAddress['provinceCode']
                    ?? $shopifyAddress['province']
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
        | Log Order Data Before Sending to Fullscript
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Sending Shopify fulfillment order to Fullscript.',
            [
                'shopify_fulfillment_order_id' =>
                    $fulfillmentOrderId,

                'shopify_order_id' =>
                    $fulfillmentOrder['order']['id']
                    ?? null,

                'shopify_order_name' =>
                    $fulfillmentOrder['order']['name']
                    ?? null,

                'shipping_method' =>
                    $shippingMethod,

                'line_items' =>
                    $lineItems,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Send Order to Fullscript
        |--------------------------------------------------------------------------
        */

        $fullscriptResult =
            $this->fullscript->createOrder(
                $orderData,
                $fulfillmentOrderId
            );

        /*
        |--------------------------------------------------------------------------
        | Extract Fullscript Order ID
        |--------------------------------------------------------------------------
        */

        $fullscriptResponse =
            $fullscriptResult['response']
            ?? [];

        $fullscriptOrderId =
            $fullscriptResponse['order_id']
            ?? $fullscriptResponse['order']['id']
            ?? $fullscriptResponse['fulfillment_order']['id']
            ?? $fullscriptResponse['id']
            ?? null;

        /*
        |--------------------------------------------------------------------------
        | Save Shopify ↔ Fullscript Relationship
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Log Successful Submission
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Shopify fulfillment successfully submitted to Fullscript.',
            [
                'shopify_fulfillment_order_id' =>
                    $fulfillmentOrderId,

                'shopify_order_name' =>
                    $fulfillmentOrder['order']['name']
                    ?? null,

                'fullscript_order_id' =>
                    $fullscriptOrderId,
            ]
        );

        return $fullscriptResult;
    }

    /*
    |--------------------------------------------------------------------------
    | Update Shopify Fulfillment Tracking
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | This method updates an EXISTING Shopify fulfillment.
    |
    | It does NOT create a new fulfillment.
    |
    | $fulfillmentId must be:
    |
    | gid://shopify/Fulfillment/XXXXXXXX
    |
    |--------------------------------------------------------------------------
    */

    public function updateTracking(
        string $fulfillmentId,
        string $trackingNumber,
        ?string $carrier = null,
        ?string $trackingUrl = null
    ): array {
        if (blank($fulfillmentId)) {
            throw new RuntimeException(
                'Shopify fulfillment ID is required.'
            );
        }

        if (blank($trackingNumber)) {
            throw new RuntimeException(
                'Tracking number is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Build Tracking Information
        |--------------------------------------------------------------------------
        */

        $trackingInfo = [
            'number' => $trackingNumber,
        ];

        if (filled($carrier)) {
            $trackingInfo['company'] = $carrier;
        }

        if (filled($trackingUrl)) {
            $trackingInfo['url'] = $trackingUrl;
        }

        /*
        |--------------------------------------------------------------------------
        | Update Existing Shopify Fulfillment
        |--------------------------------------------------------------------------
        */

        $mutation = <<<'GRAPHQL'
        mutation FulfillmentTrackingInfoUpdate(
            $fulfillmentId: ID!
            $trackingInfoInput: FulfillmentTrackingInput!
            $notifyCustomer: Boolean
        ) {
            fulfillmentTrackingInfoUpdate(
                fulfillmentId: $fulfillmentId
                trackingInfoInput: $trackingInfoInput
                notifyCustomer: $notifyCustomer
            ) {
                fulfillment {
                    id
                    status
                    trackingInfo {
                        company
                        number
                        url
                    }
                }

                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        /*
        |--------------------------------------------------------------------------
        | Variables
        |--------------------------------------------------------------------------
        */

        $variables = [
            'fulfillmentId' =>
                $fulfillmentId,

            'trackingInfoInput' =>
                $trackingInfo,

            'notifyCustomer' =>
                true,
        ];

        Log::info(
            'Sending Shopify fulfillment tracking update.',
            [
                'fulfillment_id' =>
                    $fulfillmentId,

                'tracking_info' =>
                    $trackingInfo,

                'notify_customer' =>
                    true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Execute Shopify GraphQL
        |--------------------------------------------------------------------------
        */

        $data = $this->shopify->execute(
            $mutation,
            $variables
        );

        /*
        |--------------------------------------------------------------------------
        | Log Raw Response
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Shopify fulfillment tracking GraphQL response.',
            [
                'fulfillment_id' =>
                    $fulfillmentId,

                'response' =>
                    $data,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Response
        |--------------------------------------------------------------------------
        */

        $result =
            $data['fulfillmentTrackingInfoUpdate']
            ?? null;

        if (! $result) {
            throw new RuntimeException(
                'Shopify fulfillmentTrackingInfoUpdate returned no result.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Handle Shopify User Errors
        |--------------------------------------------------------------------------
        */

        if (! empty($result['userErrors'])) {
            Log::error(
                'Shopify fulfillment tracking update returned user errors.',
                [
                    'fulfillment_id' =>
                        $fulfillmentId,

                    'user_errors' =>
                        $result['userErrors'],
                ]
            );

            throw new RuntimeException(
                'Shopify fulfillment tracking update failed: ' .
                json_encode(
                    $result['userErrors'],
                    JSON_PRETTY_PRINT
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Make Sure Fulfillment Was Returned
        |--------------------------------------------------------------------------
        */

        if (empty($result['fulfillment'])) {
            throw new RuntimeException(
                'Shopify fulfillment tracking update did not return a fulfillment.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Log Successful Update
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Shopify fulfillment tracking successfully updated.',
            [
                'shopify_fulfillment_id' =>
                    $fulfillmentId,

                'tracking_number' =>
                    $trackingNumber,

                'carrier' =>
                    $carrier,

                'tracking_url' =>
                    $trackingUrl,

                'fulfillment' =>
                    $result['fulfillment'],
            ]
        );

        return $result['fulfillment'];
    }
}