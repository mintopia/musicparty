<?php

namespace App\Services\Music\Data;

enum PlaybackSource: string
{
    case Polling = 'polling';
    case Webhook = 'webhook';
}
