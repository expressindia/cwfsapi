<?php

use App\Http\Controllers\FullscriptOAuthController;
use App\Http\Controllers\FullscriptStatusController;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\FullscriptWebhookController;
use App\Http\Controllers\ShopifyOAuthController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\FulfillmentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\VendorInventoryController;
use App\Http\Controllers\ProductsController;
use Illuminate\Support\Facades\Route;



Route::get('/', [DashboardController::class, 'index']) ->middleware('shopify.standalone') ->name('dashboard');

Route::get('/shopify', [ ShopifyController::class, 'index',]) ->middleware('shopify.standalone') ->name('shopify.index');

Route::post('/shopify/fulfillment/register', [ ShopifyController::class, 'registerFulfillmentService',]) ->middleware('shopify.standalone') ->name('shopify.fulfillment.register');

//Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/shopify/install', [ ShopifyOAuthController::class, 'install', ])->name('shopify.install');
Route::get('/shopify/callback', [ ShopifyOAuthController::class, 'callback', ])->name('shopify.callback');

Route::get('/logs', [LogController::class, 'index']) ->name('logs');
Route::post('logs/clear', [LogController::class, 'clear']) ->name('logs.clear');
Route::get('/fullscript/status', [FullscriptStatusController::class,'status']) ->name('fullscript.status');
Route::get('/fullscript/connect', [FullscriptOAuthController::class, 'redirect'])->name('fullscript.connect');

Route::get('/fulfillments', [FulfillmentController::class, 'index'])->name('fulfillments.index');
Route::get('/fulfillments/{fulfillment}', [FulfillmentController::class, 'show'])->name('fulfillments.show');
Route::get('/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');

// Order Route 
Route::get('/orders', [OrderController::class, 'index'])->middleware('shopify.standalone')->name('orders.index');
Route::get('/orders/{orderId}', [OrderController::class, 'show'])->whereNumber('orderId')->middleware('shopify.standalone')->name('orders.show');

Route::post( '/orders/{orderId}/fulfillments/{fulfillmentId}/tracking', [OrderController::class, 'updateTracking'])
    ->whereNumber('orderId')
    ->whereNumber('fulfillmentId')
    ->middleware('shopify.standalone')
    ->name('orders.tracking.update');



/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

Route::get('/products', [ProductsController::class, 'index'])->middleware('shopify.standalone')->name('products.index');

/*
|--------------------------------------------------------------------------
| Vendor Inventory Activation for locations
|--------------------------------------------------------------------------
*/

Route::get('/vendor-inventory',[VendorInventoryController::class, 'index'])->middleware('shopify.standalone')->name('vendor-inventory.index');
Route::get('/vendor-inventory/products',[VendorInventoryController::class, 'products'])->middleware('shopify.standalone')->name('vendor-inventory.products');
Route::post('/vendor-inventory/product/action',[VendorInventoryController::class, 'productAction'])->middleware('shopify.standalone')->name('vendor-inventory.product.action');
/*
|--------------------------------------------------------------------------
| Fullscript OAuth
|--------------------------------------------------------------------------
*/
// The callback path is deliberately /callback to match the registered Sandbox URI.

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