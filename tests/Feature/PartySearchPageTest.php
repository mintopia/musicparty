<?php

use App\Enums\PartyModerationType;
use App\Enums\Purpose;
use App\Exceptions\ProviderUnavailableException;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\PartyModeration;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMapper;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyBackoffException;
use App\Services\Spotify\SpotifyGateway;
use App\Services\Spotify\SpotifyRelinkRequiredException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSpotify;

require_once __DIR__.'/../Support/PartyPageHelpers.php';

function pageSearchUpcoming(Party $party, object $track, ?User $requester = null, int $score = 3, bool $queued = false): UpcomingSong
{
    $upcoming = new UpcomingSong;
    $upcoming->party()->associate($party);
    $upcoming->song()->associate(Song::fromTrack(SpotifyMapper::track($track)));
    if ($requester) {
        $upcoming->user()->associate($requester);
    }
    $upcoming->score = $score;
    $upcoming->queued_at = $queued ? now() : null;
    $upcoming->save();

    return $upcoming;
}

function pageSearchResults(FakeSpotify $fake, object ...$tracks): void
{
    $fake->respond('search', FakeSpotify::searchResponse(array_values($tracks), trackTotal: 57));
}

beforeEach(function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
    $this->fake = FakeSpotify::install();
    $this->owner = User::factory()->create();
    pageSearchLinkAccount($this->owner, 'spotify', 'control-token');
    $this->party = pageSearchParty($this->owner);
    $this->member = pageSearchMember($this->party);
});

it('redirects a guest away from the search page', function () {
    $this->get(route('parties.search', $this->party->code))->assertRedirect();
});

it('renders the Inertia search page for a member with results', function () {
    pageSearchResults($this->fake, FakeSpotify::track('fresh'));

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Search')
            ->where('party.code', $this->party->code)
            ->where('party.downvotes', true)
            ->where('params.query', 'q')
            ->missing('results')
            ->loadDeferredProps(fn (Assert $loaded) => $loaded
                ->where('results.view', 'overview')
                ->where('results.top.kind', 'track')
                ->where('results.top.track.id', 'fresh')
                ->where('results.top.track.eligibility.allowed', true)
                ->where('results.top.track.queue.in_queue', false)
                ->has('results.tracks', 1)
                ->where('results.tracks.0.id', 'fresh')));
});

it('renders the page without results when there is no query', function () {
    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', $this->party->code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Search')
            ->where('params.query', null)
            ->where('results', null)
            ->where('playerState.code', $this->party->code)
            ->has('playerState.now'));
});

it('marks queued tracks and reports why a banned member cannot request', function () {
    $banned = PartyMember::factory()->banned()->create(['party_id' => $this->party->id, 'user_id' => User::factory()->create(['first_login' => false])->id]);
    pageSearchUpcoming($this->party, FakeSpotify::track('waiting'), null, 5);
    pageSearchResults($this->fake, FakeSpotify::track('waiting'), FakeSpotify::track('other'));

    $this->actingAs($banned->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Search')
            ->loadDeferredProps(fn (Assert $loaded) => $loaded
                ->where('results.top.track.queue.in_queue', true)
                ->where('results.top.track.queue.score', 5)
                ->where('results.tracks.0.eligibility.allowed', false)
                ->whereNot('results.tracks.0.eligibility.reason', null)));
});

it('tells the requester a queued track is theirs and when it was added', function () {
    pageSearchUpcoming($this->party, FakeSpotify::track('mine'), $this->member->user);
    pageSearchUpcoming($this->party, FakeSpotify::track('theirs'), User::factory()->create(['first_login' => false]));
    pageSearchResults($this->fake, FakeSpotify::track('mine'), FakeSpotify::track('theirs'));

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $loaded) => $loaded
            ->where('results.top.track.queue.requested_by_you', true)
            ->where('results.top.track.queue.requested_ago', 'just now')
            ->where('results.tracks.0.queue.requested_by_you', true)
            ->where('results.tracks.1.queue.requested_by_you', false)));
});

it('paginates a single type using perPage and page and keeps the query string on page links', function () {
    pageSearchResults($this->fake, FakeSpotify::track('a'));

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q', 'type' => 'track', 'perPage' => 5, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('params.perPage', 5)
            ->where('params.type', 'track')
            ->loadDeferredProps(fn (Assert $loaded) => $loaded
                ->where('results.view', 'type')
                ->where('results.items.current_page', 2)
                ->where('results.items.last_page', 12)
                ->where('results.total', 57)
                ->where('results.capped', false)
                ->where('results.items.links.1.url', fn ($url) => str_contains($url, 'query=q') && str_contains($url, 'type=track') && str_contains($url, 'perPage=5'))));

    expect($this->fake->callsTo('search')[0][2])->toMatchArray(['limit' => 5, 'offset' => 5])
        ->and($this->fake->callsTo('search')[0][1])->toBe('track');
});

