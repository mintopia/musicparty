<?php

use App\Events\UpcomingSong\UpdatedEvent;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\Whamageddon\Whamageddon;
use App\Services\Music\Fake\FakeMusicProvider;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;
use Tests\Support\WhamageddonFixtures;

/**
 * @return list<int> the scores clients of the Song's Party were told about, oldest first
 */
function queueScoresBroadcastFor(UpcomingSong $song): array
{
    return Queue::pushed(BroadcastEvent::class)
        ->map(fn (BroadcastEvent $job): object => $job->event)
        ->filter(fn (object $event): bool => $event instanceof UpdatedEvent
            && $event->broadcastWith()['id'] === $song->id
            && array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn()) === ["private-party.{$song->party->code}"])
        ->map(fn (UpdatedEvent $event): int => $event->broadcastWith()['score'])
        ->values()
        ->all();
}

function lastQueueScoreBroadcastFor(UpcomingSong $song): ?int
{
    $scores = queueScoresBroadcastFor($song);

    return $scores === [] ? null : array_last($scores);
}

beforeEach(function () {
    $this->party = Party::factory()->create(['provider_id' => 'fake', 'active' => true]);
    $this->voter = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($this->voter)->create();
    User::query()->update(['first_login' => false]);
});

it('broadcasts the new score to the party when a vote is cast and removed', function () {
    $song = UpcomingSong::factory()->for($this->party)->waiting()->create(['score_adjustment' => 0]);
    Queue::fake();
    Sanctum::actingAs($this->voter);

    $this->postJson(route('api.v1.parties.upcomingsongs.vote.store', [$this->party, $song]), ['vote' => 1])->assertSuccessful();
    expect(lastQueueScoreBroadcastFor($song))->toBe(1);

    $this->postJson(route('api.v1.parties.upcomingsongs.vote.store', [$this->party, $song]), ['vote' => 0])->assertSuccessful();
    expect(lastQueueScoreBroadcastFor($song))->toBe(0);
});

it('broadcasts a newly created Request to the party with its score', function () {
    app(FakeMusicProvider::class)->withTrack(FakeFallbackPlaylist::track('reqtrack'));
    Sanctum::actingAs($this->voter);
    Queue::fake();

    $this->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 'reqtrack'])->assertSuccessful();

    $song = UpcomingSong::sole();
    expect(lastQueueScoreBroadcastFor($song))->toBe($song->fresh()->score);
});

it('broadcasts a Flagged Request to the party with its score once it is approved', function () {
    $request = UpcomingSong::factory()->for($this->party)->create(['queued_at' => null, 'flagged_at' => now(), 'flag_reason' => 'Needs a look']);
    Queue::fake();

    $this->actingAs($this->party->user->fresh(), 'sanctum')->post(route('parties.flagged.approve', [$this->party, $request]))->assertRedirect();

    expect(lastQueueScoreBroadcastFor($request))->toBe($request->fresh()->score);
});

it('broadcasts the Whamageddon score bump to the party', function () {
    WhamageddonFixtures::fake();
    WhamageddonFixtures::install();
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    $this->travelTo(CarbonImmutable::parse('2026-12-24 12:00:00', 'UTC'));
    Queue::fake();

    new Whamageddon($party)->hourly();

    expect($upcoming->fresh()->score)->toBe(1)
        ->and(lastQueueScoreBroadcastFor($upcoming))->toBe(1);
});
