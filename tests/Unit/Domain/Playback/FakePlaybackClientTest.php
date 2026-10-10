<?php

use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Playback\Testing\FakePlaybackClient;

it('reports an idle host as stopped and the scripted playback otherwise', function () {
    $client = new FakePlaybackClient;

    expect($client->currentPlayback('host-account-1')->status)->toBe(PlaybackStatus::Stopped);

    $playing = new PlaybackState(PlaybackStatus::Playing, new TrackReference('fake', 'track-1'), 1000, null, 180000);
    $client->playbackIs($playing);

    expect($client->currentPlayback('host-account-1'))->toBe($playing);
});

it('queues tracks for the host in order', function () {
    $client = new FakePlaybackClient;

    $client->queueTrack('track-1', 'host-account-1');
    $client->queueTrack('track-2', 'host-account-1');

    expect($client->queuedTracks())->toBe(['track-1', 'track-2']);
});

it('surfaces temporary failures from playback calls once', function () {
    $client = new FakePlaybackClient;
    $client->rateLimitNext(15);

    expect(fn () => $client->currentPlayback('host-account-1'))->toThrow(ProviderTemporaryFailure::class);

    $client->failNextWith(new ProviderTemporaryFailure);

    expect(fn () => $client->queueTrack('track-1', 'host-account-1'))->toThrow(ProviderTemporaryFailure::class)
        ->and($client->queuedTracks())->toBe([])
        ->and($client->currentPlayback('host-account-1')->status)->toBe(PlaybackStatus::Stopped);
});

it('records playback commands for the host and surfaces an injected failure once', function () {
    $client = new FakePlaybackClient;

    $client->play('h');
    $client->pause('h');
    $client->next('h');
    $client->seek(5000, 'h');
    $client->volume(30, 'h');

    expect($client->commands())->toBe([['play', null], ['pause', null], ['next', null], ['seek', 5000], ['volume', 30]]);

    $client->rateLimitNext(3);

    expect(fn () => $client->play('h'))->toThrow(ProviderTemporaryFailure::class)
        ->and($client->commands())->toHaveCount(5);
});
