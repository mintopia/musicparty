<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Playback\Jobs\StartPlayback;
use App\Domain\Playback\Jobs\TickParty;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Stats\Jobs\RefreshPartyStatsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD', 'downvotes' => true, 'downvotes_per_hour' => null]);
    $this->host = PartyMember::factory()->for($this->party)->host()->create();
});

function seedQueued(Party $party, int $count): void
{
    foreach (range(1, $count) as $_) {
        $member = PartyMember::factory()->for($party)->create();
        $request = TrackRequest::factory()->for($party)->create(['party_member_id' => $member->id]);
        RequestVote::factory()->create(['track_request_id' => $request->id, 'party_member_id' => $member->id]);
    }
}

/**
 * @param  Closure(): mixed  $measure
 */
function expectConstantQueries(Party $party, Closure $seed, Closure $measure): void
{
    $seed(1);
    $measure();
    $few = countQueries($measure);

    $seed(49);
    $many = countQueries($measure);

    expect($many)->toBe($few);
}

it('runs a constant number of queries for the party page', function () {
    $user = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($user)->create();

    expectConstantQueries(
        $this->party,
        fn (int $n) => seedQueued($this->party, $n),
        fn () => $this->withoutVite()->actingAs($user)->get('/parties/ABCD')->assertOk(),
    );
});

it('runs a constant number of queries for the queue API', function () {
    $user = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($user)->create();
    Sanctum::actingAs($user);

    expectConstantQueries(
        $this->party,
        fn (int $n) => seedQueued($this->party, $n),
        fn () => $this->getJson('/api/v1/parties/ABCD/queue')->assertOk(),
    );
});

it('runs a constant number of queries for a vote', function () {
    $users = User::factory()->count(3)->create();
    $users->each(fn (User $user) => PartyMember::factory()->for($this->party)->for($user)->create());
    $vote = function () use (&$users): void {
        $this->travelTo(now()->addMinute());
        Sanctum::actingAs($users->shift());
        $target = TrackRequest::query()->where('party_id', $this->party->id)->firstOrFail();
        $this->putJson("/api/v1/parties/ABCD/requests/{$target->id}/vote", ['value' => 'up'])->assertSuccessful();
    };
    Queue::fake();

    seedQueued($this->party, 1);
    $vote();
    $few = countQueries($vote);
    seedQueued($this->party, 49);

    expect(countQueries($vote))->toBe($few);
});

it('runs a constant number of queries for a request', function () {
    Bus::fake([StartPlayback::class]);
    Queue::fake();
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $users = User::factory()->count(3)->create();
    $users->each(fn (User $user) => PartyMember::factory()->for($this->party)->for($user)->create());
    $request = function (string $trackId) use (&$users): void {
        Sanctum::actingAs($users->shift());
        $this->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => $trackId])->assertCreated();
    };

    seedQueued($this->party, 1);
    $request('track-1');
    $few = countQueries(fn () => $request('track-2'));
    seedQueued($this->party, 49);

    expect(countQueries(fn () => $request('track-3')))->toBe($few);
});

it('runs a constant number of queries for a tick', function () {
    $party = livePlaybackParty(array_map(playbackTrack(...), range(1, 5)), ['code' => 'TICK']);
    useFakePlayer($party);
    $tick = fn () => dispatch_sync(new TickParty($party->code));

    seedQueued($party, 1);
    $tick();
    $few = countQueries($tick);
    seedQueued($party, 49);

    expect(countQueries($tick))->toBe($few);
});

it('runs a constant number of queries for the queue snapshot', function () {
    expectConstantQueries(
        $this->party,
        fn (int $n) => seedQueued($this->party, $n),
        fn () => app(PartyQueueSnapshot::class)->build($this->party->fresh()),
    );
});

it('runs a constant number of queries for the members list', function () {
    Sanctum::actingAs($this->host->user);

    expectConstantQueries(
        $this->party,
        fn (int $n) => PartyMember::factory()->for($this->party)->count($n)->create(),
        fn () => $this->getJson('/api/v1/parties/ABCD/members')->assertOk(),
    );
});

it('runs a constant number of queries for the party log list', function () {
    Sanctum::actingAs($this->host->user);

    expectConstantQueries(
        $this->party,
        fn (int $n) => PartyLogEntry::factory()->for($this->party)->count($n)->create(),
        fn () => $this->getJson('/api/v1/parties/ABCD/log')->assertOk(),
    );
});

it('runs a constant number of queries for a live stats refresh', function () {
    expectConstantQueries(
        $this->party,
        function (int $n): void {
            foreach (range(1, $n) as $_) {
                $member = PartyMember::factory()->for($this->party)->create();
                $request = TrackRequest::factory()->for($this->party)->create(['party_member_id' => $member->id, 'status' => RequestStatus::Played, 'started_at' => now()]);
                Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $request->id, 'party_member_id' => $member->id]);
                RequestVote::factory()->create(['track_request_id' => $request->id, 'party_member_id' => $member->id]);
            }
        },
        fn () => app()->call([new RefreshPartyStatsJob($this->party->id), 'handle']),
    );
});
