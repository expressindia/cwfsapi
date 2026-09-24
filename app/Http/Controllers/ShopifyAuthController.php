<?php

namespace App\Http\Controllers;

use App\Services\Shopify\ShopifyAppService;
use Illuminate\Http\Request;

class ShopifyAuthController extends Controller
{
    public function patchIdToken(
        Request $request,
        ShopifyAppService $shopifyApp
    ) {
        $shopify = $shopifyApp->getApp();

        $shopifyRequest = $shopifyApp
            ->toShopifyRequest($request);

        $result = $shopify->appHomePatchIdToken(
            $shopifyRequest
        );

        return response(
            $result['response']['body'],
            $result['response']['status']
        )->withHeaders(
            $result['response']['headers'] ?? []
        );
    }
}