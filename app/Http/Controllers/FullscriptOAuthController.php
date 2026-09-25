<?php

namespace App\Http\Controllers;

use App\Exceptions\FullscriptOAuthException;
use App\Services\FullscriptTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FullscriptOAuthController extends Controller
{
    // public function redirect(Request $request): RedirectResponse
    // {
    //     abort_unless(config('fullscript.client_id'), 500, 'Set FULLSCRIPT_CLIENT_ID before connecting.');

    //     $state = Str::random(64);
    //     $request->session()->put('fullscript.oauth_state', $state);

    //     return redirect()->away(config('fullscript.authorization_url').'?'.http_build_query([
    //         'response_type' => 'code',
    //         'client_id' => config('fullscript.client_id'),
    //         'redirect_uri' => config('fullscript.redirect_uri'),
    //         'scope' => config('fullscript.scope'),
    //         'state' => $state,
    //     ]));

    // }

    public function redirect(Request $request)
    {
        abort_unless(
            config('fullscript.client_id'),
            500,
            'Set FULLSCRIPT_CLIENT_ID before connecting.'
        );

        $state = Str::random(64);

        $request->session()->put(
            'fullscript.oauth_state',
            $state
        );

        $url = config('fullscript.authorization_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('fullscript.client_id'),
            'redirect_uri' => config('fullscript.redirect_uri'),
            'scope' => config('fullscript.scope'),
            'state' => $state,
        ]);

        return response()->json([
            'url' => $url,
        ]);
    }

    public function callback(Request $request, FullscriptTokenService $tokens): RedirectResponse
    {
        
        if ($request->filled('error')) {
            return redirect('/')->with('fullscript_error', $request->string('error_description', $request->input('error'))->toString());
        }

        $expectedState = $request->session()->pull('fullscript.oauth_state');
        $receivedState = $request->input('state');

        

         abort_unless(
             filled($expectedState) && filled($receivedState) && hash_equals($expectedState, $receivedState),
             403,
             'Invalid OAuth state.',
         );
        abort_unless($request->filled('code'), 422, 'Fullscript did not return an authorization code.');
    
        try {
            $tokens->exchangeAuthorizationCode($request->string('code')->toString());
        } catch (FullscriptOAuthException $exception) {
            
            return redirect('/')->with('fullscript_error', $exception->getMessage());
        }

        return redirect('/')->with('fullscript_success', 'Fullscript Sandbox connected.');
    }
}
