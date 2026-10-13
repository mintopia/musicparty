<?php

use App\Enums\PartyModerationType;
use App\Enums\Purpose;
use App\Models\PartyMember;
use App\Models\PartyModeration;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMapper;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyBackoffException;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use SpotifyWebAPI\SpotifyWebAPIException;
use Tests\Support\FakeSpotify;

require_once __DIR__.'/../Support/PartyPageHelpers.php';

function pageArtistAlbums(int $total = 2): object
{
    $items = [];
    for ($i = 1; $i <= min($total, 12); $i++) {
        $items[] = (object) ['id' => "alb{$i}", 'name' => "Album {$i}", 'images' => [(object) ['url' => "https://i.scdn.co/image/alb{$i}", 'height' => 640, 'width' => 640]]];
    }

    return (object) ['items' => $items, 'total' => $total, 'limit' => 12, 'offset' => 0];
}

beforeEach(function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose, ?string $market = null) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose, $market));
    $this->fake = FakeSpotify::install();
    $this->owner = User::factory()->create();
    pageSearchLinkAccount($this->owner, 'spotify', 'control-token');
    $this->party = pageSearchParty($this->owner);
    $this->member = pageSearchMember($this->party);
    Cache::flush();
    $this->fake->respond('getArtist', (object) ['id' => 'artist1', 'name' => 'Test Artist', 'images' => [(object) ['url' => 'https://i.scdn.co/image/artist', 'height' => 640, 'width' => 640]]]);
    $this->fake->respond('getArtistTopTracks', (object) ['tracks' => [FakeSpotify::track('t1', 'First'), FakeSpotify::track('t2', 'Second')]]);
    $this->fake->respond('getArtistAlbums', pageArtistAlbums());
    $this->url = route('parties.artists.show', ['party' => $this->party->code, 'artist' => 'artist1']);
});

it('redirects a guest away from the artist page', function () {
    $this->get($this->url)->assertRedirect();
});

it('renders the artist with annotated top tracks and album cards', function () {
    $this->actingAs($this->member->user, 'sanctum')
        ->get($this->url.'?query=hello')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Artist')
            ->where('query', 'hello')
            ->where('retry', false)
            ->where('artist.name', 'Test Artist')
            ->where('artist.image_url', 'https://i.scdn.co/image/artist')
            ->where('tracks.data.0.id', 't1')
            ->where('tracks.data.0.eligibility.allowed', true)
            ->where('albums.data.0.id', 'alb1')
            ->where('albums.data.1.name', 'Album 2'));

    expect($this->fake->callsTo('getArtistAlbums')[0][1]['include_groups'])->toBe('album,single');
});

it('paginates albums and keeps the query on page links', function () {
    $this->fake->respond('getArtistAlbums', pageArtistAlbums(30));

    $this->actingAs($this->member->user, 'sanctum')
        ->get($this->url.'?query=hello&page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->where('albums.current_page', 2)
            ->where('albums.last_page', 3)
            ->where('albums.next_page_url', fn ($url) => str_contains($url, 'query=hello') && str_contains($url, 'page=3')));
    expect($this->fake->callsTo('getArtistAlbums')[0][1]['offset'])->toBe(12);
});

it('returns a 404 page for an unknown artist id', function () {
    $this->fake->respond('getArtist', new SpotifyWebAPIException('Non existing id', 404));

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.artists.show', ['party' => $this->party->code, 'artist' => 'nope']))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Parties/Artist')->where('artist', null)->where('retry', false));
});

