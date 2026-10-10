<?php

use App\Domain\Party\PartyRole;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\RequestStatus;
use App\Http\Resources\V1\QueueEntryResource;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the party page with exact props', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD', 'name' => 'Friday LAN']);
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->moderator()->create();

    $this->withoutVite()->actingAs($user)->get('/parties/abcd')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Party/Show')
            ->where('party', [
                'code' => 'ABCD',
                'name' => 'Friday LAN',
                'state' => 'live',
                'musicProvider' => 'fake',
                'playerKind' => 'fake',
                'downvotes' => true,
            ])
            ->where('membership', ['role' => 'moderator', 'banned' => false])
            ->where('section', 'queue')
            ->where('readOnly', false)
            ->where('nowPlaying', null)
            ->where('upNext', null));
});

it('serves each section', function (string $section) {
    $party = Party::factory()->create(['code' => 'ABCD']);

    $this->withoutVite()->actingAs(User::factory()->create())->get("/parties/ABCD/{$section}")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('section', $section));
})->with(['queue', 'search', 'history', 'party']);

it('returns 404 for an invalid section or unknown party', function (string $path) {
    Party::factory()->create(['code' => 'ABCD']);

    $this->withoutVite()->actingAs(User::factory()->create())->get($path)->assertNotFound();
})->with(['/parties/ABCD/nope', '/parties/ZZZZ', '/parties/ABCDE']);

it('redirects guests to login', function () {
    Party::factory()->create(['code' => 'ABCD']);

    $this->get('/parties/ABCD')->assertRedirect(route('login'));
});

it('auto-joins a non-member as a guest', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')->assertOk();
    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')->assertOk();

    expect(PartyMember::query()->where('party_id', $party->id)->count())->toBe(1)
        ->and($party->memberFor($user)->role)->toBe(PartyRole::Guest);
});

it('marks the page read-only for ended parties and banned members', function (string $scenario) {
    $party = $scenario === 'ended' ? Party::factory()->ended()->create() : Party::factory()->create();
    $user = User::factory()->create();
    if ($scenario === 'banned') {
        PartyMember::factory()->for($party)->for($user)->banned()->create();
    }

    $this->withoutVite()->actingAs($user)->get(route('parties.show', ['party' => $party->code]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('readOnly', true)
            ->where('membership.banned', $scenario === 'banned'));
})->with(['ended', 'banned']);

it('shares joined parties with the shell', function () {
    $party = Party::factory()->create(['code' => 'ABCD', 'name' => 'Friday LAN']);
    $user = User::factory()->create();

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD');

    $this->withoutVite()->actingAs($user->fresh())->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('parties', [['code' => 'ABCD', 'name' => 'Friday LAN']]));
});

it('passes now playing and up next, rendering requester-less fallback requests', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->create();
    TrackRequest::factory()->for($party)->fallback()->status(RequestStatus::Playing)->create(['title' => 'Now']);
    TrackRequest::factory()->for($party)->status(RequestStatus::UpNext)->create(['title' => 'Next']);
    TrackRequest::factory()->for($party)->fallback()->create(['title' => 'Later']);

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('nowPlaying.track.title', 'Now')
            ->where('nowPlaying.status', 'playing')
            ->where('nowPlaying.requested_by', ['name' => null])
            ->where('upNext.track.title', 'Next')
            ->where('upNext.requested_by.name', fn ($name): bool => $name !== null)
            ->has('queue', 1)
            ->where('queue.0.requested_by', ['name' => null]));
});

it('serialises requester-less requests in the snapshot and the queue resource', function () {
    $party = Party::factory()->live()->create();
    $request = TrackRequest::factory()->for($party)->fallback()->create();

    $snapshot = app(PartyQueueSnapshot::class)->build($party);
    $resource = new QueueEntryResource($request)->resolve();

    expect($snapshot['queue'][0]['requested_by'])->toBe(['name' => null])
        ->and($resource['requested_by'])->toBe(['name' => null]);
});
