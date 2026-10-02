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
     *
     * This is used when the Products page is opened without filters.
     */
    public function getProducts(
        int $page = 1,
        int $perPage = 10
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

        $accessToken =
            $this->tokenService->freshAccessToken();

        $url =
            $baseUrl . '/catalog/products';

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->get($url, [
                    'page[number]' => $page,
                    'page[size]' => $perPage,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Unable to connect to Fullscript catalog API: '
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
     * Get one Fullscript product by ID.
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

        $accessToken =
            $this->tokenService->freshAccessToken();

        $url =
            $baseUrl
            . '/catalog/products/'
            . urlencode($productId);

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Unable to connect to Fullscript product API: '
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
     * Search Fullscript products.
     *
     * This is used only when the user enters a filter.
     *
     * IMPORTANT:
     * The newer /catalog/search/products endpoint was supplied
     * separately from the Fulfillment API reference.
     *
     * The brand parameter below is "brand".
     * If Fullscript's current endpoint expects a different
     * parameter name, this is the only place that needs changing.
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

        $accessToken =
            $this->tokenService->freshAccessToken();

        $query = [
            'page[number]' => $page,
            'page[size]' => $perPage,
        ];

        if (
            $brand !== null
            && $brand !== ''
        ) {
            $query['brand'] = $brand;
        }

        if (
            $search !== null
            && $search !== ''
        ) {
            $query['search'] = $search;
        }

        $url =
            $baseUrl
            . '/catalog/search/products';

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Unable to connect to Fullscript search API: '
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
     * Convert Fullscript response into a useful exception message.
     */
    protected function responseMessage(
        Response $response
    ): string {
        $status =
            $response->status();

        $json =
            $response->json();

        if (is_array($json)) {

            $message =
                $json['message']
                ?? $json['error']
                ?? $json['errors']
                ?? null;

            if (is_array($message)) {
                $message =
                    json_encode(
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

        $body =
            trim(
                $response->body()
            );

        if ($body !== '') {
            return sprintf(
                'Fullscript API error (%s): %s',
                $status,
                $body
            );
        }

        return sprintf(
            'Fullscript API request failed with HTTP status %s.',
            $status
        );
    }
}