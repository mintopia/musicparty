<?php

return [
    'allow_overlapping_updates' => env('MUSICPARTY_ALLOW_OVERLAPPING_UPDATES', true),
    'webhook_dispatch_after_request' => env('MUSICPARTY_WEBHOOK_DISPATCH_AFTER_REQUEST', true),
    'webhook_should_queue' => env('MUSICPARTY_WEBHOOK_SHOULD_QUEUE', true),
];
