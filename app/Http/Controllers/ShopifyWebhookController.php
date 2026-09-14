<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessShopifyFulfillmentRequest;
use App\Models\WebhookEvent;
use App\Support\Webhook\ShopifyWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function fulfillmentRequest( Request $request, ShopifyWebhookVerifier $verifier ): Response {

        $rawBody = $request->getContent();

        $hmac = $request->header( 'X-Shopify-Hmac-SHA256' );


        Log::info(
            'Shopify fulfillment webhook received.',
            [
                'method' => $request->method(),

                'path' => $request->path(),

                'shop' => $request->header(
                    'X-Shopify-Shop-Domain'
                ),

                'topic' => $request->header(
                    'X-Shopify-Topic'
                ),

                'webhook_id' => $request->header(
                    'X-Shopify-Webhook-Id'
                ),

                'hmac_present' => filled($hmac),

                'body_length' => strlen($rawBody),

                'body' => $rawBody,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Shopify HMAC
        |--------------------------------------------------------------------------
        */

        if ( !$verifier->verify( $rawBody, $hmac ) ) {

            Log::warning( 'Invalid Shopify webhook HMAC.' );

            return response( 'Unauthorized', 401 );
        }


        /*
        |--------------------------------------------------------------------------
        | Verify shop
        |--------------------------------------------------------------------------
        */

        $shop =
            $request->header( 'X-Shopify-Shop-Domain' );

        if ( blank($shop) || $shop !== config('shopify.store_domain') ) {

            Log::warning( 'Invalid Shopify shop domain.', [ 'shop' => $shop, ] );

            return response( 'Unauthorized', 401 );
        }

        /*
        |--------------------------------------------------------------------------
        | Get webhook metadata
        |--------------------------------------------------------------------------
        */

        $topic = $request->header( 'X-Shopify-Topic' );

        $webhookId = $request->header( 'X-Shopify-Webhook-Id' );

        if (blank($webhookId)) {

            return response( 'Missing webhook ID', 400 );
        }

        $payload = json_decode( $rawBody, true );

        if (!is_array($payload)) {

            return response( 'Invalid JSON',  400 );
        }

        /*
        |--------------------------------------------------------------------------
        | Extract fulfillment order ID
        |--------------------------------------------------------------------------
        */

        $fulfillmentOrderId = $payload[ 'submitted_fulfillment_order' ]['id'] ?? $payload[ 'original_fulfillment_order' ]['id'] ?? null;

        if (blank($fulfillmentOrderId)) {

            Log::error('Shopify webhook has no fulfillment order ID.', [ 'payload' => $payload, ] );

            return response( 'Missing fulfillment order ID', 400 );
        }

        /*
        |--------------------------------------------------------------------------
        | Store webhook
        |--------------------------------------------------------------------------
        */

        try {

            $event = WebhookEvent::firstOrCreate(

                    [
                        'provider' => 'shopify',

                        'webhook_id' => $webhookId,
                    ],

                    [

                        'topic' => $topic,

                        'payload' => $payload,

                    ]
                );

        } catch (\Throwable $e) {

            Log::error(
                'Unable to store Shopify webhook.',
                [
                    'error' => $e->getMessage(),
                ]
            );

            return response( 'Webhook storage error', 500 );
        }

        /*
        |--------------------------------------------------------------------------
        | If already processed, do nothing
        |--------------------------------------------------------------------------
        */

        if ($event->processed_at) {

            return response( 'Already processed', 200 );
        }

        /*
        |--------------------------------------------------------------------------
        | Dispatch job
        |--------------------------------------------------------------------------
        */

        ProcessShopifyFulfillmentRequest::dispatch( $event->id, (string) $fulfillmentOrderId );

        /*
        |--------------------------------------------------------------------------
        | Return immediately to Shopify
        |--------------------------------------------------------------------------
        */

        return response( 'Accepted', 202 );
    }
}