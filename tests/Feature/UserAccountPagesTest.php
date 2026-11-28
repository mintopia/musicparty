<?php

use App\Enums\SettingType;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SocialProvider;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function accountPagesUser(array $attributes = []): User
{
    return User::factory()->create(['first_login' => false, ...$attributes]);
}

function setDefaultParty(?string $code): void
{
    Setting::query()->where('code', 'defaultparty')->delete();
    if ($code !== null) {
        Setting::query()->forceCreate(['code' => 'defaultparty', 'name' => 'Default Party', 'type' => SettingType::stString, 'value' => $code]);
    }
    Setting::query()->get()->each->clearCache();
}

beforeEach(fn () => $this->withoutVite());

afterEach(fn () => Setting::query()->get()->each->clearCache());

it('sends guests to login from the authenticated account pages', function (string $route) {
    $this->get(route($route, $route === 'linkedaccounts.delete' ? 1 : []))
        ->assertRedirect();
})->with(['user.profile', 'user.profile.edit', 'user.spotify', 'login.signup', 'linkedaccounts.delete']);

it('renders the Home page with the member parties', function () {
    setDefaultParty(null);
    $user = accountPagesUser();

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home', false)
            ->has('parties', 0));
});

it('redirects Home to the default party when one is set', function () {
    $party = Party::factory()->create();
    setDefaultParty($party->code);

    $this->actingAs(accountPagesUser())->get(route('home'))
        ->assertRedirect(route('parties.show', $party->code));
});

it('renders the Login page with only auth-enabled providers', function () {
    SocialProvider::factory()->create(['code' => 'alpha', 'name' => 'Alpha']);
    SocialProvider::factory()->create(['code' => 'beta', 'name' => 'Beta', 'auth_enabled' => false]);
    SocialProvider::factory()->create(['code' => 'gamma', 'name' => 'Gamma', 'enabled' => false]);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Login', false)
            ->has('providers', 1)
            ->where('providers.0', ['code' => 'alpha', 'name' => 'Alpha']));
});

it('renders the Signup page with terms and privacy', function () {
    Setting::query()->forceCreate(['code' => 'terms', 'name' => 'Terms', 'type' => SettingType::stString, 'value' => 'Be nice']);
    Setting::query()->forceCreate(['code' => 'privacypolicy', 'name' => 'Privacy', 'type' => SettingType::stString, 'value' => 'No secrets']);
    Setting::query()->get()->each->clearCache();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('login.signup'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Signup', false)
            ->where('user.nickname', $user->nickname)
            ->where('user.avatar', $user->avatarUrl())
            ->where('terms', 'Be nice')
            ->where('privacy', 'No secrets'));
});

it('validates and completes signup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('login.signup.process'), [])
        ->assertSessionHasErrors();

    $this->actingAs($user)->put(route('login.signup.process'), ['nickname' => 'Newbie', 'terms' => 'on'])
        ->assertRedirect(route('home'));
    expect($user->fresh()->first_login)->toBeFalse();
});

it('renders the Profile page with accounts and available links', function () {
    $user = accountPagesUser();
    $linked = SocialProvider::factory()->create(['code' => 'alpha', 'name' => 'Alpha']);
    SocialProvider::factory()->create(['code' => 'beta', 'name' => 'Beta']);
    $account = LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => $linked->id, 'name' => 'Ally']);

    $this->actingAs($user)->get(route('user.profile'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Profile', false)
            ->where('user.nickname', $user->nickname)
            ->where('user.email', $user->getEmail())
            ->where('user.avatar', $user->avatarUrl())
            ->has('accounts', 1)
            ->where('accounts.0.id', $account->id)
            ->where('accounts.0.providerCode', 'alpha')
            ->where('accounts.0.providerName', 'Alpha')
            ->where('accounts.0.name', 'Ally')
            ->where('accounts.0.avatarUrl', $account->avatar_url)
            ->where('accounts.0.canDelete', false)
            ->where('availableLinks', [['code' => 'beta', 'name' => 'Beta']]));
});

it('renders the Edit page', function () {
    $user = accountPagesUser();

    $this->actingAs($user)->get(route('user.profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Edit', false)
            ->where('user.nickname', $user->nickname));
});

it('redirects Spotify to the profile when the user cannot create parties', function () {
    $this->actingAs(accountPagesUser())->get(route('user.spotify'))
        ->assertRedirect(route('user.profile'))
        ->assertSessionHas('errorMessage');
});

it('renders the Spotify page with the linked spotify accounts', function () {
    $user = accountPagesUser();
    $user->roles()->attach(Role::forceCreate(['code' => 'create-party', 'name' => 'Create Party']));
    $spotify = SocialProvider::factory()->create(['code' => 'spotify']);
    LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => $spotify->id, 'name' => 'DJ']);

    $this->actingAs($user)->get(route('user.spotify'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Spotify', false)
            ->where('spotify', ['name' => 'DJ'])
            ->where('search', null)
            ->where('spotifyEnabled', true)
            ->where('searchEnabled', false));
});

it('renders the linked account delete confirmation', function () {
    $user = accountPagesUser();
    $provider = SocialProvider::factory()->create(['name' => 'Alpha']);
    $account = LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => $provider->id]);
    LinkedAccount::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('linkedaccounts.delete', $account))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/LinkedAccountDelete', false)
            ->where('account', ['id' => $account->id, 'providerName' => 'Alpha']));
});

it('redirects the delete confirmation to the profile when removal is not allowed', function () {
    $user = accountPagesUser();
    $account = LinkedAccount::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('linkedaccounts.delete', $account))
        ->assertRedirect(route('user.profile'))
        ->assertSessionHas('errorMessage');
});
