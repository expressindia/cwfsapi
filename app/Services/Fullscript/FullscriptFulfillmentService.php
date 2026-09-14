<?php

namespace App\Services\Fullscript;

use App\Services\FullscriptTokenService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FullscriptFulfillmentService
{
    public function __construct(
        protected FullscriptTokenService $tokenService
    ) {
    }

    public function createOrder(
        array $orderData,
        string $idempotencyKey
    ): array {

        $baseUrl = rtrim(
            config('fullscript.api_base_url'),
            '/'
        );

        if (blank($baseUrl)) {
            throw new RuntimeException(
                'FULLSCRIPT_API_BASE_URL is not configured.'
            );
        }

        if (
            blank(
                $orderData['shipping_method']
                ?? null
            )
        ) {
            throw new RuntimeException(
                'Fullscript shipping method is missing.'
            );
        }

        if (
            empty(
                $orderData['line_items']
                ?? []
            )
        ) {
            throw new RuntimeException(
                'No line items available for Fullscript.'
            );
        }

        $address =
            $orderData['shipping_address']
            ?? [];

        if (empty($address)) {
            throw new RuntimeException(
                'Shipping address is missing.'
            );
        }

        if (
            blank(
                $address['phone']
                ?? null
            )
        ) {
            throw new RuntimeException(
                'Customer phone number is required by Fullscript.'
            );
        }

        $lineItems = [];

        foreach (
            $orderData['line_items']
            as $item
        ) {

            $sku =
                $item['sku']
                ?? null;

            $quantity =
                (int) (
                    $item['quantity']
                    ?? 0
                );

            if (blank($sku)) {
                throw new RuntimeException(
                    'Line item SKU is missing.'
                );
            }

            if ($quantity <= 0) {
                throw new RuntimeException(
                    'Invalid quantity for SKU: ' . $sku
                );
            }

            $lineItems[] = [

                'sku' => (string) $sku,

                'quantity' => (string) $quantity,

            ];
        }

        $payload = [

            'shipping_method' =>
                (string) $orderData['shipping_method'],

            'line_items' =>
                $lineItems,

            'shipping_address' => [

                'firstname' =>
                    (string) (
                        $address['firstname']
                        ?? ''
                    ),

                'lastname' =>
                    (string) (
                        $address['lastname']
                        ?? ''
                    ),

                'address1' =>
                    (string) (
                        $address['address1']
                        ?? ''
                    ),

                'address2' =>
                    (string) (
                        $address['address2']
                        ?? ''
                    ),

                'city' =>
                    (string) (
                        $address['city']
                        ?? ''
                    ),

                'state' =>
                    (string) (
                        $address['state']
                        ?? ''
                    ),

                'zipcode' =>
                    (string) (
                        $address['zipcode']
                        ?? ''
                    ),

                'country' =>
                    (string) (
                        $address['country']
                        ?? ''
                    ),

                'phone' =>
                    (string) $address['phone'],

            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        | Reuse the existing FullscriptTokenService.
        |--------------------------------------------------------------------------
        */

        $accessToken =
            $this->tokenService
                ->freshAccessToken();

        $response = Http::withToken(
                $accessToken
            )
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->withHeaders([

                'Idempotency-Key' =>
                    $idempotencyKey,

            ])
            ->post(
                $baseUrl .
                '/fulfillment/orders',
                $payload
            );

        if ($response->failed()) {

            throw new RuntimeException(
                $this->responseMessage(
                    $response
                )
            );
        }

        return [

            'http_code' =>
                $response->status(),

            'request' =>
                $payload,

            'response' =>
                $response->json(),

        ];
    }

    protected function responseMessage(
        Response $response
    ): string {

        return
            'Fullscript fulfillment request failed (HTTP ' .
            $response->status() .
            '): ' .
            (
                $response->json('message')
                ??
                $response->json('error')
                ??
                $response->body()
            );
    }
}