<?php

use App\Enums\PartyModerationType;
use App\Enums\Purpose;
use App\Enums\SettingType;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\PartyModeration;
use App\Models\PlayedSong;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
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
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSpotify;

function pageSearchLinkAccount(User $user, string $providerCode, string $token): void
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

function pageSearchParty(User $owner, array $attributes = []): Party
{
    $party = new Party;
    $party->forceFill(array_merge(['code' => strtoupper(Str::random(4)), 'name' => 'Search Party', 'user_id' => $owner->id, 'provider_id' => 'spotify', 'active' => false, 'allow_requests' => true, 'explicit' => true], $attributes));
    Party::withoutEvents(fn () => $party->save());

    return $party;
}

function pageSearchMember(Party $party, string $roleCode = 'member'): PartyMember
{
    $role = PartyMemberRole::query()->where('code', $roleCode)->first() ?? PartyMemberRole::query()->forceCreate(['code' => $roleCode, 'name' => ucfirst($roleCode)]);
    $member = new PartyMember;
    $member->party_id = $party->id;
    $member->user_id = User::factory()->create(['first_login' => false])->id;
    $member->role_id = $role->id;
    $member->save();

    return $member;
}

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
    $fake->respond('search', (object) ['tracks' => (object) ['items' => $tracks, 'total' => 57, 'limit' => 20, 'offset' => 0]]);
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
            ->where('results.current_page', 1)
            ->where('results.data.0.id', 'fresh')
            ->where('results.data.0.eligibility.allowed', true)
            ->where('results.data.0.queue.in_queue', false));
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
            ->where('results.data.0.queue.in_queue', true)
            ->where('results.data.0.queue.score', 5)
            ->where('results.data.1.eligibility.allowed', false)
            ->whereNot('results.data.1.eligibility.reason', null));
});

it('paginates using perPage and page and keeps the query string on page links', function () {
    pageSearchResults($this->fake, FakeSpotify::track('a'));

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q', 'perPage' => 10, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('params.perPage', 10)
            ->where('results.current_page', 2)
            ->where('results.last_page', 6)
            ->where('results.links.1.url', fn ($url) => str_contains($url, 'query=q') && str_contains($url, 'perPage=10')));
});

it('flashes a relink message when Spotify needs re-linking', function () {
    $this->mock(MusicProviderFactory::class, function ($mock) {
        $provider = Mockery::mock(MusicProvider::class, ProviderPlayback::class);
        $provider->shouldReceive('searchTracks')->andThrow(new SpotifyRelinkRequiredException);
        $mock->shouldReceive('forParty')->andReturn($provider);
    });

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.error', 'The music provider needs re-linking before search is available.')
            ->where('results', null));
});

it('flashes a busy message when Spotify is backing off', function () {
    $this->mock(MusicProviderFactory::class, function ($mock) {
        $provider = Mockery::mock(MusicProvider::class, ProviderPlayback::class);
        $provider->shouldReceive('searchTracks')->andThrow(new SpotifyBackoffException(30));
        $mock->shouldReceive('forParty')->andReturn($provider);
    });

    $this->actingAs($this->member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.error', 'The music provider is busy right now. Please try again shortly.'));
});

it('reports every eligibility reason on the search page', function (string $reason, Closure $arrange) {
    $track = FakeSpotify::track('target');
    $member = $this->member;
    $arrange($this->party, $member, $track);
    pageSearchResults($this->fake, $track);

    $this->actingAs($member->user, 'sanctum')
        ->get(route('parties.search', ['party' => $this->party->code, 'query' => 'q']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.data.0.eligibility.allowed', false)
            ->where('results.data.0.eligibility.reason', $reason));
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
    'played recently' => ['This song has been played recently', function ($party, $member, $track) {
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
