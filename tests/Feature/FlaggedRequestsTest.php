<?php

use App\Enums\PartyModerationAction;
use App\Enums\PartyModerationType;
use App\Events\FlaggedRequestAdded;
use App\Events\FlaggedRequestRejected;
use App\Events\FlaggedRequestResolved;
use App\Events\UpcomingSong\UpdatedEvent;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyModeration;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\QueueSelector;
use App\Services\Requests\RequestMode;
use App\Services\Requests\RequestRules;
use App\Services\Requests\TrackEligibility;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;

function flaggedPost(string $trackId = 't1')
{
    return test()->postJson(route('api.v1.parties.upcomingsongs.store', [test()->party]), ['provider_track_id' => $trackId]);
}

function flaggedRequest(Party $party, ?User $user = null, array $attributes = []): UpcomingSong
{
    return UpcomingSong::factory()->for($party)->for(Song::factory()->create(['provider_id' => 'fake']))->for($user ?? User::factory()->create())->create(array_merge([
        'queued_at' => null,
        'flagged_at' => now(),
        'flag_reason' => 'Needs a look',
    ], $attributes));
}

beforeEach(function () {
    app(FakeMusicProvider::class)->withTrack(FakeFallbackPlaylist::track('t1'))->withTrack(FakeFallbackPlaylist::track('t2'));
    $this->party = Party::factory()->create(['provider_id' => 'fake', 'active' => true, 'flag_requests' => true]);
    PartyModeration::factory()->flagging()->for($this->party)->create(['type' => PartyModerationType::mtId, 'value' => 't1']);
    $this->member = PartyMember::factory()->for($this->party)->for(User::factory())->create();
    $this->withoutVite();
    User::query()->update(['first_login' => false]);
    $this->manager = $this->party->user->fresh();
});

describe('submitting a flagged request', function () {
    it('holds it for approval when the party flags requests', function () {
        Event::fake([FlaggedRequestAdded::class]);
        Sanctum::actingAs($this->member->user);

        flaggedPost()->assertStatus(202)->assertJson(['awaiting_approval' => true]);

        $request = UpcomingSong::sole();
        expect($request->flagged_at)->not->toBeNull()
            ->and($request->flag_reason)->toBe('Track is not allowed')
            ->and($request->user_id)->toBe($this->member->user_id);
        Event::assertDispatched(FlaggedRequestAdded::class, fn ($event) => $event->item['id'] === $request->id
            && $event->item['requester']['id'] === $this->member->user_id
            && $event->item['reason'] === 'Track is not allowed'
            && $event->item['track']['id'] === 't1'
            && $event->broadcastOn()->name === "private-party.{$this->party->code}.managers"
            && $event->broadcastAs() === 'FlaggedRequestAdded');
    });

    it('keeps the scheduled time when a scheduled request is flagged', function () {
        PartyMember::factory()->owner()->for($this->party)->for($this->manager)->create();
        Sanctum::actingAs($this->manager);

        test()->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 't1', 'scheduled_for' => '2030-01-01T20:00:00+00:00'])
            ->assertStatus(202);

        $request = UpcomingSong::sole();
        expect($request->flagged_at)->not->toBeNull()
            ->and($request->scheduled_for?->toIso8601String())->toBe('2030-01-01T20:00:00+00:00')
            ->and($request->not_before?->toIso8601String())->toBe('2030-01-01T20:00:00+00:00');
    });

    it('treats a flag as allow when the party does not flag requests', function () {
        $this->party->forceFill(['flag_requests' => false])->save();
        Sanctum::actingAs($this->member->user);

        flaggedPost()->assertSuccessful();

        expect(UpcomingSong::sole()->flagged_at)->toBeNull();
    });

    it('still rejects with a reject filter', function () {
        PartyModeration::factory()->for($this->party)->create(['type' => PartyModerationType::mtId, 'value' => 't2']);
        Sanctum::actingAs($this->member->user);

        flaggedPost('t2')->assertStatus(400);

        expect(UpcomingSong::count())->toBe(0);
    });

    it('defaults a filter to reject', function () {
        expect(PartyModeration::factory()->for($this->party)->create()->fresh()->action)->toBe(PartyModerationAction::Reject);
    });

    it('does not let another member vote on or re-request a flagged request', function () {
        $request = flaggedRequest($this->party);
        Sanctum::actingAs($this->member->user);

        $this->postJson(route('api.v1.parties.upcomingsongs.vote.store', [$this->party, $request]), ['vote' => 1])->assertStatus(400);
        flaggedPost($request->song->provider_track_id)->assertStatus(400);

        expect($request->votes()->count())->toBe(0);
    });

    it('counts a flagged request toward the limit', function () {
        $this->party->forceFill(['max_requests' => 1])->save();
        Sanctum::actingAs($this->member->user);

        flaggedPost()->assertStatus(202);
        flaggedPost('t2')->assertStatus(400);
    });

    it('frees the limit when withdrawn', function () {
        $this->party->forceFill(['max_requests' => 1])->save();
        Sanctum::actingAs($this->member->user);
        flaggedPost()->assertStatus(202);

        $this->delete(route('parties.requests.withdraw', [$this->party, UpcomingSong::sole()]))->assertRedirect();

        flaggedPost('t2')->assertSuccessful();
    });
});

