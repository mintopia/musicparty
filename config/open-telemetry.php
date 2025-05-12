<?php

return [
    'enabled' => env('OPENTELEMETRY_ENABLED', false),
    'endpoints' => [
        'traces' => env('OPENTELEMETRY_ENDPOINT_TRACES', 'http://collector:4318/v1/traces'),
        'metrics' => env('OPENTELEMETRY_ENDPOINT_METRICS', 'http://collector:4318/v1/metrics'),
        'logs' => env('OPENTELEMETRY_ENDPOINT_LOGS', 'http://collector:4318/v1/logs'),
    ],
    'service' => [
        'name' => env('OPENTELEMETRY_SERVICE_NAME', config('app.name')),
        'namespace' => env('OPENTELEMETRY_SERVICE_NAMESPACE', \Illuminate\Support\Str::slug(config('app.name'))),
        'version' => env('OPENTELEMETRY_SERVICE_VERSION', '1.0.0'),
    ],
    'environment' => env('OPENTELEMETRY_ENVIRONMENT', config('app.env')),
];
