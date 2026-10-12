<?php

use App\Domain\Admin\SiteSettings;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Http\Resources\V1\QueueEntryResource;
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
                'selection_mode' => 'deterministic',
                'downvotes' => true,
                'historyPlaylistUrl' => null,
                'playlistCsvUrl' => null,
            ])
            ->where('membership.role', 'moderator')
            ->where('membership.banned', false)
            ->has('membership.id')
            ->where('section', 'queue')
            ->where('readOnly', false)
            ->where('nowPlaying', null)
            ->where('upNext', null));
});

it('serves each section', function (string $section) {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->create();

    $this->withoutVite()->actingAs($user)->get("/parties/ABCD/{$section}")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('section', $section));
})->with(['queue', 'search', 'history', 'party']);

it('returns 404 for an invalid section or malformed code', function (string $path) {
    Party::factory()->create(['code' => 'ABCD']);

    $this->withoutVite()->actingAs(User::factory()->create())->get($path)->assertNotFound();
})->with(['/parties/ABCD/nope', '/parties/ABCDE']);

it('redirects guests to login', function () {
    Party::factory()->create(['code' => 'ABCD']);

    $this->get('/parties/ABCD')->assertRedirect(route('login'));
});

it('joins a non-member as a guest when they open the party link', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('Party/Show')->where('membership.role', PartyRole::Guest->value));

    expect($party->memberFor($user)->role)->toBe(PartyRole::Guest);
});

it('does not duplicate an existing membership when the link is opened', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->moderator()->create();

    $this->withoutVite()->actingAs($user)->get('/parties/abcd')->assertOk();
    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')->assertOk();

    expect(PartyMember::query()->where('party_id', $party->id)->count())->toBe(1)
        ->and($party->memberFor($user)->role)->toBe(PartyRole::Moderator);
});

it('shows the join form with an error for an unknown code', function () {
    $user = User::factory()->create();

    $this->withoutVite()->actingAs($user)->get('/parties/ZZZZ')
        ->assertRedirect(route('home', ['code' => 'ZZZZ']))
        ->assertSessionHasErrors(['code' => 'No party found with that code.']);

    expect(PartyMember::query()->count())->toBe(0);
});

it('sends an anonymous visitor to login and joins them on return', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();

    $this->get('/parties/ABCD')->assertRedirect(route('login'));
    expect(session('url.intended'))->toEndWith('/parties/ABCD');

    $this->withoutVite()->actingAs($user)->get(session('url.intended'))->assertOk();

    expect($party->memberFor($user)->role)->toBe(PartyRole::Guest);
});

it('falls back to the join form when join attempts are rate limited', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();

    foreach (range(1, 20) as $attempt) {
        $other = Party::factory()->create();
        $this->withoutVite()->actingAs($user)->get('/parties/'.$other->code)->assertOk();
    }

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')->assertRedirect(route('home', ['code' => 'ABCD']));

    expect($party->memberFor($user))->toBeNull();
});

it('joins a non-member to an ended party as read-only', function () {
    $party = Party::factory()->ended()->create(['code' => 'ABCD']);
    $user = User::factory()->create();

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')
        ->assertInertia(fn (Assert $page): Assert => $page->where('readOnly', true)->where('section', 'history'));

    expect($party->memberFor($user))->not->toBeNull();
});

it('joins a logged-in visitor through the default party redirect', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();
    $this->partialMock(SiteSettings::class, fn ($mock) => $mock->shouldReceive('get')->with('default_party')->andReturn('ABCD'));

    $this->withoutVite()->actingAs($user)->get('/')->assertRedirect('/parties/ABCD');
    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')->assertOk();

    expect($party->memberFor($user)->role)->toBe(PartyRole::Guest);
});

it('prefills the join form from the code', function () {
    $this->withoutVite()->actingAs(User::factory()->create())->get('/?code=abcd')
        ->assertInertia(fn (Assert $page): Assert => $page->component('Home')->where('prefillCode', 'ABCD'));
});

it('still shows the page to a member', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->create();

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')->assertOk();
});

it('marks the page read-only for ended parties and banned members', function (string $scenario) {
    $party = $scenario === 'ended' ? Party::factory()->ended()->create() : Party::factory()->create();
    $user = User::factory()->create();
    $member = PartyMember::factory()->for($party)->for($user);
    ($scenario === 'banned' ? $member->banned() : $member)->create();

    $this->withoutVite()->actingAs($user)->get(route('parties.show', ['party' => $party->code]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('readOnly', true)
            ->where('membership.banned', $scenario === 'banned'));
})->with(['ended', 'banned']);

it('shares joined parties with the shell', function () {
    $party = Party::factory()->create(['code' => 'ABCD', 'name' => 'Friday LAN']);
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->create();

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
