<?php

use App\Http\Controllers\FullscriptOAuthController;
use App\Http\Controllers\FullscriptStatusController;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\FullscriptWebhookController;
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
Route::get('/', [DashboardController::class, 'index'])
    ->name('dashboard');

// The callback path is deliberately /callback to match the registered Sandbox URI.
Route::get('/fullscript/connect', [FullscriptOAuthController::class, 'redirect'])
    ->name('fullscript.connect');
Route::get('/callback', [FullscriptOAuthController::class, 'callback'])
    ->name('fullscript.callback');
Route::get('/fullscript/status', [FullscriptStatusController::class,'status'])
    ->name('fullscript.status');


/*
|--------------------------------------------------------------------------
| Laravel Logs data 
|--------------------------------------------------------------------------
*/
Route::get('/logs', [LogController::class, 'index'])
    ->name('logs');

Route::post('logs/clear', [LogController::class, 'clear'])
    ->name('logs.clear');

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