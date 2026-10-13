<?php

use App\Http\Resources\PlaybackStateResource;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

it('serialises the PlaybackState JSON contract with an ISO 8601 sampledAt', function () {
    $state = new PlaybackState(
        true, 'track-1', 'linked-1', 200000, 45000,
        CarbonImmutable::parse('2026-10-13T12:00:00+00:00'),
        true, 'context', 'dev-1', 'Kitchen', PlaybackSource::Webhook,
    );

    expect(new PlaybackStateResource($state)->toArray(new Request))->toBe([
        'isPlaying' => true,
        'trackId' => 'track-1',
        'linkedFromId' => 'linked-1',
        'durationMs' => 200000,
        'progressMs' => 45000,
        'sampledAt' => '2026-10-13T12:00:00+00:00',
        'shuffle' => true,
        'repeat' => 'context',
        'deviceId' => 'dev-1',
        'deviceName' => 'Kitchen',
        'source' => 'webhook',
    ]);
});

it('keeps every key for an idle state', function () {
    $state = PlaybackState::idle(PlaybackSource::Polling);

    $json = new PlaybackStateResource($state)->toArray(new Request);

    expect($json)->toHaveKeys(['isPlaying', 'trackId', 'durationMs', 'progressMs', 'sampledAt', 'source'])
        ->and($json['isPlaying'])->toBeFalse()
        ->and($json['trackId'])->toBeNull()
        ->and($json['sampledAt'])->toBeString();
});
