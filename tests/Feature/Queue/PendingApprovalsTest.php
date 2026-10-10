<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Broadcast\PartyLogEntryAddedEvent;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Queue\Actions\ListQueue;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\PendingRequestAddedEvent;
use App\Events\Party\PendingRequestResolvedEvent;
use App\Events\Party\QueueUpdatedEvent;
use App\Events\Party\RequestDecidedEvent;
use App\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider([
        new TrackData('fake', 't1', 'Song t1', [new ArtistData('a', 'Artist')], new AlbumData('al', 'Album'), 180000, false, 'ISRC1'),
    ]));
    $this->party = Party::factory()->live()->create(['code' => 'ABCD', 'hold_requests' => true]);
    $this->host = PartyMember::factory()->for($this->party)->host()->create();
    $this->moderator = PartyMember::factory()->for($this->party)->moderator()->create();
    $this->vip = PartyMember::factory()->for($this->party)->vip()->create();
    $this->guest = PartyMember::factory()->for($this->party)->create();
    $this->other = PartyMember::factory()->for($this->party)->create();
});

function pendingFor(PartyMember $requester, RequestStatus $status = RequestStatus::Pending): TrackRequest
{
    return TrackRequest::factory()->create(['party_id' => $requester->party_id, 'party_member_id' => $requester->id, 'status' => $status]);
}

function asMember(PartyMember $member): void
{
    Sanctum::actingAs($member->user);
}

function requestTrack(PartyMember $member)
{
    asMember($member);

    return test()->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => 't1']);
}

function requestUrl(TrackRequest $request, string $suffix = ''): string
{
    return "/api/v1/parties/ABCD/requests/{$request->id}{$suffix}";
}

describe('holding', function () {
    it('queues requests when the hold is off', function () {
        $this->party->forceFill(['hold_requests' => false])->save();

        requestTrack($this->guest)->assertCreated()->assertJsonPath('data.status', 'queued');
    });

    it('holds a guest and vip request as pending', function (string $who) {
        requestTrack($this->{$who})->assertCreated()->assertJsonPath('data.status', 'pending');

        expect(TrackRequest::query()->sole()->status)->toBe(RequestStatus::Pending);
    })->with(['guest', 'vip']);

    it('exempts the host and moderators from the hold', function (string $who) {
        requestTrack($this->{$who})->assertCreated()->assertJsonPath('data.status', 'queued');
    })->with(['host', 'moderator']);

    it('hides pending requests from the queue', function () {
        pendingFor($this->guest);
        $queued = pendingFor($this->guest, RequestStatus::Queued);

        expect(app(ListQueue::class)($this->party)->pluck('id')->all())->toBe([$queued->id]);
        asMember($this->other);
        $this->getJson('/api/v1/parties/ABCD/queue')->assertJsonCount(1, 'data');
    });

    it('refuses votes on a pending request', function () {
        $pending = pendingFor($this->guest);
        asMember($this->other);

        $this->putJson(requestUrl($pending, '/vote'), ['direction' => 'up'])->assertUnprocessable();
        expect($pending->votes()->count())->toBe(0);
    });

    it('turns a duplicate of a pending request into an upvote', function () {
        requestTrack($this->guest)->assertCreated();
        requestTrack($this->other)->assertOk()->assertJsonPath('meta.duplicate', true);

        expect(TrackRequest::query()->count())->toBe(1)
            ->and(TrackRequest::query()->sole()->votes()->count())->toBe(2);
    });

    it('announces a pending request to moderators and the requester only', function () {
        Event::fake([PendingRequestAddedEvent::class, RequestDecidedEvent::class, QueueUpdatedEvent::class]);

        requestTrack($this->guest)->assertCreated();

        Event::assertDispatched(PendingRequestAddedEvent::class, fn ($e) => $e->broadcastOn()[0]->name === 'private-party.ABCD.moderators'
            && $e->broadcastWith()['title'] === 'Song t1');
        Event::assertDispatched(RequestDecidedEvent::class, fn ($e) => $e->broadcastOn()[0]->name === "private-party.ABCD.member.{$this->guest->id}"
            && $e->broadcastWith()['status'] === 'pending');
        Event::assertNotDispatched(QueueUpdatedEvent::class);
    });

    it('still broadcasts publicly for an exempt queued request', function () {
        Event::fake([PendingRequestAddedEvent::class, QueueUpdatedEvent::class]);

        requestTrack($this->host)->assertCreated();

        Event::assertDispatched(QueueUpdatedEvent::class);
        Event::assertNotDispatched(PendingRequestAddedEvent::class);
    });
});

