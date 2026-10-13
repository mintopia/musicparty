<?php

use App\Exceptions\SocialProviderException;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Services\SocialProviders\DiscordProvider;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

function makeFirstUserAdmin(): User
{
    return User::factory()->create(['first_login' => false]);
}

it('creates a user and logs in on first discord login', function () {
    providerFor(DiscordProvider::class, 'discord');
    fakeRemoteUser('d1', 'new@example.com', ['verified' => true]);

    $this->get(route('login.return', 'discord'))->assertRedirect(route('home'));

    $user = User::sole();
    $this->assertAuthenticatedAs($user);
    expect($user->nickname)->toBe('nickd1')
        ->and($user->last_login)->not->toBeNull()
        ->and($user->accounts()->sole()->external_id)->toBe('d1');
});

it('logs in the existing user on a repeat login without creating another', function () {
    $provider = providerFor(DiscordProvider::class, 'discord');
    $account = LinkedAccount::factory()->create([
        'social_provider_id' => $provider->id,
        'external_id' => 'd1',
        'email' => 'a@example.com',
    ]);
    fakeRemoteUser('d1', 'a@example.com', ['verified' => true]);

    $this->get(route('login.return', 'discord'))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($account->user);
    expect(User::count())->toBe(1);
});

it('merges a verified login into the existing user over http', function () {
    $existing = LinkedAccount::factory()->verified()->create(['email' => 'a@example.com']);
    providerFor(DiscordProvider::class, 'discord');
    fakeRemoteUser('d1', 'a@example.com', ['verified' => true]);

    $this->get(route('login.return', 'discord'));

    $this->assertAuthenticatedAs($existing->user);
    expect(User::count())->toBe(1)->and($existing->user->accounts()->count())->toBe(2);
});

it('creates a separate user when the discord email is unverified', function () {
    $existing = LinkedAccount::factory()->verified()->create(['email' => 'a@example.com']);
    providerFor(DiscordProvider::class, 'discord');
    fakeRemoteUser('d1', 'a@example.com', ['verified' => false]);

    $this->get(route('login.return', 'discord'));

    expect(User::count())->toBe(2);
    $this->assertNotEquals($existing->user_id, auth()->id());
});

it('links a new account to the logged in user', function () {
    $user = User::factory()->create(['first_login' => false]);
    providerFor(DiscordProvider::class, 'discord');
    fakeRemoteUser('d9', 'link@example.com', ['verified' => true]);

    $this->actingAs($user)->get(route('linkedaccounts.store', 'discord'))
        ->assertRedirect(route('user.profile'));

    expect($user->accounts()->sole()->external_id)->toBe('d9')->and(User::count())->toBe(1);
});

it('refuses to link an account already owned by another user', function () {
    $provider = providerFor(DiscordProvider::class, 'discord');
    LinkedAccount::factory()->create(['social_provider_id' => $provider->id, 'external_id' => 'd9']);
    $user = User::factory()->create(['first_login' => false]);
    $this->withoutExceptionHandling();
    fakeRemoteUser('d9', 'x@example.com', ['verified' => true]);

    $this->actingAs($user)->get(route('linkedaccounts.store', 'discord'));

    expect($user->accounts()->count())->toBe(0);
})->throws(SocialProviderException::class);

it('refuses login when the provider has login disabled', function (array $flags) {
    providerFor(DiscordProvider::class, 'discord')->forceFill($flags)->save();
    fakeRemoteUser('d1', 'new@example.com', ['verified' => true]);

    $this->get(route('login.return', 'discord'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('errorMessage', 'Unable to login');
    $this->get(route('login.redirect', 'discord'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with([
    'auth disabled' => [['auth_enabled' => false]],
    'provider disabled' => [['enabled' => false]],
]);

it('refuses a suspended user', function () {
    $provider = providerFor(DiscordProvider::class, 'discord');
    $account = LinkedAccount::factory()->create(['social_provider_id' => $provider->id, 'external_id' => 'd1']);
    $account->user->forceFill(['suspended' => true])->save();
    fakeRemoteUser('d1', null);

    $this->get(route('login.return', 'discord'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('errorMessage', 'Your account has been suspended');
    $this->assertGuest();
});

it('makes only the first user an admin', function () {
    providerFor(DiscordProvider::class, 'discord');

    fakeRemoteUser('d1', 'one@example.com', ['verified' => true]);
    $this->get(route('login.return', 'discord'));
    $first = User::sole();
    auth()->logout();

    fakeRemoteUser('d2', 'two@example.com', ['verified' => true]);
    $this->get(route('login.return', 'discord'));
    $second = User::where('id', '!=', $first->id)->sole();

    expect($first->hasRole('admin'))->toBeTrue()
        ->and($second->hasRole('admin'))->toBeFalse();
});

it('lets an admin impersonate a user', function () {
    $admin = makeFirstUserAdmin();
    $target = User::factory()->create(['first_login' => false]);

    $this->actingAs($admin)->get(route('admin.users.impersonate', $target))
        ->assertRedirect(route('home'));

    expect(session(auth('web')->getName()))->toBe($target->id)
        ->and(session('impersonating'))->toBeTrue()->and(session('originalUserId'))->toBe($admin->id);
});

it('restores the original user when impersonation stops', function () {
    $admin = makeFirstUserAdmin();
    $target = User::factory()->create(['first_login' => false]);

    $this->actingAs($target)
        ->withSession(['impersonating' => true, 'originalUserId' => $admin->id])
        ->get(route('admin.unimpersonate'))
        ->assertRedirect(route('admin.users.show', $target->id));

    expect(session(auth('web')->getName()))->toBe($admin->id)->and(session('impersonating'))->toBeNull();
});

it('forbids impersonation by non-admins', function () {
    makeFirstUserAdmin();
    $user = User::factory()->create(['first_login' => false]);
    $target = User::factory()->create(['first_login' => false]);

    $this->actingAs($user)->get(route('admin.users.impersonate', $target))->assertForbidden();

    $this->assertAuthenticatedAs($user);
});

it('forbids unimpersonate outside an impersonation session', function () {
    $this->actingAs(makeFirstUserAdmin())->get(route('admin.unimpersonate'))->assertForbidden();
});

it('redirects guests away from impersonation', function () {
    $target = User::factory()->create(['first_login' => false]);

    $this->get(route('admin.users.impersonate', $target))->assertRedirect();
    $this->assertGuest();
});
