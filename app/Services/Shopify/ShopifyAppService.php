<?php

namespace App\Http\Middleware;

use App\Services\Shopify\ShopifyAppService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopifyAuth
{
    public function __construct(
        protected ShopifyAppService $shopifyApp
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $shopify = $this->shopifyApp->getApp();

        $shopifyRequest = $this->shopifyApp
            ->toShopifyRequest($request);

        $result = $shopify->verifyAppHomeReq(
            $shopifyRequest,
            appHomePatchIdTokenPath: '/auth/patch-id-token',
        );

        /*
        |--------------------------------------------------------------------------
        | Authentication failed
        |--------------------------------------------------------------------------
        */

        if (!$result['ok']) {

            \Log::warning('Shopify App Home authentication failed', [
                'code' => $result['log']['code'] ?? null,
                'detail' => $result['log']['detail'] ?? null,
            ]);

            $shopifyResponse = $result['response'];

            return response(
                $shopifyResponse['body'],
                $shopifyResponse['status']
            )->withHeaders(
                $shopifyResponse['headers'] ?? []
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Authentication successful
        |--------------------------------------------------------------------------
        */

        $request->attributes->set(
            'shopify_shop',
            $result['shop']
        );

        $request->attributes->set(
            'shopify_user_id',
            $result['userId']
        );

        $request->attributes->set(
            'shopify_id_token',
            $result['idToken']
        );

        $response = $next($request);

        /*
        |--------------------------------------------------------------------------
        | Add Shopify App Home security headers
        |--------------------------------------------------------------------------
        */

        foreach (
            $result['response']['headers'] ?? []
            as $header => $value
        ) {
            $response->headers->set($header, $value);
        }

        return $response;
    }
}