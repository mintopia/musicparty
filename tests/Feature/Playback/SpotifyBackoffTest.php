<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Providers\SpotifyMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Playback\EnqueueBackoff;
use App\Domain\Playback\Jobs\PollPlayback;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Spotify\SpotifyFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->freezeTime();
    Cache::flush();
    Queue::fake();
    config([
        'services.spotify' => ['client_id' => 'id', 'client_secret' => 'secret', 'market' => 'GB'],
        'musicparty.music_providers.spotify' => ['label' => 'Spotify', 'class' => SpotifyMusicProvider::class],
    ]);

    $this->spotify = new stdClass;
    $this->spotify->status = 429;
    $this->spotify->override = null;
    Http::preventStrayRequests();
    Http::fake([
        'accounts.spotify.com/*' => Http::response(SpotifyFake::fixture('token')),
        'api.spotify.com/*' => fn (Request $request) => $this->spotify->override !== null
            ? ($this->spotify->override)($request)
            : ($this->spotify->status === 429
            ? Http::response('', 429, ['Retry-After' => '7'])
            : (str_contains($request->url(), '/me/player/queue') ? Http::response('', 204) : Http::response(SpotifyFake::fixture('playlist-tracks')))),
    ]);

    $this->partyA = backoffParty('AAAA');
    $this->partyB = backoffParty('BBBB');
    $this->member = User::factory()->create();
    PartyMember::factory()->for($this->partyB)->for($this->member)->create();
});

function backoffParty(string $code): Party
{
    $party = Party::factory()->live()->create(['code' => $code, 'music_provider' => 'spotify', 'player_kind' => 'polling']);
    $provider = SocialProvider::query()->where('code', 'spotify')->first() ?? SocialProvider::factory()->create(['code' => 'spotify']);
    LinkedAccount::factory()->for($party->user)->create(['social_provider_id' => $provider->id]);

    return $party;
}

function spotifyApiCalls(): int
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'api.spotify.com'))->count();
}

function trigger429(): void
{
    $provider = app(SpotifyMusicProvider::class);

    expect(fn () => $provider->search('song', 1, 0))->toThrow(ProviderTemporaryFailure::class);
    expect(spotifyApiCalls())->toBe(1);
}

function pollParty(Party $party): void
{
    app()->call([new PollPlayback($party->code, true), 'handle']);
}

function hostId(Party $party): string
{
    return (string) LinkedAccount::query()->where('user_id', $party->user_id)->firstOrFail()->getKey();
}

it('stores one instance-wide deadline from the Retry-After header', function () {
    trigger429();

    expect(Cache::get('music.spotify.backoff-until'))->toBe(now()->getTimestamp() + 7);
});

it('fails a playlist read fast with the remaining wait and no HTTP call', function () {
    trigger429();
    $provider = app(SpotifyMusicProvider::class);

    $this->travel(3)->seconds();

    try {
        $provider->playlistTracks('pl', hostId($this->partyB));
        $this->fail('Expected the backoff to apply.');
    } catch (ProviderTemporaryFailure $failure) {
        expect($failure->retryAfterSeconds)->toBe(4);
    }

    expect(spotifyApiCalls())->toBe(1);
});

it('refuses a search from another Party with when to retry and no HTTP call', function () {
    trigger429();
    Sanctum::actingAs($this->member);

    $this->travel(2)->seconds();

    $this->getJson('/api/v1/parties/BBBB/search?q=song')
        ->assertServiceUnavailable()
        ->assertHeader('Retry-After', '5')
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, '5 seconds'));

    expect(spotifyApiCalls())->toBe(1);
});

it('skips a poll cycle without calling Spotify and reschedules after the wait', function () {
    trigger429();
    $this->mock(PlaybackCoordinator::class)->shouldNotReceive('trackChanged', 'tick', 'playbackEnded');

    pollParty($this->partyB);

    expect(spotifyApiCalls())->toBe(1);
    Queue::assertPushed(PollPlayback::class, fn (PollPlayback $job): bool => $job->reschedule && $job->delay >= 7);
});

it('fails an enqueue for another Party fast without consuming an enqueue backoff attempt', function () {
    trigger429();
    $request = TrackRequest::factory()->for($this->partyB)->create(['status' => RequestStatus::UpNext, 'provider_track_id' => 'track-1']);

    app(PlaybackCoordinator::class)->tick($this->partyB);

    expect(spotifyApiCalls())->toBe(1)
        ->and($request->fresh()->enqueued_at)->toBeNull()
        ->and(app(EnqueueBackoff::class)->shouldAttempt($request))->toBeTrue()
        ->and(Cache::get("playback.enqueue-backoff.{$request->id}"))->toBeNull()
        ->and(PartyLogEntry::query()->where('party_id', $this->partyB->id)->where('action', 'player.enqueue_failed')->exists())->toBeFalse();
});

it('resumes every kind of call once the Retry-After has passed', function () {
    trigger429();
    $this->spotify->status = 200;
    $request = TrackRequest::factory()->for($this->partyB)->create(['status' => RequestStatus::UpNext, 'provider_track_id' => 'track-1']);

    $this->travel(7)->seconds();

    expect(app(SpotifyMusicProvider::class)->playlistTracks('pl', hostId($this->partyB)))->not->toBeEmpty();

    app(PlaybackCoordinator::class)->tick($this->partyB);

    expect($request->fresh()->enqueued_at)->not->toBeNull()
        ->and(spotifyApiCalls())->toBeGreaterThan(1);
});

it('keeps the claim when Spotify answers an enqueue with a server error or times out', function (Closure $response) {
    $this->spotify->override = $response;
    $request = TrackRequest::factory()->for($this->partyB)->create(['status' => RequestStatus::UpNext, 'provider_track_id' => 'track-1']);

    app(PlaybackCoordinator::class)->tick($this->partyB);

    expect($request->fresh()->enqueued_at)->not->toBeNull()
        ->and($request->fresh()->enqueue_unconfirmed)->toBeTrue()
        ->and(Cache::get("playback.enqueue-backoff.{$request->id}"))->toBeNull();
})->with([
    '503' => fn () => fn () => Http::response('', 503),
    'timeout' => fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
]);

it('releases the claim when Spotify refuses the enqueue or the connection is refused', function (Closure $response) {
    $this->spotify->override = $response;
    $request = TrackRequest::factory()->for($this->partyB)->create(['status' => RequestStatus::UpNext, 'provider_track_id' => 'track-1']);

    app(PlaybackCoordinator::class)->tick($this->partyB);

    expect($request->fresh()->enqueued_at)->toBeNull()
        ->and($request->fresh()->enqueue_unconfirmed)->toBeFalse()
        ->and(Cache::get("playback.enqueue-backoff.{$request->id}"))->not->toBeNull();
})->with([
    '404' => fn () => fn () => Http::response('', 404),
    'refused' => fn () => fn () => throw new ConnectionException('cURL error 7: Failed to connect: Connection refused'),
]);
