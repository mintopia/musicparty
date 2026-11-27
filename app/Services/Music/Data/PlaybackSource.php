<?php

namespace App\Services\Music\Data;

enum PlaybackSource: string
{
    case Polling = 'polling';
    case Webhook = 'webhook';
    case WebPlayer = 'web_player';
    case Encore = 'encore';

    public function label(): string
    {
        return match ($this) {
            self::Polling => 'Polling',
            self::Webhook => 'Webhook',
            self::WebPlayer => 'Web Player',
            self::Encore => 'Encore',
        };
    }

    public function isPushed(): bool
    {
        return $this === self::WebPlayer || $this === self::Encore;
    }
}
