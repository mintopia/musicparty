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
use Laravel\Reverb\Events\MessageReceived;

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

function receive(array|string $frame): void
{
    $message = is_array($frame) ? json_encode($frame) : $frame;
    $event = (new ReflectionClass(MessageReceived::class))->newInstanceWithoutConstructor();
    $event->message = $message;
    (new DispatchPushedPlaybackState)->handle($event);
}

function runIngest(?PushedStateMapper $mapper, string $channel, mixed $data, string $event = 'client-playback_state'): void
{
    app()->instance(PushedStateRouter::class, new PushedStateRouter($mapper ? [$mapper] : []));
    (new ApplyPushedPlaybackState($channel, $event, $data))->handle(app(PushedStateRouter::class), app(PlaybackSync::class));
}

it('dispatches a queued job for client events with only channel, event and data', function () {
    Queue::fake();

    receive(['event' => 'client-playback_state', 'channel' => 'private-encore.ABC', 'data' => '{"a":1}']);

    Queue::assertPushed(ApplyPushedPlaybackState::class, fn ($job) => $job->channel === 'private-encore.ABC'
        && $job->event === 'client-playback_state'
        && $job->data === '{"a":1}'
        && $job->queue === 'partyupdates');
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
