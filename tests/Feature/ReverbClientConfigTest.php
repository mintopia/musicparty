<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pusherConfigFrom(string $html): array
{
    preg_match('/window\.pusherConfig = (\{.*?\});/', $html, $matches);

    return json_decode($matches[1], true);
}

it('shows the public reverb host and not the internal one in the root view', function (): void {
    config([
        'broadcasting.connections.reverb.options.host' => 'reverb',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.client.host' => 'music.example.com',
        'broadcasting.connections.reverb.client.port' => 443,
        'broadcasting.connections.reverb.client.scheme' => 'https',
        'broadcasting.connections.reverb.client.key' => 'public-key',
    ]);

    $html = $this->get('/')->getContent();

    expect(pusherConfigFrom($html))->toBe([
        'appKey' => 'public-key',
        'host' => 'music.example.com',
        'port' => 443,
        'scheme' => 'https',
    ])->and($html)->not->toContain('"reverb"');
});
