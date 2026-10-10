<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Broadcast\MemberRatingChangedEvent;
use App\Domain\Queue\Broadcast\MemberVoteChangedEvent;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD', 'downvotes' => true, 'downvotes_per_hour' => null]);
    $this->user = User::factory()->create();
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
    $this->other = PartyMember::factory()->for($this->party)->create();
    $this->track = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);
    Sanctum::actingAs($this->user);
});

/**
 * @return array<int, string>
 */
function channelNames(MemberVoteChangedEvent|MemberRatingChangedEvent $event): array
{
    return array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn());
}

it('broadcasts one member vote event to that member channel only', function () {
    Event::fake([MemberVoteChangedEvent::class]);

    $this->putJson("/api/v1/parties/ABCD/requests/{$this->track->id}/vote", ['value' => 'up'])->assertOk();

    Event::assertDispatchedTimes(MemberVoteChangedEvent::class, 1);
    Event::assertDispatched(MemberVoteChangedEvent::class, fn (MemberVoteChangedEvent $event): bool => $event->broadcastAs() === 'member.vote_changed'
        && $event->broadcastWith() === ['request_id' => $this->track->id, 'value' => 1]
        && channelNames($event) === ["private-party.ABCD.member.{$this->member->id}"]);
});

it('broadcasts the retraction as a zero value', function () {
    RequestVote::query()->create(['track_request_id' => $this->track->id, 'party_member_id' => $this->member->id, 'value' => 1]);
    Event::fake([MemberVoteChangedEvent::class]);

    $this->deleteJson("/api/v1/parties/ABCD/requests/{$this->track->id}/vote")->assertOk();

    Event::assertDispatched(MemberVoteChangedEvent::class, fn (MemberVoteChangedEvent $event): bool => $event->broadcastWith()['value'] === 0);
});

it('broadcasts nothing for a refused vote', function () {
    $this->track->forceFill(['status' => RequestStatus::UpNext])->save();
    Event::fake([MemberVoteChangedEvent::class]);

    $this->putJson("/api/v1/parties/ABCD/requests/{$this->track->id}/vote", ['value' => 'up'])->assertConflict();

    Event::assertNotDispatched(MemberVoteChangedEvent::class);
});

it('broadcasts nothing when the vote did not change', function () {
    RequestVote::query()->create(['track_request_id' => $this->track->id, 'party_member_id' => $this->member->id, 'value' => 1]);
    Event::fake([MemberVoteChangedEvent::class]);

    $this->putJson("/api/v1/parties/ABCD/requests/{$this->track->id}/vote", ['value' => 'up'])->assertOk();

    Event::assertNotDispatched(MemberVoteChangedEvent::class);
});

it('queues the member vote broadcast on the broadcast queue after commit', function () {
    Queue::fake();

    $this->putJson("/api/v1/parties/ABCD/requests/{$this->track->id}/vote", ['value' => 'down'])->assertOk();

    Queue::assertPushedOn('broadcast', BroadcastEvent::class, fn ($job): bool => $job->event instanceof MemberVoteChangedEvent);
});

it('broadcasts one member rating event to that member channel only', function () {
    $play = Play::factory()->for($this->party)->create();
    Event::fake([MemberRatingChangedEvent::class]);

    $this->putJson("/api/v1/parties/ABCD/plays/{$play->id}/rating", ['value' => 'down'])->assertOk();

    Event::assertDispatchedTimes(MemberRatingChangedEvent::class, 1);
    Event::assertDispatched(MemberRatingChangedEvent::class, fn (MemberRatingChangedEvent $event): bool => $event->broadcastAs() === 'member.rating_changed'
        && $event->broadcastWith() === ['play_id' => $play->id, 'value' => -1]
        && channelNames($event) === ["private-party.ABCD.member.{$this->member->id}"]);
});

it('broadcasts nothing for a refused rating', function () {
    $play = Play::factory()->for($this->party)->create();
    $this->member->forceFill(['banned' => true])->save();
    Event::fake([MemberRatingChangedEvent::class]);

    $this->putJson("/api/v1/parties/ABCD/plays/{$play->id}/rating", ['value' => 'up'])->assertStatus(403);

    Event::assertNotDispatched(MemberRatingChangedEvent::class);
});

it('returns the member own votes and ratings', function () {
    $play = Play::factory()->for($this->party)->create();
    $otherTrack = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);
    RequestVote::query()->create(['track_request_id' => $this->track->id, 'party_member_id' => $this->member->id, 'value' => -1]);
    RequestVote::query()->create(['track_request_id' => $otherTrack->id, 'party_member_id' => $this->other->id, 'value' => 1]);
    Rating::query()->create(['play_id' => $play->id, 'party_member_id' => $this->member->id, 'value' => 1]);
    Rating::query()->create(['play_id' => $play->id, 'party_member_id' => $this->other->id, 'value' => -1]);

    $this->getJson('/api/v1/parties/ABCD/me/votes')->assertOk()
        ->assertExactJson(['data' => [
            'votes' => [['request_id' => $this->track->id, 'value' => -1]],
            'ratings' => [['play_id' => $play->id, 'value' => 1]],
        ]]);
});

it('refuses the member votes endpoint to non-members and banned members', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/parties/ABCD/me/votes')->assertForbidden();

    $this->member->forceFill(['banned' => true])->save();
    Sanctum::actingAs($this->user);
    $this->getJson('/api/v1/parties/ABCD/me/votes')->assertForbidden();
});
