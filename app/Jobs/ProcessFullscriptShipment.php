<?php

namespace App\Jobs;

use App\Models\FulfillmentOrder;
use App\Models\ShopifyToken;
use App\Services\Shopify\ShopifyFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessFullscriptShipment implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $fulfillmentOrderId
    ) {
    }

    public function handle(
        ShopifyFulfillmentService $shopifyFulfillmentService
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Get local fulfillment order
        |--------------------------------------------------------------------------
        */

        $fulfillmentOrder = FulfillmentOrder::find(
            $this->fulfillmentOrderId
        );

        if (! $fulfillmentOrder) {
            Log::error(
                'Fulfillment order record not found.',
                [
                    'fulfillment_order_id' =>
                        $this->fulfillmentOrderId,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Get Shopify token
        |--------------------------------------------------------------------------
        |
        | ShopifyFulfillmentService requires the Shopify token ID for all
        | Shopify API operations.
        |
        | The application currently has one Shopify store/token record,
        | so we use the configured ShopifyToken record.
        |
        */

        $shopifyToken = ShopifyToken::query()->first();

        if (! $shopifyToken) {
            throw new RuntimeException(
                'Shopify token record was not found.'
            );
        }

        $shopifyTokenId = (int) $shopifyToken->id;

        if ($shopifyTokenId <= 0) {
            throw new RuntimeException(
                'Shopify token ID is invalid.'
            );
        }

        Log::info(
            'Shopify token resolved for Fullscript shipment.',
            [
                'fulfillment_order_id' =>
                    $fulfillmentOrder->id,

                'shopify_token_id' =>
                    $shopifyTokenId,

                'shop_domain' =>
                    $shopifyToken->shop_domain,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Get Fullscript tracking data
        |--------------------------------------------------------------------------
        */

        $trackingData =
            $fulfillmentOrder->tracking_data ?? [];

        $trackingNumber =
            $trackingData['tracking_number'] ?? null;

        $carrier =
            $trackingData['carrier'] ?? null;

        $trackingUrl =
            $trackingData['tracking_url'] ?? null;

        $shippedSkus =
            $trackingData['shipped_skus'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Validate tracking number
        |--------------------------------------------------------------------------
        */

        if (blank($trackingNumber)) {
            Log::info(
                'Fullscript shipment has no tracking number. Shopify update skipped.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'fullscript_order_id' =>
                        $fulfillmentOrder->fullscript_order_id,

                    'shipment_number' =>
                        $trackingData['shipment_number'] ?? null,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Validate carrier
        |--------------------------------------------------------------------------
        */

        if (blank($carrier)) {
            Log::warning(
                'Fullscript shipment has no carrier. Shopify update skipped.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'fullscript_order_id' =>
                        $fulfillmentOrder->fullscript_order_id,

                    'tracking_number' =>
                        $trackingNumber,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Shopify FulfillmentOrder ID
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | gid://shopify/FulfillmentOrder/8321819967559
        |
        */

        $shopifyFulfillmentOrderId =
            $fulfillmentOrder->shopify_fulfillment_order_id
            ?? null;

        if (blank($shopifyFulfillmentOrderId)) {
            Log::error(
                'Shopify fulfillment order ID is missing.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'fullscript_order_id' =>
                        $fulfillmentOrder->fullscript_order_id,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Validate shipped SKUs
        |--------------------------------------------------------------------------
        */

        if (empty($shippedSkus)) {
            Log::warning(
                'Fullscript shipment contains no shipped SKUs. Shopify fulfillment skipped.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'fullscript_order_id' =>
                        $fulfillmentOrder->fullscript_order_id,

                    'shipment_number' =>
                        $trackingData['shipment_number'] ?? null,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Log shipment information
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Processing Fullscript shipment.',
            [
                'fulfillment_order_id' =>
                    $fulfillmentOrder->id,

                'shopify_fulfillment_order_id' =>
                    $shopifyFulfillmentOrderId,

                'fullscript_order_id' =>
                    $fulfillmentOrder->fullscript_order_id,

                'shopify_token_id' =>
                    $shopifyTokenId,

                'carrier' =>
                    $carrier,

                'tracking_number' =>
                    $trackingNumber,

                'tracking_url' =>
                    $trackingUrl,

                'shipped_skus' =>
                    $shippedSkus,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Get Shopify FulfillmentOrder line items
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | getFulfillmentOrderLineItems() requires the Shopify token ID.
        |
        */

        $shopifyLineItems =
            $shopifyFulfillmentService->getFulfillmentOrderLineItems(
                $shopifyFulfillmentOrderId,
                $shopifyTokenId
            );

        if (empty($shopifyLineItems)) {
            Log::warning(
                'No Shopify fulfillment order line items found.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'shopify_fulfillment_order_id' =>
                        $shopifyFulfillmentOrderId,

                    'shopify_token_id' =>
                        $shopifyTokenId,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Match Fullscript shipped SKUs with Shopify line items
        |--------------------------------------------------------------------------
        */

        $fulfillmentLineItems = [];

        foreach ($shippedSkus as $shippedSku) {
            $sku =
                $shippedSku['sku']
                ?? null;

            $shippedQuantity =
                (int) (
                    $shippedSku['quantity']
                    ?? 0
                );

            if (
                blank($sku) ||
                $shippedQuantity <= 0
            ) {
                continue;
            }

            foreach ($shopifyLineItems as $shopifyLineItem) {
                $shopifySku =
                    $shopifyLineItem['sku']
                    ?? null;

                if (
                    blank($shopifySku) ||
                    $shopifySku !== $sku
                ) {
                    continue;
                }

                $remainingQuantity =
                    (int) (
                        $shopifyLineItem['remaining_quantity']
                        ?? 0
                    );

                if ($remainingQuantity <= 0) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Never fulfill more than Shopify allows
                |--------------------------------------------------------------------------
                */

                $quantity =
                    min(
                        $shippedQuantity,
                        $remainingQuantity
                    );

                if ($quantity <= 0) {
                    continue;
                }

                $fulfillmentLineItems[] = [
                    'id' =>
                        $shopifyLineItem['id'],

                    'quantity' =>
                        $quantity,
                ];

                Log::info(
                    'Matched Fullscript SKU with Shopify fulfillment line item.',
                    [
                        'sku' =>
                            $sku,

                        'fullscript_quantity' =>
                            $shippedQuantity,

                        'shopify_remaining_quantity' =>
                            $remainingQuantity,

                        'fulfillment_quantity' =>
                            $quantity,

                        'shopify_line_item_id' =>
                            $shopifyLineItem['id'],
                    ]
                );

                break;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate matched line items
        |--------------------------------------------------------------------------
        */

        if (empty($fulfillmentLineItems)) {
            Log::warning(
                'No valid Shopify line items matched Fullscript shipped SKUs.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'shopify_fulfillment_order_id' =>
                        $shopifyFulfillmentOrderId,

                    'shipped_skus' =>
                        $shippedSkus,

                    'shopify_line_items' =>
                        $shopifyLineItems,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Create Shopify Fulfillment
        |--------------------------------------------------------------------------
        |
        | If a Shopify fulfillment was already created by a previous attempt,
        | reuse it instead of creating another fulfillment.
        |
        */

        $shopifyFulfillmentId =
            $fulfillmentOrder->shopify_fulfillment_id
            ?? null;

        if (blank($shopifyFulfillmentId)) {
            Log::info(
                'Creating Shopify fulfillment.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'shopify_fulfillment_order_id' =>
                        $shopifyFulfillmentOrderId,

                    'shopify_token_id' =>
                        $shopifyTokenId,

                    'line_items' =>
                        $fulfillmentLineItems,
                ]
            );

            $shopifyFulfillment =
                $shopifyFulfillmentService->createFulfillment(
                    $shopifyFulfillmentOrderId,
                    $fulfillmentLineItems,
                    true,
                    $shopifyTokenId
                );

            /*
            |--------------------------------------------------------------------------
            | Get Shopify Fulfillment ID
            |--------------------------------------------------------------------------
            |
            | This is different from the FulfillmentOrder ID.
            |
            | Example:
            |
            | gid://shopify/Fulfillment/6564809244743
            |
            */

            $shopifyFulfillmentId =
                $shopifyFulfillment['id']
                ?? null;

            if (blank($shopifyFulfillmentId)) {
                Log::error(
                    'Shopify fulfillment was created but no fulfillment ID was returned.',
                    [
                        'fulfillment_order_id' =>
                            $fulfillmentOrder->id,

                        'shopify_fulfillment_order_id' =>
                            $shopifyFulfillmentOrderId,

                        'shopify_token_id' =>
                            $shopifyTokenId,

                        'shopify_response' =>
                            $shopifyFulfillment,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Save Shopify Fulfillment ID
            |--------------------------------------------------------------------------
            */

            $fulfillmentOrder->update([
                'shopify_fulfillment_id' =>
                    $shopifyFulfillmentId,
            ]);

            Log::info(
                'Shopify fulfillment created successfully.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'shopify_fulfillment_order_id' =>
                        $shopifyFulfillmentOrderId,

                    'shopify_fulfillment_id' =>
                        $shopifyFulfillmentId,

                    'fullscript_order_id' =>
                        $fulfillmentOrder->fullscript_order_id,

                    'shopify_token_id' =>
                        $shopifyTokenId,
                ]
            );
        } else {
            /*
            |--------------------------------------------------------------------------
            | Existing Shopify Fulfillment
            |--------------------------------------------------------------------------
            */

            Log::info(
                'Existing Shopify fulfillment found. Reusing it.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'shopify_fulfillment_order_id' =>
                        $shopifyFulfillmentOrderId,

                    'shopify_fulfillment_id' =>
                        $shopifyFulfillmentId,

                    'shopify_token_id' =>
                        $shopifyTokenId,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Shopify tracking
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Updating Shopify fulfillment tracking from Fullscript.',
            [
                'fulfillment_order_id' =>
                    $fulfillmentOrder->id,

                'shopify_fulfillment_id' =>
                    $shopifyFulfillmentId,

                'fullscript_order_id' =>
                    $fulfillmentOrder->fullscript_order_id,

                'shopify_token_id' =>
                    $shopifyTokenId,

                'carrier' =>
                    $carrier,

                'tracking_number' =>
                    $trackingNumber,

                'tracking_url' =>
                    $trackingUrl,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT:
        | Pass Shopify token ID to updateTracking()
        |--------------------------------------------------------------------------
        */

        $result =
            $shopifyFulfillmentService->updateTracking(
                $shopifyFulfillmentId,
                $trackingNumber,
                $carrier,
                $trackingUrl,
                $shopifyTokenId
            );

        /*
        |--------------------------------------------------------------------------
        | Save successful status
        |--------------------------------------------------------------------------
        */

        $fulfillmentOrder->update([
            'status' =>
                'tracking_updated',
        ]);

        Log::info(
            'Shopify fulfillment tracking successfully updated.',
            [
                'fulfillment_order_id' =>
                    $fulfillmentOrder->id,

                'shopify_fulfillment_id' =>
                    $shopifyFulfillmentId,

                'fullscript_order_id' =>
                    $fulfillmentOrder->fullscript_order_id,

                'shopify_token_id' =>
                    $shopifyTokenId,

                'carrier' =>
                    $carrier,

                'tracking_number' =>
                    $trackingNumber,

                'shopify_response' =>
                    $result,
            ]
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error(
            'Fullscript shipment processing job failed.',
            [
                'fulfillment_order_id' =>
                    $this->fulfillmentOrderId,

                'error' =>
                    $exception->getMessage(),

                'trace' =>
                    $exception->getTraceAsString(),
            ]
        );
    }
}