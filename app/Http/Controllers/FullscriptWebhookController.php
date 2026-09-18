<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFullscriptShipment;
use App\Models\FulfillmentOrder;
use App\Support\Webhook\FullscriptWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FullscriptWebhookController extends Controller
{
    public function __construct(
        protected FullscriptWebhookVerifier $webhookVerifier
    ) {
    }

    public function handle(Request $request)
    {
        $rawBody = $request->getContent();

        $signature = $request->header('Fullscript-Signature');

        Log::info(
            'Fullscript webhook received.',
            [
                'method' => $request->method(),
                'path' => $request->path(),
                'headers' => $request->headers->all(),
                'query' => $request->query(),
                'body' => $rawBody,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Fullscript webhook registration verification
        |--------------------------------------------------------------------------
        */

        $challengeToken = config(
            'fullscript.webhook.challenge_token'
        );

        if (blank($challengeToken)) {
            Log::error(
                'Fullscript webhook challenge token is not configured.'
            );

            return response()->json([
                'error' => 'Webhook challenge token is not configured.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Fullscript sends an empty request during verification
        |--------------------------------------------------------------------------
        */

        if (blank($rawBody)) {
            Log::info(
                'Fullscript webhook verification request received.'
            );

            return response()->json([
                'challenge' => $challengeToken,
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Fullscript webhook signature
        |--------------------------------------------------------------------------
        */

        if (! $this->webhookVerifier->verify(
            $rawBody,
            $signature
        )) {
            Log::warning(
                'Invalid Fullscript webhook signature.',
                [
                    'signature_present' => filled($signature),
                ]
            );

            return response()->json([
                'error' => 'Invalid webhook signature.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Decode webhook
        |--------------------------------------------------------------------------
        */

        $payload = json_decode(
            $rawBody,
            true
        );

        if (! is_array($payload)) {
            Log::error(
                'Fullscript webhook contains invalid JSON.'
            );

            return response()->json([
                'error' => 'Invalid JSON payload.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Event
        |--------------------------------------------------------------------------
        */

        $event = $payload['event'] ?? null;

        if (! is_array($event)) {
            Log::warning(
                'Fullscript webhook event is missing.'
            );

            return response()->json([
                'success' => true,
            ], 200);
        }

        $eventId = $event['id'] ?? null;

        $eventType = $event['type'] ?? null;

        Log::info(
            'Fullscript webhook event received.',
            [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payload' => $payload,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | We only need shipment shipped events here
        |--------------------------------------------------------------------------
        */

        if (
            $eventType !==
            'fulfillment.shipment.shipped'
        ) {
            Log::info(
                'Fullscript webhook event ignored.',
                [
                    'event_type' => $eventType,
                ]
            );

            return response()->json([
                'success' => true,
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Shipment
        |--------------------------------------------------------------------------
        */

        $shipment = $event['data']['fulfillment_shipment']
            ?? null;

        if (! is_array($shipment)) {
            Log::error(
                'Fullscript fulfillment shipment data is missing.',
                [
                    'event_id' => $eventId,
                ]
            );

            return response()->json([
                'error' => 'Shipment data is missing.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Fullscript Order ID
        |--------------------------------------------------------------------------
        */

        $fullscriptOrderId =
            $shipment['order_id'] ?? null;

        if (blank($fullscriptOrderId)) {
            Log::error(
                'Fullscript shipment does not contain order_id.',
                [
                    'event_id' => $eventId,
                ]
            );

            return response()->json([
                'error' => 'Fullscript order ID is missing.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Shopify ↔ Fullscript mapping
        |--------------------------------------------------------------------------
        */

        $fulfillmentOrder =
            FulfillmentOrder::where(
                'fullscript_order_id',
                $fullscriptOrderId
            )->first();

        if (! $fulfillmentOrder) {
            Log::warning(
                'No Shopify fulfillment order found for Fullscript order.',
                [
                    'fullscript_order_id' =>
                        $fullscriptOrderId,

                    'event_id' =>
                        $eventId,
                ]
            );

            /*
             * Return 200 so Fullscript doesn't continuously
             * retry an order we don't know about.
             */

            return response()->json([
                'success' => true,
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Prepare shipment data
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | The actual Fullscript payload contains:
        |
        | shipments[]
        |     shipmentTracking[]
        |         carrier
        |         trackingNumber
        |
        */

        $shipments = $payload['shipments'] ?? [];

        $trackingData = [
            'event_id' => $eventId,

            'shipment_number' =>
                $shipment['number'] ?? null,

            'state' =>
                $shipment['state'] ?? null,

            'shipped_at' =>
                $shipment['shipped_at'] ?? null,

            'delivered_at' =>
                $shipment['delivered_at'] ?? null,

            'tracking_url' =>
                $shipment['tracking_url'] ?? null,

            'carrier' => null,

            'tracking_number' => null,

            'order_shipment_state' =>
                $shipment['order_shipment_state'] ?? null,

            'shipped_skus' =>
                $shipment['shipped_skus'] ?? [],

            'shipments' => $shipments,
        ];

        /*
        |--------------------------------------------------------------------------
        | Extract tracking information from actual Fullscript payload
        |--------------------------------------------------------------------------
        */

        foreach ($shipments as $fullscriptShipment) {
            $shipmentTrackings =
                $fullscriptShipment['shipmentTracking'] ?? [];

            foreach ($shipmentTrackings as $tracking) {
                $carrier =
                    $tracking['carrier'] ?? null;

                $trackingNumber =
                    $tracking['trackingNumber'] ?? null;

                if (
                    blank($carrier) &&
                    blank($trackingNumber)
                ) {
                    continue;
                }

                /*
                 * Store the first tracking number in the
                 * main tracking fields.
                 */
                if (blank($trackingData['carrier'])) {
                    $trackingData['carrier'] = $carrier;
                }

                if (blank($trackingData['tracking_number'])) {
                    $trackingData['tracking_number'] =
                        $trackingNumber;
                }

                Log::info(
                    'Fullscript shipment tracking found.',
                    [
                        'fullscript_order_id' =>
                            $fullscriptOrderId,

                        'shipment_number' =>
                            $fullscriptShipment['shipmentNumber']
                            ?? null,

                        'carrier' => $carrier,

                        'tracking_number' =>
                            $trackingNumber,

                        'tracking_id' =>
                            $tracking['id'] ?? null,
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Save Fullscript shipment
        |--------------------------------------------------------------------------
        */

        $fulfillmentOrder->update([
            'tracking_data' => $trackingData,
            'status' => 'shipped',
        ]);

        Log::info(
            'Fullscript shipment saved.',
            [
                'fulfillment_order_id' =>
                    $fulfillmentOrder->id,

                'fullscript_order_id' =>
                    $fullscriptOrderId,

                'tracking_data' =>
                    $trackingData,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Check tracking information
        |--------------------------------------------------------------------------
        */

        $trackingNumber =
            $trackingData['tracking_number'] ?? null;

        $carrier =
            $trackingData['carrier'] ?? null;

        if (
            blank($trackingNumber) ||
            blank($carrier)
        ) {
            Log::info(
                'Fullscript shipment has no complete tracking information yet.',
                [
                    'fullscript_order_id' =>
                        $fullscriptOrderId,

                    'carrier' =>
                        $carrier,

                    'tracking_number' =>
                        $trackingNumber,
                ]
            );

            return response()->json([
                'success' => true,
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Dispatch Shopify tracking update
        |--------------------------------------------------------------------------
        */

        ProcessFullscriptShipment::dispatch(
            $fulfillmentOrder->id
        );

        Log::info(
            'Fullscript shipment processing job dispatched.',
            [
                'fulfillment_order_id' =>
                    $fulfillmentOrder->id,

                'fullscript_order_id' =>
                    $fullscriptOrderId,

                'carrier' =>
                    $carrier,

                'tracking_number' =>
                    $trackingNumber,
            ]
        );

        return response()->json([
            'success' => true,
        ], 200);
    }
}