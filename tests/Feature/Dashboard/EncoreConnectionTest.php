<?php

use App\Models\Party;
use App\Services\Playback\EncoreConnection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

beforeEach(fn () => Cache::flush());

it('is unknown when the broadcaster is not Pusher compatible', function () {
    config(['broadcasting.default' => 'null']);

    expect(app(EncoreConnection::class)->isConnected(Party::factory()->make(['code' => 'ABCD'])))->toBeNull();
});

it('is unknown, and says why in the log, when asking the broadcaster fails', function () {
    Log::spy();
    Broadcast::shouldReceive('connection')->andThrow(new RuntimeException('down'));

    expect(app(EncoreConnection::class)->isConnected(Party::factory()->make(['code' => 'ABCD'])))->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'private-encore.ABCD') && str_contains($message, 'down'));
});
