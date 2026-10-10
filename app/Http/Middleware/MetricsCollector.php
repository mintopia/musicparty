<?php

namespace App\Http\Middleware;

use App\Support\Metrics\CounterStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MetricsCollector
{
    public const METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    public const OTHER_METHOD = 'OTHER';

    public const STATUS_CODES_KEY = 'metrics.http.status_codes';

    public function __construct(protected CounterStore $counters) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->path() === trim((string) config('prometheus.urls.default'), '/')) {
            return $response;
        }

        try {
            $this->storeMetrics($request->getMethod(), $response->getStatusCode());
        } catch (\Throwable $ex) {
            Log::warning("Unable to store metrics: {$ex->getMessage()}");
        }

        return $response;
    }

    protected function storeMetrics(string $method, int $statusCode): void
    {
        $method = strtoupper($method);
        if (! in_array($method, self::METHODS, true)) {
            $method = self::OTHER_METHOD;
        }

        $this->counters->increment("metrics.http.method.{$method}");
        $this->counters->increment("metrics.http.status.{$statusCode}");
        $this->counters->addMember(self::STATUS_CODES_KEY, $statusCode);
        $this->counters->increment('metrics.http.requests');
    }
}
