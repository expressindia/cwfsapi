<?php

namespace App\Services\Fullscript;

use App\Services\FullscriptTokenService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FullscriptProductService
{
    public function __construct(
        protected FullscriptTokenService $tokenService
    ) {
    }

    /**
     * Get products from the Fullscript fulfillment catalog.
     */
    public function getProducts(
        int $page = 1,
        int $perPage = 100
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

        $accessToken = $this->tokenService->freshAccessToken();

        $url = $baseUrl . '/catalog/products';

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->get($url, [
                'page[number]' => $page,
                'page[size]' => $perPage,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                $this->responseMessage($response)
            );
        }

        return $response->json();
    }

    /**
     * Get detailed Fullscript product.
     */
    public function getProduct(
        string $productId
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

        $accessToken = $this->tokenService->freshAccessToken();

        $url = $baseUrl
            . '/catalog/products/'
            . urlencode($productId);

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException(
                $this->responseMessage($response)
            );
        }

        return $response->json();
    }

    /**
     * Search Fullscript products.
     *
     * IMPORTANT:
     * The brand parameter below assumes the Fullscript search
     * endpoint accepts "brand".
     *
     * If Fullscript specifies another parameter name, change
     * only this query parameter.
     */
    public function searchProducts(
        ?string $brand = null,
        ?string $search = null,
        int $page = 1,
        int $perPage = 25
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

        $accessToken = $this->tokenService->freshAccessToken();

        $query = [
            'page[number]' => $page,
            'page[size]' => $perPage,
        ];

        /*
        |--------------------------------------------------------------------------
        | Brand
        |--------------------------------------------------------------------------
        */

        if ($brand !== null && $brand !== '') {
            $query['brand'] = $brand;
        }

        /*
        |--------------------------------------------------------------------------
        | Product name / SKU
        |--------------------------------------------------------------------------
        */

        if ($search !== null && $search !== '') {
            $query['search'] = $search;
        }

        $url = $baseUrl . '/catalog/search/products';

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Unable to connect to Fullscript product search API: '
                . $e->getMessage(),
                0,
                $e
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                $this->responseMessage($response)
            );
        }

        return $response->json();
    }

    /**
     * Get a useful Fullscript API error message.
     */
    protected function responseMessage(
        Response $response
    ): string {
        $status = $response->status();

        $body = $response->json();

        if (is_array($body)) {
            $message =
                $body['message']
                ?? $body['error']
                ?? $body['errors']
                ?? null;

            if (is_array($message)) {
                $message = json_encode(
                    $message,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                );
            }

            if ($message) {
                return sprintf(
                    'Fullscript API error (%s): %s',
                    $status,
                    $message
                );
            }
        }

        $text = trim(
            $response->body()
        );

        if ($text !== '') {
            return sprintf(
                'Fullscript API error (%s): %s',
                $status,
                $text
            );
        }

        return sprintf(
            'Fullscript API request failed with HTTP status %s.',
            $status
        );
    }
}