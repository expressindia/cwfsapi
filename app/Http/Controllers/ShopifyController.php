<?php

namespace App\Http\Controllers;

use App\Services\Shopify\ShopifyFulfillmentRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class ShopifyController extends Controller
{
    public function __construct(
        protected ShopifyFulfillmentRegistrationService $fulfillmentService
    ) {
    }

    /**
     * Display Shopify connection/status page.
     */
    public function index(): View
    {
        try {
            $status = $this->fulfillmentService->getStatus();

            return view('shopify.index', [
                'status' => $status,
            ]);
        } catch (Throwable $e) {
            report($e);

            return view('shopify.index', [
                'status' => [
                    'connected' => false,
                    'shop_name' => session('shopify.shop_domain'),
                    'shop_domain' => session('shopify.shop_domain'),
                    'service_name' => config(
                        'shopify.fulfillment.service_name',
                        'FSWarehouse'
                    ),
                    'service' => null,
                    'services' => [],
                ],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register FSWarehouse fulfillment service.
     */
    public function registerFulfillmentService(): RedirectResponse
    {
        try {
            $result = $this->fulfillmentService->register();

            if ($result['created'] ?? false) {
                return redirect()
                    ->route('shopify.index')
                    ->with(
                        'success',
                        'FSWarehouse fulfillment service registered successfully.'
                    );
            }

            return redirect()
                ->route('shopify.index')
                ->with(
                    'success',
                    'FSWarehouse is already registered. The Shopify service and location IDs have been saved.'
                );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('shopify.index')
                ->with(
                    'error',
                    $e->getMessage()
                );
        }
    }
}