it('shows the retry state without calling the provider when the bucket is exhausted', function () {
    config(['musicparty.search_burst' => 1, 'musicparty.search_leak_per_second' => 0.001]);

    $this->actingAs($this->member->user, 'sanctum')->get($this->url)->assertOk();
    $this->actingAs($this->member->user, 'sanctum')->get($this->url.'?x=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('retry', true)->where('artist', null));
    expect($this->fake->callsTo('getArtist'))->toHaveCount(1);
});

it('shows the retry state while the provider is backing off', function () {
    $this->fake->respond('getArtistTopTracks', new SpotifyBackoffException(30));

    $this->actingAs($this->member->user, 'sanctum')->get($this->url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('retry', true));
});

it('caches per Party country but never shares eligibility between members', function () {
    $this->party->forceFill(['country' => 'DE'])->save();
    $banned = PartyMember::factory()->banned()->create(['party_id' => $this->party->id, 'user_id' => User::factory()->create(['first_login' => false])->id]);

    $this->actingAs($this->member->user, 'sanctum')->get($this->url)
        ->assertInertia(fn (Assert $page) => $page->where('tracks.data.0.eligibility.allowed', true));
    $this->actingAs($banned->user, 'sanctum')->get($this->url)
        ->assertInertia(fn (Assert $page) => $page->where('tracks.data.0.eligibility.allowed', false));

    expect($this->fake->callsTo('getArtistTopTracks'))->toHaveCount(1)
        ->and($this->fake->callsTo('getArtistTopTracks')[0][1]['market'])->toBe('DE')
        ->and(Cache::has('music.spotify.artist.artist1.top-tracks.DE'))->toBeTrue();
});

it('gives the same eligibility reasons on search, album and artist pages', function () {
    $this->party->forceFill(['explicit' => false, 'max_requests' => 5, 'no_repeat_interval' => 3600, 'min_song_length' => 100])->save();
    $explicit = FakeSpotify::track('t1', 'First');
    $explicit->explicit = true;
    $recent = FakeSpotify::track('t2', 'Second');
    $blocked = FakeSpotify::track('t3', 'Third');
    $ok = FakeSpotify::track('t4', 'Fourth');
    PlayedSong::factory()->for($this->party)->for(Song::fromTrack(SpotifyMapper::track($recent)))->create(['played_at' => now()->subMinute()]);
    $moderation = new PartyModeration;
    $moderation->party()->associate($this->party);
    $moderation->type = PartyModerationType::mtId;
    $moderation->value = 't3';
    $moderation->regex = false;
    $moderation->save();
    $tracks = [$explicit, $recent, $blocked, $ok];

    $this->fake->respond('getArtistTopTracks', (object) ['tracks' => $tracks]);
    $this->fake->respond('search', (object) ['tracks' => (object) ['items' => $tracks, 'total' => 4, 'limit' => 20, 'offset' => 0]]);
    $albumTracks = array_map(function ($track) {
        $copy = clone $track;
        unset($copy->album);

        return $copy;
    }, $tracks);
    $this->fake->respond('getAlbum', (object) ['id' => 'alb1', 'name' => 'A', 'images' => [], 'artists' => [FakeSpotify::artist()], 'release_date' => '2019', 'total_tracks' => 4, 'tracks' => (object) ['items' => $albumTracks]]);

    $reasons = fn (string $url, string $key) => $this->actingAs($this->member->user, 'sanctum')->get($url)->viewData('page')['props'][$key]['data'];
    $extract = fn (array $rows) => array_map(fn ($row) => [$row['id'], $row['eligibility']['allowed'], $row['eligibility']['reason']], $rows);

    $artist = $extract($reasons($this->url, 'tracks'));
    $search = $extract($reasons(route('parties.search', ['party' => $this->party->code, 'query' => 'q']), 'results'));
    $album = $extract($reasons(route('parties.albums.show', ['party' => $this->party->code, 'album' => 'alb1']), 'tracks'));

    expect($artist)->toBe($search)->toBe($album)
        ->and($artist[0][1])->toBeFalse()
        ->and($artist[1][1])->toBeFalse()
        ->and($artist[2][1])->toBeFalse()
        ->and($artist[3][1])->toBeTrue();
});

it('rejects a blocked track posted directly', function () {
    $moderation = new PartyModeration;
    $moderation->party()->associate($this->party);
    $moderation->type = PartyModerationType::mtId;
    $moderation->value = 't1';
    $moderation->regex = false;
    $moderation->save();
    $this->fake->respond('getTrack', FakeSpotify::track('t1', 'First'));

    $response = $this->actingAs($this->member->user, 'sanctum')
        ->postJson(route('api.v1.parties.upcomingsongs.store', ['party' => $this->party->code]), ['provider_track_id' => 't1']);

    expect($response->status())->toBeGreaterThanOrEqual(400);
    expect(UpcomingSong::query()->where('party_id', $this->party->id)->count())->toBe(0);
});