describe('queue exclusion', function () {
    it('is never selected, listed or returned from the API queue', function () {
        $flagged = flaggedRequest($this->party, null, ['score' => 50]);
        $normal = flaggedRequest($this->party, null, ['score' => 1, 'flagged_at' => null, 'flag_reason' => null]);
        app(FakeMusicProvider::class)->withQueueDepth(-1);

        app(QueueSelector::class)->sendNext($this->party);

        expect($flagged->fresh()->queued_at)->toBeNull()
            ->and($normal->fresh()->queued_at)->not->toBeNull();

        $other = flaggedRequest($this->party);
        Sanctum::actingAs($this->member->user);
        $ids = collect($this->getJson(route('api.v1.parties.upcomingsongs.index', [$this->party]))->json('data'))->pluck('id');
        expect($ids)->not->toContain($other->id);
    });

    it('broadcasts the queue update only once approved', function () {
        Event::fake([UpdatedEvent::class, FlaggedRequestResolved::class]);
        $request = flaggedRequest($this->party);
        Event::assertNotDispatched(UpdatedEvent::class);

        $this->actingAs($this->manager, 'sanctum')->post(route('parties.flagged.approve', [$this->party, $request]));

        Event::assertDispatched(UpdatedEvent::class);
    });
});

describe('search annotation', function () {
    it('reports flag when on and allow when off', function () {
        $track = app(FakeMusicProvider::class)->getTrack('t1');

        $on = app(RequestRules::class)->check($this->party, $this->member, $track, RequestMode::Annotate);
        $this->party->forceFill(['flag_requests' => false])->save();
        $off = app(RequestRules::class)->check($this->party->fresh(), $this->member, $track, RequestMode::Annotate);

        expect($on->isFlagged())->toBeTrue()->and($off->isFlagged())->toBeFalse()->and($off->allowed)->toBeTrue();
    });

    it('marks the requester own flagged request as awaiting approval but hides it from others', function () {
        $request = flaggedRequest($this->party, $this->member->user);
        $request->song->forceFill(['provider_track_id' => 't1'])->save();
        $track = app(FakeMusicProvider::class)->getTrack('t1');
        $other = PartyMember::factory()->for($this->party)->for(User::factory()->create(['first_login' => false]))->create();

        $mine = app(TrackEligibility::class)->annotate($this->party, $this->member, [$track])[0];
        $theirs = app(TrackEligibility::class)->annotate($this->party, $other, [$track])[0];

        expect($mine->awaitingApproval)->toBeTrue()->and($mine->upcoming)->toBeFalse()->and($mine->upcomingId)->toBe($request->id)
            ->and($theirs->awaitingApproval)->toBeFalse()->and($theirs->upcomingId)->toBeNull();
    });
});

