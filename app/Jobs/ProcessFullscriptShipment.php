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
        | Shopify Fulfillment ID
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | This must be:
        |
        | gid://shopify/Fulfillment/XXXXXXXX
        |
        | It must NOT be:
        |
        | gid://shopify/FulfillmentOrder/XXXXXXXX
        |
        */

        $shopifyFulfillmentId =
            $fulfillmentOrder->shopify_fulfillment_id
            ?? null;

        /*
        |--------------------------------------------------------------------------
        | Backward compatibility
        |--------------------------------------------------------------------------
        |
        | If your database currently stores the Shopify fulfillment
        | ID in shopify_fulfillment_order_id, this fallback allows
        | the existing data to continue working.
        |
        | We should rename/fix this column later if it actually
        | contains a Fulfillment ID.
        */

        if (blank($shopifyFulfillmentId)) {
            $shopifyFulfillmentId =
                $fulfillmentOrder->shopify_fulfillment_order_id
                ?? null;
        }

        if (blank($shopifyFulfillmentId)) {
            Log::error(
                'Shopify fulfillment ID is missing.',
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
        | Log update
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

        /*
        |--------------------------------------------------------------------------
        | Update Shopify tracking
        |--------------------------------------------------------------------------
        */

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
            'status' => 'tracking_updated',
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