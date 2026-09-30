<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFullscriptShipment;
use App\Models\FulfillmentOrder;
use App\Support\Webhook\FullscriptWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class FullscriptWebhookController extends Controller
{
    /**
     * Handle Fullscript webhook events.
     */
    public function handle(Request $request)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('Fullscript-Signature');

        Log::info('Fullscript webhook received.', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'signature_present' => !empty($signature),
            'body_length' => strlen($rawBody),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Challenge / verification request
        |--------------------------------------------------------------------------
        |
        | Fullscript may send an empty-body request while registering/verifying
        | the webhook endpoint.
        |
        */

        if (empty($rawBody)) {
            $challengeToken = config('fullscript.webhook.challenge_token');

            Log::info('Fullscript webhook challenge request received.');

            return response()->json([
                'challenge' => $challengeToken,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify webhook signature
        |--------------------------------------------------------------------------
        */

        try {
            $verifier = new FullscriptWebhookVerifier();

            $isValid = $verifier->verify(
                $rawBody,
                $signature
            );
        } catch (Throwable $e) {
            Log::error('Fullscript webhook signature verification exception.', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Webhook verification failed.',
            ], 401);
        }

        if (!$isValid) {
            Log::warning('Invalid Fullscript webhook signature.');

            return response()->json([
                'message' => 'Invalid signature.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Decode webhook payload
        |--------------------------------------------------------------------------
        */

        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            Log::error('Invalid Fullscript webhook JSON payload.', [
                'body' => $rawBody,
            ]);

            return response()->json([
                'message' => 'Invalid JSON payload.',
            ], 400);
        }

        Log::debug('Fullscript webhook payload decoded.', [
            'payload' => $payload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Get event
        |--------------------------------------------------------------------------
        */

        $event = $payload['event'] ?? null;

        if (!is_array($event)) {
            Log::warning('Fullscript webhook event is missing.', [
                'payload' => $payload,
            ]);

            return response()->json([
                'message' => 'Event not found.',
            ], 200);
        }

        $eventType = $event['type'] ?? null;
        $eventId = $event['id'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate webhook processing
        |--------------------------------------------------------------------------
        */

        if (blank($eventId)) {
            Log::warning('Fullscript webhook event ID is missing.', [
                'event_type' => $eventType,
            ]);

            return response()->json([
                'message' => 'Event ID is required.',
            ], 200);
        }

        $alreadyProcessed = FulfillmentOrder::where(
            'fullscript_event_id',
            $eventId
        )->exists();

        if ($alreadyProcessed) {
            Log::info(
                'Fullscript webhook event already processed. Ignoring duplicate.',
                [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                ]
            );

            return response()->json([
                'message' => 'Event already processed.',
            ], 200);
        }

        Log::info('Fullscript webhook event identified.', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        /*
        |--------------------------------------------------------------------------
        | We only process shipment shipped events
        |--------------------------------------------------------------------------
        */

        if ($eventType !== 'fulfillment.shipment.shipped') {
            Log::info('Fullscript webhook event ignored.', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            return response()->json([
                'message' => 'Event ignored.',
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Get fulfillment shipment
        |--------------------------------------------------------------------------
        |
        | Actual Fullscript payload structure:
        |
        | event
        |   └── data
        |       └── fulfillment_shipment
        |
        */

        $shipment = $event['data']['fulfillment_shipment'] ?? null;

        if (!is_array($shipment)) {
            Log::warning('Fullscript shipment data is missing.', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            return response()->json([
                'message' => 'Shipment data not found.',
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Fullscript order ID
        |--------------------------------------------------------------------------
        */

        $fullscriptOrderId = $shipment['order_id'] ?? null;

        if (empty($fullscriptOrderId)) {
            Log::warning(
                'Fullscript shipment does not contain order_id.',
                [
                    'event_id' => $eventId,
                    'shipment' => $shipment,
                ]
            );

            return response()->json([
                'message' => 'Fullscript order ID not found.',
            ], 200);
        }

        Log::info('Fullscript shipment identified.', [
            'event_id' => $eventId,
            'fullscript_order_id' => $fullscriptOrderId,
            'shipment_number' => $shipment['number'] ?? null,
            'state' => $shipment['state'] ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Find local fulfillment order
        |--------------------------------------------------------------------------
        */

        $fulfillmentOrder = FulfillmentOrder::where(
            'fullscript_order_id',
            $fullscriptOrderId
        )->first();

        if (!$fulfillmentOrder) {
            Log::warning(
                'No local fulfillment order found for Fullscript order.',
                [
                    'event_id' => $eventId,
                    'fullscript_order_id' => $fullscriptOrderId,
                ]
            );

            return response()->json([
                'message' => 'Fulfillment order not found.',
            ], 200);
        }

        Log::info('Local fulfillment order found.', [
            'event_id' => $eventId,
            'fullscript_order_id' => $fullscriptOrderId,
            'fulfillment_order_id' => $fulfillmentOrder->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Normalize shipped SKUs and tracking information
        |--------------------------------------------------------------------------
        |
        | Fullscript shipment payloads can provide the shipped items in:
        |
        | shipments[].lineItems
        |
        | and tracking information in:
        |
        | shipments[].shipmentTracking[]
        |
        | This is important for partial shipments.
        |
        | Example:
        |
        | "shipments": [
        |     {
        |         "lineItems": [
        |             {
        |                 "quantity": 1,
        |                 "sku": "DF0193"
        |             },
        |             {
        |                 "quantity": 1,
        |                 "sku": "DF0078"
        |             }
        |         ],
        |         "shipmentTracking": [
        |             {
        |                 "carrier": "UPS",
        |                 "trackingNumber": "TR01434677e444c51"
        |             }
        |         ]
        |     }
        | ]
        |
        | We convert that into:
        |
        | [
        |     [
        |         'sku' => 'DF0193',
        |         'quantity' => 1,
        |     ],
        |     [
        |         'sku' => 'DF0078',
        |         'quantity' => 1,
        |     ],
        | ]
        |
        */

        $shipments = $shipment['shipments'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Get shipped SKUs
        |--------------------------------------------------------------------------
        |
        | First use the existing webhook format if shipped_skus is available.
        |
        */

        $shippedSkus = $shipment['shipped_skus'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Support shipments[].lineItems
        |--------------------------------------------------------------------------
        |
        | Dave's partial shipment payload uses:
        |
        | shipments[].lineItems[]
        |
        | The top-level lineItems contains ALL ordered items, so we must NOT
        | use that to determine what was actually shipped.
        |
        */

        if (is_array($shipments) && !empty($shipments)) {
            $skuQuantities = [];

            foreach ($shipments as $shipmentItem) {
                if (!is_array($shipmentItem)) {
                    continue;
                }

                foreach ($shipmentItem['lineItems'] ?? [] as $lineItem) {
                    if (!is_array($lineItem)) {
                        continue;
                    }

                    $sku = $lineItem['sku'] ?? null;

                    $quantity = (int) (
                        $lineItem['quantity'] ?? 0
                    );

                    if (blank($sku) || $quantity <= 0) {
                        continue;
                    }

                    if (!isset($skuQuantities[$sku])) {
                        $skuQuantities[$sku] = 0;
                    }

                    $skuQuantities[$sku] += $quantity;
                }
            }

            $shippedSkus = [];

            foreach ($skuQuantities as $sku => $quantity) {
                $shippedSkus[] = [
                    'sku' => $sku,
                    'quantity' => $quantity,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Get tracking information
        |--------------------------------------------------------------------------
        |
        | First check the existing fulfillment_shipment structure.
        |
        | Then fall back to:
        |
        | shipments[].shipmentTracking[]
        |
        */

        $trackingUrl = $shipment['tracking_url'] ?? null;
        $carrier = $shipment['carrier'] ?? null;
        $trackingNumber = $shipment['tracking_number'] ?? null;

        if (is_array($shipments)) {
            foreach ($shipments as $shipmentItem) {
                if (!is_array($shipmentItem)) {
                    continue;
                }

                foreach (
                    $shipmentItem['shipmentTracking'] ?? []
                    as $tracking
                ) {
                    if (!is_array($tracking)) {
                        continue;
                    }

                    $trackingUrl = $trackingUrl
                        ?: (
                            $tracking['trackingUrl']
                            ?? $tracking['tracking_url']
                            ?? null
                        );

                    $carrier = $carrier
                        ?: ($tracking['carrier'] ?? null);

                    $trackingNumber = $trackingNumber
                        ?: (
                            $tracking['trackingNumber']
                            ?? $tracking['tracking_number']
                            ?? null
                        );

                    /*
                     * We only need the first available tracking record.
                     */
                    if (
                        $carrier ||
                        $trackingNumber ||
                        $trackingUrl
                    ) {
                        break 2;
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Build tracking data
        |--------------------------------------------------------------------------
        */

        $trackingData = [
            'event_id' => $eventId,

            'shipment_number' => $shipment['number'] ?? null,

            'state' => $shipment['state'] ?? null,

            'shipped_at' => $shipment['shipped_at'] ?? null,

            'delivered_at' => $shipment['delivered_at'] ?? null,

            'tracking_url' => $trackingUrl,

            'carrier' => $carrier,

            'tracking_number' => $trackingNumber,

            'order_shipment_state' =>
                $shipment['order_shipment_state'] ?? null,

            'shipped_skus' => $shippedSkus,

            /*
             * Keep the original shipment information for reference/debugging.
             */
            'shipments' => $shipments,
        ];

        /*
        |--------------------------------------------------------------------------
        | Log extracted shipment information
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Fullscript shipment tracking information extracted.',
            [
                'event_id' => $eventId,

                'fullscript_order_id' => $fullscriptOrderId,

                'carrier' => $trackingData['carrier'],

                'tracking_number' =>
                    $trackingData['tracking_number'],

                'tracking_url' =>
                    $trackingData['tracking_url'],

                'shipment_number' =>
                    $trackingData['shipment_number'],

                'state' => $trackingData['state'],

                'shipped_skus' =>
                    $trackingData['shipped_skus'],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Save tracking data locally
        |--------------------------------------------------------------------------
        */

        $fulfillmentOrder->update([
            'fullscript_event_id' => $eventId,

            'tracking_data' => $trackingData,

            'status' => 'shipped',
        ]);

        Log::info('Fullscript shipment saved.', [
            'fulfillment_order_id' => $fulfillmentOrder->id,

            'fullscript_order_id' => $fullscriptOrderId,

            'tracking_data' => $trackingData,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Check tracking information
        |--------------------------------------------------------------------------
        */

        if (
            empty($trackingData['tracking_number']) ||
            empty($trackingData['carrier'])
        ) {
            Log::warning(
                'Fullscript shipment has no complete tracking information yet.',
                [
                    'event_id' => $eventId,

                    'fullscript_order_id' =>
                        $fullscriptOrderId,

                    'carrier' =>
                        $trackingData['carrier'],

                    'tracking_number' =>
                        $trackingData['tracking_number'],

                    'tracking_url' =>
                        $trackingData['tracking_url'],
                ]
            );

            return response()->json([
                'message' =>
                    'Shipment received but tracking information is incomplete.',
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Check shipped SKUs
        |--------------------------------------------------------------------------
        */

        if (empty($trackingData['shipped_skus'])) {
            Log::warning(
                'Fullscript shipment contains no shipped SKUs.',
                [
                    'event_id' => $eventId,

                    'fullscript_order_id' =>
                        $fullscriptOrderId,

                    'shipment_number' =>
                        $trackingData['shipment_number'],
                ]
            );

            return response()->json([
                'message' =>
                    'Shipment received but no shipped SKUs were found.',
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Dispatch job to update Shopify
        |--------------------------------------------------------------------------
        |
        | The ProcessFullscriptShipment job is responsible for:
        |
        | 1. Getting Shopify fulfillment-order line items.
        | 2. Matching only the shipped SKUs.
        | 3. Creating the Shopify fulfillment.
        | 4. Updating the tracking information.
        |
        */

        Log::info(
            'Dispatching ProcessFullscriptShipment job.',
            [
                'fulfillment_order_id' =>
                    $fulfillmentOrder->id,

                'fullscript_order_id' =>
                    $fullscriptOrderId,

                'carrier' =>
                    $trackingData['carrier'],

                'tracking_number' =>
                    $trackingData['tracking_number'],

                'shipped_skus' =>
                    $trackingData['shipped_skus'],
            ]
        );

        ProcessFullscriptShipment::dispatch(
            $fulfillmentOrder->id
        );

        Log::info(
            'ProcessFullscriptShipment job dispatched.',
            [
                'fulfillment_order_id' =>
                    $fulfillmentOrder->id,

                'fullscript_order_id' =>
                    $fullscriptOrderId,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Return success
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' =>
                'Fullscript shipment processed successfully.',
        ], 200);
    }
}