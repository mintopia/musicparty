<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeMetricsScrape
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hasValidToken($request) && ! $this->isFromAllowedIp($request)) {
            abort(403);
        }

        return $next($request);
    }

    protected function hasValidToken(Request $request): bool
    {
        $expected = (string) config('prometheus.token');
        $presented = $request->bearerToken();

        return $expected !== '' && $presented !== null && hash_equals($expected, $presented);
    }

    protected function isFromAllowedIp(Request $request): bool
    {
        /** @var array<int, string> $allowed */
        $allowed = (array) config('prometheus.allowed_ips');
        $ip = $request->ip();

        return $allowed !== [] && $ip !== null && IpUtils::checkIp($ip, $allowed);
    }
}
