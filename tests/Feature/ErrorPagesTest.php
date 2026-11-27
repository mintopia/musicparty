<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/DashboardFixtures.php';

beforeEach(function () {
    config(['app.debug' => false]);
});

it('renders a branded 404 for guests without leaking exception text', function () {
    $this->get('/no/such/page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('partyNotFound', false)
            ->where('breadcrumbs.1.label', 'Page not found')
            ->where('auth.user', null));
});

it('renders a branded 404 for logged-in users', function () {
    $this->actingAs(User::factory()->create())
        ->get('/no/such/page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('auth.user.id', fn ($id) => $id !== null));
});

it('offers party code entry when the party code is unknown for a guest', function () {
    $response = $this->get(route('parties.tv', 'ZZZZ'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('partyNotFound', true)
            ->where('title', 'No party with that code'));

    expect($response->getContent())->not->toContain('No query results')->not->toContain('ModelNotFoundException');
});

it('offers party code entry when the party code is unknown for a logged-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('parties.tv', 'ZZZZ'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('partyNotFound', true));
});

it('does not treat other missing models as a missing party', function () {
    $party = Party::factory()->create();

    $this->get(route('parties.tv', $party->code))->assertOk();
    $this->get('/theme/missing.css')
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('partyNotFound', false));
});

it('keeps the default JSON response for JSON requests', function () {
    $this->getJson('/no/such/page')->assertNotFound()->assertJsonMissing(['component' => 'Error']);
});

it('does not render the Inertia error page in debug mode', function () {
    config(['app.debug' => true]);

    $response = $this->get('/no/such/page')->assertNotFound();

    expect($response->headers->get('X-Inertia'))->toBeNull()
        ->and($response->getContent())->not->toContain('"component":"Error"');
});

it('never exposes the exception message for a 500 in production', function () {
    app('router')->middleware('web')->get('/boom', fn () => throw new RuntimeException('secret-db-password'));

    $response = $this->get('/boom')->assertStatus(500);

    expect($response->getContent())->not->toContain('secret-db-password')->toContain('"component":"Error"');
});

it('passes the typed party code uppercased for pre-filling', function () {
    $this->get(route('parties.tv', 'kx9b'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('typedCode', 'KX9B')->where('actions', []));
});

it('names the party, role and owner on a 403 for a plain member', function () {
    $party = dashboardParty();
    $member = dashboardUser($party, 'user');
    $owner = $party->user->nickname;

    $this->actingAs($member)->get(route('parties.dashboard', $party))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 403)
            ->where('icon', 'ti-lock')
            ->where('title', "You can't open Dashboard")
            ->where('message', "Only the owner and moderators of {$party->name} can. You're in this party as a user. Ask {$owner}, the owner, if you need access.")
            ->where('actions.0.label', 'Back to the Queue')
            ->where('actions.0.href', route('parties.show', $party))
            ->where('breadcrumbs.1.label', $party->name)
            ->where('breadcrumbs.2.label', 'Not allowed'));
});

it('limits a 403 to the owner on owner-only pages and treats non-members as users', function () {
    $party = dashboardParty();

    $this->actingAs(User::factory()->create(['first_login' => false]))->get(route('parties.mods.edit', $party))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->where('title', "You can't open Settings")
            ->where('message', fn (string $message) => str_starts_with($message, "Only the owner of {$party->name} can. You're in this party as a user.")));
});

it('shows a distinct banned 403', function () {
    $party = dashboardParty();
    $banned = User::factory()->create(['first_login' => false]);
    PartyMember::factory()->banned()->for($party)->for($banned)->create();

    $this->actingAs($banned)->get(route('parties.dashboard', $party))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->where('icon', 'ti-ban')
            ->where('title', "You can't join {$party->name}")
            ->where('message', "The host has removed you from this party. Ask {$party->user->nickname}, the owner, if you think that's a mistake.")
            ->where('actions.0.label', 'Go to Home'));
});

it('keeps the plain 403 copy when there is no party', function () {
    $this->actingAs(User::factory()->create(['first_login' => false]))->get(route('admin.dashboard'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->where('title', "You can't open that page")->where('icon', null));
});

it('explains a disabled AI reviews feature with a settings link for owners only', function () {
    Artisan::call('mods:sync');
    $party = dashboardParty();
    app(PartyMods::class)->disable($party, AiRequestReviewMod::CODE);
    $moderator = dashboardUser($party, 'moderator');

    $this->actingAs($party->user)->get(route('parties.ai-reviews.index', $party))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->where('partyNotFound', false)
            ->where('icon', 'ti-robot-off')
            ->where('title', 'AI reviews are off for this party')
            ->where('message', 'Turn on the AI Request Review mod to have new Requests checked against your guidelines.')
            ->where('actions.0.label', 'Open Mods settings')
            ->where('actions.0.href', route('parties.mods.edit', $party))
            ->where('actions.1.label', 'Go to Moderation')
            ->where('breadcrumbs.1.label', $party->name)
            ->where('breadcrumbs.2.label', 'Moderation'));

    $this->actingAs($moderator)->get(route('parties.ai-reviews.index', $party))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->where('message', "The host hasn't turned this on. Moderators still approve or reject Flagged Requests by hand.")
            ->has('actions', 1)
            ->where('actions.0.label', 'Go to Moderation')
            ->where('actions.0.href', route('parties.moderation.index', $party)));
});
