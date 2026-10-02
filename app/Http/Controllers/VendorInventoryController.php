<?php

namespace App\Http\Controllers;

use App\Services\Shopify\VendorInventoryMoveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class VendorInventoryController extends Controller
{
    public function __construct(
        protected VendorInventoryMoveService $inventoryService
    ) {
    }

    /**
     * Initial page.
     */
    public function index(Request $request)
    {
        return view('vendor-inventory.index', [
            'vendor' => '',
            'products' => [],
            'totalProducts' => 0,
            'page' => 1,
            'hasNextPage' => false,
            'hasPreviousPage' => false,
            'nextCursor' => null,
            'previousCursor' => null,
            'after' => null,
            'before' => null,
            'actionResult' => null,
        ]);
    }

    /**
     * Load 25 products for a vendor.
     */
    public function products(Request $request)
    {
        $validated = $request->validate([
            'vendor' => [
                'required',
                'string',
                'max:255',
            ],

            'after' => [
                'nullable',
                'string',
            ],

            'before' => [
                'nullable',
                'string',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $vendor = trim($validated['vendor']);

        $after = $validated['after'] ?? null;
        $before = $validated['before'] ?? null;
        $page = (int) ($validated['page'] ?? 1);

        try {
            $result = $this->inventoryService->getVendorProductPage(
                vendor: $vendor,
                after: $after,
                before: $before,
            );

            return view('vendor-inventory.index', [
                'vendor' => $vendor,

                'products' =>
                    $result['products'],

                'totalProducts' =>
                    $result['total_products'],

                'page' =>
                    $page,

                'totalPages' =>
                    $result['total_pages'],

                'hasNextPage' =>
                    $result['has_next_page'],

                'hasPreviousPage' =>
                    $result['has_previous_page'],

                'nextCursor' =>
                    $result['next_cursor'],

                'previousCursor' =>
                    $result['previous_cursor'],

                'after' =>
                    $after,

                'before' =>
                    $before,

                'actionResult' =>
                    session('actionResult'),
            ]);

        } catch (Throwable $e) {

            Log::error(
                'Vendor inventory page failed.',
                [
                    'vendor' => $vendor,
                    'after' => $after,
                    'before' => $before,
                    'exception' => $e,
                ]
            );

            return redirect()
                ->route('vendor-inventory.index')
                ->withInput()
                ->withErrors([
                    'vendor' =>
                        'Unable to load vendor products: '
                        . $e->getMessage(),
                ]);
        }
    }

    /**
     * Perform one product-level action.
     *
     * Supported actions:
     *
     * activate_fs
     * activate_hq
     * activate_both
     * deactivate_fs
     * deactivate_hq
     * deactivate_both
     */
    public function productAction(Request $request)
    {
        $validated = $request->validate([
            'vendor' => [
                'required',
                'string',
                'max:255',
            ],

            'product_id' => [
                'required',
                'string',
            ],

            'action' => [
                'required',
                'in:activate_fs,activate_hq,activate_both,deactivate_fs,deactivate_hq,deactivate_both',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'after' => [
                'nullable',
                'string',
            ],

            'before' => [
                'nullable',
                'string',
            ],
        ]);

        $vendor = trim($validated['vendor']);

        try {

            $result = $this->inventoryService->productAction(
                vendor: $vendor,
                productId: $validated['product_id'],
                action: $validated['action'],
            );

            return redirect()
                ->route(
                    'vendor-inventory.products',
                    array_filter([
                        'vendor' => $vendor,
                        'page' => $validated['page'] ?? 1,
                        'after' => $validated['after'] ?? null,
                        'before' => $validated['before'] ?? null,
                    ])
                )
                ->with('actionResult', $result);

        } catch (Throwable $e) {

            Log::error(
                'Vendor inventory product action failed.',
                [
                    'vendor' => $vendor,
                    'product_id' =>
                        $validated['product_id'],
                    'action' =>
                        $validated['action'],
                    'exception' => $e,
                ]
            );

            return back()
                ->withInput()
                ->withErrors([
                    'product' =>
                        'Unable to update product inventory locations: '
                        . $e->getMessage(),
                ]);
        }
    }
}