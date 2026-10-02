<?php

namespace App\Http\Controllers;

use App\Services\Shopify\VendorInventoryMoveService;
use Illuminate\Http\Request;
use Throwable;

class VendorInventoryController extends Controller
{
    public function index(Request $request)
    {
        return view('vendor-inventory.index', [
            'vendor' => old(
                'vendor',
                $request->session()->get('vendor')
            ),

            'preview' => null,

            'activationResult' => $request->session()->get(
                'activationResult'
            ),

            'activationVerification' => $request->session()->get(
                'activationVerification'
            ),

            'deactivationResult' => $request->session()->get(
                'deactivationResult'
            ),
        ]);
    }

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
            $vendor = trim($validated['vendor']);

            $preview = $service->preview($vendor);

            return view('vendor-inventory.index', [
                'vendor' => $vendor,
                'preview' => $preview,
                'activationResult' => null,
                'activationVerification' => null,
                'deactivationResult' => null,
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

    public function activate(
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
            $vendor = trim($validated['vendor']);

            $result = $service->activate($vendor);

            return redirect()
                ->route('vendor-inventory.index')
                ->with([
                    'vendor' => $vendor,
                    'activationResult' => $result,
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

    public function verifyActivation(
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
            $vendor = trim($validated['vendor']);

            $result = $service->verifyActivation($vendor);

            return redirect()
                ->route('vendor-inventory.index')
                ->with([
                    'vendor' => $vendor,
                    'activationVerification' => $result,
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

    public function deactivate(
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
            $vendor = trim($validated['vendor']);

            $result = $service->deactivate($vendor);

            return redirect()
                ->route('vendor-inventory.index')
                ->with([
                    'vendor' => $vendor,
                    'deactivationResult' => $result,
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