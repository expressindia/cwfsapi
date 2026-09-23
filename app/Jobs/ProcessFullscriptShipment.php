<?php

namespace App\Jobs;

use App\Models\FulfillmentOrder;
use App\Services\Shopify\ShopifyFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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
        | This is the Shopify FulfillmentOrder GID.
        |
        | Example:
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
        | Existing Shopify Fulfillment
        |--------------------------------------------------------------------------
        |
        | If the Shopify Fulfillment was already created by a previous
        | attempt, do NOT create another one.
        |
        | We continue processing because the previous attempt may have
        | created the fulfillment but failed while updating tracking.
        |
        */

        $shopifyFulfillmentId =
            $fulfillmentOrder->shopify_fulfillment_id
            ?? null;

        if (filled($shopifyFulfillmentId)) {
            Log::info(
                'Shopify fulfillment already exists. Skipping fulfillment creation.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'shopify_fulfillment_order_id' =>
                        $shopifyFulfillmentOrderId,

                    'shopify_fulfillment_id' =>
                        $shopifyFulfillmentId,

                    'fullscript_order_id' =>
                        $fulfillmentOrder->fullscript_order_id,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create Shopify Fulfillment if it does not already exist
        |--------------------------------------------------------------------------
        */

        if (blank($shopifyFulfillmentId)) {
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

            Log::info(
                'Processing Fullscript shipment.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'shopify_fulfillment_order_id' =>
                        $shopifyFulfillmentOrderId,

                    'fullscript_order_id' =>
                        $fulfillmentOrder->fullscript_order_id,

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
            */

            $shopifyLineItems =
                $shopifyFulfillmentService->getFulfillmentOrderLineItems(
                    $shopifyFulfillmentOrderId
                );

            if (empty($shopifyLineItems)) {
                Log::warning(
                    'No Shopify fulfillment order line items found.',
                    [
                        'fulfillment_order_id' =>
                            $fulfillmentOrder->id,

                        'shopify_fulfillment_order_id' =>
                            $shopifyFulfillmentOrderId,
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
            */

            Log::info(
                'Creating Shopify fulfillment.',
                [
                    'fulfillment_order_id' =>
                        $fulfillmentOrder->id,

                    'shopify_fulfillment_order_id' =>
                        $shopifyFulfillmentOrderId,

                    'line_items' =>
                        $fulfillmentLineItems,
                ]
            );

            $shopifyFulfillment =
                $shopifyFulfillmentService->createFulfillment(
                    $shopifyFulfillmentOrderId,
                    $fulfillmentLineItems,
                    true
                );

            /*
            |--------------------------------------------------------------------------
            | Get Shopify Fulfillment ID
            |--------------------------------------------------------------------------
            |
            | Example:
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

                'carrier' =>
                    $carrier,

                'tracking_number' =>
                    $trackingNumber,

                'tracking_url' =>
                    $trackingUrl,
            ]
        );

        $result =
            $shopifyFulfillmentService->updateTracking(
                $shopifyFulfillmentId,
                $trackingNumber,
                $carrier,
                $trackingUrl
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