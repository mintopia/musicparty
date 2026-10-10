<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Playback\Actions\PairPlayer;
use App\Domain\Playback\Control;
use App\Domain\Playback\Exceptions\IncompatibleProviderException;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\Jobs\CheckSoloistHealth;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Playback\PlayerHealth;
use App\Domain\Playback\Players\SoloistPlayer;
use App\Domain\Queue\RequestStatus;
use App\Events\Player\PlayerCommandEvent;
use App\Jobs\BroadcastPartyQueue;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\TrackRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');
    Cache::flush();
    Bus::fake([BroadcastPartyQueue::class]);
    Event::fake([PlayerCommandEvent::class]);
    config(['musicparty.soloist.stale_after_seconds' => 30, 'musicparty.soloist.disconnect_after_seconds' => 90]);
    $this->party = livePlaybackParty([], ['player_kind' => 'soloist', 'music_provider' => 'fake']);
    $this->player = app(PartyPlayers::class)->for($this->party);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function soloistFrame(string $name): array
{
    return json_decode((string) file_get_contents(base_path("tests/Fixtures/Playback/Soloist/{$name}.json")), true, 512, JSON_THROW_ON_ERROR);
}

function soloistFrames(): array
{
    return Event::dispatched(PlayerCommandEvent::class)->map(fn (array $args): array => $args[0]->broadcastWith())->values()->all();
}

function soloistCommands(string $command): array
{
    return array_values(array_filter(soloistFrames(), fn (array $frame): bool => ($frame['command'] ?? null) === $command));
}

function soloistLog(Party $party, string $action): int
{
    return PartyLogEntry::query()->where('party_id', $party->id)->where('action', $action)->count();
}

function soloistRequest(Party $party, string $trackId, RequestStatus $status, array $attributes = []): TrackRequest
{
    return TrackRequest::factory()->for($party)->create(['provider_track_id' => $trackId, 'status' => $status, 'duration_ms' => 180000, ...$attributes]);
}

it('is a just-in-time Spotify player with every control and no Host account', function () {
    expect($this->player)->toBeInstanceOf(SoloistPlayer::class)
        ->and($this->player->kind())->toBe('soloist')
        ->and($this->player->feedMode())->toBe(FeedMode::JustInTime)
        ->and($this->player->requiresHostAccount())->toBeFalse()
        ->and($this->player->compatibleProviders())->toBe(['spotify'])
        ->and($this->player->state()->status)->toBe(PlaybackStatus::Stopped)
        ->and(array_map(fn (Control $control): bool => $this->player->supports($control), Control::cases()))->each->toBeTrue();
});

it('is offered by the pairing catalogue', function () {
    expect(collect(app(PairingCatalogue::class)->players())->firstWhere('kind', 'soloist'))
        ->toMatchArray(['compatibleProviders' => ['spotify']]);
});

it('requests state and logs once on connect, then requests state again on reconnect without logging', function () {
    $this->player->markConnected();
    $this->player->markConnected();

    expect($this->player->health())->toBe(PlayerHealth::Connected)
        ->and(soloistCommands('get_state'))->toHaveCount(2)
        ->and(soloistLog($this->party, 'player.connected'))->toBe(1);
});

it('treats the first valid frame as a connection', function () {
    expect($this->player->handleFrame(soloistFrame('volume_changed')))->toBeTrue()
        ->and($this->player->health())->toBe(PlayerHealth::Connected)
        ->and(soloistCommands('get_state'))->toHaveCount(1)
        ->and(soloistLog($this->party, 'player.connected'))->toBe(1);
});

it('advances the queue from playback_state and records the cached state', function () {
    $upNext = soloistRequest($this->party, 't1', RequestStatus::UpNext);

    expect($this->player->handleFrame(soloistFrame('playback_state_playing')))->toBeTrue();

    $state = app(PartyPlayers::class)->for($this->party)->state();
    expect($upNext->fresh()->status)->toBe(RequestStatus::Playing)
        ->and($state->status)->toBe(PlaybackStatus::Playing)
        ->and($state->currentTrack->providerTrackId)->toBe('t1')
        ->and($state->currentTrack->providerId)->toBe('spotify')
        ->and($state->positionMs)->toBe(1500)
        ->and($state->durationMs)->toBe(180000)
        ->and(soloistCommands('pause'))->toBeEmpty();
});

