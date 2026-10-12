<?php

use App\Enums\SettingType;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use App\Services\SpotifySearchService;
use Illuminate\Support\Str;
use SpotifyWebAPI\SpotifyWebAPI;
use Tests\Support\FakeSpotify;

function searchLinkAccount(User $user, string $providerCode, string $token): void
{
    $provider = SocialProvider::query()->where('code', $providerCode)->first() ?? SocialProvider::factory()->create(['code' => $providerCode]);
    foreach (['client_id', 'client_secret'] as $code) {
        $setting = new ProviderSetting;
        $setting->forceFill(['code' => $code, 'name' => $code, 'type' => SettingType::stString, 'value' => 'test']);
        $setting->provider()->associate($provider);
        $setting->save();
    }
    LinkedAccount::factory()->create([
        'user_id' => $user->id,
        'social_provider_id' => $provider->id,
        'access_token' => $token,
        'refresh_token' => 'refresh',
        'access_token_expires_at' => now()->addHour(),
    ]);
}

function searchParty(User $owner, array $attributes = []): Party
{
    $party = new Party;
    $party->forceFill(array_merge(['code' => strtoupper(Str::random(4)), 'name' => 'Search Party', 'user_id' => $owner->id, 'active' => false, 'allow_requests' => true, 'explicit' => true], $attributes));
    Party::withoutEvents(fn () => $party->save());

    return $party;
}

function searchMember(Party $party, string $roleCode = 'member'): PartyMember
{
    $role = PartyMemberRole::query()->where('code', $roleCode)->first() ?? PartyMemberRole::query()->forceCreate(['code' => $roleCode, 'name' => ucfirst($roleCode)]);
    $member = new PartyMember;
    $member->party_id = $party->id;
    $member->user_id = User::factory()->create()->id;
    $member->role_id = $role->id;
    $member->save();

    return $member;
}

function searchUpcoming(Party $party, object $track, ?User $requester = null, int $score = 3, bool $queued = false): UpcomingSong
{
    $upcoming = new UpcomingSong;
    $upcoming->party()->associate($party);
    $upcoming->song()->associate(Song::fromSpotify($track));
    if ($requester) {
        $upcoming->user()->associate($requester);
    }
    $upcoming->score = $score;
    $upcoming->queued_at = $queued ? now() : null;
    $upcoming->save();

    return $upcoming;
}

function searchResults(FakeSpotify $fake, object ...$tracks): void
{
    $fake->respond('search', (object) ['tracks' => (object) ['items' => $tracks, 'total' => 57, 'limit' => 20, 'offset' => 0]]);
}

function searchFor(Party $party, PartyMember $member, string $query = 'q', int $page = 1, int $perPage = 20): array
{
    $paginator = (new SpotifySearchService($party, $member))->search($query, $page, $perPage);

    return collect($paginator->items())->map(fn (object $item) => clone $item)->keyBy('id')->all();
}

beforeEach(function () {
    $this->fake = FakeSpotify::install();
    $this->owner = User::factory()->create();
    searchLinkAccount($this->owner, 'spotify', 'control-token');
    $this->party = searchParty($this->owner);
    $this->member = searchMember($this->party);
});

it('marks a result that is not in the queue as not upcoming with zero score and no vote or requester', function () {
    searchResults($this->fake, FakeSpotify::track('fresh'));

    $item = searchFor($this->party, $this->member)['fresh'];

    expect($item->upcoming)->toBeFalse()
        ->and($item->upcoming_id)->toBeNull()
        ->and($item->score)->toBe(0)
        ->and($item->vote)->toBeNull()
        ->and($item->user)->toBeNull()
        ->and($item->allowed)->toBeTrue()
        ->and($item->reason)->toBeNull();
});

it('marks a waiting request with its id, score and requester nickname', function () {
    $requester = User::factory()->create();
    $upcoming = searchUpcoming($this->party, FakeSpotify::track('waiting'), $requester, 7);
    searchResults($this->fake, FakeSpotify::track('waiting'));

    $item = searchFor($this->party, $this->member)['waiting'];

    expect($item->upcoming)->toBeTrue()
        ->and($item->upcoming_id)->toBe($upcoming->id)
        ->and($item->score)->toBe(7)
        ->and($item->user)->toBe($requester->nickname)
        ->and($item->vote)->toBeNull();
});

