# CWFSAPI architecture

## One application, one connection

`fullscript_tokens` stores exactly one connection record, with primary key `1`. There is intentionally no `shops` table, user table, or relationship between a Shopify store and a Fullscript token.

Sensitive token values use Laravel's encrypted Eloquent casts. The status endpoint only returns safe connection metadata.

## OAuth lifecycle

1. `/fullscript/connect` creates and saves a cryptographically random OAuth state value in the Laravel session.
2. Fullscript redirects the browser to `/callback` with a code and the matching state value.
3. `FullscriptTokenService` exchanges the code, encrypts the received tokens at rest, and derives `expires_at` from `expires_in` (7200 seconds when supplied by the Sandbox).
4. Any caller using `freshAccessToken()` gets an automatically refreshed token when it is near expiry.
5. Laravel's scheduler invokes `fullscript:refresh --if-expiring` every five minutes as a second safety net.

## Shopify boundary

Shopify remains outside this package's authentication boundary. The existing Shopify CLI/custom-app session and access token should protect the Fullscript connect and status routes when the code is merged into the embedded app. `config/shopify.php` is only a small configuration hand-off point; it does not authenticate with Shopify.

## Fullscript endpoint configuration

The authorization and token URLs are environment values, not assumptions embedded in the service. Keep the defaults for the expected Sandbox host only after confirming they match the credentials provisioned to your Fullscript integration.