it('advances through track_changed and finishes the previous track', function () {
    soloistRequest($this->party, 't1', RequestStatus::UpNext);
    soloistRequest($this->party, 't2', RequestStatus::Queued);
    $this->player->handleFrame(soloistFrame('track_changed_t1'));
    $this->player->enqueue('spotify', 't2');
    TrackRequest::query()->where('provider_track_id', 't2')->update(['status' => RequestStatus::UpNext]);

    $this->player->handleFrame(soloistFrame('track_changed_t2'));

    expect(TrackRequest::query()->where('provider_track_id', 't1')->value('status'))->toBe(RequestStatus::Played)
        ->and(TrackRequest::query()->where('provider_track_id', 't2')->value('status'))->toBe(RequestStatus::Playing)
        ->and($this->player->state()->currentTrack->providerTrackId)->toBe('t2');
});

it('applies simple state frames', function (string $fixture, PlaybackStatus $status, int $positionMs) {
    soloistRequest($this->party, 't1', RequestStatus::UpNext);
    $this->player->handleFrame(soloistFrame('playback_state_playing'));

    expect($this->player->handleFrame(soloistFrame($fixture)))->toBeTrue()
        ->and($this->player->state()->status)->toBe($status)
        ->and($this->player->state()->positionMs)->toBe($positionMs)
        ->and($this->player->state()->currentTrack->providerTrackId)->toBe('t1');
})->with([
    'paused playback_state' => ['playback_state_paused', PlaybackStatus::Paused, 9000],
    'playback_changed paused' => ['playback_changed_paused', PlaybackStatus::Paused, 1500],
    'position_sync' => ['position_sync', PlaybackStatus::Playing, 42000],
    'volume_changed' => ['volume_changed', PlaybackStatus::Playing, 1500],
]);

it('ends playback when the Soloist goes idle with no item', function (string $fixture) {
    $request = soloistRequest($this->party, 't1', RequestStatus::UpNext);
    $this->player->handleFrame(soloistFrame('playback_state_playing'));

    $this->player->handleFrame(soloistFrame($fixture));
    $this->player->handleFrame(soloistFrame($fixture));

    expect($request->fresh()->status)->toBe(RequestStatus::Played)
        ->and($this->player->state()->status)->toBe(PlaybackStatus::Stopped)
        ->and($this->player->state()->currentTrack)->toBeNull();
})->with(['playback_state_idle', 'playback_changed_idle']);

it('does not end playback for an idle state when nothing was playing', function () {
    $this->player->handleFrame(soloistFrame('playback_state_idle'));

    expect(soloistLog($this->party, 'player.stopped'))->toBe(0)
        ->and($this->player->state()->status)->toBe(PlaybackStatus::Stopped);
});

it('discards malformed frames and ignores unknown types', function (array $frame, bool $valid) {
    expect($this->player->handleFrame($frame))->toBe($valid);
})->with([
    'missing type' => [fn () => soloistFrame('missing_type'), false],
    'non-string type' => [['type' => 5], false],
    'bad item' => [fn () => soloistFrame('malformed_item'), false],
    'bad status' => [fn () => soloistFrame('malformed_status'), false],
    'bad volume' => [['type' => 'volume_changed', 'volume' => 'loud'], false],
    'bad position' => [['type' => 'position_sync', 'position' => ['position_ms' => 'x']], false],
    'bad queue' => [['type' => 'queue_changed', 'upcoming' => 'nope'], false],
    'bad queue entry' => [['type' => 'queue_changed', 'upcoming' => [['uid' => 'u', 'item' => []]]], false],
    'unknown type' => [fn () => soloistFrame('unknown_type'), true],
]);

it('leaves state untouched for malformed frames', function () {
    $this->player->handleFrame(soloistFrame('volume_changed'));
    $before = $this->player->state();

    $this->player->handleFrame(soloistFrame('malformed_item'));

    expect($this->player->state())->toEqual($before);
});

