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
        if (!$request->session()->get('shopify.authenticated')) {
            $shop = $request->session()->get('shopify.shop_domain');

            if ($shop) {
                return redirect()->route(
                    'shopify.install',
                    ['shop' => $shop]
                );
            }

            abort(
                401,
                'Shopify authentication required.'
            );
        }

        return $next($request);
    }
}