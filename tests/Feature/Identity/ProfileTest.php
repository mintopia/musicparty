<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Contracts\Provider as SocialiteDriver;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutVite());

/**
 * @param  array<string, mixed>  $overrides
 */
function profileProvider(string $code = 'discord', array $overrides = []): SocialProvider
{
    config(["services.{$code}.client_id" => 'client-id', "services.{$code}.client_secret" => 'client-secret']);
    test()->artisan('providers:seed')->assertSuccessful();

    return tap(SocialProvider::query()->where('code', $code)->firstOrFail(), fn (SocialProvider $provider) => $provider->forceFill($overrides)->save());
}

function profileRemote(string $id = '2002', ?string $email = 'linked@example.test'): SocialiteUser
{
    return (new SocialiteUser)->map([
        'id' => $id,
        'nickname' => 'linkedname',
        'name' => 'Linked Name',
        'email' => $email,
        'avatar' => 'https://cdn.example.test/linked.png',
    ])->setToken('linked-token')->setRefreshToken('linked-refresh');
}

function profileDriver(): MockInterface
{
    $driver = Mockery::mock(SocialiteDriver::class);
    Socialite::shouldReceive('buildProvider')->andReturn($driver);

    return $driver;
}

/**
 * @param  array<string, mixed>  $account
 */
function profileUserWith(SocialProvider $provider, array $account = []): User
{
    $user = User::factory()->create(['nickname' => 'partygoer']);
    LinkedAccount::factory()->for($user)->create(array_merge(['social_provider_id' => $provider->id, 'external_id' => '1001', 'email' => 'me@example.test'], $account));

    return $user;
}

it('requires login for every profile route', function (string $method, string $uri) {
    $this->{$method}($uri)->assertRedirect();
})->with([
    ['get', '/profile'],
    ['get', '/profile/edit'],
    ['patch', '/profile'],
    ['get', '/profile/accounts/discord/link'],
    ['get', '/profile/accounts/discord/return'],
    ['get', '/profile/accounts/1/delete'],
    ['delete', '/profile/accounts/1'],
]);

it('shows the profile with linked accounts and linkable providers', function () {
    $discord = profileProvider();
    $user = profileUserWith($discord);
    SocialProvider::query()->where('code', 'twitch')->update(['enabled' => true]);
    config(['services.twitch.client_id' => 'x', 'services.twitch.client_secret' => 'y']);
    $this->artisan('providers:seed')->assertSuccessful();

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.nickname', 'partygoer')
            ->where('profile.email', 'me@example.test')
            ->has('accounts', 1)
            ->where('accounts.0.provider_code', 'discord')
            ->where('accounts.0.can_delete', false)
            ->where('available_links', fn ($links) => ! in_array('discord', array_column((array) $links, 'code'), true)));
});

it('does not offer disabled or unconfigured providers for linking', function () {
    $user = profileUserWith(profileProvider());
    SocialProvider::query()->where('code', 'twitch')->update(['enabled' => false]);
    SocialProvider::query()->where('code', 'google')->update(['enabled' => true]);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertInertia(fn (Assert $page) => $page->has('available_links', 0));
});

it('shows the edit form and updates the nickname', function () {
    $user = User::factory()->create(['nickname' => 'old']);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('Profile/Edit')->where('nickname', 'old'));

    $this->actingAs($user)->patch(route('profile.update'), ['nickname' => '  DJ Fresh '])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('success');

    expect($user->fresh()->nickname)->toBe('DJ Fresh');
});

it('rejects invalid nicknames', function (mixed $nickname) {
    $user = User::factory()->create(['nickname' => 'old']);

    $this->actingAs($user)->patch(route('profile.update'), ['nickname' => $nickname])
        ->assertSessionHasErrors('nickname');

    expect($user->fresh()->nickname)->toBe('old');
})->with([[''], [null], ['a'], [str_repeat('x', 33)], [['array']]]);

it('redirects to the provider with the profile return url', function () {
    profileProvider();
    $user = User::factory()->create();
    profileDriver()->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://discord.example.test/oauth'));

    $this->actingAs($user)->get(route('profile.accounts.link', 'discord'))->assertRedirect('https://discord.example.test/oauth');
});

it('uses the profile return url as the oauth redirect', function () {
    profileProvider();
    $user = User::factory()->create();
    $driver = Mockery::mock(SocialiteDriver::class);
    $driver->shouldReceive('redirect')->andReturn(new RedirectResponse('https://discord.example.test/oauth'));
    Socialite::shouldReceive('buildProvider')
        ->withArgs(fn ($class, array $config) => $config['redirect'] === route('profile.accounts.return', 'discord'))
        ->once()
        ->andReturn($driver);

    $this->actingAs($user)->get(route('profile.accounts.link', 'discord'))->assertRedirect('https://discord.example.test/oauth');
});

it('refuses to start linking a disabled provider', function () {
    profileProvider('discord', ['enabled' => false]);
    Socialite::shouldReceive('buildProvider')->never();

    $this->actingAs(User::factory()->create())->get(route('profile.accounts.link', 'discord'))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('error');
});

it('returns 404 for an unknown provider', function () {
    $this->actingAs(User::factory()->create())->get('/profile/accounts/myspace/link')->assertNotFound();
});

it('links a further account to the logged in user', function () {
    $discord = profileProvider();
    $twitch = profileProvider('twitch', ['enabled' => true]);
    $user = profileUserWith($discord);
    profileDriver()->shouldReceive('user')->once()->andReturn(profileRemote());

    $this->actingAs($user)->get(route('profile.accounts.return', 'twitch'))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('success');

    $account = LinkedAccount::query()->where('social_provider_id', $twitch->id)->firstOrFail();
    expect($account->user_id)->toBe($user->id)
        ->and($account->external_id)->toBe('2002')
        ->and($account->name)->toBe('linkedname')
        ->and($account->access_token)->toBe('linked-token')
        ->and(User::query()->count())->toBe(1)
        ->and(auth()->id())->toBe($user->id);
});