it('pauses and logs once when the Soloist moves to a track we did not queue', function () {
    soloistRequest($this->party, 't1', RequestStatus::Playing, ['started_at' => now()]);

    $this->player->handleFrame(soloistFrame('track_changed_autoplay'));

    expect($this->player->state()->status)->toBe(PlaybackStatus::Paused);

    $this->player->handleFrame(soloistFrame('track_changed_autoplay'));
    $this->player->handleFrame(soloistFrame('playback_state_autoplay_track'));

    expect(soloistCommands('pause'))->toHaveCount(1)
        ->and(soloistLog($this->party, 'player.autoplay_detected'))->toBe(1);
});

it('logs autoplay and context entries in the upcoming queue once per uid and ignores ours', function () {
    $this->player->handleFrame(soloistFrame('queue_changed_ours'));
    $this->player->handleFrame(soloistFrame('queue_changed_autoplay'));
    $this->player->handleFrame(soloistFrame('queue_changed_autoplay'));

    expect(soloistLog($this->party, 'player.autoplay_detected'))->toBe(2)
        ->and(soloistCommands('pause'))->toBeEmpty();
});

it('logs an error frame without failing', function () {
    expect($this->player->handleFrame(soloistFrame('error')))->toBeTrue()
        ->and(soloistLog($this->party, 'player.error'))->toBe(1);
});

it('hands the next track over just in time through the coordinator tick', function () {
    $this->player->markConnected();
    soloistRequest($this->party, 't1', RequestStatus::Playing, ['started_at' => now()->subSeconds(100)]);
    soloistRequest($this->party, 't2', RequestStatus::Queued);
    $this->player->handleFrame(soloistFrame('playback_state_playing'));

    app(PlaybackCoordinator::class)->tick($this->party);
    expect(soloistCommands('add_to_queue'))->toBeEmpty();

    CarbonImmutable::setTestNow(now()->addSeconds(70));
    app(PlaybackCoordinator::class)->tick($this->party);
    app(PlaybackCoordinator::class)->tick($this->party);

    expect(soloistCommands('add_to_queue'))->toBe([['type' => 'command', 'command' => 'add_to_queue', 'uri' => 'spotify:track:t2']]);
});

it('does not resend a queued track after reconnect while it is still upcoming or current', function () {
    $this->player->markConnected();
    $this->player->enqueue('spotify', 't2');
    $this->player->handleFrame(soloistFrame('queue_changed_ours'));

    $this->player->markDisconnected('socket_closed');
    $this->player->handleFrame(soloistFrame('queue_changed_ours'));
    $this->player->enqueue('spotify', 't2');

    expect(soloistCommands('add_to_queue'))->toHaveCount(1)
        ->and(soloistCommands('get_state'))->toHaveCount(2);

    $this->player->handleFrame(soloistFrame('queue_changed_empty'));
    $this->player->enqueue('spotify', 't2');

    expect(soloistCommands('add_to_queue'))->toHaveCount(2);
});

it('sends control commands as Soloist frames', function () {
    $this->player->markConnected();

    $this->player->play();
    $this->player->pause();
    $this->player->skip();
    $this->player->seek(12000);
    $this->player->volume(40);

    expect(array_slice(soloistFrames(), 1))->toBe([
        ['type' => 'command', 'command' => 'play'],
        ['type' => 'command', 'command' => 'pause'],
        ['type' => 'command', 'command' => 'next'],
        ['type' => 'command', 'command' => 'seek', 'position_ms' => 12000],
        ['type' => 'command', 'command' => 'set_volume', 'volume' => 40],
    ]);
});

it('refuses to send anything while disconnected', function (Closure $call) {
    expect(fn () => $call($this->player))->toThrow(PlayerDisconnectedException::class)
        ->and(soloistFrames())->toBeEmpty();
})->with([
    'enqueue' => [fn (SoloistPlayer $p) => $p->enqueue('spotify', 't1')],
    'play' => [fn (SoloistPlayer $p) => $p->play()],
    'pause' => [fn (SoloistPlayer $p) => $p->pause()],
    'skip' => [fn (SoloistPlayer $p) => $p->skip()],
    'seek' => [fn (SoloistPlayer $p) => $p->seek(1)],
    'volume' => [fn (SoloistPlayer $p) => $p->volume(1)],
]);