describe('approve and reject', function () {
    it('lets the host and moderators approve', function (string $who) {
        $pending = pendingFor($this->guest);
        asMember($this->{$who});

        $this->postJson(requestUrl($pending, '/approve'))->assertOk()->assertJsonPath('data.status', 'queued');

        $fresh = $pending->fresh();
        expect($fresh->status)->toBe(RequestStatus::Queued)
            ->and($fresh->decided_by_member_id)->toBe($this->{$who}->id)
            ->and($fresh->decided_at)->not->toBeNull();
    })->with(['host', 'moderator']);

    it('lets the host and moderators reject with an optional reason', function (string $who, ?string $reason) {
        $pending = pendingFor($this->guest);
        asMember($this->{$who});

        $this->postJson(requestUrl($pending, '/reject'), $reason === null ? [] : ['reason' => $reason])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        expect($pending->fresh()->rejection_reason)->toBe($reason);
    })->with([['host', 'Too loud'], ['moderator', null]]);

    it('refuses approve and reject to vips, guests and banned moderators', function (string $action, string $who) {
        $pending = pendingFor($this->guest);
        $this->moderator->forceFill(['banned' => true])->save();
        asMember($this->{$who});

        $this->postJson(requestUrl($pending, "/{$action}"))->assertForbidden();
        expect($pending->fresh()->status)->toBe(RequestStatus::Pending);
    })->with(['approve', 'reject'])->with(['vip', 'guest', 'moderator']);

    it('refuses to decide a request that is not pending', function (string $action) {
        $queued = pendingFor($this->guest, RequestStatus::Queued);
        asMember($this->host);

        $this->postJson(requestUrl($queued, "/{$action}"))->assertStatus(409);
        expect($queued->fresh()->status)->toBe(RequestStatus::Queued);
    })->with(['approve', 'reject']);

    it('refuses an over-long reason', function () {
        $pending = pendingFor($this->guest);
        asMember($this->host);

        $this->postJson(requestUrl($pending, '/reject'), ['reason' => str_repeat('x', 201)])->assertUnprocessable();
    });

    it('returns not found for a request from another party', function (string $action) {
        $foreign = PartyMember::factory()->for(Party::factory()->live()->create(['code' => 'WXYZ']))->create();
        $pending = pendingFor($foreign);
        asMember($this->host);

        $this->postJson(requestUrl($pending, "/{$action}"))->assertNotFound();
    })->with(['approve', 'reject']);

    it('logs the decision and dispatches events to the right channels', function () {
        $pending = pendingFor($this->guest);
        Event::fake([PendingRequestResolvedEvent::class, RequestDecidedEvent::class, QueueUpdatedEvent::class, PartyLogEntryAddedEvent::class]);
        asMember($this->moderator);

        $this->postJson(requestUrl($pending, '/reject'), ['reason' => 'Nope'])->assertOk();

        $entry = PartyLogEntry::query()->where('action', 'request.rejected')->sole();
        expect($entry->subject)->toBe($pending->title)
            ->and($entry->user_id)->toBe($this->moderator->user_id)
            ->and($entry->details)->toBe([
                'request_id' => $pending->id,
                'requester_member_id' => $this->guest->id,
                'reason' => 'Nope',
                'previous_status' => 'pending',
            ]);
        Event::assertDispatched(PendingRequestResolvedEvent::class, fn ($e) => $e->broadcastOn()[0]->name === 'private-party.ABCD.moderators'
            && $e->broadcastWith() === ['request_id' => $pending->id, 'status' => 'rejected']);
        Event::assertDispatched(RequestDecidedEvent::class, fn ($e) => $e->broadcastOn()[0]->name === "private-party.ABCD.member.{$this->guest->id}"
            && $e->broadcastWith() === ['request_id' => $pending->id, 'status' => 'rejected', 'reason' => 'Nope']);
        Event::assertDispatched(PartyLogEntryAddedEvent::class, fn ($e) => $e->broadcastOn()[0]->name === 'private-party.ABCD.moderators');
        Event::assertNotDispatched(QueueUpdatedEvent::class);
    });

    it('updates the public queue and logs when approving', function () {
        $pending = pendingFor($this->guest);
        Event::fake([QueueUpdatedEvent::class, RequestDecidedEvent::class]);
        asMember($this->host);

        $this->postJson(requestUrl($pending, '/approve'))->assertOk();

        Event::assertDispatched(QueueUpdatedEvent::class, fn ($e) => $e->broadcastOn()[0]->name === 'party.ABCD');
        Event::assertDispatched(RequestDecidedEvent::class, fn ($e) => $e->broadcastWith()['status'] === 'queued');
        expect(PartyLogEntry::query()->where('action', 'request.approved')->sole()->details['previous_status'])->toBe('pending');
        expect(app(ListQueue::class)($this->party)->pluck('id')->all())->toBe([$pending->id]);
    });
});

