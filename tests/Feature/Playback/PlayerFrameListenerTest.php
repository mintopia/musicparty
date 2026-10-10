<?php

use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Testing\FakePlayer;
use App\Events\Player\PlayerCommandEvent;
use App\Jobs\ProcessPlayerFrame;
use App\Listeners\HandlePlayerClientEvent;
use App\Models\Party;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Ratchet\RFC6455\Messaging\Frame;
use Tests\Fixtures\Playback\FrameHandlingPlayer;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('player-frames:ABC123');
    config(['musicparty.player_frames.max_bytes' => 8192, 'musicparty.player_frames.max_per_minute' => 3]);
    Queue::fake();
});

function stubReverbConnection(): Connection
{
    return new class(Mockery::mock(WebSocketConnection::class), Mockery::mock(Application::class), null) extends Connection
    {
        public function identifier(): string
        {
            return 'stub';
        }

        public function id(): string
        {
            return 'stub';
        }

        public function send(string $message): void {}

        public function control(string $type = Frame::OP_PING): void {}

        public function terminate(): void {}
    };
}

function receive(string $message, bool $subscribed = true): void
{
    $connection = stubReverbConnection();
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('find')->with($connection)->andReturn($subscribed ? Mockery::mock(ChannelConnection::class) : null);
    $manager = Mockery::mock(ChannelManager::class);
    $manager->shouldReceive('find')->andReturn($subscribed ? $channel : null);
    app()->instance(ChannelManager::class, $manager);

    app(HandlePlayerClientEvent::class)->handle(new MessageReceived($connection, $message));
}

function clientFrame(array $overrides = []): string
{
    return json_encode($overrides + ['event' => 'client-state', 'channel' => 'private-player.abc123', 'data' => ['status' => 'playing']]);
}

function discarded(): int
{
    return (int) Cache::get(ProcessPlayerFrame::DISCARDED_COUNTER, 0);
}

it('dispatches a frame job for a client event on the player channel', function () {
    receive(clientFrame());

    Queue::assertPushed(ProcessPlayerFrame::class, fn (ProcessPlayerFrame $job) => $job->partyCode === 'ABC123' && $job->frame === ['status' => 'playing']);
    expect(discarded())->toBe(0);
});

it('ignores frames from a connection that is not subscribed to the player channel', function () {
    receive(clientFrame(), subscribed: false);

    Queue::assertNothingPushed();
});

it('ignores frames for a channel the server does not know', function () {
    $manager = Mockery::mock(ChannelManager::class);
    $manager->shouldReceive('find')->andReturn(null);
    app()->instance(ChannelManager::class, $manager);

    app(HandlePlayerClientEvent::class)->handle(new MessageReceived(stubReverbConnection(), clientFrame()));

    Queue::assertNothingPushed();
});

it('ignores messages that are not player client events', function (string $message) {
    receive($message);

    Queue::assertNothingPushed();
    expect(discarded())->toBe(0);
})->with([
    'moderators channel' => [fn () => clientFrame(['channel' => 'private-party.X.moderators'])],
    'presence channel' => [fn () => clientFrame(['channel' => 'presence-player.ABC123'])],
    'public channel' => [fn () => clientFrame(['channel' => 'player.ABC123'])],
    'server event' => [fn () => clientFrame(['event' => 'pusher:ping'])],
    'non string event' => [fn () => clientFrame(['event' => 5])],
    'invalid json' => ['{not json'],
    'json scalar' => ['"hello"'],
    'missing channel' => [json_encode(['event' => 'client-x', 'data' => ['a' => 1]])],
]);

it('discards and counts unusable frames', function (string $message) {
    receive($message);

    Queue::assertNothingPushed();
    expect(discarded())->toBe(1);
})->with([
    'oversize' => [fn () => clientFrame(['data' => ['blob' => str_repeat('x', 9000)]])],
    'empty data' => [fn () => clientFrame(['data' => []])],
    'scalar data' => [fn () => clientFrame(['data' => 'text'])],
    'missing data' => [fn () => json_encode(['event' => 'client-x', 'channel' => 'private-player.ABC123'])],
]);