it('reports a relink error in the deferred results when Spotify needs re-linking', function () {
    $this->mock(MusicProviderFactory::class, function ($mock) {
        $provider = Mockery::mock(MusicProvider::class, ProviderPlayback::class);
        $provider->shouldReceive('searchOverview')->andThrow(new SpotifyRelinkRequiredException);
        $mock->shouldReceive('forParty')->andReturn($provider);
    });

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $loaded) => $loaded
                ->where('results.error.code', 'relink')
                ->where('results.error.message', 'The music provider needs re-linking before search is available.')
                ->where('results.error.retryable', false)));
});

it('reports a busy error in the deferred results when Spotify is backing off', function () {
    $this->mock(MusicProviderFactory::class, function ($mock) {
        $provider = Mockery::mock(MusicProvider::class, ProviderPlayback::class);
        $provider->shouldReceive('searchOverview')->andThrow(new SpotifyBackoffException(30));
        $mock->shouldReceive('forParty')->andReturn($provider);
    });

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $loaded) => $loaded
                ->where('results.error.code', 'busy')
                ->where('results.error.message', 'The music provider is busy right now. Please try again shortly.')
                ->where('results.error.retryable', true)));
});

it('reports every eligibility reason on the search page', function (string $reason, Closure $arrange) {
    $track = FakeSpotify::track('target');
    $member = $this->member;
    $arrange($this->party, $member, $track);
    pageSearchResults($this->fake, $track);

    $this->actingAs($member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $loaded) => $loaded
                ->where('results.top.track.eligibility.allowed', false)
                ->where('results.top.track.eligibility.reason', $reason)));
})->with([
    'not accepting requests' => ['Party is not accepting requests', fn ($party) => $party->forceFill(['allow_requests' => false])->save()],
    'banned member' => ['You are not allowed to make requests', fn ($party, $member) => $member->forceFill(['role_id' => PartyMemberRole::query()->forceCreate(['code' => 'banned', 'name' => 'Banned'])->id])->save()],
    'too many requests' => ['You are not allowed to request any more songs', function ($party, $member) {
        $party->forceFill(['max_requests' => 1])->save();
        pageSearchUpcoming($party, FakeSpotify::track('mine'), $member->user);
    }],
    'too short' => ['Song is too short', fn ($party) => $party->forceFill(['min_song_length' => 300])->save()],
    'too long' => ['Song is too long', fn ($party) => $party->forceFill(['max_song_length' => 100])->save()],
    'explicit' => ['Explicit songs are not allowed', function ($party, $member, $track) {
        $party->forceFill(['explicit' => false])->save();
        $track->explicit = true;
    }],
    'similar song waiting' => ['A similar song is already in the queue', function ($party, $member, $track) {
        pageSearchUpcoming($party, (object) [...(array) $track, 'id' => 'other-id'], null, 1);
    }],
    'played recently' => ['Played in the last hour', function ($party, $member, $track) {
        $party->forceFill(['no_repeat_interval' => 3600])->save();
        PlayedSong::factory()->for($party)->for(Song::fromTrack(SpotifyMapper::track($track)))->create(['played_at' => now()->subMinute()]);
    }],
    'played next' => ['This song will be played next', function ($party, $member, $track) {
        UpcomingSong::factory()->for($party)->for(Song::fromTrack(SpotifyMapper::track($track)))->sentToPlayer()->create();
    }],
    'moderated track' => ['Track is not allowed', function ($party, $member, $track) {
        $moderation = new PartyModeration;
        $moderation->party()->associate($party);
        $moderation->type = PartyModerationType::mtId;
        $moderation->value = 'target';
        $moderation->regex = false;
        $moderation->save();
    }],
]);

it('searches in the Party country rather than the owner market', function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose, ?string $market = null) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose, $market));
    $this->owner->forceFill(['market' => 'GB'])->save();
    $this->party->forceFill(['country' => 'DE'])->save();
    pageSearchResults($this->fake, FakeSpotify::track('fresh'));

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $loaded) => $loaded->has('results.top.track')));

    expect($this->fake->callsTo('search')[0][2]['market'])->toBe('DE');
});

it('shows the try again shortly state once a member exhausts the search bucket', function () {
    config(['musicparty.search_burst' => 2, 'musicparty.search_leak_per_second' => 0.001]);
    Cache::flush();
    pageSearchResults($this->fake, FakeSpotify::track('fresh'));
    $url = fn (string $query) => route('parties.search', ['party' => $this->party->code, 'query' => $query]);

    $load = fn ($user, Closure $assert, string $query = 'q') => $this->actingAs($user, 'sanctum')->get($url($query))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps($assert));

    $load($this->member->user, fn (Assert $loaded) => $loaded->where('results.view', 'overview'), 'q1');
    $load($this->member->user, fn (Assert $loaded) => $loaded->where('results.view', 'overview'), 'q2');
    expect($this->fake->callsTo('search'))->toHaveCount(2);

    $load($this->member->user, fn (Assert $loaded) => $loaded->where('results.error.code', 'busy'), 'q3');
    expect($this->fake->callsTo('search'))->toHaveCount(2);

    $other = pageSearchMember($this->party);
    $load($other->user, fn (Assert $loaded) => $loaded->where('results.view', 'overview'), 'q3');
});

