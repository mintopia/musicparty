<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\QueueUpdatedEvent;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->user = User::factory()->create();
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
});

function apiRequest(User $user, string $trackId = 'track-1', string $code = 'ABCD')
{
    Sanctum::actingAs($user);

    return test()->postJson("/api/v1/parties/{$code}/requests", ['provider_track_id' => $trackId]);
}

it('creates a queued request with the requester upvote through the API', function () {
    Event::fake([QueueUpdatedEvent::class]);

    apiRequest($this->user)->assertCreated()
        ->assertJsonPath('data.track.title', 'Alpha Song')
        ->assertJsonPath('data.track.artists', ['Test Artist'])
        ->assertJsonPath('data.score', 1)
        ->assertJsonPath('data.my_vote', 1)
        ->assertJsonPath('data.requested_by.name', $this->user->nickname);

    $request = TrackRequest::query()->sole();
    expect($request->status)->toBe(RequestStatus::Queued)
        ->and($request->party_member_id)->toBe($this->member->id)
        ->and($request->votes()->sole()->value)->toBe(1);
    Event::assertDispatched(QueueUpdatedEvent::class, fn ($event) => $event->broadcastWith() === ['code' => 'ABCD']);
});

it('adds an upvote to the existing request when the track is already queued', function () {
    apiRequest($this->user)->assertCreated();
    $other = PartyMember::factory()->for($this->party)->create();

    apiRequest($other->user)->assertOk()->assertJsonPath('data.score', 2)->assertJsonPath('meta.duplicate', true);

    expect(TrackRequest::query()->count())->toBe(1)->and(RequestVote::query()->count())->toBe(2);
});

it('does not record a second vote when the same member requests it again', function () {
    apiRequest($this->user)->assertCreated();
    apiRequest($this->user)->assertOk()->assertJsonPath('data.score', 1);

    expect(RequestVote::query()->count())->toBe(1);
});

it('refuses a banned member and records no vote on a duplicate', function () {
    apiRequest($this->user)->assertCreated();
    $banned = PartyMember::factory()->for($this->party)->banned()->create();

    apiRequest($banned->user)->assertForbidden()->assertJsonStructure(['message']);

    expect(RequestVote::query()->count())->toBe(1)->and(TrackRequest::query()->count())->toBe(1);
});

it('refuses a banned member a new request', function () {
    $banned = PartyMember::factory()->for($this->party)->banned()->create();

    apiRequest($banned->user, 'track-2')->assertForbidden();

    expect(TrackRequest::query()->count())->toBe(0);
});

it('refuses a non-member', function () {
    apiRequest(User::factory()->create())->assertForbidden();

    expect(TrackRequest::query()->count())->toBe(0);
});

it('refuses requests when the party is not live', function (string $state) {
    $this->party->forceFill(['state' => $state])->save();

    apiRequest($this->user)->assertUnprocessable()->assertJsonPath('message', 'This party is not live, so requests are closed.');

    expect(TrackRequest::query()->count())->toBe(0);
})->with(['paused', 'ended']);

it('refuses an unknown or unplayable track', function (string $trackId) {
    apiRequest($this->user, $trackId)->assertUnprocessable();
})->with(['unknown' => 'nope', 'region locked' => 'track-4']);

it('refuses with a service message when the provider is rate limited', function () {
    app(FakeMusicProvider::class)->rateLimitNext(5);

    apiRequest($this->user)->assertStatus(503);
});

it('validates the request body', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/parties/ABCD/requests', [])->assertUnprocessable()->assertJsonValidationErrors('provider_track_id');
});

it('requires authentication', function () {
    $this->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => 'track-1'])->assertUnauthorized();
    $this->getJson('/api/v1/parties/ABCD/queue')->assertUnauthorized();
});

it('searches the provider through the API and flags queued tracks', function () {
    apiRequest($this->user)->assertCreated();

    $this->getJson('/api/v1/parties/ABCD/search?q=song')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.provider_track_id', 'track-1')
        ->assertJsonPath('data.0.queued', true)
        ->assertJsonPath('data.1.queued', false);
});

