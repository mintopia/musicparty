<?php

namespace App\Services\Music\Data;

enum PlaybackSource: string
{
    case Polling = 'polling';
    case Webhook = 'webhook';
    case WebPlayer = 'web_player';
    case Encore = 'encore';

    public function isPushed(): bool
    {
        return $this === self::WebPlayer || $this === self::Encore;
    }
}
