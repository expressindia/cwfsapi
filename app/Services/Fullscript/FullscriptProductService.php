<?php

namespace App\Services\Fullscript;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\Services\FullscriptTokenService;
use RuntimeException;

class FullscriptProductService
{
    public function __construct(
        protected FullscriptTokenService $tokenService
    ) {
    }

    /**
     * Get Fullscript products.
     *
     * Used when no search/filter endpoint is required.
     */
    public function getProducts(
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

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->get(
                $baseUrl . '/catalog/products',
                [
                    'page[number]' => $page,
                    'page[size]' => $perPage,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                $this->responseMessage($response)
            );
        }

        return $response->json();
    }

    /**
     * Get all Fullscript brands.
     *
     * Endpoint:
     * GET /catalog/brands
     */
    public function getBrands(): array
    {
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

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->get(
                $baseUrl . '/catalog/brands'
            );

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
     * Endpoint:
     * GET /catalog/search/products
     *
     * Example:
     * /catalog/search/products
     * ?brand_id=d63354b8-635a-4559-927e-6cc278303363
     * &page[number]=1
     * &page[size]=25
     */
    public function searchProducts(
        ?string $brandId = null,
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

        if (!empty($brandId)) {
            $query['brand_id'] = $brandId;
        }

        /*
        |--------------------------------------------------------------------------
        | Product search
        |--------------------------------------------------------------------------
        |
        | Your supplied endpoint example confirms brand_id and pagination.
        | We pass "search" when the UI provides it.
        |
        | If Fullscript's endpoint uses another parameter such as "q"
        | or "query" for product-name/SKU search, change only this key.
        |
        */
        if (!empty($search)) {
            $query['search'] = $search;
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->get(
                $baseUrl . '/catalog/search/products',
                $query
            );

        if ($response->failed()) {
            throw new RuntimeException(
                $this->responseMessage($response)
            );
        }

        return $response->json();
    }

    /**
     * Get a single Fullscript product.
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

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->get(
                $baseUrl .
                '/catalog/products/' .
                urlencode($productId)
            );

        if ($response->failed()) {
            throw new RuntimeException(
                $this->responseMessage($response)
            );
        }

        return $response->json();
    }

    /**
     * Convert Fullscript HTTP error into a readable message.
     */
    protected function responseMessage(
        Response $response
    ): string {
        $json = $response->json();

        if (is_array($json)) {
            if (!empty($json['message'])) {
                return (string) $json['message'];
            }

            if (!empty($json['error'])) {
                return is_string($json['error'])
                    ? $json['error']
                    : json_encode($json['error']);
            }

            if (!empty($json['errors'])) {
                return is_string($json['errors'])
                    ? $json['errors']
                    : json_encode($json['errors']);
            }
        }

        return sprintf(
            'Fullscript API request failed with HTTP %s.',
            $response->status()
        );
    }
}