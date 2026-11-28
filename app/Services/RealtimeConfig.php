<?php

namespace App\Services;

class RealtimeConfig
{
    /**
     * @return array{appKey: string|null, host: string, port: int, forceTLS: bool}
     */
    public function forApp(): array
    {
        $url = parse_url((string) config('app.url')) ?: [];
        $secure = ($url['scheme'] ?? 'http') === 'https';
        $key = config('reverb.apps.apps.0.key');

        return [
            'appKey' => is_string($key) ? $key : null,
            'host' => $url['host'] ?? 'localhost',
            'port' => $url['port'] ?? ($secure ? 443 : 80),
            'forceTLS' => $secure,
        ];
    }
}
