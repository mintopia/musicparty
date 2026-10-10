<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Queue\Actions\VoteOnRequest;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return array{PartyMember, TrackRequest}
 */
function votableRequest(Party $party): array
{
    $member = PartyMember::factory()->for($party)->for(User::factory()->create())->create();
    $request = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Queued, 'party_member_id' => $member->id]);

    return [$member, $request];
}

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->broadcasts = new ArrayObject;
    $broadcasts = $this->broadcasts;
    $spy = new class($broadcasts) extends NullBroadcaster
    {
        /**
         * @param  ArrayObject<int, string>  $log
         */
        public function __construct(private ArrayObject $log) {}

        /**
         * @param  array<int, mixed>  $channels
         * @param  array<string, mixed>  $payload
         */
        public function broadcast(array $channels, $event, array $payload = []): void
        {
            $this->log[] = $event;
        }
    };
    config(['broadcasting.default' => 'spy', 'broadcasting.connections.spy' => ['driver' => 'spy']]);
    Broadcast::extend('spy', fn () => $spy);
});

it('does not broadcast a party log entry inside a rolled back transaction', function () {
    try {
        DB::transaction(function () {
            app(RecordPartyLogEntry::class)($this->party, 'party.paused');

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(PartyLogEntry::query()->count())->toBe(0)
        ->and($this->broadcasts->getArrayCopy())->toBe([]);
});

it('broadcasts a committed party log entry once', function () {
    DB::transaction(fn () => app(RecordPartyLogEntry::class)($this->party, 'party.paused'));

    expect(array_count_values($this->broadcasts->getArrayCopy()))->toBe(['party_log.entry_added' => 1]);
});

it('broadcasts no queue update for a vote inside a rolled back transaction', function () {
    [$member, $request] = votableRequest($this->party);
    try {
        DB::transaction(function () use ($member, $request) {
            app(VoteOnRequest::class)($this->party, $member, $request, VoteDirection::Up);

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(RequestVote::query()->count())->toBe(0)
        ->and($this->broadcasts->getArrayCopy())->toBe([]);
});

it('broadcasts one queue update for a committed vote', function () {
    [$member, $request] = votableRequest($this->party);
    DB::transaction(fn () => app(VoteOnRequest::class)($this->party, $member, $request, VoteDirection::Up));

    expect(array_count_values($this->broadcasts->getArrayCopy()))->toHaveKey('queue.updated', 1);
});

it('marks queue broadcast job and every broadcast event as dispatch after commit', function () {
    expect(BroadcastPartyQueue::class)->toImplement(ShouldDispatchAfterCommit::class);

    foreach ((glob(app_path('Domain/*/Broadcast/*Event.php')) ?: []) as $file) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(app_path()) + 1));
        expect($class)->toImplement(ShouldDispatchAfterCommit::class);
    }
});

it('enables after_commit on every queue connection', function () {
    foreach ((require config_path('queue.php'))['connections'] as $name => $connection) {
        expect($connection['after_commit'] ?? null)->toBeTrue($name);
    }
});
