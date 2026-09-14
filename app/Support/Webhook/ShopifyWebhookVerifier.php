<?php

namespace App\Support\Webhook;

class ShopifyWebhookVerifier
{
    public function verify(
        string $rawBody,
        ?string $providedHmac
    ): bool {

        if (blank($providedHmac)) {
            return false;
        }

        $secret = config('shopify.webhook_secret');

        if (blank($secret)) {
            return false;
        }

        $calculatedHmac = base64_encode(
            hash_hmac(
                'sha256',
                $rawBody,
                $secret,
                true
            )
        );

        return hash_equals(
            $calculatedHmac,
            $providedHmac
        );
    }
}