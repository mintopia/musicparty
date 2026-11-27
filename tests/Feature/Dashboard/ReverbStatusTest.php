<?php

use App\Services\Dashboard\ReverbStatus;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Pusher\Pusher;

function fakeReverbChannels(Closure $respond): void
{
    $pusher = Mockery::mock(Pusher::class)->makePartial();
    $pusher->shouldReceive('get')->with('/channels')->andReturnUsing($respond);
    Broadcast::shouldReceive('connection')->andReturn(new PusherBroadcaster($pusher));
}

it('is reachable when Reverb answers with no occupied channels', function () {
    fakeReverbChannels(fn (): object => json_decode('{"channels":[]}'));

    expect(app(ReverbStatus::class)->reachable())->toBeTrue();
});

it('is reachable when Reverb lists occupied channels', function () {
    fakeReverbChannels(fn (): object => json_decode('{"channels":{"party.ABCD":{}}}'));

    expect(app(ReverbStatus::class)->reachable())->toBeTrue();
});

it('is unreachable when the Reverb API fails', function () {
    fakeReverbChannels(fn () => throw new RuntimeException('connection refused'));

    expect(app(ReverbStatus::class)->reachable())->toBeFalse();
});
