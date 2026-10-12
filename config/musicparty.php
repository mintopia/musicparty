<?php

return [
    'allow_overlapping_updates' => env('MUSICPARTY_ALLOW_OVERLAPPING_UPDATES', true),
    'webhook_dispatch_after_request' => env('MUSICPARTY_WEBHOOK_DISPATCH_AFTER_REQUEST', true),
    'webhook_should_queue' => env('MUSICPARTY_WEBHOOK_SHOULD_QUEUE', true),
    // Soloist webhook leaky bucket: burst size, and requests per second drained.
    // Defaults allow a 20 call burst then a sustained 5/s, well above Encore's webhooks.delay_ms cadence.
    'webhook_burst' => (int) env('MUSICPARTY_WEBHOOK_BURST', 20),
    'webhook_leak_per_second' => (float) env('MUSICPARTY_WEBHOOK_LEAK_PER_SECOND', 5),
];