describe('remove', function () {
    it('lets the host and moderators remove queued and pending requests', function (string $who, RequestStatus $status) {
        $request = pendingFor($this->guest, $status);
        asMember($this->{$who});

        $this->deleteJson(requestUrl($request))->assertOk()->assertJsonPath('data.status', 'removed');

        expect($request->fresh()->status)->toBe(RequestStatus::Removed);
        expect(PartyLogEntry::query()->where('action', 'request.removed')->sole()->details['previous_status'])->toBe($status->value);
    })->with(['host', 'moderator'])->with([RequestStatus::Queued, RequestStatus::Pending]);

    it('lets a requester remove their own queued or pending request', function (RequestStatus $status) {
        $request = pendingFor($this->guest, $status);
        Event::fake([RequestDecidedEvent::class]);
        asMember($this->guest);

        $this->deleteJson(requestUrl($request))->assertOk();

        expect($request->fresh()->status)->toBe(RequestStatus::Removed);
        Event::assertNotDispatched(RequestDecidedEvent::class);
    })->with([RequestStatus::Queued, RequestStatus::Pending]);

    it('refuses a guest or vip removing someone else\'s request', function (string $who) {
        $request = pendingFor($this->other, RequestStatus::Queued);
        asMember($this->{$who});

        $this->deleteJson(requestUrl($request))->assertForbidden();
        expect($request->fresh()->status)->toBe(RequestStatus::Queued);
    })->with(['guest', 'vip']);

    it('refuses a banned requester removing their own request', function () {
        $request = pendingFor($this->guest);
        $this->guest->forceFill(['banned' => true])->save();
        asMember($this->guest);

        $this->deleteJson(requestUrl($request))->assertForbidden();
    });

    it('refuses to remove a request that has progressed', function (RequestStatus $status) {
        $request = pendingFor($this->guest, $status);
        asMember($this->host);

        $this->deleteJson(requestUrl($request))->assertStatus(409);
        expect($request->fresh()->status)->toBe($status);
    })->with([RequestStatus::UpNext, RequestStatus::Playing, RequestStatus::Played, RequestStatus::Rejected, RequestStatus::Removed]);

    it('returns not found for another party\'s request', function () {
        $pending = pendingFor(PartyMember::factory()->for(Party::factory()->live()->create(['code' => 'WXYZ']))->create());
        asMember($this->host);

        $this->deleteJson(requestUrl($pending))->assertNotFound();
    });

    it('updates the public queue only when a queued request is removed', function (RequestStatus $status, bool $public) {
        $request = pendingFor($this->guest, $status);
        Event::fake([QueueUpdatedEvent::class, PendingRequestResolvedEvent::class]);
        asMember($this->host);

        $this->deleteJson(requestUrl($request))->assertOk();

        $public ? Event::assertDispatched(QueueUpdatedEvent::class) : Event::assertNotDispatched(QueueUpdatedEvent::class);
        $public ? Event::assertNotDispatched(PendingRequestResolvedEvent::class) : Event::assertDispatched(PendingRequestResolvedEvent::class);
    })->with([[RequestStatus::Queued, true], [RequestStatus::Pending, false]]);
});

