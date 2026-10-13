<?php

use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Spotify\SpotifyMapper;

function spotifyFixture(string $key): stdClass
{
    $all = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/Spotify/tracks.json'));

    return $all->{$key};
}

it('maps a relinked track and records the original id', function () {
    $track = SpotifyMapper::track(spotifyFixture('relinked'));

    expect($track->providerId)->toBe('spotify')
        ->and($track->id)->toBe('relinked-id')
        ->and($track->linkedFromId)->toBe('original-id')
        ->and($track->explicit)->toBeTrue()
        ->and($track->isPlayable)->toBeTrue()
        ->and($track->durationMs)->toBe(215000)
        ->and($track->album->imageUrl)->toBe('https://i.scdn.co/image/big')
        ->and(array_map(fn ($artist) => $artist->name, $track->artists))->toBe(['Artist One', 'Artist Two']);
});

it('marks unplayable tracks as not playable without a relink', function () {
    $track = SpotifyMapper::track(spotifyFixture('unplayable'));

    expect($track->isPlayable)->toBeFalse()
        ->and($track->linkedFromId)->toBeNull()
        ->and($track->album->imageUrl)->toBeNull();
});

it('maps local files as unplayable and falls back to the uri for the id', function () {
    $track = SpotifyMapper::track(spotifyFixture('local'));

    expect($track->isPlayable)->toBeFalse()
        ->and($track->id)->toBe('spotify:local:Band:Album:Song:180');
});

it('builds playback state sampled at the provider timestamp', function () {
    $state = SpotifyMapper::playbackState(spotifyFixture('playback'));

    expect($state->isPlaying)->toBeTrue()
        ->and($state->trackId)->toBe('relinked-id')
        ->and($state->linkedFromId)->toBe('original-id')
        ->and($state->durationMs)->toBe(215000)
        ->and($state->progressMs)->toBe(42000)
        ->and($state->sampledAt->getTimestampMs())->toBe(1760000000000)
        ->and($state->shuffle)->toBeTrue()
        ->and($state->repeat)->toBe('context')
        ->and($state->deviceId)->toBe('dev1')
        ->and($state->deviceName)->toBe('Living Room')
        ->and($state->source)->toBe(PlaybackSource::Polling);
});

it('reports no track when the player is on a podcast episode', function () {
    $payload = spotifyFixture('playback');
    $payload->item->type = 'episode';

    $state = SpotifyMapper::playbackState($payload);

    expect($state->trackId)->toBeNull()->and($state->durationMs)->toBeNull();
});

it('maps albums with their tracks inheriting the album reference', function () {
    $album = SpotifyMapper::album((object) [
        'id' => 'alb1', 'name' => 'An Album', 'release_date' => '2020-05-01', 'total_tracks' => 1,
        'images' => [(object) ['url' => 'https://i.scdn.co/image/a']],
        'artists' => [(object) ['id' => 'art1', 'name' => 'Artist One']],
        'tracks' => (object) ['items' => [(object) ['id' => 't1', 'name' => 'One', 'duration_ms' => 1000, 'artists' => []]]],
    ]);

    expect($album->releaseDate)->toBe('2020-05-01')
        ->and($album->tracks[0]->album->id)->toBe('alb1')
        ->and($album->tracks[0]->isPlayable)->toBeTrue();
});

it('maps playlists and devices', function () {
    $playlist = SpotifyMapper::playlist((object) ['id' => 'p1', 'name' => 'Mix', 'owner' => (object) ['display_name' => 'Jess'], 'tracks' => (object) ['total' => 12]]);
    $device = SpotifyMapper::device((object) ['id' => 'd1', 'name' => 'Speaker', 'type' => 'Speaker', 'is_active' => true]);

    expect($playlist->ownerName)->toBe('Jess')->and($playlist->trackCount)->toBe(12)
        ->and($device->isActive)->toBeTrue();
});
