<?php

use App\Domain\Admin\Models\Setting;
use App\Domain\Admin\SiteSettings;
use App\Domain\Identity\Actions\ResolveSocialUser;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\SocialProviders\FacebookProvider;
use App\Domain\Identity\SocialProviders\GoogleProvider;
use App\Enums\SettingType;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

uses(RefreshDatabase::class);

it('offers only enabled and configured providers on the login page', function () {
    seedDiscord();
    SocialProvider::query()->where('code', 'twitch')->update(['enabled' => false]);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Login')
            ->has('providers', 1)
            ->where('providers.0.code', 'discord')
            ->where('providers.0.name', 'Discord')
            ->where('providers.0.url', route('login.redirect', 'discord')));
});

it('never offers the Spotify Search provider for login even when forced on', function () {
    seedDiscord();
    config(['services.spotifysearch' => ['client_id' => 'ss-id', 'client_secret' => 'ss-secret']]);
    test()->artisan('providers:seed')->assertSuccessful();
    SocialProvider::query()->where('code', 'spotifysearch')->update(['enabled' => true, 'auth_enabled' => true]);

    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->has('providers', 1)
        ->where('providers.0.code', 'discord'));
});

it('hides a provider with no credentials from the login page', function () {
    config(['services.discord.client_id' => null, 'services.discord.client_secret' => null]);
    $this->artisan('providers:seed')->assertSuccessful();
    SocialProvider::query()->update(['enabled' => true, 'auth_enabled' => true]);

    $this->get(route('login'))->assertInertia(fn ($page) => $page->has('providers', 0));
});

it('redirects to the provider when enabled', function () {
    seedDiscord();
    mockDriver()->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://discord.example.test/oauth'));

    $this->get(route('login.redirect', 'discord'))->assertRedirect('https://discord.example.test/oauth');
});

it('refuses the redirect for a disabled provider', function () {
    seedDiscord(['enabled' => false]);
    Socialite::shouldReceive('buildProvider')->never();

    $this->get(route('login.redirect', 'discord'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');
});

it('returns 404 for an unknown provider', function () {
    $this->get(route('login.redirect', 'myspace'))->assertNotFound();
});

it('creates a user and linked account on first login and sends them to signup', function () {
    $provider = seedDiscord();
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser());

    $this->get(route('login.return', 'discord'))->assertRedirect(route('login.signup'));

    $user = User::query()->firstOrFail();
    $account = LinkedAccount::query()->firstOrFail();

    expect($this->isAuthenticated())->toBeTrue()
        ->and($user->nickname)->toBe('ravebot')
        ->and($user->first_login)->toBeTrue()
        ->and($user->last_login)->not->toBeNull()
        ->and($account->user_id)->toBe($user->id)
        ->and($account->social_provider_id)->toBe($provider->id)
        ->and($account->external_id)->toBe('1001')
        ->and($account->access_token)->toBe('access-token');
});

it('logs a returning user in without duplicating anything', function () {
    seedDiscord();
    $user = User::factory()->create(['nickname' => 'established']);
    LinkedAccount::factory()->for($user)->create([
        'social_provider_id' => SocialProvider::query()->where('code', 'discord')->value('id'),
        'external_id' => '1001',
    ]);
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser());

    $this->get(route('login.return', 'discord'))->assertRedirect(route('home'));

    expect(User::query()->count())->toBe(1)
        ->and(LinkedAccount::query()->count())->toBe(1)
        ->and($user->fresh()->nickname)->toBe('established')
        ->and(auth()->id())->toBe($user->id);
});

it('refuses a suspended user', function () {
    seedDiscord();
    $user = User::factory()->suspended()->create();
    LinkedAccount::factory()->for($user)->create([
        'social_provider_id' => SocialProvider::query()->where('code', 'discord')->value('id'),
        'external_id' => '1001',
    ]);
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser());

    $this->get(route('login.return', 'discord'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'suspended'));

    $this->assertGuest();
});

it('refuses the callback for a disabled provider', function () {
    seedDiscord(['auth_enabled' => false]);
    Socialite::shouldReceive('buildProvider')->never();

    $this->get(route('login.return', 'discord'))->assertRedirect(route('login'))->assertSessionHas('error');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('handles a provider failure gracefully', function () {
    seedDiscord();
    mockDriver()->shouldReceive('user')->once()->andThrow(new RuntimeException('invalid state'));

    $this->get(route('login.return', 'discord'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Unable to login');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('stays logged in on GET /logout', function () {
    $this->actingAs(User::factory()->create())->get('/logout')->assertMethodNotAllowed();

    $this->assertAuthenticated();
});

it('resolves one user and one linked account when a concurrent login inserts the identity first', function () {
    $provider = seedDiscord();
    $competitor = User::factory()->create();
    $inserted = false;

    DB::listen(function (QueryExecuted $query) use (&$inserted, $provider, $competitor): void {
        if ($inserted || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, '"linked_accounts"')) {
            return;
        }

        $inserted = true;
        DB::table('linked_accounts')->insert([
            'user_id' => $competitor->id,
            'social_provider_id' => $provider->id,
            'external_id' => '1001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $userCount = User::query()->count();
    $resolved = app(ResolveSocialUser::class)($provider, remoteUser('1001'));

    expect($resolved->id)->toBe($competitor->id);
    expect(User::query()->count())->toBe($userCount);
    expect(LinkedAccount::query()->where('external_id', '1001')->count())->toBe(1);
});

it('logs in through the first-party Google and Facebook drivers', function (string $code): void {
    config(["services.{$code}.client_id" => 'client-id', "services.{$code}.client_secret" => 'client-secret']);
    $this->artisan('providers:seed')->assertSuccessful();
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser('2002', 'partygoer'));

    $this->get(route('login.return', $code))->assertRedirect(route('login.signup'));

    $this->assertAuthenticated();
    expect(LinkedAccount::query()->firstOrFail()->external_id)->toBe('2002');
})->with(['google', 'facebook']);

it('refuses Google login when the provider is not configured', function (): void {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    $this->artisan('providers:seed')->assertSuccessful();
    Socialite::shouldReceive('buildProvider')->never();

    $this->get(route('login.redirect', 'google'))->assertRedirect(route('login'))->assertSessionHas('error');
});

it('builds Google and Facebook providers from the factory', function (): void {
    $google = SocialProvider::factory()->google()->create();
    $facebook = SocialProvider::factory()->facebook()->create();

    expect($google->code)->toBe('google')
        ->and($google->provider_class)->toBe(GoogleProvider::class)
        ->and($facebook->code)->toBe('facebook')
        ->and($facebook->provider_class)->toBe(FacebookProvider::class)
        ->and($facebook->supports_auth)->toBeTrue();
});

it('carries the site name and logos on the login page props', function () {
    foreach (['name' => 'Lan Jam', 'logo-light' => 'site/light.png', 'logo-dark' => 'site/dark.png'] as $code => $value) {
        (new Setting)->forceFill(['code' => $code, 'name' => $code, 'encrypted' => false, 'hidden' => false, 'validation' => '', 'type' => SettingType::stString, 'value' => $value])->save();
    }

    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->component('Login')
        ->where('site.name', 'Lan Jam')
        ->where('site.logo_light_url', Storage::disk(SiteSettings::disk())->url('site/light.png'))
        ->where('site.logo_dark_url', Storage::disk(SiteSettings::disk())->url('site/dark.png')));
});
