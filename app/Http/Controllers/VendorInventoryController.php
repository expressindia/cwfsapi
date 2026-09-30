<?php

namespace App\Http\Controllers;

use App\Services\Shopify\VendorInventoryMoveService;
use Illuminate\Http\Request;
use Throwable;

class VendorInventoryController extends Controller
{
    /**
     * Show vendor inventory page.
     */
    public function index()
    {
        return view(
            'vendor-inventory.index'
        );
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

            $preview =
                $service->preview(
                    $validated['vendor']
                );

            return view(
                'vendor-inventory.index',
                [
                    'vendor' =>
                        $validated['vendor'],

                    'preview' =>
                        $preview,
                ]
            );

        } catch (Throwable $e) {

            return back()
                ->withInput()
                ->withErrors([
                    'vendor' =>
                        $e->getMessage(),
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

            $result =
                $service->move(
                    $validated['vendor']
                );

            return redirect()
                ->route(
                    'vendor-inventory.index'
                )
                ->with(
                    'result',
                    $result
                );

        } catch (Throwable $e) {

            return back()
                ->withInput()
                ->withErrors([
                    'vendor' =>
                        $e->getMessage(),
                ]);
        }
    }
}