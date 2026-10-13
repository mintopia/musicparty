<?php

use App\Events\Party\PlayerCommand;
use App\Jobs\ApplyPushedPlaybackState;
use App\Jobs\RequestEncoreState;
use App\Listeners\DispatchPushedPlaybackState;
use App\Models\Party;
use App\Models\User;
use App\Services\Music\Data\PlaybackConnectionStatus;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Playback\EncoreStateMapper;
use App\Services\Playback\PushedStateRouter;
use App\Services\PlaybackStatus;
use App\Services\PlaybackSync;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\Connection as ConnectionContract;
use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Events\MessageReceived;
use Ratchet\RFC6455\Messaging\Frame;

function soloistFrame(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/Soloist/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}

function ingestEncore(Party $party, string $name): void
{
    $frame = soloistFrame($name);
    (new ApplyPushedPlaybackState("private-encore.{$party->code}", "client-{$frame['type']}", $frame))
        ->handle(app(PushedStateRouter::class), app(PlaybackSync::class));
}

function encoreParty(): Party
{
    return Party::factory()->create(['provider_id' => 'fake', 'active' => false]);
}

it('maps a playing frame to PlaybackState via the provider track reference', function () {
    $party = encoreParty();

    $state = app(EncoreStateMapper::class)->map($party, 'client-playback_state', soloistFrame('playback_state_playing'));

    expect($state->source)->toBe(PlaybackSource::Encore)
        ->and($state->isPlaying)->toBeTrue()
        ->and($state->trackId)->toBe('t1')
        ->and($state->durationMs)->toBe(180000)
        ->and($state->progressMs)->toBe(1500)
        ->and($state->sampledAt->getTimestampMs())->toBe(1767268800000)
        ->and($state->connectionStatus)->toBe(PlaybackConnectionStatus::Connected);
});

it('maps an idle frame to a stopped state', function () {
    $state = app(EncoreStateMapper::class)->map(encoreParty(), 'client-playback_state', soloistFrame('playback_state_idle'));

    expect($state->isPlaying)->toBeFalse()->and($state->trackId)->toBeNull();
});

it('applies recorded frames through PlaybackSync without polling', function () {
    $party = encoreParty();
    $provider = app(FakeMusicProvider::class);

    ingestEncore($party, 'playback_state_playing');
    ingestEncore($party, 'queue_changed');
    ingestEncore($party, 'position_sync');

    $recall = app(PlaybackStatus::class)->recall($party->fresh());
    expect($recall->upcomingTrackIds)->toBe(['t2', 't3'])
        ->and($recall->progressMs)->toBe(42000)
        ->and($recall->trackId)->toBe('t1')
        ->and($party->fresh()->playback_source)->toBe(PlaybackSource::Encore)
        ->and($provider->callsTo('currentPlayback'))->toBe([]);
});

it('marks connection status from auth_state and error frames', function (string $frame, PlaybackConnectionStatus $expected) {
    $state = app(EncoreStateMapper::class)->map(encoreParty(), 'client-x', soloistFrame($frame));

    expect($state->connectionStatus)->toBe($expected);
})->with([
    'logged out' => ['auth_state_logged_out', PlaybackConnectionStatus::LoggedOut],
    'error' => ['error', PlaybackConnectionStatus::Error],
]);

it('drops frames with unrecognised track references or unknown types', function () {
    $mapper = app(EncoreStateMapper::class);

    expect($mapper->map(encoreParty(), 'client-track_changed', soloistFrame('malformed_item')))->toBeNull()
        ->and($mapper->map(encoreParty(), 'client-device_list', ['type' => 'device_list']))->toBeNull();
});

it('refuses user sessions on the Encore channel', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
    app('Illuminate\Broadcasting\BroadcastManager')->purge();
    require base_path('routes/channels.php');
    $this->actingAs(User::factory()->create())
        ->postJson('/broadcasting/auth', ['channel_name' => 'private-encore.ABC', 'socket_id' => '1.1'])
        ->assertForbidden();
});

function subscribe(string $channel, ?string $auth = null): void
{
    $connection = new class(Mockery::mock(WebSocketConnection::class), new Application('1', 'key', 's3cret', 60, 30, ['*'], 10000), null) extends ConnectionContract
    {
        public function identifier(): string
        {
            return '123.456';
        }

        public function id(): string
        {
            return '123.456';
        }

        public function send(string $message): void {}

        public function control(string $type = Frame::OP_PING): void {}

        public function terminate(): void {}
    };
    $auth ??= 'key:'.hash_hmac('sha256', "123.456:{$channel}", 's3cret');

    $event = new MessageReceived($connection, json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => $channel, 'auth' => $auth]]));
    (new DispatchPushedPlaybackState)->handle($event);
}

it('queues a snapshot request when Encore subscribes to its channel', function () {
    Queue::fake();

    subscribe('private-encore.ABC');
    subscribe('private-webplayer.ABC');
    subscribe('private-encore.');
    subscribe('private-encore.EVIL', 'key:forged');

    Queue::assertPushed(RequestEncoreState::class, 1);
    Queue::assertPushed(RequestEncoreState::class, fn ($job) => $job->code === 'ABC');
});

it('broadcasts get_state on the Encore channel', function () {
    Event::fake([PlayerCommand::class]);
    $party = encoreParty();

    (new RequestEncoreState($party->code))->handle();
    (new RequestEncoreState('NOPE'))->handle();

    Event::assertDispatchedTimes(PlayerCommand::class, 1);
    Event::assertDispatched(PlayerCommand::class, fn ($e) => $e->command === ['type' => 'get_state'] && $e->broadcastWith() === ['type' => 'command', 'command' => 'get_state'] && $e->broadcastOn()->name === "private-encore.{$party->code}");
});

it('wraps every Encore command in the Soloist command shape', function (array $command, array $expected) {
    $event = new PlayerCommand(encoreParty(), PlaybackSource::Encore, $command);

    expect($event->broadcastAs())->toBe('command')->and($event->broadcastWith())->toBe($expected);
})->with([
    'play' => [['type' => 'play'], ['type' => 'command', 'command' => 'play']],
    'skip_prev' => [['type' => 'skip_prev'], ['type' => 'command', 'command' => 'skip_prev']],
    'seek' => [['type' => 'seek', 'position_ms' => 4200], ['type' => 'command', 'command' => 'seek', 'position_ms' => 4200]],
]);

it('leaves Web Player commands unchanged', function () {
    $event = new PlayerCommand(encoreParty(), PlaybackSource::WebPlayer, ['type' => 'pause']);

    expect($event->broadcastAs())->toBe('pause')->and($event->broadcastWith())->toBe(['type' => 'pause']);
});
