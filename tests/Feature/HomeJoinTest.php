<?php

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\User;
use App\Services\SocialProviders\DiscordProvider;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

it('lists a member\'s parties on Home with live state and role', function () {
    $user = User::factory()->create(['first_login' => false]);
    $party = Party::factory()->create(['user_id' => $user->id, 'name' => 'Mine']);

    $this->actingAs($user)->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home', false)
            ->has('parties', 1)
            ->where('parties.0.code', $party->code)
            ->where('parties.0.role', 'Owner')
            ->where('parties.0.playback', 'idle'));
});

it('accepts four letters in any case and goes to the party', function (string $typed) {
    $party = Party::factory()->create(['code' => 'KXQB']);

    $this->actingAs(User::factory()->create(['first_login' => false]))
        ->post(route('parties.join'), ['code' => $typed])
        ->assertRedirect(route('parties.show', $party->code));
})->with(['KXQB', 'kxqb', ' kxQb ']);

it('rejects codes that are not exactly four letters', function (string $typed, string $message) {
    Party::factory()->create(['code' => 'KXQB']);

    $this->actingAs(User::factory()->create(['first_login' => false]))
        ->post(route('parties.join'), ['code' => $typed])
        ->assertSessionHasErrors(['code' => $message]);
})->with([
    'digit' => ['KX9B', 'Party codes are four letters, no digits.'],
    'too short' => ['KXQ', 'Party codes are four letters, no digits.'],
    'too long' => ['KXQBZ', 'Party codes are four letters, no digits.'],
    'empty' => ['', 'Enter the four-letter party code.'],
    'unknown' => ['ZZZZ', "We can't find a party with that code. Check the TV or ask the host."],
]);

it('lets a guest enter a code and sends them to login', function () {
    $party = Party::factory()->create(['code' => 'KXQB']);

    $this->post(route('parties.join'), ['code' => 'kxqb'])
        ->assertRedirect(route('parties.show', $party->code));
    $this->get(route('parties.show', $party->code))->assertRedirect(route('login'));
});

it('shows the party on the login page for a guest who came from its link', function () {
    $party = Party::factory()->create(['code' => 'KXQB', 'name' => 'Coyote LAN 26']);

    $this->get('/parties/KXQB')->assertRedirect(route('login'));

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->component('Users/Login', false)
        ->where('party.code', 'KXQB')
        ->where('party.name', $party->name)
        ->where('party.host', $party->user->nickname));
});

it('shows no party on the login page without a party link', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('party', null));
});

it('returns the guest to the party after login', function () {
    $this->seed(RolesSeeder::class);
    $provider = providerFor(DiscordProvider::class, 'discord');
    $account = LinkedAccount::factory()->create([
        'social_provider_id' => $provider->id,
        'external_id' => 'd1',
        'email' => 'a@example.com',
    ]);
    $account->user->forceFill(['first_login' => false])->save();
    $party = Party::factory()->create(['code' => 'KXQB']);
    fakeRemoteUser('d1', 'a@example.com', ['verified' => true]);

    $this->get('/parties/KXQB')->assertRedirect(route('login'));
    $this->get(route('login.return', 'discord'))->assertRedirect(route('parties.show', $party->code));

    $this->assertAuthenticatedAs($account->user);
});

it('keeps the party through the nickname step for a first-time guest', function () {
    $this->seed(RolesSeeder::class);
    $party = Party::factory()->create(['code' => 'KXQB']);
    providerFor(DiscordProvider::class, 'discord');
    fakeRemoteUser('d2', 'new@example.com', ['verified' => true]);

    $this->get('/parties/KXQB');
    $this->get(route('login.return', 'discord'))->assertRedirect(route('parties.show', $party->code));
    $this->get(route('parties.show', $party->code))->assertRedirect(route('login.signup'));

    $this->get(route('login.signup'))->assertInertia(fn (Assert $page) => $page
        ->component('Users/Signup', false)
        ->where('party.code', 'KXQB'));

    $this->put(route('login.signup.process'), ['nickname' => 'Ravi'])
        ->assertRedirect(route('parties.show', $party->code));
});

it('says the Fallback Playlist is next for an idle party that has one', function () {
    $user = User::factory()->create(['first_login' => false]);
    Party::factory()->create(['user_id' => $user->id, 'backup_playlist_id' => 'pl1']);

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('parties.0.playback', 'idle')
            ->where('parties.0.detail', 'Fallback Playlist is next'));
});

it('throttles code entry', function () {
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $this->post(route('parties.join'), ['code' => 'ZZZZ']);
    }

    $this->post(route('parties.join'), ['code' => 'ZZZZ'])->assertTooManyRequests();
});
