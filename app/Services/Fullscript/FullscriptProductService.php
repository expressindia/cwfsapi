<?php

namespace App\Services\Fullscript;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FullscriptProductService
{
    public function getProducts(
        int $page = 1,
        int $perPage = 100
    ): array {

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        | Replace the endpoint below with the exact Fullscript
        | catalog/products endpoint already used by your project.
        |--------------------------------------------------------------------------
        */

        $baseUrl = rtrim(
            config(
                'fullscript.api_base_url',
                ''
            ),
            '/'
        );

        $accessToken =
            config(
                'fullscript.access_token'
            );

        if (
            !$baseUrl ||
            !$accessToken
        ) {

            throw new RuntimeException(
                'Fullscript API configuration is incomplete.'
            );
        }

        $response =
            Http::timeout(60)
                ->withToken(
                    $accessToken
                )
                ->acceptJson()
                ->get(
                    $baseUrl . '/products',
                    [
                        'page' =>
                            $page,

                        'per_page' =>
                            $perPage,
                    ]
                );

        $response->throw();

        return $response->json();
    }
}