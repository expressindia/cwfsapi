<?php

namespace App\Support\Webhook;

class FullscriptWebhookVerifier
{
    public function verify(
        string $rawBody,
        ?string $signatureHeader
    ): bool {
        if (blank($signatureHeader)) {
            return false;
        }

        $secret = config(
            'fullscript.webhook.secret'
        );

        if (blank($secret)) {
            return false;
        }

        $parts = [];

        foreach (
            explode(',', $signatureHeader)
            as $part
        ) {
            [$key, $value] = array_pad(
                explode('=', trim($part), 2),
                2,
                null
            );

            if ($key !== null && $value !== null) {
                $parts[$key] = $value;
            }
        }

        $timestamp = $parts['t'] ?? null;
        $providedSignature = $parts['v1'] ?? null;

        if (
            blank($timestamp)
            || blank($providedSignature)
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent replay attacks
        |--------------------------------------------------------------------------
        */

        $timestampAge = abs(
            now()->timestamp - (int) $timestamp
        );

        if ($timestampAge > 300) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Fullscript signature
        |--------------------------------------------------------------------------
        */

        $signedPayload =
            $timestamp . '.' . $rawBody;

        $calculatedSignature = hash_hmac(
            'sha256',
            $signedPayload,
            $secret
        );
        \Log::info('Fullscript signature debug', [
    'raw_body_length' => strlen($rawBody),
    'timestamp' => $timestamp,
    'provided_signature' => $providedSignature,
    'calculated_signature' => $calculatedSignature,
    'secret_length' => strlen($secret),
]);
        return hash_equals(
            $calculatedSignature,
            $providedSignature
        );
    }
}