<?php

namespace App\Http\Controllers;

use App\Models\ShopifyToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ShopifyOAuthController extends Controller
{
    /**
     * Start Shopify OAuth installation.
     *
     * This application is non-embedded, so it uses the
     * Shopify Authorization Code Grant.
     *
     * No grant_options[]=per-user is used because we want
     * an offline access token.
     */
    public function install(Request $request): RedirectResponse
    {
        $shop = $request->query('shop');

        abort_unless(
            filled($shop),
            400,
            'Shop parameter is required.'
        );

        $shop = strtolower(trim($shop));

        abort_unless(
            preg_match(
                '/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/',
                $shop
            ),
            400,
            'Invalid Shopify shop domain.'
        );

        /*
         * Generate CSRF state.
         */
        $state = Str::random(64);

        $request->session()->put(
            'shopify.oauth_state',
            $state
        );

        /*
         * Build Shopify authorization URL.
         *
         * We intentionally DO NOT include:
         *
         * grant_options[]=per-user
         *
         * Therefore Shopify will issue an offline access token.
         */
        $params = http_build_query([
            'client_id' => config('shopify.client_id'),
            'scope' => config('shopify.scopes'),
            'redirect_uri' => config('shopify.redirect_uri'),
            'state' => $state,
        ]);

        return redirect()->away(
            "https://{$shop}/admin/oauth/authorize?{$params}"
        );
    }
      
    /**
     * Handle Shopify OAuth callback.
     *
     * Exchanges the authorization code for an OFFLINE
     * Shopify Admin API access token and stores it in
     * the shopify_tokens table.
     */
    public function callback(Request $request): RedirectResponse
    {
        /*
         * ---------------------------------------------------------
         * 1. Handle Shopify OAuth errors
         * ---------------------------------------------------------
         */

        if ($request->filled('error')) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'shopify_error',
                    $request->input(
                        'error_description',
                        $request->input('error')
                    )
                );
        }

        /*
         * ---------------------------------------------------------
         * 2. Validate required callback parameters
         * ---------------------------------------------------------
         */

        abort_unless(
            $request->filled('shop'),
            400,
            'Shopify shop parameter is missing.'
        );

        abort_unless(
            $request->filled('code'),
            400,
            'Shopify authorization code is missing.'
        );

        abort_unless(
            $request->filled('state'),
            400,
            'Shopify OAuth state is missing.'
        );

        abort_unless(
            $request->filled('hmac'),
            400,
            'Shopify OAuth HMAC is missing.'
        );

        /*
         * ---------------------------------------------------------
         * 3. Validate Shopify shop domain
         * ---------------------------------------------------------
         */

        $shop = strtolower(
            trim(
                $request->string('shop')->toString()
            )
        );

        abort_unless(
            preg_match(
                '/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/',
                $shop
            ),
            400,
            'Invalid Shopify shop domain.'
        );

        /*
         * ---------------------------------------------------------
         * 4. Validate OAuth state
         * ---------------------------------------------------------
         *
         * The state was generated before redirecting to Shopify
         * and is now compared with the returned state.
         */

        $expectedState = $request->session()->pull(
            'shopify.oauth_state'
        );

        $receivedState = $request->string('state')->toString();

        abort_unless(
            filled($expectedState)
            && filled($receivedState)
            && hash_equals(
                $expectedState,
                $receivedState
            ),
            403,
            'Invalid Shopify OAuth state.'
        );

        /*
         * ---------------------------------------------------------
         * 5. Validate Shopify HMAC
         * ---------------------------------------------------------
         */

        $query = $request->query();

        unset($query['hmac']);

        ksort($query);

        $message = http_build_query(
            $query,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $calculatedHmac = hash_hmac(
            'sha256',
            $message,
            (string) config('shopify.client_secret')
        );

        $receivedHmac = $request->string('hmac')->toString();

        abort_unless(
            hash_equals(
                $calculatedHmac,
                $receivedHmac
            ),
            403,
            'Invalid Shopify OAuth HMAC.'
        );

        /*
         * ---------------------------------------------------------
         * 6. Exchange authorization code for OFFLINE token
         * ---------------------------------------------------------
         *
         * Because this is a non-embedded app, Shopify uses the
         * Authorization Code Grant.
         *
         * We do NOT send grant_options[]=per-user.
         *
         * Therefore the token is an offline access token.
         */

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(30)
                ->post(
                    "https://{$shop}/admin/oauth/access_token",
                    [
                        'client_id' => config('shopify.client_id'),

                        'client_secret' => config(
                            'shopify.client_secret'
                        ),

                        'code' => $request
                            ->string('code')
                            ->toString(),

                        /*
                         * Explicitly request the non-expiring
                         * offline token behavior.
                         *
                         * For this custom-distributed app,
                         * expiring=0 is appropriate.
                         
                        'expiring' => '0',*/
                    ]
                );

            if ($response->failed()) {
                Log::error(
                    'Shopify OAuth token exchange failed.',
                    [
                        'shop' => $shop,
                        'status' => $response->status(),
                        'response' => $response->json(),
                    ]
                );

                return redirect()
                    ->route('dashboard')
                    ->with(
                        'shopify_error',
                        'Shopify authorization failed.'
                    );
            }

            $tokenData = $response->json();

            /*
             * -----------------------------------------------------
             * 7. Validate token response
             * -----------------------------------------------------
             */

            abort_unless(
                filled(
                    $tokenData['access_token'] ?? null
                ),
                500,
                'Shopify did not return an access token.'
            );

            /*
             * -----------------------------------------------------
             * 8. Store ONE token for this store
             * -----------------------------------------------------
             *
             * shop_domain is unique in your database.
             *
             * We therefore store one offline token for:
             *
             * curated-supplement.myshopify.com
             *
             * or the development store.
             */

            $shopifyToken = ShopifyToken::updateOrCreate(
                [
                    'shop_domain' => $shop,
                ],
                [
                    'access_token' => $tokenData[
                        'access_token'
                    ],

                    'scope' => $tokenData[
                        'scope'
                    ] ?? null,

                    /*
                     * Offline token does not use the
                     * associated Shopify staff user.
                     */
                    'associated_user_id' => null,

                    'associated_user_email' => null,

                    'associated_user_first_name' => null,

                    'associated_user_last_name' => null,

                    /*
                     * Non-expiring offline token.
                     */
                    'expires_at' => null,
                ]
            );

            /*
             * -----------------------------------------------------
             * 9. Create Laravel authenticated session
             * -----------------------------------------------------
             */

            $request->session()->regenerate();

            $request->session()->put(
                'shopify.authenticated',
                true
            );

            $request->session()->put(
                'shopify.shop_domain',
                $shop
            );

            $request->session()->put(
                'shopify.token_id',
                $shopifyToken->id
            );

            /*
             * These user fields are intentionally not populated
             * because we are using an offline token.
             */

            $request->session()->put(
                'shopify.user_id',
                null
            );

            $request->session()->put(
                'shopify.user_email',
                null
            );

            $request->session()->put(
                'shopify.user_name',
                null
            );

            return redirect()
                ->route('dashboard')
                ->with(
                    'shopify_success',
                    'Shopify authentication successful.'
                );
        } catch (Throwable $exception) {
            Log::error(
                'Shopify OAuth callback exception.',
                [
                    'shop' => $shop,
                    'message' => $exception->getMessage(),
                ]
            );

            return redirect()
                ->route('dashboard')
                ->with(
                    'shopify_error',
                    'Unable to complete Shopify authentication.'
                );
        }
    }
}