it('refreshes an account that is already linked to the same user', function () {
    $discord = profileProvider();
    $user = profileUserWith($discord);
    profileDriver()->shouldReceive('user')->once()->andReturn(profileRemote('1001', 'me@example.test'));

    $this->actingAs($user)->get(route('profile.accounts.return', 'discord'))->assertSessionHas('success');

    expect(LinkedAccount::query()->count())->toBe(1)
        ->and(LinkedAccount::query()->firstOrFail()->access_token)->toBe('linked-token');
});

it('refuses an account already linked to another user', function () {
    $discord = profileProvider();
    $other = profileUserWith($discord, ['email' => 'other@example.test']);
    $user = User::factory()->create();
    profileDriver()->shouldReceive('user')->once()->andReturn(profileRemote('1001', 'new@example.test'));

    $this->actingAs($user)->get(route('profile.accounts.return', 'discord'))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'another user'));

    expect(LinkedAccount::query()->count())->toBe(1)
        ->and(LinkedAccount::query()->firstOrFail()->user_id)->toBe($other->id);
});

it('refuses an account whose email belongs to another user', function () {
    $discord = profileProvider();
    $twitch = profileProvider('twitch', ['enabled' => true]);
    profileUserWith($discord, ['email' => 'shared@example.test']);
    $user = User::factory()->create();
    profileDriver()->shouldReceive('user')->once()->andReturn(profileRemote('555', 'shared@example.test'));

    $this->actingAs($user)->get(route('profile.accounts.return', 'twitch'))->assertSessionHas('error');

    expect(LinkedAccount::query()->where('social_provider_id', $twitch->id)->exists())->toBeFalse();
});

it('refuses a second account on a provider the user already has', function () {
    $discord = profileProvider();
    $user = profileUserWith($discord);
    profileDriver()->shouldReceive('user')->once()->andReturn(profileRemote('9999', 'different@example.test'));

    $this->actingAs($user)->get(route('profile.accounts.return', 'discord'))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'already have'));

    expect(LinkedAccount::query()->count())->toBe(1);
});

it('refuses to complete linking for a disabled provider', function () {
    profileProvider('discord', ['enabled' => false]);
    Socialite::shouldReceive('buildProvider')->never();

    $this->actingAs(User::factory()->create())->get(route('profile.accounts.return', 'discord'))->assertSessionHas('error');

    expect(LinkedAccount::query()->count())->toBe(0);
});

it('reports a failure when the provider errors', function () {
    profileProvider();
    profileDriver()->shouldReceive('user')->once()->andThrow(new RuntimeException('boom'));

    $this->actingAs(User::factory()->create())->get(route('profile.accounts.return', 'discord'))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('error', 'Unable to link your account');
});

it('confirms and removes a deletable linked account', function () {
    $discord = profileProvider();
    $twitch = profileProvider('twitch', ['enabled' => true, 'auth_enabled' => true]);
    $user = profileUserWith($discord);
    $second = LinkedAccount::factory()->for($user)->create(['social_provider_id' => $twitch->id]);

    $this->withoutVite()->actingAs($user)->get(route('profile.accounts.delete', $second))
        ->assertInertia(fn (Assert $page) => $page->component('Profile/DeleteAccount')->where('account.id', $second->id));

    $this->actingAs($user)->delete(route('profile.accounts.destroy', $second))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('success');

    expect(LinkedAccount::query()->whereKey($second->id)->exists())->toBeFalse();
});

it('refuses to remove the last account', function () {
    $user = profileUserWith(profileProvider());
    $only = $user->accounts()->firstOrFail();

    $this->actingAs($user)->get(route('profile.accounts.delete', $only))->assertRedirect(route('profile.show'))->assertSessionHas('error');
    $this->actingAs($user)->delete(route('profile.accounts.destroy', $only))->assertRedirect(route('profile.show'))->assertSessionHas('error');

    expect(LinkedAccount::query()->count())->toBe(1);
});

it('refuses to remove the last login capable account', function () {
    $discord = profileProvider('discord', ['auth_enabled' => true]);
    $spotify = profileProvider('spotify', ['enabled' => true, 'auth_enabled' => false]);
    $user = profileUserWith($discord);
    LinkedAccount::factory()->for($user)->create(['social_provider_id' => $spotify->id]);
    $login = $user->accounts()->where('social_provider_id', $discord->id)->firstOrFail();

    $this->actingAs($user)->delete(route('profile.accounts.destroy', $login))->assertSessionHas('error');

    expect(LinkedAccount::query()->count())->toBe(2);
});

it('forbids touching another users linked account', function () {
    $discord = profileProvider();
    $twitch = profileProvider('twitch', ['enabled' => true]);
    $victim = profileUserWith($discord);
    $theirs = LinkedAccount::factory()->for($victim)->create(['social_provider_id' => $twitch->id]);
    $attacker = User::factory()->create();

    $this->actingAs($attacker)->get(route('profile.accounts.delete', $theirs))->assertForbidden();
    $this->actingAs($attacker)->delete(route('profile.accounts.destroy', $theirs))->assertForbidden();

    expect(LinkedAccount::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('returns 404 for a missing linked account', function () {
    $this->actingAs(User::factory()->create())->delete('/profile/accounts/99999')->assertNotFound();
});
