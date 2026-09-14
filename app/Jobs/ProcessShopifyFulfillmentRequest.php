<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Shopify\ShopifyFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessShopifyFulfillmentRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $webhookEventId,
        public string $fulfillmentOrderId
    ) {
    }

    public function handle(
        ShopifyFulfillmentService $service
    ): void {

        $event =
            WebhookEvent::find(
                $this->webhookEventId
            );

        if (!$event) {

            Log::error(
                'Webhook event not found.',
                [
                    'webhook_event_id' =>
                        $this->webhookEventId,
                ]
            );

            return;
        }

        if ($event->processed_at) {

            Log::info(
                'Webhook already processed.',
                [
                    'webhook_event_id' =>
                        $event->id,
                ]
            );

            return;
        }

        $service->processFulfillmentRequest(
            $this->fulfillmentOrderId
        );

        $event->update([
            'processed_at' =>
                Carbon::now(),
        ]);

        Log::info(
            'Shopify fulfillment request processed.',
            [
                'webhook_event_id' =>
                    $event->id,

                'fulfillment_order_id' =>
                    $this->fulfillmentOrderId,
            ]
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error(
            'Shopify fulfillment job failed.',
            [
                'webhook_event_id' =>
                    $this->webhookEventId,

                'fulfillment_order_id' =>
                    $this->fulfillmentOrderId,

                'error' =>
                    $exception->getMessage(),
            ]
        );
    }
}