describe('pending list', function () {
    it('shows every pending request to the host and moderators', function (string $who) {
        $first = pendingFor($this->guest);
        $second = pendingFor($this->other);
        pendingFor($this->guest, RequestStatus::Queued);
        asMember($this->{$who});

        $this->getJson('/api/v1/parties/ABCD/requests/pending')->assertOk()
            ->assertJsonPath('data.*.id', [$first->id, $second->id]);
    })->with(['host', 'moderator']);

    it('shows a requester only their own pending requests', function () {
        $mine = pendingFor($this->guest);
        pendingFor($this->other);
        asMember($this->guest);

        $this->getJson('/api/v1/parties/ABCD/requests/pending')->assertOk()->assertJsonPath('data.*.id', [$mine->id]);
    });

    it('refuses non-members and banned members', function () {
        $banned = PartyMember::factory()->for($this->party)->banned()->create();

        asMember($banned);
        $this->getJson('/api/v1/parties/ABCD/requests/pending')->assertForbidden();
        Sanctum::actingAs(PartyMember::factory()->create()->user);
        $this->getJson('/api/v1/parties/ABCD/requests/pending')->assertForbidden();
    });
});

describe('web', function () {
    beforeEach(fn () => $this->withoutVite());

    it('renders the pending page and applies decisions through the same actions', function () {
        $approve = pendingFor($this->guest);
        $reject = pendingFor($this->guest);
        $remove = pendingFor($this->guest);

        $this->actingAs($this->moderator->user)->get(route('parties.requests.pending', ['party' => 'ABCD']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Party/Pending')->where('canModerate', true)->has('requests', 3));

        $this->post(route('parties.requests.approve', ['party' => 'ABCD', 'trackRequest' => $approve->id]))->assertRedirect();
        $this->post(route('parties.requests.reject', ['party' => 'ABCD', 'trackRequest' => $reject->id]), ['reason' => 'No'])->assertRedirect();
        $this->delete(route('parties.requests.destroy', ['party' => 'ABCD', 'trackRequest' => $remove->id]))->assertRedirect();

        expect($approve->fresh()->status)->toBe(RequestStatus::Queued)
            ->and($reject->fresh()->status)->toBe(RequestStatus::Rejected)
            ->and($reject->fresh()->rejection_reason)->toBe('No')
            ->and($remove->fresh()->status)->toBe(RequestStatus::Removed);
    });

    it('refuses guests and reports conflicts', function () {
        $pending = pendingFor($this->other);
        $queued = pendingFor($this->guest, RequestStatus::Queued);
        pendingFor($this->guest);

        $this->actingAs($this->guest->user)
            ->post(route('parties.requests.approve', ['party' => 'ABCD', 'trackRequest' => $pending->id]))->assertForbidden();
        $this->delete(route('parties.requests.destroy', ['party' => 'ABCD', 'trackRequest' => $pending->id]))->assertForbidden();
        $this->get(route('parties.requests.pending', ['party' => 'ABCD']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canModerate', false)->has('requests', 1));

        $this->actingAs($this->host->user)
            ->post(route('parties.requests.approve', ['party' => 'ABCD', 'trackRequest' => $queued->id]))
            ->assertSessionHasErrors('request');
    });
});

describe('settings', function () {
    beforeEach(fn () => $this->withoutVite());

    it('exposes and updates the hold flag on web and api', function () {
        $this->party->forceFill(['hold_requests' => false])->save();

        $this->actingAs($this->host->user)->get(route('parties.settings', ['party' => 'ABCD']))
            ->assertInertia(fn (Assert $page) => $page->where('settings.hold_requests', false));
        $this->patch(route('parties.update', ['party' => 'ABCD']), ['hold_requests' => true])->assertRedirect();
        expect($this->party->fresh()->hold_requests)->toBeTrue();

        asMember($this->host);
        $this->patchJson('/api/v1/parties/ABCD', ['hold_requests' => false])->assertOk()->assertJsonPath('data.hold_requests', false);
        $this->patchJson('/api/v1/parties/ABCD', ['hold_requests' => 'maybe'])->assertUnprocessable();

        expect(PartyLogEntry::query()->where('action', 'party.settings_changed')->where('subject', 'hold_requests')->count())->toBe(2);
    });

    it('does not log an unchanged hold flag', function () {
        $this->actingAs($this->host->user)->patch(route('parties.update', ['party' => 'ABCD']), ['hold_requests' => true])->assertRedirect();

        expect(PartyLogEntry::query()->where('action', 'party.settings_changed')->count())->toBe(0);
    });
});
