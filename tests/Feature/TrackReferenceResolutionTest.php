<?php

use App\Enums\Purpose;
use App\Models\User;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;

function spotifyProviderForReferences(): SpotifyMusicProvider
{
    return new SpotifyMusicProvider(app(SpotifyGateway::class), User::factory()->make(), Purpose::Search);
}

it('resolves a spotify track uri to its provider track id', function () {
    expect(spotifyProviderForReferences()->resolveTrackReference('spotify:track:6rqhFgbbKwnb9MLmUQDhG6'))
        ->toBe('6rqhFgbbKwnb9MLmUQDhG6');
});

it('returns null for unrecognised spotify references', function (string $reference) {
    expect(spotifyProviderForReferences()->resolveTrackReference($reference))->toBeNull();
})->with([
    'other uri type' => 'spotify:album:6rqhFgbbKwnb9MLmUQDhG6',
    'episode' => 'spotify:episode:6rqhFgbbKwnb9MLmUQDhG6',
    'local file' => 'spotify:local:artist:album:title:123',
    'missing id' => 'spotify:track:',
    'malformed id' => 'spotify:track:not valid!',
    'trailing junk' => "spotify:track:6rqhFgbbKwnb9MLmUQDhG6\n",
    'url' => 'https://open.spotify.com/track/6rqhFgbbKwnb9MLmUQDhG6',
    'empty' => '',
]);

it('resolves fake track references', function () {
    $fake = new FakeMusicProvider;

    expect($fake->resolveTrackReference('fake:track:fake-track-3'))->toBe('fake-track-3')
        ->and($fake->resolveTrackReference('fake:album:x'))->toBeNull()
        ->and($fake->resolveTrackReference('fake:track:'))->toBeNull()
        ->and($fake->resolveTrackReference(''))->toBeNull();
});
