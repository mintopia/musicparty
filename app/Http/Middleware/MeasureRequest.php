<?php

namespace App\Http\Middleware;

use App\Services\OpenTelemetry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\Response;

class MeasureRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('open-telemetry.enabled')) {
            return $next($request);
        }

        $name = Route::currentRouteName();
        if ($name === null) {
            $name = $request->getRequestUri();
        }

        $blacklist = config('open-telemetry.traces.disabledroutes', []);
        if (in_array($name, $blacklist)) {
            return $next($request);
        }

        $blacklist = config('open-telemetry.traces.disabledpaths', []);
        if (in_array($request->getRequestUri(), $blacklist)) {
            return $next($request);
        }

        $trace = $request->header('traceparent');
        $state = $request->header('tracestate');
        $span = OpenTelemetry::startSpan(name: "{$request->getMethod()} {$name}", parentTrace: $trace, parentState: $state);
        $span->span->setAttributes([
            TraceAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
            TraceAttributes::HTTP_URL => $request->getRequestUri(),
        ]);
        if (Route::getCurrentRoute() !== null) {
            $span->span->setAttribute(TraceAttributes::HTTP_ROUTE, Route::getCurrentRoute()->uri());
        }

        $request->attributes->set('traceId', $span->span->getContext()->getTraceId());

        $response = $next($request);
        $response->headers->set('X-TraceId', $span->span->getContext()->getTraceId());
        $span->span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
        $span->end();
        return $response;
    }
}
