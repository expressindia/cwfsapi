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

        $state = Str::random(64);

        $request->session()->put(
            'shopify.oauth_state',
            $state
        );

        $params = http_build_query([
            'client_id' => config('shopify.client_id'),
            'scope' => config('shopify.scopes'),
            'redirect_uri' => config('shopify.redirect_uri'),
            'state' => $state,

            // Request an online/per-user access token.
            'grant_options[]' => 'per-user',
        ]);

        return redirect()->away(
            "https://{$shop}/admin/oauth/authorize?{$params}"
        );
    }

    /**
     * Handle Shopify OAuth callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Handle Shopify OAuth errors
        |--------------------------------------------------------------------------
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
        |--------------------------------------------------------------------------
        | 2. Validate required parameters
        |--------------------------------------------------------------------------
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

        $shop = strtolower(
            trim($request->string('shop')->toString())
        );

        /*
        |--------------------------------------------------------------------------
        | 3. Validate shop domain
        |--------------------------------------------------------------------------
        */

        abort_unless(
            preg_match(
                '/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/',
                $shop
            ),
            400,
            'Invalid Shopify shop domain.'
        );

        /*
        |--------------------------------------------------------------------------
        | 4. Validate OAuth state
        |--------------------------------------------------------------------------
        */

        $expectedState = $request->session()->pull(
            'shopify.oauth_state'
        );

        $receivedState = $request->string('state')->toString();

        abort_unless(
            filled($expectedState)
            && filled($receivedState)
            && hash_equals($expectedState, $receivedState),
            403,
            'Invalid Shopify OAuth state.'
        );

        /*
        |--------------------------------------------------------------------------
        | 5. Validate Shopify HMAC
        |--------------------------------------------------------------------------
        */

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
            'Invalid Shopify OAuth HMAC.'
        );

        /*
        |--------------------------------------------------------------------------
        | 6. Exchange authorization code for online access token
        |--------------------------------------------------------------------------
        */

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(30)
                ->post(
                    "https://{$shop}/admin/oauth/access_token",
                    [
                        'client_id' => config('shopify.client_id'),
                        'client_secret' => config('shopify.client_secret'),
                        'code' => $request->string('code')->toString(),
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
            |--------------------------------------------------------------------------
            | 7. Validate token response
            |--------------------------------------------------------------------------
            */

            abort_unless(
                filled($tokenData['access_token'] ?? null),
                500,
                'Shopify did not return an access token.'
            );

            /*
            |--------------------------------------------------------------------------
            | 8. Extract associated Shopify user
            |--------------------------------------------------------------------------
            */

            $associatedUser = $tokenData['associated_user'] ?? null;

            abort_unless(
                is_array($associatedUser)
                && filled($associatedUser['id'] ?? null),
                500,
                'Shopify did not return the associated user.'
            );

            /*
            |--------------------------------------------------------------------------
            | 9. Store Shopify token
            |--------------------------------------------------------------------------
            */

            $expiresAt = null;

            if (isset($tokenData['expires_in'])) {
                $expiresAt = now()->addSeconds(
                    (int) $tokenData['expires_in']
                );
            }

            $shopifyToken = ShopifyToken::updateOrCreate(
                [
                    'shop_domain' => $shop,
                    'associated_user_id' => (string) $associatedUser['id'],
                ],
                [
                    'access_token' => $tokenData['access_token'],
                    'scope' => $tokenData['associated_user_scope']
                        ?? $tokenData['scope']
                        ?? null,

                    'associated_user_email' =>
                        $associatedUser['email'] ?? null,

                    'associated_user_first_name' =>
                        $associatedUser['first_name'] ?? null,

                    'associated_user_last_name' =>
                        $associatedUser['last_name'] ?? null,

                    'expires_at' => $expiresAt,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 10. Create Laravel authenticated session
            |--------------------------------------------------------------------------
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

            $request->session()->put(
                'shopify.user_id',
                (string) $associatedUser['id']
            );

            $request->session()->put(
                'shopify.user_email',
                $associatedUser['email'] ?? null
            );

            $request->session()->put(
                'shopify.user_name',
                trim(
                    ($associatedUser['first_name'] ?? '')
                    . ' '
                    . ($associatedUser['last_name'] ?? '')
                )
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