<?php

use App\Enums\Purpose;
use App\Models\PartyMember;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyBackoffException;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use SpotifyWebAPI\SpotifyWebAPIException;
use Tests\Support\FakeSpotify;

require_once __DIR__.'/../Support/PartyPageHelpers.php';

function pageAlbumPayload(string $id = 'alb1'): object
{
    $first = FakeSpotify::track('t1', 'First');
    $second = FakeSpotify::track('t2', 'Second');
    unset($first->album, $second->album);

    return (object) [
        'id' => $id,
        'name' => 'The Album',
        'images' => [(object) ['url' => 'https://i.scdn.co/image/alb', 'height' => 640, 'width' => 640]],
        'artists' => [FakeSpotify::artist('artist1', 'Test Artist')],
        'release_date' => '2019-05-17',
        'total_tracks' => 2,
        'tracks' => (object) ['items' => [$first, $second]],
    ];
}

beforeEach(function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose, ?string $market = null) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose, $market));
    $this->fake = FakeSpotify::install();
    $this->owner = User::factory()->create();
    pageSearchLinkAccount($this->owner, 'spotify', 'control-token');
    $this->party = pageSearchParty($this->owner);
    $this->member = pageSearchMember($this->party);
    Cache::flush();
    $this->fake->respond('getAlbum', pageAlbumPayload());
    $this->url = route('parties.albums.show', ['party' => $this->party->code, 'album' => 'alb1']);
});

it('redirects a guest away from the album page', function () {
    $this->get($this->url)->assertRedirect();
});

it('renders the album with annotated tracks in order for a member', function () {
    $this->actingAs($this->member->user, 'sanctum')
        ->get($this->url.'?query=hello')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Album')
            ->where('party.code', $this->party->code)
            ->where('query', 'hello')
            ->where('retry', false)
            ->where('album.name', 'The Album')
            ->where('album.release_year', '2019')
            ->where('album.track_count', 2)
            ->where('album.artists.0.name', 'Test Artist')
            ->where('tracks.data.0.id', 't1')
            ->where('tracks.data.1.id', 't2')
            ->where('tracks.data.0.eligibility.allowed', true)
            ->where('tracks.data.0.queue.in_queue', false));
});

it('reports blocked reasons to a banned member', function () {
    $banned = PartyMember::factory()->banned()->create(['party_id' => $this->party->id, 'user_id' => User::factory()->create(['first_login' => false])->id]);

    $this->actingAs($banned->user, 'sanctum')
        ->get($this->url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('tracks.data.0.eligibility.allowed', false)
            ->where('tracks.data.1.eligibility.allowed', false));
});

it('returns a 404 page for an unknown album id', function () {
    $this->fake->respond('getAlbum', new SpotifyWebAPIException('Non existing id', 404));

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.albums.show', ['party' => $this->party->code, 'album' => 'nope']))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Album')
            ->where('album', null)
            ->where('retry', false));
});

it('shows the retry state without calling the provider when the bucket is exhausted', function () {
    config(['musicparty.search_burst' => 1, 'musicparty.search_leak_per_second' => 0.001]);

    $this->actingAs($this->member->user, 'sanctum')->get($this->url)->assertOk();
    expect($this->fake->callsTo('getAlbum'))->toHaveCount(1);

    $this->actingAs($this->member->user, 'sanctum')->get($this->url.'?x=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('retry', true)->where('album', null));
    expect($this->fake->callsTo('getAlbum'))->toHaveCount(1);
});

it('shows the retry state while the provider is backing off', function () {
    $this->fake->respond('getAlbum', new SpotifyBackoffException(30));

    $this->actingAs($this->member->user, 'sanctum')->get($this->url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('retry', true));
});

it('caches the album per Party country but never shares eligibility between members', function () {
    $this->party->forceFill(['country' => 'DE'])->save();
    $banned = PartyMember::factory()->banned()->create(['party_id' => $this->party->id, 'user_id' => User::factory()->create(['first_login' => false])->id]);

    $this->actingAs($this->member->user, 'sanctum')->get($this->url)
        ->assertInertia(fn (Assert $page) => $page->where('tracks.data.0.eligibility.allowed', true));
    $this->actingAs($banned->user, 'sanctum')->get($this->url)
        ->assertInertia(fn (Assert $page) => $page->where('tracks.data.0.eligibility.allowed', false));

    expect($this->fake->callsTo('getAlbum'))->toHaveCount(1)
        ->and($this->fake->callsTo('getAlbum')[0][1]['market'])->toBe('DE')
        ->and(Cache::has('music.spotify.album.alb1.DE'))->toBeTrue();
});
