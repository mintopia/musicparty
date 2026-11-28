<?php

use App\Events\UpcomingSong\RemovedEvent;
use App\Events\UpcomingSong\UpdatedEvent;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\Whamageddon\Whamageddon;
use App\Services\QueueSelector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WhamageddonFixtures;

function liveVote(Party $party, UpcomingSong $request, User $user, int $value)
{
    Sanctum::actingAs($user);

    return test()->postJson(route('api.v1.parties.upcomingsongs.vote.store', [$party, $request]), ['vote' => $value]);
}

function liveMember(Party $party): User
{
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->create();

    return $user;
}

beforeEach(function () {
    $this->party = Party::factory()->create(['downvotes' => true]);
    $this->request = UpcomingSong::factory()->waiting()->for($this->party)->create(['score_adjustment' => 0]);
});

describe('score broadcasts', function () {
    it('broadcasts an update with the new score after a vote and after an unvote', function () {
        $user = liveMember($this->party);
        Event::fake([UpdatedEvent::class]);

        liveVote($this->party, $this->request, $user, 1)->assertOk();
        Event::assertDispatched(UpdatedEvent::class, fn (UpdatedEvent $event): bool => $event->broadcastWith()['score'] === 1);

        Event::fake([UpdatedEvent::class]);
        liveVote($this->party, $this->request, $user, 0)->assertOk();
        Event::assertDispatched(UpdatedEvent::class, fn (UpdatedEvent $event): bool => $event->broadcastWith()['score'] === 0);
    });

    it('does not broadcast when a vote leaves the score unchanged', function () {
        $user = liveMember($this->party);
        liveVote($this->party, $this->request, $user, 1)->assertOk();
        Event::fake([UpdatedEvent::class]);

        liveVote($this->party, $this->request, $user, 1)->assertOk();
        liveVote($this->party, $this->request, liveMember($this->party), 0)->assertOk();

        Event::assertNotDispatched(UpdatedEvent::class);
    });

    it('does not broadcast score changes for flagged requests', function () {
        $flagged = UpcomingSong::factory()->for($this->party)->create(['flagged_at' => now(), 'queued_at' => null]);
        Event::fake([UpdatedEvent::class]);

        UpcomingSong::query()->whereKey($flagged->id)->update(['score_adjustment' => 3]);
        $flagged->refresh()->updateScore();

        Event::assertNotDispatched(UpdatedEvent::class);
    });

    it('broadcasts when a Request is created', function () {
        $song = Song::factory()->create();
        Event::fake([UpdatedEvent::class]);

        $request = app(QueueSelector::class)->addRequest($this->party, $song, null, ['score_adjustment' => 2]);

        Event::assertDispatched(UpdatedEvent::class, fn (UpdatedEvent $event): bool => $event->broadcastWith()['id'] === $request->id && $event->broadcastWith()['score'] === 2);
    });

    it('broadcasts the Whamageddon bump', function () {
        WhamageddonFixtures::fake();
        WhamageddonFixtures::install();
        $party = WhamageddonFixtures::party(['enabled' => true]);
        $request = WhamageddonFixtures::upcoming($party);
        $this->travelTo(CarbonImmutable::parse('2026-12-24 12:00:00', 'UTC'));
        Event::fake([UpdatedEvent::class]);

        new Whamageddon($party)->hourly();

        Event::assertDispatched(UpdatedEvent::class, fn (UpdatedEvent $event): bool => $event->broadcastWith()['id'] === $request->id && $event->broadcastWith()['score'] === 1);
    });
});

describe('removal broadcasts', function () {
    it('marks a Request sent to the Player as played and a deleted one as not', function () {
        $deleted = UpcomingSong::factory()->waiting()->for($this->party)->create();
        Event::fake([RemovedEvent::class]);

        $this->request->forceFill(['queued_at' => now()])->save();
        $deleted->delete();

        Event::assertDispatched(RemovedEvent::class, fn (RemovedEvent $event): bool => $event->broadcastWith() === ['id' => $this->request->id, 'played' => true]);
        Event::assertDispatched(RemovedEvent::class, fn (RemovedEvent $event): bool => $event->broadcastWith() === ['id' => $deleted->id, 'played' => false]);
    });
});

describe('vote response position', function () {
    it('returns the Request position in the order the server ranks the Queue', function () {
        UpcomingSong::factory()->waiting()->for($this->party)->create(['score' => 5]);
        UpcomingSong::factory()->waiting()->for($this->party)->create(['score' => 3]);
        $user = liveMember($this->party);

        liveVote($this->party, $this->request, $user, 1)->assertOk()->assertJsonPath('data.position', 3);
        $this->request->forceFill(['score_adjustment' => 10])->save();
        liveVote($this->party, $this->request, liveMember($this->party), 1)->assertOk()->assertJsonPath('data.position', 1);
    });
});
