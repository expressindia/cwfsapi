<?php

namespace App\Http\Controllers;

use App\Exceptions\FullscriptOAuthException;
use App\Models\FullscriptToken;
use App\Services\FullscriptTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FullscriptStatusController extends Controller
{
    public function status(): View
    {
        $token = FullscriptToken::find(1);

        return view('fullscript.status', [
            'connected' => $token !== null
                && filled($token->access_token),
            'expires_at' => $token?->expires_at?->toIso8601String(),
            'expired' => $token?->expiresSoon() ?? true,
            'scope' => $token?->scope,
            'environment' => 'sandbox',
        ]);
    }

    public function refreshToken(
        FullscriptTokenService $tokenService
    ): RedirectResponse {
        try {
            $token = $tokenService->refreshIfNeeded(force: true);

            if (!$token || blank($token->access_token)) {
                return redirect()
                    ->route('fullscript.status')
                    ->with('error', 'No valid token returned. Please reconnect Fullscript.');
            }

            return redirect()
                ->route('fullscript.status')
                ->with('success', 'Fullscript token refreshed successfully.');

        } catch (FullscriptOAuthException $e) {
            Log::warning('Manual Fullscript token refresh failed.', [
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('fullscript.status')
                ->with('error', $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('Unexpected Fullscript refresh error.', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('fullscript.status')
                ->with('error', 'Refresh failed. Check the Laravel application logs.');
        }
    }

    public function reconnect(): RedirectResponse
    {
        return redirect()->route('fullscript.connect');
    }
}