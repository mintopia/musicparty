<?php

it('has no legacy webhook endpoint', function (string $path) {
    $this->post("/webhooks/parties/ABCD/{$path}")->assertNotFound();
})->with(['soloist', 'simple', 'librespot']);