it('requires a search query and membership', function () {
    Sanctum::actingAs($this->user);
    $this->getJson('/api/v1/parties/ABCD/search')->assertUnprocessable()->assertJsonValidationErrors('q');
    $this->getJson('/api/v1/parties/ABCD/search?q=%20%20')->assertUnprocessable();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/parties/ABCD/search?q=song')->assertForbidden();
});

it('returns an empty result for a query nothing matches', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/parties/ABCD/search?q=zzzz')->assertOk()->assertJsonCount(0, 'data');
});

it('orders the queue by score then oldest and reports my vote', function () {
    $older = TrackRequest::factory()->for($this->party)->for($this->member, 'requester')->create(['created_at' => now()->subMinutes(10)]);
    $newer = TrackRequest::factory()->for($this->party)->for($this->member, 'requester')->create(['created_at' => now()->subMinutes(5)]);
    $popular = TrackRequest::factory()->for($this->party)->for($this->member, 'requester')->create(['created_at' => now()]);
    $removed = TrackRequest::factory()->for($this->party)->for($this->member, 'requester')->create(['status' => RequestStatus::Removed]);
    $other = PartyMember::factory()->for($this->party)->create();
    RequestVote::factory()->for($older, 'request')->for($this->member, 'member')->create();
    RequestVote::factory()->for($newer, 'request')->for($this->member, 'member')->create();
    RequestVote::factory()->for($popular, 'request')->for($this->member, 'member')->create();
    RequestVote::factory()->for($popular, 'request')->for($other, 'member')->create();
    RequestVote::factory()->down()->for($removed, 'request')->for($other, 'member')->create();
    RequestVote::factory()->down()->for($older, 'request')->for($other, 'member')->create();

    Sanctum::actingAs($this->user);
    $response = $this->getJson('/api/v1/parties/ABCD/queue')->assertOk();

    expect($response->json('data.*.id'))->toBe([$popular->id, $newer->id, $older->id])
        ->and($response->json('data.*.score'))->toBe([2, 1, 0])
        ->and($response->json('data.*.my_vote'))->toBe([1, 1, 1]);
});

it('keeps the queue private to members', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/parties/ABCD/queue')->assertForbidden();
});

it('requests a track through the web and flashes success', function () {
    $this->actingAs($this->user)->from('/parties/ABCD')
        ->post(route('parties.requests.store', ['party' => 'ABCD']), ['provider_track_id' => 'track-1'])
        ->assertRedirect('/parties/ABCD')
        ->assertSessionHas('success');

    expect(TrackRequest::query()->sole()->votes()->count())->toBe(1);
});

it('flashes a request error for a refused web request', function () {
    $this->party->forceFill(['state' => 'paused'])->save();

    $this->actingAs($this->user)->post(route('parties.requests.store', ['party' => 'ABCD']), ['provider_track_id' => 'track-1'])
        ->assertSessionHasErrors('request');
    $this->actingAs($this->user)->post(route('parties.requests.store', ['party' => 'ABCD']), [])
        ->assertSessionHasErrors('provider_track_id');
    $this->actingAs(User::factory()->create())->post(route('parties.requests.store', ['party' => 'ABCD']), ['provider_track_id' => 'track-1'])
        ->assertSessionHasErrors('request');
});

it('shares queue and search props on the party page', function () {
    Sanctum::actingAs($this->user);
    $this->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => 'track-1'])->assertCreated();

    $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD?q=alpha')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('search_query', 'alpha')
            ->has('queue', 1)
            ->where('queue.0.track.title', 'Alpha Song')
            ->where('queue.0.score', 1)
            ->where('queue.0.my_vote', 1)
            ->has('results', 1)
            ->where('results.0.queued', true)
            ->where('results.0.provider_track_id', 'track-1'));
});

it('has null results and empty query without q', function () {
    $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD')
        ->assertInertia(fn (Assert $page): Assert => $page->where('search_query', '')->where('results', null)->where('queue', []));
});

it('matches web and API queue output', function () {
    apiRequest($this->user)->assertCreated();
    $api = $this->getJson('/api/v1/parties/ABCD/queue')->json('data');

    $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD')
        ->assertInertia(fn (Assert $page): Assert => $page->where('queue', $api));
});
