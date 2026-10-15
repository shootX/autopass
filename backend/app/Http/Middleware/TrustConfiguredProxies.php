<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * X-Forwarded-* is honoured only from the proxies in TRUSTED_PROXIES (comma-separated IPs/CIDRs,
 * or '*'). Empty = trust nobody, so rate limits key on the real peer address.
 */
class TrustConfiguredProxies extends TrustProxies
{
    protected function proxies()
    {
        $value = trim((string) config('security.trusted_proxies', ''));
        if ($value === '') {
            return null;
        }
        if ($value === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
