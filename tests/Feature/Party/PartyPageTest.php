<?php

use App\Domain\Party\PartyRole;
use App\Models\Party;
use App\Models\PartyMember;
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
            ])
            ->where('membership', ['role' => 'moderator', 'banned' => false])
            ->where('section', 'queue')
            ->where('readOnly', false)
            ->where('nowPlaying', null));
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
