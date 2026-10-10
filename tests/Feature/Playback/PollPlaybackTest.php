<?php

use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Exceptions\HostAccountNeedsRelink;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Actions\GoLiveParty;
use App\Domain\Party\FallbackPlaylistCheck;
use App\Domain\Party\FallbackPlaylistGate;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Actions\ChangePartyPlayer;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\Jobs\PollPlayback;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Queue\Blocklist;
use App\Domain\Queue\RequestStatus;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\Play;
use App\Models\SocialProvider;
use App\Models\TrackRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');
    Cache::flush();
    Queue::fake();
    $this->provider = new FakeMusicProvider;
    app()->instance(FakeMusicProvider::class, $this->provider);
    $this->party = Party::factory()->live()->create(['player_kind' => 'polling']);
    $this->account = LinkedAccount::factory()
        ->for($this->party->user)
        ->create(['social_provider_id' => SocialProvider::factory()->create(['code' => 'fake'])->id]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function playing(string $trackId, int $positionMs = 1000, ?int $durationMs = 180000): PlaybackState
{
    return new PlaybackState(PlaybackStatus::Playing, new TrackReference('fake', $trackId), $positionMs, CarbonImmutable::now(), $durationMs);
}

function poll(Party $party, bool $reschedule = true): void
{
    app()->call([new PollPlayback($party->code, $reschedule), 'handle']);
}

function assertRescheduledIn(int $seconds): void
{
    Queue::assertPushed(PollPlayback::class, fn (PollPlayback $job): bool => $job->delay === $seconds && $job->reschedule);
}

it('calls trackChanged when the track differs from the last poll and reschedules', function () {
    $this->provider->playbackIs(playing('t1'));
    $this->mock(PlaybackCoordinator::class)->shouldReceive('trackChanged')->once()
        ->withArgs(fn (Party $party, string $id): bool => $party->is($this->party) && $id === 't1');

    poll($this->party);

    assertRescheduledIn(10);
});

it('does not repeat trackChanged for the same track', function () {
    $this->provider->playbackIs(playing('t1'));
    $coordinator = $this->mock(PlaybackCoordinator::class);
    $coordinator->shouldReceive('trackChanged')->once();
    $coordinator->shouldReceive('tick')->once();

    poll($this->party);
    poll($this->party);
});

it('fires trackChanged again when the next poll shows a different track', function () {
    $coordinator = $this->mock(PlaybackCoordinator::class);
    $coordinator->shouldReceive('trackChanged')->twice();

    $this->provider->playbackIs(playing('t1'));
    poll($this->party);
    $this->provider->playbackIs(playing('t2'));
    poll($this->party);
});

it('does not treat a paused track as a change', function () {
    $this->provider->playbackIs(new PlaybackState(PlaybackStatus::Paused, new TrackReference('fake', 't1'), 500, CarbonImmutable::now(), 180000));
    $this->mock(PlaybackCoordinator::class)->shouldNotReceive('trackChanged');

    poll($this->party);

    assertRescheduledIn(60);
});

it('records the play, advances the queue and keeps one Up Next in the provider queue', function () {
    TrackRequest::factory()->for($this->party)->create(['provider_track_id' => 'r1', 'created_at' => now()->subMinutes(2)]);
    TrackRequest::factory()->for($this->party)->create(['provider_track_id' => 'r2', 'created_at' => now()->subMinute()]);

    app(PlaybackCoordinator::class)->startIfIdle($this->party);
    expect($this->provider->queuedTracks())->toBe(['r1']);

    $this->provider->playbackIs(playing('r1'));
    poll($this->party);

    expect($this->provider->queuedTracks())->toBe(['r1', 'r2'])
        ->and(TrackRequest::query()->where('provider_track_id', 'r1')->first()->status)->toBe(RequestStatus::Playing)
        ->and(TrackRequest::query()->where('provider_track_id', 'r2')->first()->status)->toBe(RequestStatus::UpNext)
        ->and(Play::query()->count())->toBe(1);

    poll($this->party);
    expect($this->provider->queuedTracks())->toBe(['r1', 'r2']);
});

it('goes idle with a long delay when nothing is playing', function () {
    $this->mock(PlaybackCoordinator::class)->shouldNotReceive('trackChanged', 'playbackEnded');

    poll($this->party);

    assertRescheduledIn(60);
    expect(app(PartyPlayers::class)->for($this->party)->state()->status)->toBe(PlaybackStatus::Stopped);
});

it('signals the end of playback when a track was playing and now nothing is', function () {
    $coordinator = $this->mock(PlaybackCoordinator::class);
    $coordinator->shouldReceive('trackChanged')->once();
    $coordinator->shouldReceive('playbackEnded')->once();

    $this->provider->playbackIs(playing('t1'));
    poll($this->party);
    $this->provider->playbackIs(PlaybackState::stopped());
    poll($this->party);
    poll($this->party);
});

it('polls sooner near the end of the track', function () {
    $this->provider->playbackIs(playing('t1', positionMs: 177500, durationMs: 180000));
    $this->mock(PlaybackCoordinator::class)->shouldReceive('trackChanged');

    poll($this->party);

    assertRescheduledIn(4);
});

it('exposes the remembered state through the player', function () {
    $this->provider->playbackIs(playing('t1', 4000));
    $this->mock(PlaybackCoordinator::class)->shouldReceive('trackChanged');

    poll($this->party);
    $state = app(PartyPlayers::class)->for($this->party)->state();

    expect($state->status)->toBe(PlaybackStatus::Playing)
        ->and($state->currentTrack->providerTrackId)->toBe('t1')
        ->and($state->positionMs)->toBe(4000);
});

it('pauses the party and tells the host when the account is unlinked', function () {
    $this->account->delete();
    $this->mock(PlaybackCoordinator::class)->shouldNotReceive('trackChanged');

    poll($this->party);

    $log = PartyLogEntry::query()->where('party_id', $this->party->id)->orderBy('id')->get();
    expect($this->party->fresh()->state)->toBe(PartyState::Paused)
        ->and($log->pluck('action')->all())->toBe(['party.paused', 'player.host_account_unlinked'])
        ->and($log[0]->system_actor)->toBe('player')
        ->and($log[0]->user_id)->toBeNull()
        ->and($log[0]->details['reason'])->toBe('player.host_account_unlinked')
        ->and($log[1]->system_actor)->toBe('player');
    Queue::assertNotPushed(PollPlayback::class);
});

it('pauses the party when the account is flagged for relink', function () {
    $this->account->forceFill(['needs_relink' => true])->save();

    poll($this->party);

    expect($this->party->fresh()->state)->toBe(PartyState::Paused)
        ->and(PartyLogEntry::query()->where('action', 'player.host_account_needs_relink')->exists())->toBeTrue();
    Queue::assertNotPushed(PollPlayback::class);
});

it('pauses the party when the provider reports the account needs relinking', function () {
    $this->provider->failNextWith(HostAccountNeedsRelink::forAccount($this->account->id));

    poll($this->party);

    expect($this->party->fresh()->state)->toBe(PartyState::Paused)
        ->and(PartyLogEntry::query()->where('action', 'player.host_account_needs_relink')->exists())->toBeTrue();
    Queue::assertNotPushed(PollPlayback::class);
});

it('backs off exponentially on transient failures without recording a track change', function () {
    $coordinator = $this->mock(PlaybackCoordinator::class);
    $coordinator->shouldNotReceive('trackChanged', 'playbackEnded', 'tick');

    $this->provider->failNextWith(new ProviderTemporaryFailure);
    poll($this->party);
    $this->provider->failNextWith(new ProviderTemporaryFailure);
    poll($this->party);

    Queue::assertPushed(PollPlayback::class, fn (PollPlayback $job): bool => $job->delay === 10);
    Queue::assertPushed(PollPlayback::class, fn (PollPlayback $job): bool => $job->delay === 20);
    expect($this->party->fresh()->state)->toBe(PartyState::Live);
});

it('honours the retry-after of a rate limit and resets the back-off on success', function () {
    $this->provider->rateLimitNext(120);
    poll($this->party);
    assertRescheduledIn(120);

    poll($this->party);
    expect(Cache::get('playback.poll.'.$this->party->code.'.failures'))->toBeNull();
});

it('stops polling when the party is not live', function (PartyState $state) {
    $this->party->forceFill(['state' => $state])->save();
    $this->mock(PlaybackCoordinator::class)->shouldNotReceive('trackChanged');

    poll($this->party);

    Queue::assertNotPushed(PollPlayback::class);
    expect(Cache::has('playback.poll.'.$this->party->code.'.chain'))->toBeFalse();
})->with([PartyState::Paused, PartyState::Ended]);

it('stops polling when the party no longer uses the polling player', function () {
    $this->party->forceFill(['player_kind' => 'fake'])->save();
    app(PartyPlayers::class)->forget($this->party);

    poll($this->party);

    Queue::assertNotPushed(PollPlayback::class);
});

it('stops polling for a party that no longer exists', function () {
    $code = $this->party->code;
    $this->party->delete();

    app()->call([new PollPlayback($code), 'handle']);

    Queue::assertNotPushed(PollPlayback::class);
});

it('does not reschedule a check-now poll', function () {
    $this->provider->playbackIs(playing('t1'));
    $this->mock(PlaybackCoordinator::class)->shouldReceive('trackChanged')->once();

    poll($this->party, reschedule: false);

    Queue::assertNotPushed(PollPlayback::class);
});

it('dispatches a check-now job', function () {
    PollPlayback::checkNow($this->party->code);

    Queue::assertPushed(PollPlayback::class, fn (PollPlayback $job): bool => ! $job->reschedule && $job->partyCode === $this->party->code);
});

it('starts exactly one chain per party', function () {
    expect(PollPlayback::start($this->party))->toBeTrue()
        ->and(PollPlayback::start($this->party))->toBeFalse();

    Queue::assertPushed(PollPlayback::class, 1);
});

it('does not start a chain for a non-live party or another player', function () {
    $paused = Party::factory()->create(['player_kind' => 'polling']);
    $fake = Party::factory()->live()->create(['player_kind' => 'fake']);

    expect(PollPlayback::start($paused))->toBeFalse()
        ->and(PollPlayback::start($fake))->toBeFalse();
    Queue::assertNothingPushed();
});

it('starts a chain when a polling party goes live', function () {
    $party = Party::factory()->create(['player_kind' => 'polling']);
    app()->instance(FallbackPlaylistGate::class, new readonly class(app(PairingCatalogue::class), app(Blocklist::class), app(AuthorisesHost::class)) extends FallbackPlaylistGate
    {
        public function check(Party $party): FallbackPlaylistCheck
        {
            return new FallbackPlaylistCheck(self::REQUIRED_PLAYABLE_TRACKS, self::REQUIRED_PLAYABLE_TRACKS);
        }
    });

    app(GoLiveParty::class)($party->user, $party);

    Queue::assertPushed(PollPlayback::class, fn (PollPlayback $job): bool => $job->partyCode === $party->code);
});

it('starts a chain when a live party switches to the polling player', function () {
    $party = Party::factory()->live()->create(['player_kind' => 'fake']);
    config(['musicparty.music_providers.spotify' => ['label' => 'Spotify', 'class' => FakeMusicProvider::class]]);
    $party->forceFill(['music_provider' => 'spotify'])->save();
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider(id: 'spotify'));

    app(ChangePartyPlayer::class)($party->user, $party, 'polling');

    Queue::assertPushed(PollPlayback::class, fn (PollPlayback $job): bool => $job->partyCode === $party->code);
});