it('marks a waiting request with a null requester when it has no user', function () {
    searchUpcoming($this->party, FakeSpotify::track('anon'));
    searchResults($this->fake, FakeSpotify::track('anon'));

    expect(searchFor($this->party, $this->member)['anon']->user)->toBeNull();
});

it('reports the searching user vote value and ignores other users votes', function (int $value) {
    $upcoming = searchUpcoming($this->party, FakeSpotify::track('voted'));
    Vote::factory()->create(['upcoming_song_id' => $upcoming->id, 'user_id' => $this->member->user_id, 'value' => $value]);
    Vote::factory()->create(['upcoming_song_id' => $upcoming->id, 'user_id' => User::factory()->create()->id, 'value' => -$value]);
    searchResults($this->fake, FakeSpotify::track('voted'));

    expect(searchFor($this->party, $this->member)['voted']->vote)->toBe($value);
})->with([
    'upvote' => [1],
    'downvote' => [-1],
]);

it('does not mark a request that is already queued as upcoming and blocks it as playing next', function () {
    searchUpcoming($this->party, FakeSpotify::track('queued'), null, 4, true);
    searchResults($this->fake, FakeSpotify::track('queued'));

    $item = searchFor($this->party, $this->member)['queued'];

    expect($item->upcoming)->toBeFalse()
        ->and($item->upcoming_id)->toBeNull()
        ->and($item->score)->toBe(0)
        ->and($item->allowed)->toBeFalse()
        ->and($item->reason)->toBe('This song will be played next');
});

it('does not mark a request from another party', function () {
    $other = searchParty($this->owner, ['name' => 'Other']);
    searchUpcoming($other, FakeSpotify::track('elsewhere'));
    searchResults($this->fake, FakeSpotify::track('elsewhere'));

    expect(searchFor($this->party, $this->member)['elsewhere']->upcoming)->toBeFalse();
});

it('marks each result in a mixed page independently', function () {
    $upcoming = searchUpcoming($this->party, FakeSpotify::track('b'), null, 2);
    Vote::factory()->create(['upcoming_song_id' => $upcoming->id, 'user_id' => $this->member->user_id, 'value' => 1]);
    searchResults($this->fake, FakeSpotify::track('a'), FakeSpotify::track('b'), FakeSpotify::track('c'));

    $items = searchFor($this->party, $this->member);

    expect($items['a']->upcoming)->toBeFalse()
        ->and($items['b']->upcoming)->toBeTrue()
        ->and($items['b']->vote)->toBe(1)
        ->and($items['c']->upcoming)->toBeFalse();
});

it('blocks a banned member with the not-allowed-to-request reason', function () {
    $banned = searchMember($this->party, 'banned');
    searchResults($this->fake, FakeSpotify::track('x'));

    $item = searchFor($this->party, $banned)['x'];

    expect($item->allowed)->toBeFalse()->and($item->reason)->toBe('You are not allowed to make requests');
});

it('blocks every result when the party is not accepting requests', function () {
    $this->party->forceFill(['allow_requests' => false])->save();
    searchResults($this->fake, FakeSpotify::track('x'));

    $item = searchFor($this->party->refresh(), $this->member)['x'];

    expect($item->allowed)->toBeFalse()->and($item->reason)->toBe('Party is not accepting requests');
});

it('blocks explicit tracks when the party disallows them and allows clean ones', function () {
    $this->party->forceFill(['explicit' => false])->save();
    $explicit = FakeSpotify::track('dirty');
    $explicit->explicit = true;
    searchResults($this->fake, $explicit, FakeSpotify::track('clean'));

    $items = searchFor($this->party->refresh(), $this->member);

    expect($items['dirty']->allowed)->toBeFalse()
        ->and($items['dirty']->reason)->toBe('Explicit songs are not allowed')
        ->and($items['clean']->allowed)->toBeTrue()
        ->and($items['clean']->reason)->toBeNull();
});

it('blocks tracks outside the party length limits', function () {
    $this->party->forceFill(['min_song_length' => 150, 'max_song_length' => 250])->save();
    $short = FakeSpotify::track('short');
    $short->duration_ms = 100000;
    $long = FakeSpotify::track('long');
    $long->duration_ms = 300000;
    searchResults($this->fake, $short, $long, FakeSpotify::track('ok'));

    $items = searchFor($this->party->refresh(), $this->member);

    expect($items['short']->reason)->toBe('Song is too short')
        ->and($items['long']->reason)->toBe('Song is too long')
        ->and($items['ok']->allowed)->toBeTrue();
});

