<?php

namespace App\Services;

use App\Exceptions\FullscriptOAuthException;
use App\Models\FullscriptToken;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class FullscriptTokenService
{
    public function exchangeAuthorizationCode(string $code): FullscriptToken
    {   
        return $this->persistTokenResponse($this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('fullscript.redirect_uri'),
        ]));
    }

    public function refreshIfNeeded(bool $force = false): ?FullscriptToken
    {
        $token = FullscriptToken::find(1);
        //dd($token);

        if ($token === null || (! $force && ! $token->expiresSoon(config('fullscript.refresh_leeway_seconds')))) {
            return $token;
        }

        return $this->refresh($token);
    }

    public function freshAccessToken(): string
    {
        $token = $this->refreshIfNeeded();

        if ($token === null || blank($token->access_token)) {
            throw new FullscriptOAuthException('Fullscript is not connected. Visit /fullscript/connect first.');
        }

        return $token->access_token;
    }

    private function refresh(FullscriptToken $token): FullscriptToken
    {
        
        if (blank($token->refresh_token)) {
            throw new FullscriptOAuthException('The stored Fullscript token has no refresh token. Reconnect Fullscript.');
        }

        return $this->persistTokenResponse($this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refresh_token,
        ]), $token);
    }

    private function tokenRequest(array $payload): array
    {
        $this->ensureConfigured();

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post(
                config('fullscript.token_url'),
                array_merge($payload, [
                    'client_id' => config('fullscript.client_id'),
                    'client_secret' => config('fullscript.client_secret'),
                ])
            );

        if ($response->failed()) {
            throw new FullscriptOAuthException(
                $this->responseMessage($response)
            );
        }

        $data = $response->json();

        // Fullscript wraps the OAuth response inside "oauth"
        $oauth = $data['oauth'] ?? null;

        if (! is_array($oauth) || blank($oauth['access_token'] ?? null)) {
            throw new FullscriptOAuthException(
                'Fullscript returned a token response without an access token.'
            );
        }

        return $oauth;
    }

    private function persistTokenResponse(array $data, ?FullscriptToken $token = null): FullscriptToken
    {

        $token ??= FullscriptToken::firstOrNew(['id' => 1]);
        $expiresIn = max(1, (int) ($data['expires_in'] ?? 7200));

        $token->fill([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => $data['scope'] ?? config('fullscript.scope'),
            'expires_at' => Carbon::now()->addSeconds($expiresIn),
        ]);
        $token->save();

        return $token;
    }

    private function ensureConfigured(): void
    {
        if (blank(config('fullscript.client_id')) || blank(config('fullscript.client_secret'))) {
            throw new FullscriptOAuthException('Set FULLSCRIPT_CLIENT_ID and FULLSCRIPT_CLIENT_SECRET before connecting.');
        }
    }

    private function responseMessage(Response $response): string
    {
        return 'Fullscript token request failed (HTTP '.$response->status().'): '
            .($response->json('error_description') ?: $response->json('error') ?: 'unknown error');
    }
}