it('marks a silent playing Soloist stale once, asks for state, and recovers on the next frame', function () {
    soloistRequest($this->party, 't1', RequestStatus::Playing, ['started_at' => now()]);
    $this->player->handleFrame(soloistFrame('playback_state_playing'));
    $this->player->handleFrame(soloistFrame('playback_state_playing'));
    $before = count(soloistCommands('get_state'));

    CarbonImmutable::setTestNow(now()->addSeconds(31));
    $this->player->checkHealth();
    $this->player->checkHealth();

    expect($this->player->health())->toBe(PlayerHealth::Stale)
        ->and(soloistCommands('get_state'))->toHaveCount($before + 1)
        ->and(soloistLog($this->party, 'player.stale'))->toBe(1);

    $this->player->handleFrame(soloistFrame('position_sync'));

    expect($this->player->health())->toBe(PlayerHealth::Connected)
        ->and(soloistLog($this->party, 'player.connected'))->toBe(2);
});

it('does not treat a long pause or idle as a disconnection', function (string $fixture) {
    $this->player->handleFrame(soloistFrame($fixture));

    CarbonImmutable::setTestNow(now()->addHours(3));
    $this->player->checkHealth();

    expect($this->player->health())->toBe(PlayerHealth::Connected)
        ->and(soloistLog($this->party, 'player.stale'))->toBe(0)
        ->and(soloistLog($this->party, 'player.disconnected'))->toBe(0);
})->with(['playback_state_paused', 'playback_state_idle']);

it('goes idle and logs once when a stale Soloist stays silent', function () {
    soloistRequest($this->party, 't1', RequestStatus::Playing, ['started_at' => now()]);
    $this->player->handleFrame(soloistFrame('playback_state_playing'));
    CarbonImmutable::setTestNow(now()->addSeconds(31));
    $this->player->checkHealth();
    CarbonImmutable::setTestNow(now()->addSeconds(60));
    $this->player->checkHealth();
    $this->player->checkHealth();

    expect($this->player->health())->toBe(PlayerHealth::Disconnected)
        ->and($this->player->state()->status)->toBe(PlaybackStatus::Stopped)
        ->and($this->player->state()->currentTrack)->toBeNull()
        ->and(soloistLog($this->party, 'player.disconnected'))->toBe(1)
        ->and(fn () => $this->player->play())->toThrow(PlayerDisconnectedException::class);
});

it('requests state when a frame arrives after a disconnection', function () {
    $this->player->markConnected();
    $this->player->markDisconnected('socket_closed');
    $this->player->markDisconnected('socket_closed');

    $this->player->handleFrame(soloistFrame('volume_changed'));

    expect($this->player->health())->toBe(PlayerHealth::Connected)
        ->and(soloistLog($this->party, 'player.disconnected'))->toBe(1)
        ->and(soloistLog($this->party, 'player.connected'))->toBe(2)
        ->and(soloistCommands('get_state'))->toHaveCount(2);
});

it('writes one log entry per health change when the connection flaps', function () {
    soloistRequest($this->party, 't1', RequestStatus::Playing, ['started_at' => now()]);
    $this->player->handleFrame(soloistFrame('playback_state_playing'));

    foreach (range(1, 3) as $ignored) {
        CarbonImmutable::setTestNow(now()->addSeconds(31));
        $this->player->checkHealth();
        $this->player->checkHealth();
        $this->player->handleFrame(soloistFrame('playback_state_playing'));
        $this->player->handleFrame(soloistFrame('playback_state_playing'));
    }

    expect(soloistLog($this->party, 'player.stale'))->toBe(3)
        ->and(soloistLog($this->party, 'player.connected'))->toBe(4);
});

it('checks every live Soloist party from the health job and tolerates other players', function () {
    soloistRequest($this->party, 't1', RequestStatus::Playing, ['started_at' => now()]);
    $this->player->handleFrame(soloistFrame('playback_state_playing'));
    livePlaybackParty([], ['player_kind' => 'polling', 'music_provider' => 'fake']);
    CarbonImmutable::setTestNow(now()->addSeconds(31));

    app(CheckSoloistHealth::class)->handle(app(PartyPlayers::class));

    expect($this->player->health())->toBe(PlayerHealth::Stale);
});

it('pairs only with Spotify', function () {
    expect(fn () => (new PairPlayer)($this->player, new FakeMusicProvider))->toThrow(IncompatibleProviderException::class);
    (new PairPlayer)($this->player, new FakeMusicProvider(id: 'spotify'));
});
