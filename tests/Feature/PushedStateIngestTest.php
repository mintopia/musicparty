<?php

use App\Jobs\ApplyPushedPlaybackState;
use App\Listeners\DispatchPushedPlaybackState;
use App\Models\Party;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Playback\PushedStateMapper;
use App\Services\Playback\PushedStateRouter;
use App\Services\PlaybackSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelManager;
use Ratchet\RFC6455\Messaging\Frame;

function stateMapper(?PlaybackState $result): PushedStateMapper
{
    return new class($result) implements PushedStateMapper
    {
        public ?array $seen = null;

        public function __construct(private ?PlaybackState $result) {}

        public function channelPrefix(): string
        {
            return 'private-encore.';
        }

        public function map(Party $party, string $event, array $data): ?PlaybackState
        {
            $this->seen = [$party->code, $event, $data];

            return $this->result;
        }
    };
}

function ingestConnection(string $id = '123.456'): Connection
{
    return new class(Mockery::mock(WebSocketConnection::class), new Application('1', 'key', 's3cret', 60, 30, ['*'], 10000), null, $id) extends Connection
    {
        public function __construct(WebSocketConnection $connection, Application $application, ?string $origin, private string $socketId)
        {
            parent::__construct($connection, $application, $origin);
        }

        public function identifier(): string
        {
            return $this->socketId;
        }

        public function id(): string
        {
            return $this->socketId;
        }

        public function send(string $message): void {}

        public function control(string $type = Frame::OP_PING): void {}

        public function terminate(): void {}
    };
}

function channelManager(): ChannelManager
{
    app()->singletonIf(ChannelManager::class, fn () => new ArrayChannelManager);
    app()->bindIf(ChannelConnectionManager::class, fn () => new ArrayChannelConnectionManager);

    return app(ChannelManager::class);
}

function subscribeConnection(Connection $connection, string $channel): void
{
    channelManager()->for($connection->app())->findOrCreate($channel)->subscribe($connection, 'key:'.hash_hmac('sha256', "{$connection->id()}:{$channel}", 's3cret'));
}

function receive(array|string $frame, ?Connection $connection = null): void
{
    $message = is_array($frame) ? json_encode($frame) : $frame;
    channelManager();
    (new DispatchPushedPlaybackState)->handle(new MessageReceived($connection ?? ingestConnection(), $message));
}

function runIngest(?PushedStateMapper $mapper, string $channel, mixed $data, string $event = 'client-playback_state'): void
{
    app()->instance(PushedStateRouter::class, new PushedStateRouter($mapper ? [$mapper] : []));
    new ApplyPushedPlaybackState($channel, $event, $data)->handle(app(PushedStateRouter::class), app(PlaybackSync::class));
}

it('dispatches a queued job for client events with only channel, event and data', function () {
    Queue::fake();

    $connection = ingestConnection();
    subscribeConnection($connection, 'private-encore.ABC');

    receive(['event' => 'client-playback_state', 'channel' => 'private-encore.ABC', 'data' => '{"a":1}'], $connection);

    Queue::assertPushed(ApplyPushedPlaybackState::class, fn ($job) => $job->channel === 'private-encore.ABC'
        && $job->event === 'client-playback_state'
        && $job->data === '{"a":1}'
        && $job->queue === 'partyupdates');
});

it('drops client events from a connection that is not subscribed to the channel', function () {
    Queue::fake();
    Log::spy();
    $member = ingestConnection('111.111');
    subscribeConnection($member, 'private-encore.ABC');
    $outsider = ingestConnection('222.222');

    receive(['event' => 'client-playback_state', 'channel' => 'private-encore.ABC', 'data' => '{"secret":1}'], $outsider);
    receive(['event' => 'client-playback_state', 'channel' => 'private-encore.NOPE', 'data' => '{"secret":1}'], $outsider);

    Queue::assertNothingPushed();
    Log::shouldHaveReceived('debug')->twice()->withArgs(fn (string $message, array $context) => $context === ['connection' => '222.222', 'channel' => 'private-encore.ABC']
        || $context === ['connection' => '222.222', 'channel' => 'private-encore.NOPE']);
});

it('ignores pusher protocol events and unparseable frames', function () {
    Queue::fake();

    receive(['event' => 'pusher:ping', 'data' => []]);
    receive(['event' => 'client-x']);
    receive('not json');

    Queue::assertNothingPushed();
});

it('routes a frame by channel to the Party and applies the mapped state', function () {
    $party = Party::factory()->create(['provider_id' => 'fake', 'active' => false]);
    $state = new PlaybackState(false, null, null, null, 0, CarbonImmutable::now(), false, 'off', null, null, PlaybackSource::Encore);
    $mapper = stateMapper($state);

    runIngest($mapper, "private-encore.{$party->code}", '{"item":{"uri":"u"}}');

    expect($mapper->seen)->toBe([$party->code, 'client-playback_state', ['item' => ['uri' => 'u']]])
        ->and($party->fresh()->playback_source)->toBe(PlaybackSource::Encore);
});

it('drops and debug-logs a frame on an unknown channel', function () {
    Log::spy();

    runIngest(stateMapper(null), 'private-other.ABC', []);

    Log::shouldHaveReceived('debug')->once()->with(Mockery::pattern('/unknown channel/'));
});

it('drops and debug-logs a frame for an unknown Party', function () {
    Log::spy();
    $mapper = stateMapper(null);

    runIngest($mapper, 'private-encore.NOPE', []);

    Log::shouldHaveReceived('debug')->once()->with(Mockery::pattern('/unknown Party/'));
    expect($mapper->seen)->toBeNull();
});

it('drops and debug-logs malformed data without calling the mapper', function (mixed $data) {
    Log::spy();
    $party = Party::factory()->create();
    $mapper = stateMapper(null);

    runIngest($mapper, "private-encore.{$party->code}", $data);

    Log::shouldHaveReceived('debug')->with(Mockery::pattern('/malformed data/'));
    expect($mapper->seen)->toBeNull();
})->with(['invalid json' => '{oops', 'scalar json' => '"x"', 'null' => null]);

it('drops a frame the mapper cannot turn into state', function () {
    Log::spy();
    $party = Party::factory()->create();

    runIngest(stateMapper(null), "private-encore.{$party->code}", []);

    Log::shouldHaveReceived('debug')->with(Mockery::pattern('/no playback state/'));
    expect($party->fresh()->playback_source)->toBeNull();
});
