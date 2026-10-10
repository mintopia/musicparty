<?php

$trusted = trim((string) env('TRUSTED_PROXIES', '*'));

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated addresses or CIDRs from TRUSTED_PROXIES, or "*" to trust
    | any proxy. With "*" the client address is only as trustworthy as the
    | network guarantee that clients reach the app through the proxy.
    |
    */

    'proxies' => in_array($trusted, ['*', '**', ''], true)
        ? '*'
        : array_values(array_filter(array_map(trim(...), explode(',', $trusted)))),

];
