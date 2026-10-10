<?php

use App\Http\Middleware\AuthorizeMetricsScrape;
use Spatie\Prometheus\Actions\RenderCollectorsAction;

return [
    'enabled' => env('PROMETHEUS_ENABLED', true),
    /*
     * The urls that will return metrics.
     */
    'urls' => [
        'default' => env('PROMETHEUS_PATH', 'prometheus'),
    ],

    /*
     * Bearer token a scraper may present to visit the above urls.
     */
    'token' => env('PROMETHEUS_TOKEN', ''),

    /*
     * IPs or CIDR ranges allowed to visit the above urls.
     * Access is refused when neither a token nor an IP range is configured.
     */
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('PROMETHEUS_ALLOWED_IPS', ''))))),

    /*
     * This is the default namespace that will be
     * used by all metrics
     */
    'default_namespace' => env('PROMETHEUS_NAMESPACE', 'musicparty'),

    /*
     * The middleware that will be applied to the urls above
     */
    'middleware' => [
        AuthorizeMetricsScrape::class,
    ],

    /*
     * You can override these classes to customize low-level behaviour of the package.
     * In most cases, you can just use the defaults.
     */
    'actions' => [
        'render_collectors' => RenderCollectorsAction::class,
    ],
];
