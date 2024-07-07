<?php

return [
    'enabled' => env('PROMETHEUS_ENABLED', true),
    /*
     * The urls that will return metrics.
     */
    'urls' => [
        'default' => env('PROMETHEUS_PATH', 'prometheus'),
    ],

    /*
     * Only these IP's will be allowed to visit the above urls.
     * All IP's are allowed when empty.
     */
    'allowed_ips' => array_filter(explode(',', env('PROMETHEUS_ALLOWED_IPS', ''))),

    /*
     * This is the default namespace that will be
     * used by all metrics
     */
    'default_namespace' => env('PROMETHEUS_NAMESPACE', 'app'),

    /*
     * The middleware that will be applied to the urls above
     */
    'middleware' => [
        Spatie\Prometheus\Http\Middleware\AllowIps::class,
    ],

    /*
     * You can override these classes to customize low-level behaviour of the package.
     * In most cases, you can just use the defaults.
     */
    'actions' => [
        'render_collectors' => Spatie\Prometheus\Actions\RenderCollectorsAction::class,
    ],
];
