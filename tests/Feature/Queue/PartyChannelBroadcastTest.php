<?php

use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\QueueUpdatedEvent;
use App\Jobs\BroadcastPartyQueue;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->user = User::factory()->create(['nickname' => 'Alice']);
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
});

function trackFor(Party $party, PartyMember $member, RequestStatus $status, string $title, int $score = 0): TrackRequest
{
    $request = TrackRequest::factory()->for($party)->create([
        'status' => $status,
        'title' => $title,
        'party_member_id' => $member->id,
    ]);

    if ($score !== 0) {
        RequestVote::query()->create(['track_request_id' => $request->id, 'party_member_id' => $member->id, 'value' => $score]);
    }

    return $request;
}

it('builds a snapshot of now playing, up next and the ordered queue with nicknames', function () {
    trackFor($this->party, $this->member, RequestStatus::Playing, 'Now');
    trackFor($this->party, $this->member, RequestStatus::UpNext, 'Next');
    trackFor($this->party, $this->member, RequestStatus::Queued, 'Low');
    trackFor($this->party, $this->member, RequestStatus::Queued, 'High', 1);

    $snapshot = app(PartyQueueSnapshot::class)->build($this->party);

    expect($snapshot['version'])->toBe(1)
        ->and($snapshot['sequence'])->toBe(1)
        ->and($snapshot['code'])->toBe('ABCD')
        ->and($snapshot['now_playing']['track']['title'])->toBe('Now')
        ->and($snapshot['now_playing']['requested_by'])->toBe(['name' => 'Alice'])
        ->and($snapshot['up_next']['track']['title'])->toBe('Next')
        ->and(array_column(array_column($snapshot['queue'], 'track'), 'title'))->toBe(['High', 'Low'])
        ->and($snapshot['queue'][0]['score'])->toBe(1);
});

it('has a stable payload shape', function () {
    trackFor($this->party, $this->member, RequestStatus::Queued, 'Only');

    $snapshot = app(PartyQueueSnapshot::class)->build($this->party);

    expect(array_keys($snapshot))->toBe(['version', 'sequence', 'code', 'now_playing', 'up_next', 'queue'])
        ->and($snapshot['now_playing'])->toBeNull()
        ->and($snapshot['up_next'])->toBeNull()
        ->and(array_keys($snapshot['queue'][0]))->toBe(['id', 'track', 'status', 'score', 'likes', 'dislikes', 'requested_by'])
        ->and(array_keys($snapshot['queue'][0]['track']))->toBe(['title', 'artists', 'album', 'artwork_url', 'duration_ms', 'explicit']);
});

it('increments the sequence on every snapshot', function () {
    $builder = app(PartyQueueSnapshot::class);

    expect($builder->build($this->party)['sequence'])->toBe(1)
        ->and($builder->build($this->party)['sequence'])->toBe(2);
});

it('excludes pending, rejected and played requests and other parties', function () {
    trackFor($this->party, $this->member, RequestStatus::Pending, 'Pending');
    trackFor($this->party, $this->member, RequestStatus::Rejected, 'Rejected');
    trackFor($this->party, $this->member, RequestStatus::Played, 'Played');
    $other = Party::factory()->live()->create();
    $otherMember = PartyMember::factory()->for($other)->create();
    trackFor($other, $otherMember, RequestStatus::Queued, 'Elsewhere');

    $snapshot = app(PartyQueueSnapshot::class)->build($this->party);

    expect($snapshot['queue'])->toBe([]);
});

it('never leaks member ids, emails or secrets', function () {
    trackFor($this->party, $this->member, RequestStatus::Queued, 'Song', 1);

    $json = json_encode(app(PartyQueueSnapshot::class)->build($this->party), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('member')
        ->not->toContain('user_id')->not->toContain('party_member_id')
        ->not->toContain('token')
        ->not->toContain('secret')
        ->not->toContain('email');
});

it('broadcasts the snapshot on the public party channel', function () {
    Event::fake([QueueUpdatedEvent::class]);
    trackFor($this->party, $this->member, RequestStatus::Queued, 'Song');

    new BroadcastPartyQueue('ABCD')->handle(app(PartyQueueSnapshot::class));

    Event::assertDispatched(QueueUpdatedEvent::class, function (QueueUpdatedEvent $event) {
        $channels = $event->broadcastOn();

        return $channels[0] instanceof Channel
            && $channels[0]->name === 'party.ABCD'
            && $event->broadcastWith()['queue'][0]['track']['title'] === 'Song';
    });
});

it('does nothing for an unknown party', function () {
    Event::fake([QueueUpdatedEvent::class]);

    new BroadcastPartyQueue('NOPE')->handle(app(PartyQueueSnapshot::class));

    Event::assertNotDispatched(QueueUpdatedEvent::class);
});

it('coalesces a burst of votes into one broadcast job', function () {
    Queue::fake();
    $this->party->forceFill(['downvotes' => true, 'downvotes_per_hour' => null])->save();
    Sanctum::actingAs($this->user);
    $requests = collect(range(1, 5))->map(fn (int $i) => trackFor($this->party, PartyMember::factory()->for($this->party)->create(), RequestStatus::Queued, "Song {$i}"));

    foreach ($requests as $request) {
        $this->putJson("/api/v1/parties/ABCD/requests/{$request->id}/vote", ['value' => 'up'])->assertOk();
    }

    Queue::assertPushed(BroadcastPartyQueue::class, 1);
});
