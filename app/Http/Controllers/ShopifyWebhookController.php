<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessShopifyFulfillmentRequest;
use App\Models\ShopifyToken;
use App\Models\WebhookEvent;
use App\Support\Webhook\ShopifyWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function fulfillmentRequest(
        Request $request,
        ShopifyWebhookVerifier $verifier
    ): Response {
        $rawBody = $request->getContent();

        $hmac = $request->header('X-Shopify-Hmac-SHA256');

        $shop = $request->header('X-Shopify-Shop-Domain');

        $topic = $request->header('X-Shopify-Topic');

        $webhookId = $request->header('X-Shopify-Webhook-Id');

        Log::info(
            'Shopify fulfillment webhook received.',
            [
                'method' => $request->method(),

                'path' => $request->path(),

                'shop' => $shop,

                'topic' => $topic,

                'webhook_id' => $webhookId,

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

        if (! $verifier->verify($rawBody, $hmac)) {
            Log::warning(
                'Invalid Shopify webhook HMAC.',
                [
                    'shop' => $shop,
                    'topic' => $topic,
                    'webhook_id' => $webhookId,
                ]
            );

            return response('Unauthorized', 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify shop domain
        |--------------------------------------------------------------------------
        |
        | Do NOT compare the shop against config('shopify.store_domain').
        |
        | This is a multi-store application. The shop must be resolved
        | from the ShopifyToken table.
        |
        */

        if (blank($shop)) {
            Log::warning(
                'Shopify webhook missing shop domain.'
            );

            return response('Missing shop domain', 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Shopify credentials for this store
        |--------------------------------------------------------------------------
        */

        $shopifyToken = ShopifyToken::where(
            'shop_domain',
            $shop
        )->first();

        if (! $shopifyToken) {
            Log::warning(
                'No Shopify credentials found for webhook store.',
                [
                    'shop' => $shop,
                    'topic' => $topic,
                    'webhook_id' => $webhookId,
                ]
            );

            return response('Unauthorized', 401);
        }

        if (blank($shopifyToken->access_token)) {
            Log::error(
                'Shopify token exists but access token is missing.',
                [
                    'shop' => $shop,
                    'token_id' => $shopifyToken->id,
                ]
            );

            return response('Shopify credentials unavailable', 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Get webhook metadata
        |--------------------------------------------------------------------------
        */

        if (blank($webhookId)) {
            Log::warning(
                'Shopify webhook ID is missing.',
                [
                    'shop' => $shop,
                    'topic' => $topic,
                ]
            );

            return response('Missing webhook ID', 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Decode payload
        |--------------------------------------------------------------------------
        */

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            Log::error(
                'Shopify webhook contains invalid JSON.',
                [
                    'shop' => $shop,
                    'webhook_id' => $webhookId,
                ]
            );

            return response('Invalid JSON', 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Extract fulfillment order ID
        |--------------------------------------------------------------------------
        */

        $fulfillmentOrderId =
            $payload['submitted_fulfillment_order']['id']
            ?? $payload['original_fulfillment_order']['id']
            ?? null;

        if (blank($fulfillmentOrderId)) {
            Log::error(
                'Shopify webhook has no fulfillment order ID.',
                [
                    'shop' => $shop,
                    'webhook_id' => $webhookId,
                    'payload' => $payload,
                ]
            );

            return response(
                'Missing fulfillment order ID',
                400
            );
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
                    'shop' => $shop,

                    'webhook_id' => $webhookId,

                    'error' => $e->getMessage(),
                ]
            );

            return response(
                'Webhook storage error',
                500
            );
        }

        /*
        |--------------------------------------------------------------------------
        | If already processed, do nothing
        |--------------------------------------------------------------------------
        */

        if ($event->processed_at) {
            return response(
                'Already processed',
                200
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Dispatch fulfillment processing job
        |--------------------------------------------------------------------------
        |
        | Pass the Shopify token ID so the queued job can retrieve the
        | correct store credentials from the database.
        |
        */

        ProcessShopifyFulfillmentRequest::dispatch(
            $event->id,
            (string) $fulfillmentOrderId,
            $shopifyToken->id
        );

        /*
        |--------------------------------------------------------------------------
        | Return immediately to Shopify
        |--------------------------------------------------------------------------
        */

        return response(
            'Accepted',
            202
        );
    }
}