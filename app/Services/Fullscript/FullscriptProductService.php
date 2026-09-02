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
    public function getProducts( int $page = 1, int $perPage = 100 ): array 
    {
        $baseUrl = rtrim( config('fullscript.api_base_url'), '/' );

        if (blank($baseUrl)) {
            throw new RuntimeException(
                'FULLSCRIPT_API_BASE_URL is not configured.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Get valid access token
        |--------------------------------------------------------------------------
        |
        | This method gets the token from the database.
        | It also refreshes the token automatically when required.
        |
        */

        $accessToken = $this->tokenService ->freshAccessToken();
        /*
        |--------------------------------------------------------------------------
        | Fullscript API request
        |--------------------------------------------------------------------------
        */

        $response = Http::withToken( $accessToken )
            ->acceptJson()
            ->timeout(60)
            ->get( $baseUrl . '/catalog/products',
                [
                    'page[number]' => $page,
                    'page[size]' => $perPage,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                $this->responseMessage( $response )
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
}