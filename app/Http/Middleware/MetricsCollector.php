<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class MetricsCollector
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $promUrl = config('prometheus.urls.default');
        if (!str_starts_with($promUrl, '/')) {
            $promUrl = "/{$promUrl}";
        }
        if ($request->getRequestUri() === $promUrl) {
            return $response;
        }
        $method = $request->getMethod();
        $statusCode = $response->getStatusCode();
        try {
            $this->storeMetrics($method, $statusCode);
        } catch (\Exception $ex) {
            // Purposely do nothing other than logging
            Log::warning("Unable to store metrics: {$ex->getMessage()}");
        }
        return $response;
    }

    protected function storeMetrics(string $method, int $statusCode): void
    {
        Redis::incr("metrics.http.method.{$method}", 1);
        Redis::incr("metrics.http.status.{$statusCode}", 1);
        Redis::incr("metrics.http.requests", 1);
    }
}
