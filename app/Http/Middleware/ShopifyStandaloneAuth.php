<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopifyStandaloneAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /*
        |--------------------------------------------------------------------------
        | Already authenticated
        |--------------------------------------------------------------------------
        */

        if ($request->session()->get('shopify.authenticated')) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Initial Shopify app launch
        |--------------------------------------------------------------------------
        |
        | Shopify sends the shop, hmac, timestamp and host parameters
        | when launching a non-embedded app.
        |
        */

        if (
            $request->filled('shop')
            && $request->filled('hmac')
            && $request->filled('timestamp')
        ) {
            $this->validateShopifyLaunch($request);

            $shop = strtolower(
                trim($request->string('shop')->toString())
            );

            return redirect()->route(
                'shopify.install',
                ['shop' => $shop]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | No Shopify session and no valid Shopify launch
        |--------------------------------------------------------------------------
        */

        abort(
            401,
            'Shopify authentication required.'
        );
    }

    /**
     * Validate the HMAC sent by Shopify when launching the app.
     */
    protected function validateShopifyLaunch(Request $request): void
    {
        $shop = $request->string('shop')->toString();

        abort_unless(
            preg_match(
                '/^[a-zA-Z0-9][a-zA-Z0-9-]*\.myshopify\.com$/',
                $shop
            ),
            400,
            'Invalid Shopify shop domain.'
        );

        $query = $request->query();

        unset($query['hmac']);

        ksort($query);

        $message = urldecode(
            http_build_query(
                $query,
                '',
                '&',
                PHP_QUERY_RFC3986
            )
        );

        $calculatedHmac = hash_hmac(
            'sha256',
            $message,
            (string) config('shopify.client_secret')
        );

        $receivedHmac = $request->string('hmac')->toString();

        abort_unless(
            hash_equals($calculatedHmac, $receivedHmac),
            403,
            'Invalid Shopify launch HMAC.'
        );
    }
}