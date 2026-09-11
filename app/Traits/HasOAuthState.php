<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait HasOAuthState
{
    /**
     * Signs a payload (user id, pending booking data) into a tamper-proof
     * string to send as the OAuth "state" param. Google/Zoom echo it back
     * unchanged on the callback, so it survives even if the callback lands
     * in a different cookie jar (e.g. a Chrome Custom Tab vs the app's WebView).
     */
    private function buildOAuthState(array $payload): string
    {
        return Crypt::encryptString(json_encode($payload));
    }

    /**
     * Decodes a signed state string back into its payload.
     * Returns null if missing, tampered with, or malformed.
     */
    private function decodeOAuthState(?string $state): ?array
    {
        if (empty($state)) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($state), true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}