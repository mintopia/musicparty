<?php

use App\Enums\Purpose;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use SpotifyWebAPI\SpotifyWebAPIException;
use Tests\Support\FakeSpotify;

require_once __DIR__.'/../Support/PartyPageHelpers.php';

beforeEach(function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose, ?string $market = null) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose, $market));
    $this->fake = FakeSpotify::install();
    $owner = User::factory()->create();
    pageSearchLinkAccount($owner, 'spotify', 'control-token');
    $this->party = pageSearchParty($owner);
    $this->member = pageSearchMember($this->party);
    Cache::flush();

    $track = FakeSpotify::track('t1', 'First');
    $albumTrack = FakeSpotify::track('t1', 'First');
    unset($albumTrack->album);
    $this->fake->respond('search', (object) ['tracks' => (object) ['items' => [$track], 'total' => 1, 'limit' => 20, 'offset' => 0]]);
    $this->fake->respond('getAlbum', (object) [
        'id' => 'alb1', 'name' => 'The Album', 'images' => [], 'artists' => [FakeSpotify::artist('artist1', 'Test Artist')],
        'release_date' => '2019-05-17', 'total_tracks' => 1, 'tracks' => (object) ['items' => [$albumTrack]],
    ]);
    $this->fake->respond('getArtist', (object) ['id' => 'artist1', 'name' => 'Test Artist', 'images' => []]);
    $this->fake->respond('getArtistTopTracks', (object) ['tracks' => [$track]]);
    $this->fake->respond('getArtistAlbums', (object) ['items' => [], 'total' => 0, 'limit' => 12, 'offset' => 0]);

    $this->searchUrl = route('parties.search', ['party' => $this->party->code, 'query' => 'q']);
    $this->albumUrl = route('parties.albums.show', ['party' => $this->party->code, 'album' => 'alb1']);
    $this->artistUrl = route('parties.artists.show', ['party' => $this->party->code, 'artist' => 'artist1']);
});

it('shares one leaky bucket per member across search, album and artist pages', function () {
    config(['musicparty.search_burst' => 2, 'musicparty.search_leak_per_second' => 0.001]);

    $this->actingAs($this->member->user, 'sanctum')->get($this->searchUrl)
        ->assertInertia(fn (Assert $page) => $page->where('results.current_page', 1));
    $this->actingAs($this->member->user, 'sanctum')->get($this->albumUrl)
        ->assertInertia(fn (Assert $page) => $page->where('retry', false)->where('album.id', 'alb1'));
    $this->actingAs($this->member->user, 'sanctum')->get($this->artistUrl)
        ->assertInertia(fn (Assert $page) => $page->where('retry', true)->where('artist', null));
    $this->actingAs($this->member->user, 'sanctum')->get($this->albumUrl.'?x=1')
        ->assertInertia(fn (Assert $page) => $page->where('retry', true));

    expect($this->fake->callsTo('getArtist'))->toBeEmpty();
});

it('keeps the bucket separate per member', function () {
    config(['musicparty.search_burst' => 1, 'musicparty.search_leak_per_second' => 0.001]);
    $other = pageSearchMember($this->party);

    $this->actingAs($this->member->user, 'sanctum')->get($this->albumUrl)->assertOk();
    $this->actingAs($other->user, 'sanctum')->get($this->artistUrl)
        ->assertInertia(fn (Assert $page) => $page->where('retry', false)->where('artist.id', 'artist1'));
});

it('creates a Party member on first view of the search, album and artist pages', function (string $url) {
    PartyMemberRole::query()->forceCreate(['code' => 'user', 'name' => 'User']);
    $visitor = User::factory()->create(['first_login' => false]);
    expect(PartyMember::query()->where('party_id', $this->party->id)->where('user_id', $visitor->id)->exists())->toBeFalse();

    $this->actingAs($visitor, 'sanctum')->get($this->{$url})->assertOk();

    $member = PartyMember::query()->where('party_id', $this->party->id)->where('user_id', $visitor->id)->first();
    expect($member)->not->toBeNull()->and($member->role->code)->toBe('user');
})->with(['searchUrl', 'albumUrl', 'artistUrl']);

it('returns a 404 page for an odd artist id and keeps the query', function () {
    $this->fake->respond('getArtist', new SpotifyWebAPIException('Invalid id', 400));

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.artists.show', ['party' => $this->party->code, 'artist' => 'not%20an%20id']).'?query=abba')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Parties/Artist')->where('artist', null)->where('query', 'abba'));
});
