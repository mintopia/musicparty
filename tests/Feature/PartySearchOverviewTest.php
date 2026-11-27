<?php

use App\Enums\Purpose;
use App\Models\PartySearch;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;
use Inertia\Testing\AssertableInertia as Assert;
use SpotifyWebAPI\SpotifyWebAPIException;
use Tests\Support\FakeSpotify;

require_once __DIR__.'/../Support/PartyPageHelpers.php';

beforeEach(function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
    $this->fake = FakeSpotify::install();
    $owner = User::factory()->create();
    pageSearchLinkAccount($owner, 'spotify', 'control-token');
    $this->party = pageSearchParty($owner);
    $this->member = pageSearchMember($this->party);
    $this->visit = fn (array $query, Closure $assert) => $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, ...$query]))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps($assert));
});

it('asks Spotify once for tracks, albums and artists at the per type limit', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1')]));

    ($this->visit)(['query' => 'closing time'], fn (Assert $loaded) => $loaded->where('results.view', 'overview'));

    expect($this->fake->callsTo('search'))->toHaveCount(1)
        ->and($this->fake->callsTo('search')[0][0])->toBe('closing time')
        ->and($this->fake->callsTo('search')[0][1])->toBe(['track', 'album', 'artist'])
        ->and($this->fake->callsTo('search')[0][2])->toMatchArray(['limit' => 10, 'offset' => 0]);
});

it('makes Spotify\'s first track the top result and repeats it in the tracks list', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse(
        [FakeSpotify::track('first', 'Closing Time'), FakeSpotify::track('second'), FakeSpotify::track('third')],
        [FakeSpotify::album('a1', 'Closing Time')],
        [FakeSpotify::artistWithImage('x1', 'Someone Else')],
    ));

    ($this->visit)(['query' => 'Closing Time'], fn (Assert $loaded) => $loaded
        ->where('results.top.kind', 'track')
        ->where('results.top.track.id', 'first')
        ->where('results.tracks.0.id', 'first')
        ->where('results.tracks.1.id', 'second')
        ->where('results.tracks.2.id', 'third')
        ->has('results.tracks', 3)
        ->where('results.albums.0.id', 'a1')
        ->where('results.artists.0.id', 'x1'));
});

it('never makes an album the top result even when its name equals the query', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1')], [FakeSpotify::album('a1', 'Closing Time')]));

    ($this->visit)(['query' => 'closing time'], fn (Assert $loaded) => $loaded
        ->where('results.top.kind', 'track')
        ->where('results.top.track.id', 't1'));
});

it('makes an artist with the exact name the top result and keeps every track', function (string $query) {
    $this->fake->respond('search', FakeSpotify::searchResponse(
        array_map(fn (int $n) => FakeSpotify::track("t{$n}"), range(1, 5)),
        [FakeSpotify::album('a1', 'Feeling Strangely Fine')],
        [FakeSpotify::artistWithImage('x0', 'Semisonic Tribute'), FakeSpotify::artistWithImage('x1', 'Semisonic')],
    ));

    ($this->visit)(['query' => $query], fn (Assert $loaded) => $loaded
        ->where('results.top.kind', 'artist')
        ->where('results.top.artist.id', 'x1')
        ->where('results.top.artist.image_url', 'https://i.scdn.co/image/x1')
        ->where('results.tracks.0.id', 't1')
        ->has('results.tracks', 4)
        ->has('results.artists', 2));
})->with(['same case' => 'Semisonic', 'case and punctuation' => 'semisonic!', 'leading The' => 'The Semisonic']);

it('omits empty types and reports a single populated type', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1'), FakeSpotify::track('t2')]));

    ($this->visit)(['query' => 'q'], fn (Assert $loaded) => $loaded
        ->where('results.albums', [])
        ->where('results.artists', [])
        ->where('results.empty', false)
        ->where('results.top.track.id', 't1'));
});

it('reports nothing found when every type is empty', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse());

    ($this->visit)(['query' => 'zzzz'], fn (Assert $loaded) => $loaded
        ->where('results.empty', true)
        ->where('results.top', null)
        ->where('results.tracks', []));
});

it('maps album and artist results', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse(
        [],
        [FakeSpotify::album('a1', 'Closing Time', 'single', '2003-05-01')],
        [FakeSpotify::artist('x1', 'No Image')],
    ));

    ($this->visit)(['query' => 'q'], fn (Assert $loaded) => $loaded
        ->where('results.albums.0', [
            'id' => 'a1',
            'name' => 'Closing Time',
            'image_url' => 'https://i.scdn.co/image/a1',
            'album_type' => 'Single',
            'artists' => [['id' => 'artist1', 'name' => 'Test Artist']],
            'release_year' => '2003',
        ])
        ->where('results.artists.0', ['id' => 'x1', 'name' => 'No Image', 'image_url' => null])
        ->where('results.top', null));
});