it('renders the page immediately without calling the provider until the deferred props load', function () {
    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Parties/Search')->missing('results'));

    expect($this->fake->callsTo('search'))->toBeEmpty();
});

it('maps provider unavailability to a friendly retryable error', function (bool $timedOut, string $code, string $message) {
    $this->mock(MusicProviderFactory::class, function ($mock) use ($timedOut) {
        $provider = Mockery::mock(MusicProvider::class, ProviderPlayback::class);
        $provider->shouldReceive('searchOverview')->andThrow(new ProviderUnavailableException($timedOut));
        $provider->shouldReceive('displayName')->andReturn('Spotify');
        $mock->shouldReceive('forParty')->andReturn($provider);
    });

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $loaded) => $loaded
            ->where('results.error.code', $code)
            ->where('results.error.message', $message)
            ->where('results.error.retryable', true)));
})->with([
    'timeout' => [true, 'timeout', "Spotify didn't answer in time."],
    'unreachable' => [false, 'unavailable', 'The music provider could not be reached.'],
]);

it('rescues an unexpected failure in the deferred results instead of erroring the page', function () {
    $this->mock(MusicProviderFactory::class, function ($mock) {
        $provider = Mockery::mock(MusicProvider::class, ProviderPlayback::class);
        $provider->shouldReceive('searchOverview')->andThrow(new RuntimeException('boom'));
        $mock->shouldReceive('forParty')->andReturn($provider);
    });
    $url = route('parties.search', ['party' => $this->party->code, 'query' => 'q']);

    $this->actingAs($this->member->user, 'sanctum')->get($url)
        ->assertInertia(fn (Assert $page) => $page->reload(fn (Assert $loaded) => $loaded->missing('results'), only: 'results'));
});

it('serves a repeated search within a minute from cache with one provider call and per-viewer badges', function () {
    Cache::flush();
    config(['musicparty.search_burst' => 1, 'musicparty.search_leak_per_second' => 0.001]);
    $track = FakeSpotify::track('fresh');
    pageSearchResults($this->fake, $track);
    pageSearchUpcoming($this->party, $track, $this->member->user, score: 4);
    $other = pageSearchMember($this->party);
    $url = route('parties.search', ['party' => $this->party->code, 'query' => 'q']);

    $load = fn ($user, Closure $assert) => $this->actingAs($user, 'sanctum')->get($url)
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps($assert));

    $load($this->member->user, fn (Assert $loaded) => $loaded->where('results.top.track.queue.requested_by_you', true));
    $load($other->user, fn (Assert $loaded) => $loaded->where('results.top.track.queue.requested_by_you', false));
    $load($this->member->user, fn (Assert $loaded) => $loaded->where('results.view', 'overview'));

    expect($this->fake->callsTo('search'))->toHaveCount(1);
});

it('serves a repeated search from a serialising cache store', function () {
    config(['cache.stores.array.serialize' => true, 'musicparty.search_burst' => 1, 'musicparty.search_leak_per_second' => 0.001]);
    Cache::purge('array');
    Cache::flush();
    pageSearchResults($this->fake, FakeSpotify::track('fresh'));
    $url = route('parties.search', ['party' => $this->party->code, 'query' => 'q']);

    foreach ([1, 2] as $_) {
        $this->actingAs($this->member->user, 'sanctum')->get($url)
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $loaded) => $loaded->where('results.view', 'overview')));
    }

    expect($this->fake->callsTo('search'))->toHaveCount(1);
});

it('makes another provider call for a different page or country', function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose, ?string $market = null) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose, $market));
    Cache::flush();
    pageSearchResults($this->fake, FakeSpotify::track('fresh'));
    $get = fn (int $page) => $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q', 'type' => 'track', 'page' => $page]))
        ->assertInertia(fn (Assert $p) => $p->loadDeferredProps(fn (Assert $loaded) => $loaded->where('results.view', 'type')));

    $get(1);
    $get(1);
    expect($this->fake->callsTo('search'))->toHaveCount(1);

    $get(2);
    expect($this->fake->callsTo('search'))->toHaveCount(2);

    $this->party->forceFill(['country' => 'DE'])->save();
    $get(1);
    expect($this->fake->callsTo('search'))->toHaveCount(3);
});

it('rejects a query over 200 characters', function () {
    $this->actingAs($this->member->user, 'sanctum')
        ->getJson(route('parties.search', ['party' => $this->party->code, 'query' => str_repeat('a', 201)]))
        ->assertUnprocessable();
});

it('returns 429 from the search route past the throttle and guards album and artist the same way', function () {
    foreach (['parties.search', 'parties.albums.show', 'parties.artists.show'] as $name) {
        expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain('throttle:search');
    }

    $this->actingAs($this->member->user, 'sanctum');
    $url = route('parties.search', $this->party->code);
    for ($i = 0; $i < 120; $i++) {
        $this->get($url)->assertOk();
    }

    $this->get($url)->assertStatus(429);
});