it('blocks a member who has reached the request limit but not an owner', function () {
    $this->party->forceFill(['max_requests' => 1])->save();
    searchUpcoming($this->party, FakeSpotify::track('mine'), $this->member->user);
    searchResults($this->fake, FakeSpotify::track('x'));

    $limited = searchFor($this->party->refresh(), $this->member)['x'];
    $owner = searchFor($this->party->refresh(), searchMember($this->party, 'owner'))['x'];

    expect($limited->allowed)->toBeFalse()
        ->and($limited->reason)->toBe('You are not allowed to request any more songs')
        ->and($owner->allowed)->toBeTrue();
});

it('blocks the context playlist track as unavailable', function () {
    searchResults($this->fake, FakeSpotify::track('62mFjcWGkt2OY9LT9EvxS2'));

    $item = searchFor($this->party, $this->member)['62mFjcWGkt2OY9LT9EvxS2'];

    expect($item->allowed)->toBeFalse()->and($item->reason)->toBe('This song is not available');
});

it('pins E5-8 R11: a same-name same-artist waiting request does not block a different track because checkSimilarSongs matches on array indexes', function () {
    searchUpcoming($this->party, FakeSpotify::track('original', 'Same Name'));
    searchResults($this->fake, FakeSpotify::track('remaster', 'Same Name'));

    $item = searchFor($this->party, $this->member)['remaster'];

    expect($item->upcoming)->toBeFalse()
        ->and($item->allowed)->toBeTrue()
        ->and($item->reason)->toBeNull();
});

it('skips the upcoming lookup and returns an empty page when Spotify returns no tracks', function () {
    searchResults($this->fake);

    $paginator = (new SpotifySearchService($this->party, $this->member))->search('nothing');

    expect($paginator->items())->toBe([])->and($paginator->total())->toBe(57);
});

it('passes market, limit and offset to Spotify and paginates with the reported total', function () {
    $this->owner->forceFill(['market' => 'GB'])->save();
    $this->party->refresh();
    searchResults($this->fake, FakeSpotify::track('a'));

    $paginator = (new SpotifySearchService($this->party, $this->member))->search('daft punk', 3, 10);

    expect($this->fake->callsTo('search'))->toBe([['daft punk', 'track', ['market' => 'GB', 'limit' => 10, 'offset' => 20]]])
        ->and($paginator->total())->toBe(57)
        ->and($paginator->perPage())->toBe(10)
        ->and($paginator->currentPage())->toBe(3);
});

it('searches with the spotifysearch account session when the owner has one', function () {
    searchLinkAccount($this->owner, 'spotifysearch', 'search-token');
    $tokens = [];
    $fake = $this->fake;
    app()->bind(SpotifyWebAPI::class, function ($app, array $params) use ($fake, &$tokens) {
        $tokens[] = $params['session']->getAccessToken();

        return $fake;
    });
    searchResults($this->fake, FakeSpotify::track('a'));

    searchFor($this->party->refresh(), $this->member);

    expect($tokens)->toBe(['search-token'])->and($this->fake->callsTo('search'))->toHaveCount(1);
});

it('falls back to the control account session when the owner has no spotifysearch account', function () {
    $tokens = [];
    $fake = $this->fake;
    app()->bind(SpotifyWebAPI::class, function ($app, array $params) use ($fake, &$tokens) {
        $tokens[] = $params['session']->getAccessToken();

        return $fake;
    });
    searchResults($this->fake, FakeSpotify::track('a'));

    $items = searchFor($this->party->refresh(), $this->member);

    expect($tokens)->toBe(['control-token'])
        ->and($this->fake->callsTo('search'))->toHaveCount(1)
        ->and($items['a']->allowed)->toBeTrue();
});

it('throws an Error when the owner has neither a spotifysearch nor a control account', function () {
    $bare = User::factory()->create();
    $party = searchParty($bare, ['name' => 'Bare']);
    $member = searchMember($party);

    expect(fn () => (new SpotifySearchService($party, $member))->search('q'))->toThrow(Error::class);
    expect($this->fake->callsTo('search'))->toBeEmpty();
});
