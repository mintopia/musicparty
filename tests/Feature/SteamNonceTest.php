<?php

use App\Models\LinkedAccount;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\SocialProviders\SteamProvider;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;

const STEAM_NONCE = 'valid-nonce';

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    SocialProvider::factory()->create([
        'code' => 'steam',
        'provider_class' => SteamProvider::class,
        'enabled' => true,
        'auth_enabled' => true,
    ]);
    fakeRemoteUser('76561198000000001', null);
});

it('stores a nonce in the session when starting the login flow', function () {
    $this->get(route('login.redirect', 'steam'));

    expect(session(SteamProvider::NONCE_SESSION_KEY))->toBeString()->toHaveLength(40);
});

it('stores a nonce in the session when starting the link flow', function () {
    $this->actingAs(User::factory()->create())->get(route('linkedaccounts.create', 'steam'));

    expect(session(SteamProvider::NONCE_SESSION_KEY))->toBeString()->toHaveLength(40);
});

it('logs in with a matching nonce and clears it', function () {
    $this->withSession([SteamProvider::NONCE_SESSION_KEY => STEAM_NONCE])
        ->get(route('login.return', ['socialprovider' => 'steam', 'nonce' => STEAM_NONCE]))
        ->assertRedirect(route('home'));

    $this->assertAuthenticated();
    expect(session(SteamProvider::NONCE_SESSION_KEY))->toBeNull();
});

it('cannot reuse a nonce', function () {
    $this->withSession([SteamProvider::NONCE_SESSION_KEY => STEAM_NONCE])
        ->get(route('login.return', ['socialprovider' => 'steam', 'nonce' => STEAM_NONCE]));
    auth()->logout();

    $this->get(route('login.return', ['socialprovider' => 'steam', 'nonce' => STEAM_NONCE]))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('refuses a login callback with a missing or wrong nonce', function (?string $nonce) {
    $this->withSession([SteamProvider::NONCE_SESSION_KEY => STEAM_NONCE])
        ->get(route('login.return', array_filter(['socialprovider' => 'steam', 'nonce' => $nonce])))
        ->assertRedirect(route('login'))
        ->assertSessionHas('errorMessage');

    $this->assertGuest();
    expect(User::count())->toBe(0)->and(LinkedAccount::count())->toBe(0);
})->with([null, 'wrong']);

it('refuses a login callback when the session holds no nonce', function () {
    $this->get(route('login.return', ['socialprovider' => 'steam', 'nonce' => STEAM_NONCE]))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('refuses a link callback with a missing or wrong nonce', function (?string $nonce) {
    $victim = User::factory()->create();

    $this->actingAs($victim)
        ->withSession([SteamProvider::NONCE_SESSION_KEY => STEAM_NONCE])
        ->get(route('linkedaccounts.store', array_filter(['socialprovider' => 'steam', 'nonce' => $nonce])))
        ->assertRedirect(route('user.profile'))
        ->assertSessionHas('errorMessage');

    expect(LinkedAccount::count())->toBe(0);
})->with([null, 'wrong']);

it('does not link an attacker steam account when a captured callback is replayed in the victim session', function () {
    $victim = User::factory()->create();

    $this->actingAs($victim)
        ->get(route('linkedaccounts.store', ['socialprovider' => 'steam', 'nonce' => 'captured-from-attacker-session']))
        ->assertRedirect(route('user.profile'));

    expect($victim->accounts()->count())->toBe(0)->and(LinkedAccount::count())->toBe(0);
});

it('links with a matching nonce', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([SteamProvider::NONCE_SESSION_KEY => STEAM_NONCE])
        ->get(route('linkedaccounts.store', ['socialprovider' => 'steam', 'nonce' => STEAM_NONCE]))
        ->assertRedirect(route('user.profile'));

    expect($user->accounts()->count())->toBe(1);
});

it('configures the callback return_to with the nonce it sent to Steam', function () {
    app()->forgetInstance(Factory::class);
    Socialite::clearResolvedInstances();
    $nonce = str_repeat('a', 40);
    app()->instance('request', Request::create('/return', 'GET', ['nonce' => $nonce]));
    $steam = new SteamProvider(SocialProvider::whereCode('steam')->sole(), 'https://example.test/return');

    $driver = (fn () => $this->getSocialiteProvider())->call($steam);

    expect((fn () => $this->redirectUrl)->call($driver))->toBe('https://example.test/return?nonce='.$nonce);
});

it('derives the steam realm host from the app url, not the request host', function () {
    config(['app.url' => 'https://music.example.com']);
    app()->forgetInstance(Factory::class);
    Socialite::clearResolvedInstances();
    app()->instance('request', Request::create('https://evil.example/return', 'GET', ['nonce' => 'n']));
    $steam = new SteamProvider(SocialProvider::whereCode('steam')->sole(), 'https://music.example.com/return');

    $driver = (fn () => $this->getSocialiteProvider())->call($steam);

    expect((fn () => $this->config['allowed_hosts'])->call($driver))->toBe('music.example.com');
});
