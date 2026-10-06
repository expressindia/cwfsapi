<?php

namespace App\Services\Fullscript;

use App\Services\FullscriptTokenService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FullscriptProductService
{
    public function __construct(
        protected FullscriptTokenService $tokenService
    ) {
    }

    /**
     * Get products from the Fullscript catalog.
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

        $url =
            $baseUrl . '/catalog/products';

        try {

            $response = Http::withToken(
                $accessToken
            )
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->get(
                    $url,
                    [
                        'page[number]' =>
                            $page,

                        'page[size]' =>
                            $perPage,
                    ]
                );

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
                $this->responseMessage(
                    $response
                )
            );
        }

        return $response->json();
    }

    /**
     * Get one Fullscript product.
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

            $response = Http::withToken(
                $accessToken
            )
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
                $this->responseMessage(
                    $response
                )
            );
        }

        return $response->json();
    }

    /**
     * Search Fullscript products.
     *
     * IMPORTANT:
     * $brandId must be the Fullscript brand ID,
     * not the brand name.
     */
        /**
 * Search Fullscript products.
 *
 * SKU search is handled locally because the Fullscript
 * catalog search endpoint does not reliably filter by SKU.
 *
 * Brand filtering is still sent to Fullscript using brand_id.
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

        /*
        |--------------------------------------------------------------------------
        | SKU search
        |--------------------------------------------------------------------------
        */

        $search = trim(
            (string) $search
        );

        if ($search !== '') {
            return $this->searchProductsBySku(
                $accessToken,
                $baseUrl,
                $brandId,
                $search,
                $page,
                $perPage
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Normal filtered catalog request
        |--------------------------------------------------------------------------
        */

        $query = [
            'page[number]' =>
                $page,

            'page[size]' =>
                $perPage,
        ];

        if (
            $brandId !== null
            && $brandId !== ''
        ) {
            $query['brand_id'] =
                $brandId;
        }

        $url =
            $baseUrl
            . '/catalog/search/products';

        Log::info(
            'FULLSCRIPT SEARCH QUERY',
            [
                'url' =>
                    $url,

                'query' =>
                    $query,
            ]
        );

        try {

            $response = Http::withToken(
                $accessToken
            )
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->get(
                    $url,
                    $query
                );

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
                $this->responseMessage(
                    $response
                )
            );
        }

        return $response->json();
    }



    /**
 * Search the Fullscript catalog by SKU.
 *
 * Fullscript's catalog search endpoint does not reliably
 * filter the returned products by SKU, so we search the
 * catalog pages and build the SKU result set ourselves.
 */
    protected function searchProductsBySku(
        string $accessToken,
        string $baseUrl,
        ?string $brandId,
        string $search,
        int $page,
        int $perPage
    ): array {

        $url =
            $baseUrl
            . '/catalog/search/products';

        /*
        |--------------------------------------------------------------------------
        | Use 100 products per Fullscript API request
        |--------------------------------------------------------------------------
        |
        | This reduces the number of API requests substantially.
        |
        */

        $apiPerPage = 100;

        $apiPage = 1;

        $matchingProducts = [];

        $totalApiPages = 1;

        Log::info(
            'FULLSCRIPT SKU SEARCH START',
            [
                'sku' =>
                    $search,

                'brand_id' =>
                    $brandId,

                'requested_page' =>
                    $page,

                'requested_per_page' =>
                    $perPage,
            ]
        );

        while ($apiPage <= $totalApiPages) {

            $query = [
                'page[number]' =>
                    $apiPage,

                'page[size]' =>
                    $apiPerPage,
            ];

            if (
                $brandId !== null
                && $brandId !== ''
            ) {
                $query['brand_id'] =
                    $brandId;
            }

            Log::info(
                'FULLSCRIPT SKU SEARCH PAGE',
                [
                    'page' =>
                        $apiPage,

                    'query' =>
                        $query,

                    'sku' =>
                        $search,
                ]
            );

            try {

                $response = Http::withToken(
                    $accessToken
                )
                    ->acceptJson()
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->get(
                        $url,
                        $query
                    );

            } catch (ConnectionException $e) {

                throw new RuntimeException(
                    'Unable to connect to Fullscript SKU search API: '
                    . $e->getMessage(),
                    0,
                    $e
                );
            }

            if ($response->failed()) {
                throw new RuntimeException(
                    $this->responseMessage(
                        $response
                    )
                );
            }

            $json =
                $response->json();

            $rawProducts =
                $json['products']
                ?? $json['data']
                ?? [];

            if (!is_array($rawProducts)) {
                $rawProducts = [];
            }

            /*
            |--------------------------------------------------------------------------
            | Find total API pages
            |--------------------------------------------------------------------------
            */

            $meta =
                $json['meta']
                ?? [];

            $totalApiPages =
                (int) (
                    $meta['total_pages']
                    ?? $meta['last_page']
                    ?? 1
                );

            if ($totalApiPages <= 0) {
                $totalApiPages = 1;
            }

            /*
            |--------------------------------------------------------------------------
            | Check every product for SKU
            |--------------------------------------------------------------------------
            */

            foreach ($rawProducts as $product) {

                if (!is_array($product)) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | JSON:API support
                |--------------------------------------------------------------------------
                */

                if (
                    isset($product['attributes'])
                    && is_array(
                        $product['attributes']
                    )
                ) {

                    $product =
                        array_merge(
                            [
                                'id' =>
                                    $product['id']
                                    ?? null,
                            ],
                            $product['attributes']
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Get SKU
                |--------------------------------------------------------------------------
                */

                $sku = '';

                if (
                    isset(
                        $product['primary_variant']
                    )
                    && is_array(
                        $product['primary_variant']
                    )
                ) {

                    $sku =
                        trim(
                            (string) (
                                $product[
                                    'primary_variant'
                                ]['sku']
                                ?? ''
                            )
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Fallback SKU fields
                |--------------------------------------------------------------------------
                */

                if ($sku === '') {

                    $sku =
                        trim(
                            (string) (
                                $product['sku']
                                ?? ''
                            )
                        );
                }

                if ($sku === '') {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | SKU match
                |--------------------------------------------------------------------------
                |
                | Case-insensitive partial SKU search.
                |
                | Example:
                |
                | Search: VN-FO
                |
                | Matches:
                | VN-FO130
                | VN-FO2
                | VN-FO430
                |
                */

                if (
                    stripos(
                        $sku,
                        $search
                    ) === false
                ) {
                    continue;
                }

                $matchingProducts[] =
                    $product;
            }

            $apiPage++;

        }

        /*
        |--------------------------------------------------------------------------
        | Remove duplicates
        |--------------------------------------------------------------------------
        */

        $uniqueProducts = [];

        foreach (
            $matchingProducts
            as $product
        ) {

            $id =
                (string) (
                    $product['id']
                    ?? ''
                );

            $sku = '';

            if (
                isset(
                    $product['primary_variant']
                )
                && is_array(
                    $product['primary_variant']
                )
            ) {

                $sku =
                    trim(
                        (string) (
                            $product[
                                'primary_variant'
                            ]['sku']
                            ?? ''
                        )
                    );
            }

            $key =
                $id !== ''
                ? $id
                : $sku;

            if ($key === '') {
                continue;
            }

            $uniqueProducts[$key] =
                $product;
        }

        $matchingProducts =
            array_values(
                $uniqueProducts
            );

        /*
        |--------------------------------------------------------------------------
        | Correct search pagination
        |--------------------------------------------------------------------------
        */

        $total =
            count(
                $matchingProducts
            );

        $totalPages =
            max(
                1,
                (int) ceil(
                    $total / $perPage
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Keep requested page inside valid range
        |--------------------------------------------------------------------------
        */

        $page =
            min(
                max($page, 1),
                $totalPages
            );

        /*
        |--------------------------------------------------------------------------
        | Slice products for requested page
        |--------------------------------------------------------------------------
        */

        $offset =
            ($page - 1)
            * $perPage;

        $pageProducts =
            array_slice(
                $matchingProducts,
                $offset,
                $perPage
            );

        Log::info(
            'FULLSCRIPT SKU SEARCH RESULT',
            [
                'sku' =>
                    $search,

                'total_matches' =>
                    $total,

                'total_pages' =>
                    $totalPages,

                'requested_page' =>
                    $page,

                'returned_products' =>
                    count($pageProducts),
            ]
        );

        return [
            'products' =>
                array_values(
                    $pageProducts
                ),

            'meta' => [
                'current_page' =>
                    $page,

                'total_count' =>
                    $total,

                'total_pages' =>
                    $totalPages,
            ],
        ];
    }
    /**
     * Get Fullscript brands.
     */
    public function getBrands(
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

        $accessToken =
            $this->tokenService->freshAccessToken();

        $url =
            $baseUrl . '/catalog/brands';

        $perPage =
            min(
                max($perPage, 1),
                100
            );

        Log::info(
            'FULLSCRIPT BRANDS REQUEST',
            [
                'url' =>
                    $url,

                'page' =>
                    $page,

                'perPage' =>
                    $perPage,
            ]
        );

        try {

            $response = Http::withToken(
                $accessToken
            )
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->get(
                    $url,
                    [
                        'page[number]' =>
                            $page,

                        'page[size]' =>
                            $perPage,
                    ]
                );

        } catch (ConnectionException $e) {

            throw new RuntimeException(
                'Unable to connect to Fullscript brands API: '
                . $e->getMessage(),
                0,
                $e
            );
        }

        Log::info(
            'FULLSCRIPT BRANDS RESPONSE',
            [
                'status' =>
                    $response->status(),

                'successful' =>
                    $response->successful(),
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException(
                $this->responseMessage(
                    $response
                )
            );
        }

        return $response->json();
    }

    /**
     * Find Fullscript brand ID by brand name.
     */
    public function findBrandIdByName(
        string $brandName
    ): ?string {
        $brandName =
            trim($brandName);

        if ($brandName === '') {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Search through brand pages
        |--------------------------------------------------------------------------
        */

        $page = 1;

        $perPage = 100;

        $maxPages = 100;

        while ($page <= $maxPages) {

            $response =
                $this->getBrands(
                    $page,
                    $perPage
                );

            /*
            |--------------------------------------------------------------------------
            | Extract brands
            |--------------------------------------------------------------------------
            */

            $brands =
                $response['brands']
                ?? $response['data']
                ?? [];

            if (
                !is_array($brands)
                || empty($brands)
            ) {
                break;
            }

            foreach ($brands as $rawBrand) {

                if (!is_array($rawBrand)) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Support JSON:API-style attributes
                |--------------------------------------------------------------------------
                */

                if (
                    isset(
                        $rawBrand['attributes']
                    )
                    && is_array(
                        $rawBrand['attributes']
                    )
                ) {

                    $brand = array_merge(
                        [
                            'id' =>
                                $rawBrand['id']
                                ?? null,
                        ],
                        $rawBrand['attributes']
                    );

                } else {

                    $brand =
                        $rawBrand;
                }

                $name =
                    $brand['name']
                    ?? $brand['brand_name']
                    ?? '';

                $id =
                    $brand['id']
                    ?? $brand['brand_id']
                    ?? null;

                if (
                    $id === null
                    || trim((string) $name) === ''
                ) {
                    continue;
                }

                if (
                    strcasecmp(
                        trim((string) $name),
                        $brandName
                    ) === 0
                ) {

                    Log::info(
                        'FULLSCRIPT BRAND FOUND',
                        [
                            'brand_name' =>
                                $brandName,

                            'brand_id' =>
                                $id,
                        ]
                    );

                    return (string) $id;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Determine whether another page exists
            |--------------------------------------------------------------------------
            */

            $meta =
                $response['meta']
                ?? [];

            $totalPages =
                (int) (
                    $meta['total_pages']
                    ?? $meta['last_page']
                    ?? $response['total_pages']
                    ?? 0
                );

            if (
                $totalPages > 0
                && $page >= $totalPages
            ) {
                break;
            }

            /*
            |--------------------------------------------------------------------------
            | If fewer than 100 brands were returned,
            | this is normally the last page.
            |--------------------------------------------------------------------------
            */

            if (
                count($brands)
                < $perPage
            ) {
                break;
            }

            $page++;
        }

        Log::warning(
            'FULLSCRIPT BRAND NOT FOUND',
            [
                'brand_name' =>
                    $brandName,
            ]
        );

        return null;
    }

    /**
     * Convert Fullscript API errors into a readable message.
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