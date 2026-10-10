<?php

use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Playback\Broadcast\PlayerCommandEvent;
use App\Domain\Playback\Jobs\ProcessPlayerFrame;
use App\Domain\Playback\Listeners\HandlePlayerClientEvent;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Testing\FakePlayer;
use App\Support\Metrics\CounterStore;
use App\Support\Realtime\PlayerConnections;
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
    $this->party = Party::factory()->create(['code' => 'ABC123', 'music_provider' => 'fake', 'player_kind' => 'fake']);
    $this->token = $this->party->createToken('Stage', ['player:connect'])->accessToken;
    app(PlayerConnections::class)->register('stub', 'ABC123', $this->token->getKey());
});

function stubReverbConnection(): Connection
{
    return new class(Mockery::mock(WebSocketConnection::class), Mockery::mock(Application::class), null) extends Connection
    {
        public int $disconnects = 0;

        public function disconnect(): void
        {
            $this->disconnects++;
        }

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

function receive(string $message, bool $subscribed = true, ?Connection $connection = null): void
{
    $connection ??= stubReverbConnection();
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('find')->with($connection)->andReturn($subscribed ? Mockery::mock(ChannelConnection::class) : null);
    $manager = Mockery::mock(ChannelManager::class);
    $manager->shouldReceive('find')->andReturn($subscribed ? $channel : null);
    app()->instance(ChannelManager::class, $manager);

    app(HandlePlayerClientEvent::class)->handle(new MessageReceived($connection, $message));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function clientFrame(array $overrides = []): string
{
    return json_encode($overrides + ['event' => 'client-state', 'channel' => 'private-player.abc123', 'data' => ['status' => 'playing']]);
}

function discarded(): int
{
    return (int) Cache::get(ProcessPlayerFrame::DISCARDED_COUNTER, 0);
}

it('stores the frame and dispatches a drain job for a client event on the player channel', function () {
    receive(clientFrame());

    Queue::assertPushed(ProcessPlayerFrame::class, fn (ProcessPlayerFrame $job) => $job->partyCode === 'ABC123');
    expect(Cache::get('player-frame:ABC123:1'))->toBe(['status' => 'playing'])
        ->and(discarded())->toBe(0);
});

it('stamps frames with an increasing per party arrival sequence', function () {
    receive(clientFrame(['data' => ['n' => 1]]));
    receive(clientFrame(['data' => ['n' => 2]]));

    expect(Cache::get('player-frame:ABC123:1'))->toBe(['n' => 1])
        ->and(Cache::get('player-frame:ABC123:2'))->toBe(['n' => 2]);
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

it('rejects an oversized raw frame before decoding it', function (string $message) {
    receive($message);

    Queue::assertNothingPushed();
    expect(discarded())->toBe(1);
})->with([
    'undecodable oversize' => [fn () => '{'.str_repeat('x', 9000)],
    'oversize valid frame on the player channel' => [fn () => clientFrame(['data' => ['blob' => str_repeat('x', 9000)]])],
    'oversize on a foreign channel' => [fn () => clientFrame(['channel' => 'private-party.X.moderators', 'data' => ['blob' => str_repeat('x', 9000)]])],
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
    app(PlayerConnections::class)->register('stub', 'OTHER1', $this->token->getKey());
    receive(clientFrame(['channel' => 'private-player.OTHER1']));

    Queue::assertPushed(ProcessPlayerFrame::class, 4);
    RateLimiter::clear('player-frames:OTHER1');
});

describe('ProcessPlayerFrame', function () {
    beforeEach(function () {
        $this->party = Party::factory()->create(['player_kind' => 'fake', 'music_provider' => 'fake']);
        $this->player = new FrameHandlingPlayer;
        app(PartyPlayers::class)->register($this->party, $this->player);
        $this->drain = fn (?string $code = null) => new ProcessPlayerFrame($code ?? $this->party->code)->handle(app(PartyPlayers::class));
    });

    it('hands the frame to the player', function () {
        ProcessPlayerFrame::enqueue($this->party->code, ['status' => 'playing']);
        ($this->drain)();

        expect($this->player->frames)->toBe([['status' => 'playing']])
            ->and(discarded())->toBe(0);
    });

    it('uses the new player for the next job in the same worker after a player kind switch', function () {
        $made = [];
        app()->bind(FrameHandlingPlayer::class, function () use (&$made) {
            return $made[] = new FrameHandlingPlayer;
        });
        config([
            'musicparty.players.frames_a' => ['label' => 'A', 'class' => FrameHandlingPlayer::class],
            'musicparty.players.frames_b' => ['label' => 'B', 'class' => FrameHandlingPlayer::class],
        ]);
        Party::query()->whereKey($this->party->id)->update(['player_kind' => 'frames_a']);
        app(PartyPlayers::class)->forget($this->party);

        ProcessPlayerFrame::enqueue($this->party->code, ['n' => 1]);
        ($this->drain)();

        Party::query()->whereKey($this->party->id)->update(['player_kind' => 'frames_b']);
        app()->forgetScopedInstances();

        ProcessPlayerFrame::enqueue($this->party->code, ['n' => 2]);
        ($this->drain)();

        expect($made)->toHaveCount(2)
            ->and($made[0]->frames)->toBe([['n' => 1]])
            ->and($made[1]->frames)->toBe([['n' => 2]]);
    });

    it('counts a discard when the player reports the frame malformed', function () {
        ProcessPlayerFrame::enqueue($this->party->code, ['malformed' => true]);
        ($this->drain)();

        expect(discarded())->toBe(1);
    });

    it('counts a discard for an unknown party', function () {
        ProcessPlayerFrame::enqueue('NOSUCH', ['a' => 1]);
        ($this->drain)('NOSUCH');

        expect(discarded())->toBe(1)->and($this->player->frames)->toBe([]);
    });

    it('counts a discard when the player cannot handle frames', function () {
        app(PartyPlayers::class)->register($this->party, new FakePlayer);

        ProcessPlayerFrame::enqueue($this->party->code, ['a' => 1]);
        ($this->drain)();

        expect(discarded())->toBe(1);
    });

    it('applies frames in arrival order in a single drain, a stale position_sync never after a newer track_changed', function () {
        ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'position_sync']);
        ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'track_changed']);
        ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'position_sync', 'n' => 2]);

        ($this->drain)();
        ($this->drain)();

        expect($this->player->frames)->toBe([
            ['type' => 'position_sync'],
            ['type' => 'track_changed'],
            ['type' => 'position_sync', 'n' => 2],
        ]);
    });

    it('processes frames while holding the party lock so none run concurrently', function () {
        $heldDuringFrame = null;
        $this->player->onFrame = function () use (&$heldDuringFrame) {
            $heldDuringFrame = Cache::lock('player-frame-lock:'.$this->party->code, 5)->get() === false;
        };

        ProcessPlayerFrame::enqueue($this->party->code, ['a' => 1]);
        ($this->drain)();

        expect($heldDuringFrame)->toBeTrue()
            ->and(Cache::lock('player-frame-lock:'.$this->party->code, 5)->get())->toBeTrue();
    });

    it('leaves a frame arriving during processing for the holder to apply after it, losing nothing', function () {
        $this->player->onFrame = function () {
            if (count($this->player->frames) === 0) {
                ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'track_changed']);
                ($this->drain)();
            }
        };

        ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'position_sync']);
        ($this->drain)();

        expect(array_column($this->player->frames, 'type'))->toBe(['position_sync', 'track_changed']);
    });

    it('does not wait on a held lock and applies the frame on the next drain', function () {
        $lock = Cache::lock('player-frame-lock:'.$this->party->code, 5);
        $lock->get();
        ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'track_changed']);
        ($this->drain)();

        expect($this->player->frames)->toBe([]);

        $lock->release();
        ($this->drain)();

        expect($this->player->frames)->toBe([['type' => 'track_changed']]);
    });

    it('skips and counts a sequence whose frame expired once a later frame exists', function () {
        ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'lost']);
        ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'track_changed']);
        Cache::forget('player-frame:'.$this->party->code.':1');

        ($this->drain)();

        expect($this->player->frames)->toBe([['type' => 'track_changed']])->and(discarded())->toBe(1);
    });

    it('counts an expired frame under the expired reason and logs it to the Party', function () {
        ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'lost']);
        ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'track_changed']);
        Cache::forget('player-frame:'.$this->party->code.':1');

        ($this->drain)();

        $counters = app(CounterStore::class);
        expect($counters->get('metrics.player_frames_dropped.expired'))->toBe(1)
            ->and($counters->get('metrics.player_frames_dropped.out_of_order'))->toBe(0)
            ->and(PartyLogEntry::query()->where('action', 'player.frames_dropped')->sole()->details['reason'])->toBe('expired');
    });

    it('counts a frame that arrives behind the applied cursor as out of order and does not buffer it', function () {
        Cache::forever('player-frame-applied:'.$this->party->code, 3);
        Cache::put('player-frame-latest:'.$this->party->code, 2);

        $sequence = ProcessPlayerFrame::enqueue($this->party->code, ['type' => 'late']);

        expect($sequence)->toBe(3)
            ->and(Cache::has('player-frame:'.$this->party->code.':3'))->toBeFalse()
            ->and(app(CounterStore::class)->get('metrics.player_frames_dropped.out_of_order'))->toBe(1)
            ->and(PartyLogEntry::query()->where('action', 'player.frames_dropped')->sole()->details['reason'])->toBe('out_of_order');
        Queue::assertNotPushed(ProcessPlayerFrame::class);
    });

    it('writes one Party Log entry for ten drops in a minute and another after the window', function () {
        foreach (range(1, 10) as $i) {
            Cache::forever('player-frame-applied:'.$this->party->code, $i);
            ProcessPlayerFrame::enqueue($this->party->code, ['n' => $i]);
        }

        expect(app(CounterStore::class)->get('metrics.player_frames_dropped.out_of_order'))->toBe(10)
            ->and(PartyLogEntry::query()->where('action', 'player.frames_dropped')->count())->toBe(1);

        $this->travel(61)->seconds();
        Cache::forever('player-frame-applied:'.$this->party->code, 11);
        ProcessPlayerFrame::enqueue($this->party->code, ['n' => 11]);

        expect(PartyLogEntry::query()->where('action', 'player.frames_dropped')->count())->toBe(2);
    });

    it('leaves a sequence whose frame is not stored yet for the next drain instead of skipping it', function () {
        Cache::add('player-frame-latest:'.$this->party->code, 0);
        Cache::increment('player-frame-latest:'.$this->party->code);

        ($this->drain)();
        Cache::put('player-frame:'.$this->party->code.':1', ['type' => 'track_changed'], 300);
        ($this->drain)();

        expect($this->player->frames)->toBe([['type' => 'track_changed']])->and(discarded())->toBe(0);
    });

    it('keeps applying frames after the sequence counter expires and restarts', function () {
        ProcessPlayerFrame::enqueue($this->party->code, ['n' => 1]);
        ProcessPlayerFrame::enqueue($this->party->code, ['n' => 2]);
        ($this->drain)();
        Cache::forget('player-frame-latest:'.$this->party->code);

        ProcessPlayerFrame::enqueue($this->party->code, ['n' => 3]);
        ($this->drain)();

        expect(array_column($this->player->frames, 'n'))->toBe([1, 2, 3]);
    });

    it('keeps parties separate', function () {
        $other = Party::factory()->create(['player_kind' => 'fake', 'music_provider' => 'fake']);
        $otherPlayer = new FrameHandlingPlayer;
        app(PartyPlayers::class)->register($other, $otherPlayer);

        ProcessPlayerFrame::enqueue($this->party->code, ['for' => 'first']);
        ProcessPlayerFrame::enqueue($other->code, ['for' => 'second']);
        ($this->drain)($other->code);

        expect($otherPlayer->frames)->toBe([['for' => 'second']])->and($this->player->frames)->toBe([]);
    });

    it('queues on player', function () {
        expect((new ProcessPlayerFrame('ABC123'))->queue)->toBe('player');
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

it('applies a frame from a socket whose token is valid', function () {
    $connection = stubReverbConnection();

    receive(clientFrame(), connection: $connection);

    Queue::assertPushed(ProcessPlayerFrame::class);
    expect($connection->disconnects)->toBe(0);
});

it('drops the frame and disconnects when the token is revoked, expired or the socket unknown', function (string $case) {
    match ($case) {
        'revoked' => $this->token->delete(),
        'expired' => $this->token->forceFill(['expires_at' => now()->subMinute()])->save(),
        'unknown' => null,
    };
    $connection = stubReverbConnection();
    $message = clientFrame();

    if ($case === 'unknown') {
        Cache::flush();
    }

    receive($message, connection: $connection);

    Queue::assertNothingPushed();
    expect($connection->disconnects)->toBe(1)->and(discarded())->toBe(1);
})->with(['revoked', 'expired', 'unknown']);

it('refuses a socket registered for a different party', function () {
    app(PlayerConnections::class)->register('stub', 'OTHER1', $this->token->getKey());
    $connection = stubReverbConnection();

    receive(clientFrame(), connection: $connection);

    Queue::assertNothingPushed();
    expect($connection->disconnects)->toBe(1);
});
