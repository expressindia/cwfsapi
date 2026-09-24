<?php

use App\Http\Controllers\FullscriptOAuthController;
use App\Http\Controllers\FullscriptStatusController;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\FullscriptWebhookController;
use App\Http\Controllers\ShopifyAuthController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return response()->json([
//         'application' => config('app.name'),
//         'fullscript_status' => route('fullscript.status'),
//         'fullscript_connect' => route('fullscript.connect'),
//     ]);
// });

// Route::get('/', DashboardController::class)
//     ->name('dashboard');

Route::match(['GET', 'POST'], '/auth/patch-id-token', function (
    \Illuminate\Http\Request $request
) {
    $shopify = app(\App\Services\Shopify\ShopifyAppService::class)->getApp();

    $shopifyRequest = [
        'method' => $request->method(),
        'headers' => $request->headers->all(),
        'url' => $request->fullUrl(),
        'body' => $request->getContent(),
    ];

    $result = $shopify->appHomePatchIdToken($shopifyRequest);

    return response(
        $result['response']['body'] ?? '',
        $result['response']['status'] ?? 200
    )->withHeaders(
        $result['response']['headers'] ?? []
    );
}); 



Route::middleware('shopify.auth')->group(function () {  
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/logs', [LogController::class, 'index']) ->name('logs');
    Route::post('logs/clear', [LogController::class, 'clear']) ->name('logs.clear');
    Route::get('/fullscript/status', [FullscriptStatusController::class,'status']) ->name('fullscript.status');
});

Route::post( '/auth/patch-id-token', [ShopifyAuthController::class, 'patchIdToken'] )->name('shopify.patch-id-token');

/*
|--------------------------------------------------------------------------
| Fullscript OAuth
|--------------------------------------------------------------------------
*/
// The callback path is deliberately /callback to match the registered Sandbox URI.
Route::get('/fullscript/connect', [FullscriptOAuthController::class, 'redirect'])->name('fullscript.connect');
Route::get('/callback', [FullscriptOAuthController::class, 'callback'])->name('fullscript.callback');


/*
|--------------------------------------------------------------------------
|  Add webhook route 
| Shopify webhook url
|--------------------------------------------------------------------------
*/
Route::post( '/webhooks/shopify', [ ShopifyWebhookController::class, 'fulfillmentRequest' ]);

/*
|--------------------------------------------------------------------------
|  Add webhook route 
| Fullscrip webhook url
|--------------------------------------------------------------------------
*/
Route::post( '/webhooks/fullscript', [ FullscriptWebhookController::class,  'handle' ]);
// Route::match(
//     ['get', 'post'],
//     '/webhooks/fullscript',
//     [
//         FullscriptWebhookController::class,
//         'handle'
//     ]
// );