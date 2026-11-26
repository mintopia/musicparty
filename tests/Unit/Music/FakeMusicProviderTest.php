<?php

use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\Fake\FakeMusicProvider;

it('implements both contracts with a deterministic catalogue', function () {
    $a = new FakeMusicProvider;
    $b = new FakeMusicProvider;

    expect($a)->toBeInstanceOf(MusicProvider::class)->toBeInstanceOf(ProviderPlayback::class)
        ->and($a->id())->toBe('fake')
        ->and($a->searchTracks('', 1, 100))->toEqual($b->searchTracks('', 1, 100));
});

it('searches and paginates the catalogue', function () {
    $fake = new FakeMusicProvider;

    $page = $fake->searchTracks('Fake Track 1', 1, 5);

    expect($page->total)->toBe(11)->and($page->items)->toHaveCount(5)->and($page->perPage)->toBe(5)
        ->and($fake->searchTracks('nothing-matches', 1, 5)->items)->toBe([]);
});

it('records every call in order', function () {
    $fake = new FakeMusicProvider;
    $track = $fake->getTrack('fake-track-1');

    $fake->sendToPlayer($track);
    $fake->seek(1000);
    $fake->searchTracks('x', 1, 2);

    expect(array_column($fake->calls(), 'method'))->toBe(['getTrack', 'sendToPlayer', 'seek', 'searchTracks'])
        ->and($fake->callsTo('seek'))->toBe([[1000]])
        ->and($fake->sentToPlayer())->toBe([$track]);
});

it('returns configured playback state and devices', function () {
    $fake = new FakeMusicProvider;

    expect($fake->currentPlayback())->toBeNull()
        ->and($fake->devices())->toHaveCount(1);
});

it('serves browse data from the catalogue', function () {
    $fake = new FakeMusicProvider;

    expect($fake->getAlbum('fake-album-1')->tracks)->toHaveCount(3)
        ->and($fake->getArtist('fake-artist-1')->ref->id)->toBe('fake-artist-1')
        ->and($fake->getArtistTopTracks('fake-artist-1'))->toHaveCount(5)
        ->and($fake->getArtistAlbums('fake-artist-1', 1, 10)->total)->toBe(2)
        ->and($fake->getAlbum('missing'))->toBeNull();
});

it('appends to playlists', function () {
    $fake = new FakeMusicProvider;
    $fake->appendToPlaylist('fake-playlist', [$fake->getTrack('fake-track-2')]);

    expect($fake->getPlaylistTracks('fake-playlist'))->toHaveCount(1)
        ->and($fake->listPlaylists())->toHaveCount(1);
});

it('searches albums and artists in the catalogue and records the calls', function () {
    $fake = new FakeMusicProvider;

    $albums = $fake->searchAlbums('Fake Album 2', 1, 5);
    $artists = $fake->searchArtists('Fake Artist', 1, 4);
    $overview = $fake->searchOverview('Fake Artist 1', 3);

    expect($albums->total)->toBe(1)->and($albums->items[0]->ref->id)->toBe('fake-album-2')->and($albums->items[0]->albumType)->toBe('Album')
        ->and($artists->total)->toBe(6)->and($artists->items)->toHaveCount(4)
        ->and($overview->tracks->items)->toHaveCount(3)->and($overview->tracks->total)->toBe(5)
        ->and($overview->artists->items[0]->ref->id)->toBe('fake-artist-1')
        ->and(array_column($fake->calls(), 'method'))->toBe(['searchAlbums', 'searchArtists', 'searchOverview']);
});
