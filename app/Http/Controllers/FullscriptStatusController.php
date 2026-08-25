<?php

namespace App\Http\Controllers;

use App\Models\FullscriptToken;
// use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class FullscriptStatusController extends Controller
{
    public function status(): View
    {
        $token = FullscriptToken::find(1);

        // return response()->json([
        //     'connected' => $token !== null && filled($token->access_token),
        //     'expires_at' => $token?->expires_at?->toIso8601String(),
        //     'expired' => $token?->expiresSoon() ?? true,
        //     'scope' => $token?->scope,
        //     'environment' => 'sandbox',
        // ]);

        return view('fullscript.status', [
            'connected' => $token !== null && filled($token->access_token),
             'expires_at' => $token?->expires_at?->toIso8601String(),
             'expired' => $token?->expiresSoon() ?? true,
             'scope' => $token?->scope,
             'environment' => 'sandbox',
        ]);
    }
}


