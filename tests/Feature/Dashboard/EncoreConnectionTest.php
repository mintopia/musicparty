<?php

use App\Models\Party;
use App\Services\Playback\EncoreConnection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('is unknown when the broadcaster is not Pusher compatible', function () {
    config(['broadcasting.default' => 'null']);

    expect(app(EncoreConnection::class)->isConnected(Party::factory()->make(['code' => 'ABCD'])))->toBeNull();
});

it('is unknown when asking the broadcaster fails', function () {
    Broadcast::shouldReceive('connection')->andThrow(new RuntimeException('down'));

    expect(app(EncoreConnection::class)->isConnected(Party::factory()->make(['code' => 'ABCD'])))->toBeNull();
});
