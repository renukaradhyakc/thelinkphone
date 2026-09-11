<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait HasSafeOAuthReturn
{
    /**
     * Guards against open-redirect abuse: only allow relative paths,
     * or absolute URLs whose host matches our own app.
     */
    private function isSafeRedirectUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host === parse_url(config('app.url'), PHP_URL_HOST);
    }

    /**
     * Resolves the "next" destination for an OAuth bridge return,
     * falling back to $default if missing or unsafe.
     */
    private function resolveSafeNext(Request $request, string $default): string
    {
        $next = $request->query('next', $default);

        return $this->isSafeRedirectUrl($next) ? $next : $default;
    }
}