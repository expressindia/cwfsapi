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
            Log::warning('Fullscript shipment does not contain order_id.', [
                'event_id' => $eventId,
                'shipment' => $shipment,
            ]);

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
            Log::warning('No local fulfillment order found for Fullscript order.', [
                'event_id' => $eventId,
                'fullscript_order_id' => $fullscriptOrderId,
            ]);

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
        | Extract shipment tracking information
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Fullscript sends the tracking information directly inside:
        |
        | event.data.fulfillment_shipment
        |
        | Example:
        |
        | "carrier": "UPS",
        | "tracking_number": "TR6419441179e9ca1",
        | "tracking_url": "https://www.ups.com/track..."
        |
        | Previously the code was looking for:
        |
        | payload.shipments[].shipmentTracking[]
        |
        | which does not exist in this webhook payload.
        |
        */
        $trackingData = [
            'event_id' => $eventId,

            'shipment_number' => $shipment['number'] ?? null,

            'state' => $shipment['state'] ?? null,

            'shipped_at' => $shipment['shipped_at'] ?? null,

            'delivered_at' => $shipment['delivered_at'] ?? null,

            'tracking_url' => $shipment['tracking_url'] ?? null,

            'carrier' => $shipment['carrier'] ?? null,

            'tracking_number' => $shipment['tracking_number'] ?? null,

            'order_shipment_state' => $shipment['order_shipment_state'] ?? null,

            'shipped_skus' => $shipment['shipped_skus'] ?? [],

            /*
             * Keep this field for compatibility with the existing
             * tracking_data structure.
             */
            'shipments' => [],
        ];

        Log::info('Fullscript shipment tracking information extracted.', [
            'event_id' => $eventId,
            'fullscript_order_id' => $fullscriptOrderId,
            'carrier' => $trackingData['carrier'],
            'tracking_number' => $trackingData['tracking_number'],
            'tracking_url' => $trackingData['tracking_url'],
            'shipment_number' => $trackingData['shipment_number'],
            'state' => $trackingData['state'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Save tracking data locally
        |--------------------------------------------------------------------------
        */
        $fulfillmentOrder->update([
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
                    'fullscript_order_id' => $fullscriptOrderId,
                    'carrier' => $trackingData['carrier'],
                    'tracking_number' => $trackingData['tracking_number'],
                    'tracking_url' => $trackingData['tracking_url'],
                ]
            );

            return response()->json([
                'message' => 'Shipment received but tracking information is incomplete.',
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Dispatch job to update Shopify
        |--------------------------------------------------------------------------
        |
        | The ProcessFullscriptShipment job is responsible for taking the
        | saved tracking information and updating the Shopify fulfillment.
        |
        */
        Log::info('Dispatching ProcessFullscriptShipment job.', [
            'fulfillment_order_id' => $fulfillmentOrder->id,
            'fullscript_order_id' => $fullscriptOrderId,
            'carrier' => $trackingData['carrier'],
            'tracking_number' => $trackingData['tracking_number'],
        ]);

        ProcessFullscriptShipment::dispatch(
            $fulfillmentOrder->id
        );

        Log::info('ProcessFullscriptShipment job dispatched.', [
            'fulfillment_order_id' => $fulfillmentOrder->id,
            'fullscript_order_id' => $fullscriptOrderId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Return success
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'message' => 'Fullscript shipment processed successfully.',
        ], 200);
    }
}