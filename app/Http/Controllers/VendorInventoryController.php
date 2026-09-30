<?php

namespace App\Http\Controllers;

use App\Services\Shopify\VendorInventoryMoveService;
use Illuminate\Http\Request;
use Throwable;

class VendorInventoryController extends Controller
{
    /**
     * Display the Vendor Inventory page.
     */
    public function index(Request $request)
    {
        return view('vendor-inventory.index', [
            'vendor' => old(
                'vendor',
                $request->session()->get('vendor')
            ),

            'moveResult' => $request
                ->session()
                ->get('moveResult'),
        ]);
    }

    /**
     * Preview vendor inventory.
     */
    public function preview(
        Request $request,
        VendorInventoryMoveService $service
    ) {
        $validated = $request->validate([
            'vendor' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        try {
            $vendor = trim(
                $validated['vendor']
            );

            $preview = $service->preview(
                $vendor
            );

            return view(
                'vendor-inventory.index',
                [
                    'vendor' => $vendor,
                    'preview' => $preview,
                    'moveResult' => null,
                ]
            );

        } catch (Throwable $e) {

            report($e);

            return redirect()
                ->route('vendor-inventory.index')
                ->withInput()
                ->withErrors([
                    'vendor' => $e->getMessage(),
                ]);
        }
    }

    /**
     * Move vendor inventory to FSWarehouse.
     */
    public function move(
        Request $request,
        VendorInventoryMoveService $service
    ) {
        $validated = $request->validate([
            'vendor' => [
                'required',
                'string',
                'max:255',
            ],

            'confirm' => [
                'required',
                'accepted',
            ],
        ]);

        try {
            $vendor = trim(
                $validated['vendor']
            );

            $result = $service->move(
                $vendor
            );

            return redirect()
                ->route('vendor-inventory.index')
                ->with([
                    'moveResult' => $result,
                    'vendor' => $vendor,
                    'success' =>
                        'Vendor inventory move completed.',
                ]);

        } catch (Throwable $e) {

            report($e);

            return redirect()
                ->route('vendor-inventory.index')
                ->withInput()
                ->withErrors([
                    'vendor' => $e->getMessage(),
                ]);
        }
    }
}