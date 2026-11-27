<?php

use App\Models\Party;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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