it('pages a single album or artist type with one typed call', function (string $type, string $key) {
    $items = [$type === 'album' ? FakeSpotify::album('i1') : FakeSpotify::artist('i1')];
    $this->fake->respond('search', FakeSpotify::searchResponse(albums: $key === 'albums' ? $items : [], artists: $key === 'artists' ? $items : [], albumTotal: 95, artistTotal: 95));

    ($this->visit)(['query' => 'q', 'type' => $type, 'page' => 3], fn (Assert $loaded) => $loaded
        ->where('results.view', 'type')
        ->where('results.type', $type)
        ->where('results.total', 95)
        ->where('results.capped', false)
        ->where('results.items.current_page', 3)
        ->where('results.items.last_page', 10)
        ->where('results.items.data.0.id', 'i1'));

    expect($this->fake->callsTo('search')[0][1])->toBe($type)
        ->and($this->fake->callsTo('search')[0][2])->toMatchArray(['limit' => 10, 'offset' => 20]);
})->with([
    'albums' => ['album', 'albums'],
    'artists' => ['artist', 'artists'],
]);

it('caps a huge total at 1,000 and never asks Spotify past that offset', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1')], trackTotal: 48213));

    ($this->visit)(['query' => 'love', 'type' => 'track', 'page' => 500], fn (Assert $loaded) => $loaded
        ->where('results.total', 1000)
        ->where('results.capped', true)
        ->where('results.items.last_page', 100)
        ->where('results.items.current_page', 100));

    expect($this->fake->callsTo('search')[0][2])->toMatchArray(['limit' => 10, 'offset' => 990]);
});

it('does not flag a total at or under 1,000 as capped', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1')], trackTotal: 1000));

    ($this->visit)(['query' => 'love', 'type' => 'track'], fn (Assert $loaded) => $loaded
        ->where('results.total', 1000)
        ->where('results.capped', false));
});

it('limits perPage to what Spotify allows', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1')], trackTotal: 300));

    ($this->visit)(['query' => 'q', 'type' => 'track', 'perPage' => 50], fn (Assert $loaded) => $loaded->where('results.items.last_page', 30));

    expect($this->fake->callsTo('search')[0][2])->toMatchArray(['limit' => 10]);
});

it('ignores an unknown type and shows the overview', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1')]));

    ($this->visit)(['query' => 'q', 'type' => 'podcast'], fn (Assert $loaded) => $loaded->where('results.view', 'overview'));

    expect($this->fake->callsTo('search')[0][1])->toBe(['track', 'album', 'artist']);
});

it('keeps tracks annotated in a single type page', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1')], trackTotal: 1));

    ($this->visit)(['query' => 'q', 'type' => 'track'], fn (Assert $loaded) => $loaded
        ->where('results.items.data.0.id', 't1')
        ->where('results.items.data.0.eligibility.allowed', true)
        ->where('results.items.data.0.queue.in_queue', false));
});

it('returns the friendly error shape for a single type search', function () {
    $this->fake->respond('search', new SpotifyWebAPIException('Too many requests', 429));

    ($this->visit)(['query' => 'q', 'type' => 'album'], fn (Assert $loaded) => $loaded
        ->where('results.error.code', 'busy')
        ->where('results.error.retryable', true));
});

it('flags weak matches when no result holds every word of the query', function (string $query, bool $weak) {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1', 'Get Lucky')], [FakeSpotify::album('a1', 'Random Access Memories')]));

    ($this->visit)(['query' => $query], fn (Assert $loaded) => $loaded->where('results.weak', $weak));
})->with([
    'strong' => ['get lucky', false],
    'typo' => ['lucky dafft punk', true],
]);

it('does not flag an empty result as weak', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse());

    ($this->visit)(['query' => 'zzz'], fn (Assert $loaded) => $loaded->where('results.empty', true)->where('results.weak', false));
});

it('logs an overview search for the party but not paging through a single type', function () {
    $this->fake->respond('search', FakeSpotify::searchResponse([FakeSpotify::track('t1')], trackTotal: 1));

    ($this->visit)(['query' => 'daft punk'], fn (Assert $loaded) => $loaded);
    ($this->visit)(['query' => 'daft punk', 'type' => 'track'], fn (Assert $loaded) => $loaded);

    expect(PartySearch::query()->where('party_id', $this->party->id)->pluck('query')->all())->toBe(['daft punk']);
});

it('offers suggestions from party data on the empty page and none once searching', function () {
    PartySearch::factory()->create(['party_id' => $this->party->id, 'query' => 'Levels', 'term' => 'levels']);
    $song = Song::factory()->create(['name' => 'Midnight City']);
    $this->party->forceFill(['song_id' => $song->id])->save();
    UpcomingSong::factory()->create(['party_id' => $this->party->id, 'song_id' => $song->id]);

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestions.recent', ['Levels'])
            ->where('suggestions.nowPlaying.name', 'Midnight City')
            ->where('suggestions.artists.0.requests', 1));

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'x']))
        ->assertInertia(fn (Assert $page) => $page->where('suggestions', null));
});
