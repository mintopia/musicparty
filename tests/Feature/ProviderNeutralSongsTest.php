<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\PlaybackSync;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;

beforeEach(function () {
    $this->fake = app(FakeMusicProvider::class)->withTrack(FakeFallbackPlaylist::track('t1'));
    $this->party = Party::factory()->create(['provider_id' => 'fake', 'active' => true]);
});

it('requests, syncs and plays a Fake-provider track without duplicating Songs', function () {
    $member = PartyMember::factory()->for($this->party)->for(User::factory())->create();
    Sanctum::actingAs($member->user);

    $this->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 't1'])->assertSuccessful();
    $this->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 't1'])->assertSuccessful();

    expect(Song::count())->toBe(1)
        ->and(Song::first()->provider_id)->toBe('fake')
        ->and(Album::first()->provider_id)->toBe('fake')
        ->and(Artist::first()->provider_id)->toBe('fake')
        ->and(UpcomingSong::count())->toBe(1);

    $state = new PlaybackState(true, 't1', null, 180000, 1000, CarbonImmutable::now(), false, 'off', 'dev', 'Speaker', PlaybackSource::Polling);
    app(PlaybackSync::class)->apply($this->party, $state);

    expect(Song::count())->toBe(1)
        ->and($this->party->fresh()->song_id)->toBe(Song::first()->id)
        ->and(PlayedSong::count())->toBe(1);
});

it('creates a Song for a track the sync has not seen, once', function () {
    $state = new PlaybackState(true, 't1', null, 180000, 1000, CarbonImmutable::now(), false, 'off', 'dev', 'Speaker', PlaybackSource::Polling);

    app(PlaybackSync::class)->apply($this->party, $state);
    app(PlaybackSync::class)->apply($this->party, $state);

    expect(Song::where(['provider_id' => 'fake', 'provider_track_id' => 't1'])->count())->toBe(1)
        ->and(Song::where('provider_id', 'spotify')->count())->toBe(0);
});

it('keeps Songs with the same track id on different providers apart', function () {
    $spotify = Song::fromTrack(new Track('spotify', 't1', 'S', 1000, false, true, new AlbumRef('a', 'A', null), []));
    $fake = Song::fromTrack(FakeFallbackPlaylist::track('t1'));

    expect($spotify->id)->not->toBe($fake->id)
        ->and($fake->toTrack()->providerId)->toBe('fake');
});