describe('approval queue', function () {
    it('shows managers the flagged requests', function () {
        $request = flaggedRequest($this->party, $this->member->user);

        $this->actingAs($this->manager, 'sanctum')->get(route('parties.flagged.index', $this->party->code))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Parties/Flagged')
                ->where('party.code', $this->party->code)
                ->where('flagged.0.id', $request->id)
                ->where('flagged.0.reason', 'Needs a look')
                ->where('flagged.0.requester.id', $this->member->user_id)
                ->where('flagged.0.track.id', $request->song->provider_track_id)
                ->where('flagged.0.track.eligibility.outcome', 'flag')
                ->has('flagged.0.flagged_at'));
    });

    it('refuses non-managers', function () {
        $this->actingAs($this->member->user, 'sanctum')->get(route('parties.flagged.index', $this->party->code))->assertForbidden();
        $request = flaggedRequest($this->party);
        $this->actingAs($this->member->user, 'sanctum')->post(route('parties.flagged.approve', [$this->party, $request]))->assertForbidden();
        $this->actingAs($this->member->user, 'sanctum')->post(route('parties.flagged.reject', [$this->party, $request]))->assertForbidden();
        expect($request->fresh()->flagged_at)->not->toBeNull();
    });

    it('approves into the queue', function () {
        Event::fake([FlaggedRequestResolved::class]);
        $request = flaggedRequest($this->party);

        $this->actingAs($this->manager, 'sanctum')->post(route('parties.flagged.approve', [$this->party, $request]))->assertRedirect();

        expect($request->fresh()->flagged_at)->toBeNull()->and($request->fresh()->flag_reason)->toBeNull();
        Event::assertDispatched(FlaggedRequestResolved::class, fn ($event) => $event->id === $request->id && $event->broadcastAs() === 'FlaggedRequestResolved');
    });

    it('rejects, removes the request and tells the requester', function () {
        Event::fake([FlaggedRequestResolved::class, FlaggedRequestRejected::class]);
        $request = flaggedRequest($this->party, $this->member->user);

        $this->actingAs($this->manager, 'sanctum')->post(route('parties.flagged.reject', [$this->party, $request]), ['message' => 'Not tonight'])->assertRedirect();

        expect(UpcomingSong::count())->toBe(0);
        Event::assertDispatched(FlaggedRequestResolved::class);
        Event::assertDispatched(FlaggedRequestRejected::class, fn ($event) => $event->userId === $this->member->user_id
            && $event->broadcastWith()['message'] === 'Not tonight'
            && $event->broadcastOn()->name === "private-user.{$this->member->user_id}");
    });

    it('cannot approve a request that is not flagged', function () {
        $request = flaggedRequest($this->party, null, ['flagged_at' => null]);

        $this->actingAs($this->manager, 'sanctum')->post(route('parties.flagged.approve', [$this->party, $request]))->assertNotFound();
    });
});

describe('withdrawing', function () {
    it('lets the requester withdraw their flagged request', function () {
        Event::fake([FlaggedRequestResolved::class]);
        $request = flaggedRequest($this->party, $this->member->user);

        $this->actingAs($this->member->user, 'sanctum')->delete(route('parties.requests.withdraw', [$this->party, $request]))->assertRedirect();

        expect(UpcomingSong::count())->toBe(0);
        Event::assertDispatched(FlaggedRequestResolved::class, fn ($event) => $event->id === $request->id);
    });

    it('refuses another member', function () {
        $request = flaggedRequest($this->party, $this->member->user);
        $other = PartyMember::factory()->for($this->party)->for(User::factory()->create(['first_login' => false]))->create();

        $this->actingAs($other->user, 'sanctum')->delete(route('parties.requests.withdraw', [$this->party, $request]))->assertForbidden();

        expect(UpcomingSong::count())->toBe(1);
    });

    it('refuses withdrawing a request that is already in the queue', function () {
        $request = flaggedRequest($this->party, $this->member->user, ['flagged_at' => null]);

        $this->actingAs($this->member->user, 'sanctum')->delete(route('parties.requests.withdraw', [$this->party, $request]))->assertForbidden();
    });
});

describe('manager channel', function () {
    it('authorises only managers', function () {
        $channel = fn (User $user) => Gate::forUser($user)->allows('update', $this->party);

        expect($channel($this->manager))->toBeTrue()->and($channel($this->member->user))->toBeFalse();
    });
});
