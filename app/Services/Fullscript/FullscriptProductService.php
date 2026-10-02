<?php

namespace App\Services\Fullscript;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\Services\FullscriptTokenService;
use RuntimeException;

class FullscriptProductService
{
    public function __construct(protected FullscriptTokenService $tokenService) {
    }


    /**
     * Get products from Fullscript.
     */
    public function getProducts(int $page = 1, int $perPage = 100): array
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

        /*
        |--------------------------------------------------------------------------
        | Get access token
        |--------------------------------------------------------------------------
        */

        logger()->info(
            'Fullscript products sync: requesting access token.'
        );

        $accessToken = $this->tokenService->freshAccessToken();

        logger()->info(
            'Fullscript products sync: access token received.'
        );

        /*
        |--------------------------------------------------------------------------
        | Fullscript API request
        |--------------------------------------------------------------------------
        */

        $url = $baseUrl . '/catalog/products';

        logger()->info(
            'Fullscript products sync: requesting products.',
            [
                'url' => $url,
                'page' => $page,
                'per_page' => $perPage,
            ]
        );

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->get(
                $url,
                [
                    'page[number]' => $page,
                    'page[size]' => $perPage,
                ]
            );

        logger()->info(
            'Fullscript products sync: product API responded.',
            [
                'status' => $response->status(),
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
     * Get a single product from Fullscript.
     */
    public function getProduct( string $productId ): array 
    {

        $baseUrl = rtrim( config('fullscript.api_base_url'), '/' );

        $accessToken = $this->tokenService ->freshAccessToken();

        $response = Http::withToken( $accessToken )
            ->acceptJson()
            ->timeout(60)
            ->get( $baseUrl . '/catalog/products/' . urlencode($productId) );

        if ($response->failed()) {
            throw new RuntimeException(
                $this->responseMessage( $response )
            );
        }

        return $response->json();
    }

    protected function responseMessage( Response $response ): string 
    {
        return
            'Fullscript product request failed ' . '(HTTP ' . $response->status() . '): ' .
            (
                $response->json('message')
                ??
                $response->json('error')
                ??
                $response->body()
            );
    }

    /**
 * Get Fullscript brands.
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
            'page[size]'   => $perPage,
        ];


        if (!empty($brandId)) {

            $query['brand_id'] =
                $brandId;

        }


        /*
        * Only add the search parameter if the
        * Fullscript search endpoint accepts it.
        *
        * We can adjust the exact parameter after
        * seeing the actual API response/request.
        */
        if (!empty($search)) {

            $query['search'] =
                $search;

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
}