it('discards frames over the per party rate limit', function () {
    foreach (range(1, 5) as $i) {
        receive(clientFrame());
    }

    Queue::assertPushed(ProcessPlayerFrame::class, 3);
    expect(discarded())->toBe(2);
});

it('rate limits each party separately', function () {
    foreach (range(1, 4) as $i) {
        receive(clientFrame());
    }
    receive(clientFrame(['channel' => 'private-player.OTHER1']));

    Queue::assertPushed(ProcessPlayerFrame::class, 4);
    RateLimiter::clear('player-frames:OTHER1');
});

describe('ProcessPlayerFrame', function () {
    beforeEach(function () {
        $this->party = Party::factory()->create(['player_kind' => 'fake', 'music_provider' => 'fake']);
        $this->player = new FrameHandlingPlayer;
        app(PartyPlayers::class)->register($this->party, $this->player);
    });

    it('hands the frame to the player', function () {
        new ProcessPlayerFrame($this->party->code, ['status' => 'playing'])->handle(app(PartyPlayers::class));

        expect($this->player->frames)->toBe([['status' => 'playing']])
            ->and(discarded())->toBe(0);
    });

    it('counts a discard when the player reports the frame malformed', function () {
        new ProcessPlayerFrame($this->party->code, ['malformed' => true])->handle(app(PartyPlayers::class));

        expect(discarded())->toBe(1);
    });

    it('counts a discard for an unknown party', function () {
        new ProcessPlayerFrame('NOSUCH', ['a' => 1])->handle(app(PartyPlayers::class));

        expect(discarded())->toBe(1)->and($this->player->frames)->toBe([]);
    });

    it('counts a discard when the player cannot handle frames', function () {
        app(PartyPlayers::class)->register($this->party, new FakePlayer);

        new ProcessPlayerFrame($this->party->code, ['a' => 1])->handle(app(PartyPlayers::class));

        expect(discarded())->toBe(1);
    });

    it('processes a frame while holding the party lock so frames apply one at a time', function () {
        $heldDuringFrame = null;
        $this->player->onFrame = function () use (&$heldDuringFrame) {
            $heldDuringFrame = Cache::lock('player-frame:'.$this->party->code, 5)->get() === false;
        };

        new ProcessPlayerFrame($this->party->code, ['status' => 'playing'])->handle(app(PartyPlayers::class));

        expect($heldDuringFrame)->toBeTrue()
            ->and(Cache::lock('player-frame:'.$this->party->code, 5)->get())->toBeTrue();
    });

    it('waits for a frame in flight and applies after it, never before', function () {
        $lock = Cache::lock('player-frame:'.$this->party->code, 5);
        $lock->get();

        $job = new ProcessPlayerFrame($this->party->code, ['type' => 'position_sync']);
        $job->lockWaitSeconds = 0;
        $job->handle(app(PartyPlayers::class));

        expect($this->player->frames)->toBe([]);

        $lock->release();
        $job->handle(app(PartyPlayers::class));

        expect($this->player->frames)->toBe([['type' => 'position_sync']]);
    });

    it('applies a stale position_sync before a newer track_changed, not after', function () {
        $handle = fn (array $frame) => (new ProcessPlayerFrame($this->party->code, $frame))->handle(app(PartyPlayers::class));

        $handle(['type' => 'position_sync']);
        $handle(['type' => 'track_changed']);

        expect(array_column($this->player->frames, 'type'))->toBe(['position_sync', 'track_changed']);
    });

    it('queues on player', function () {
        expect((new ProcessPlayerFrame('ABC123', ['a' => 1]))->queue)->toBe('player');
    });
});

it('broadcasts player commands on the private player channel', function () {
    $event = new PlayerCommandEvent('ABC123', ['cmd' => 'pause']);

    expect($event->broadcastOn())->toHaveCount(1)
        ->and($event->broadcastOn()[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($event->broadcastOn()[0]->name)->toBe('private-player.ABC123')
        ->and($event->broadcastAs())->toBe('player.command')
        ->and($event->broadcastWith())->toBe(['cmd' => 'pause']);
});
