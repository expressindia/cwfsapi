# CWFSAPI

Single-store Laravel application that adds a Fullscript **Sandbox** OAuth connection to an existing Shopify custom/embedded app. It deliberately contains no Laravel email/password login, no Shopify OAuth flow, and no `shops` table.

## Requirements

- macOS with Laravel Herd
- PHP 8.5.0
- Composer 2.8.12
- Laravel Installer 5.31.1
- MySQL 8+ (or a compatible local MySQL service)
- Fullscript Sandbox client ID and client secret, enabled for `catalog:read`

## Included Fullscript flow

- `GET /fullscript/connect` generates a state value and starts authorization.
- Fullscript redirects to `http://127.0.0.1:8000/callback`.
- The callback exchanges the authorization code and stores one encrypted token record in `fullscript_tokens` (fixed `id = 1`).
- `expires_at` is calculated from `expires_in`; Fullscript's expected `7200`-second response is supported.
- The token service refreshes a token within 300 seconds of expiry. `fullscript:refresh --if-expiring` runs every five minutes through Laravel's scheduler.
- `GET /fullscript/status` returns connection metadata only—never an access or refresh token.

The default Sandbox OAuth URLs are configurable because Fullscript API access is provisioned per integration. Confirm the authorization and token URLs supplied with your Sandbox credentials before starting the flow.

## Setup

1. Unzip and open the project folder in Terminal.

   ```bash
   cd CWFSAPI
   composer install
   ```

2. Create your local environment file and application key.

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Create an empty MySQL database named `cwfsapi`.

   ```sql
   CREATE DATABASE cwfsapi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Edit `.env`. Set your local MySQL credentials and these Fullscript values. Do **not** commit this file.

   ```dotenv
   APP_URL=http://127.0.0.1:8000
   DB_DATABASE=cwfsapi
   DB_USERNAME=root
   DB_PASSWORD=

   FULLSCRIPT_CLIENT_ID=your_sandbox_client_id
   FULLSCRIPT_CLIENT_SECRET=your_sandbox_client_secret
   FULLSCRIPT_REDIRECT_URI=http://127.0.0.1:8000/callback
   FULLSCRIPT_SCOPE=catalog:read
   ```

   In the Fullscript Sandbox app configuration, register this exact redirect URI:

   ```text
   http://127.0.0.1:8000/callback
   ```

5. Run the migration.

   ```bash
   php artisan migrate
   ```

6. Start Laravel at the registered redirect host and port.

   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   In another Terminal window, keep the scheduler running during local development:

   ```bash
   php artisan schedule:work
   ```

## Test the Sandbox connection

1. Open `http://127.0.0.1:8000/fullscript/status`; expected initial result: `connected: false`.
2. Open `http://127.0.0.1:8000/fullscript/connect` and complete the Fullscript Sandbox authorization prompt.
3. After returning to `/callback`, open `http://127.0.0.1:8000/fullscript/status`; it should report `connected: true`, `scope: "catalog:read"`, and an ISO-8601 `expires_at` timestamp.
4. Exercise the refresh path manually:

   ```bash
   php artisan fullscript:refresh
   ```

   Use the scheduled form when you only want to refresh near expiry:

   ```bash
   php artisan fullscript:refresh --if-expiring
   ```

5. Run automated checks:

   ```bash
   php artisan test
   ```

## Using the token in a Fullscript API client

Inject `App\Services\FullscriptTokenService` and call `freshAccessToken()`. It refreshes first if the stored token is within the configured refresh window.

```php
$accessToken = app(\App\Services\FullscriptTokenService::class)->freshAccessToken();

$response = Http::withToken($accessToken)->get('YOUR_FULLSCRIPT_SANDBOX_CATALOG_ENDPOINT');
```

Use the catalog endpoint supplied in Fullscript's approved API documentation. This project does not guess or hard-code protected catalog API paths.

## Existing Shopify app integration

This package does not install Shopify CLI, add Shopify login routes, or modify Shopify authentication. Your existing custom/embedded app remains the source of truth for the one Shopify store and access token.

- `config/shopify.php` exposes `SHOPIFY_STORE_DOMAIN` and `SHOPIFY_ACCESS_TOKEN` as simple hand-off values.
- Replace calls to `config('shopify.access_token')` with your existing Shopify token provider if it already manages rotation or session storage.
- Keep Shopify credentials in `.env` or your production secret manager—never in this repository or database migration.

## Production notes

- Change `FULLSCRIPT_REDIRECT_URI` and the registered callback to an HTTPS production URL before deploying.
- Run `php artisan schedule:run` every minute through the server scheduler, or run a supervised `php artisan schedule:work` process.
- Restrict `/fullscript/connect` and `/fullscript/status` behind your existing Shopify embedded-app/session middleware before exposing the app publicly.
- Laravel encrypts token columns with `APP_KEY`. Preserve that key across deployments and backups; rotating it makes existing encrypted tokens unreadable until reconnected.
