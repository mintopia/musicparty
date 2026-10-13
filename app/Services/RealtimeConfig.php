<?php

namespace App\Services;

use Illuminate\Http\Request;

class RealtimeConfig
{
    /**
     * @return array{appKey: string|null, host: string, port: int, forceTLS: bool}
     */
    public function forRequest(Request $request): array
    {
        $key = config('reverb.apps.apps.0.key');

        return [
            'appKey' => is_string($key) ? $key : null,
            'host' => $request->getHost(),
            'port' => (int) $request->getPort(),
            'forceTLS' => $request->isSecure(),
        ];
    }
}