it('never overlaps polls for the same party', function () {
    $middleware = new PollPlayback($this->party->code)->middleware();
    $oneOff = new PollPlayback($this->party->code, false)->middleware();

    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe($this->party->code)
        ->and($middleware[0]->releaseAfter)->toBe(2)
        ->and($oneOff[0]->releaseAfter)->toBeNull();
});

it('re-arms the chain after an unexpected failure', function () {
    Log::spy();

    new PollPlayback($this->party->code)->failed(new RuntimeException('boom'));

    assertRescheduledIn(300);
});

it('drops a stale chain job without polling or rescheduling', function () {
    $this->provider->playbackIs(playing('t1'));
    Cache::put('playback.poll.'.$this->party->code.'.chain', 'current', 600);

    $stale = new PollPlayback($this->party->code, true, 'old');
    app()->call($stale->handle(...));
    $stale->failed(new RuntimeException('boom'));

    Queue::assertNothingPushed();
    expect(Cache::get('playback.poll.'.$this->party->code.'.chain'))->toBe('current')
        ->and(Play::count())->toBe(0);
});

it('keeps the chain token when rescheduling', function () {
    PollPlayback::start($this->party);
    $token = Cache::get('playback.poll.'.$this->party->code.'.chain');

    $this->provider->playbackIs(playing('t1'));
    app()->call([new PollPlayback($this->party->code, true, $token), 'handle']);

    Queue::assertPushed(PollPlayback::class, fn (PollPlayback $job): bool => $job->chainToken === $token && $job->delay !== null);
});
