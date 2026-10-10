<?php

use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Admin\ProviderCatalogue;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->withoutVite();
});

function makeSiteAdmin(): User
{
    $user = User::factory()->create();
    app(GrantRole::class)->handle($user, $user, 'admin');
    AdminAuditEntry::query()->delete();

    return $user->fresh();
}

function signOut(): void
{
    auth()->logout();
    test()->flushSession();
}

function configureDiscord(User $admin, bool $enabled = true): void
{
    test()->actingAs($admin)->put('/admin/providers/discord', [
        'enabled' => $enabled,
        'settings' => ['client_id' => 'abc123', 'client_secret' => 'super-secret-value'],
    ])->assertRedirect();
}

it('lists the five providers masked on read', function (): void {
    $admin = makeSiteAdmin();
    configureDiscord($admin);

    $response = $this->actingAs($admin)->get('/admin/providers');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Providers')
        ->has('providers', 5)
        ->where('providers.0.code', 'discord')
        ->where('providers.0.enabled', true)
        ->where('providers.0.fields.0.value', 'abc123')
        ->where('providers.0.fields.1.value', ProviderCatalogue::MASK));
    expect($response->getContent())->not->toContain('super-secret-value');
});

it('offers an enabled configured provider on the login page and nothing else', function (): void {
    configureDiscord(makeSiteAdmin());
    $this->actingAs(makeSiteAdmin())->put('/admin/providers/twitch', ['settings' => ['client_id' => 'x']]);
    signOut();

    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->component('Login')
        ->has('providers', 1)
        ->where('providers.0.code', 'discord'));
});

it('does not offer a disabled provider and refuses logins through it', function (): void {
    $admin = makeSiteAdmin();
    configureDiscord($admin);
    configureDiscord($admin, false);
    signOut();

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->has('providers', 0));
    $this->get('/login/discord')->assertRedirect(route('login'));
    $this->get('/login/discord/return')->assertRedirect(route('login'))->assertSessionHas('errorMessage', 'Login with discord is not available.');
});

it('refuses to enable a provider that has no credentials', function (): void {
    $this->actingAs(makeSiteAdmin())->put('/admin/providers/spotify', ['enabled' => true])->assertSessionHasErrors('enabled');

    expect(SocialProvider::query()->whereCode('spotify')->value('enabled'))->toBeFalse();
});

it('returns not found for an unknown provider', function (): void {
    $this->actingAs(makeSiteAdmin())->put('/admin/providers/myspace', ['enabled' => true])->assertNotFound();
});

it('stores the secret encrypted and keeps it when the mask or blank is resubmitted', function (): void {
    $admin = makeSiteAdmin();
    configureDiscord($admin);

    $stored = DB::table('provider_settings')->where('code', 'client_secret')->value('value');
    expect($stored)->not->toContain('super-secret-value');
    $provider = SocialProvider::query()->whereCode('discord')->firstOrFail();
    expect($provider->getSetting('client_secret'))->toBe('super-secret-value');

    $this->actingAs($admin)->put('/admin/providers/discord', ['settings' => ['client_id' => 'abc123', 'client_secret' => ProviderCatalogue::MASK]])->assertRedirect();
    $this->actingAs($admin)->put('/admin/providers/discord', ['settings' => ['client_secret' => '']])->assertRedirect();

    expect(ProviderSetting::query()->whereCode('client_secret')->firstOrFail()->value)->toBe('super-secret-value')
        ->and(AdminAuditEntry::query()->where('action', 'provider.credential_changed')->count())->toBe(2);
});

it('replaces the secret when a new value is submitted and audits without the value', function (): void {
    $admin = makeSiteAdmin();
    configureDiscord($admin);
    AdminAuditEntry::query()->delete();

    $this->actingAs($admin)->put('/admin/providers/discord', ['settings' => ['client_secret' => 'brand-new-secret']])->assertRedirect();

    $entry = AdminAuditEntry::query()->sole();
    expect($entry->action)->toBe('provider.credential_changed')
        ->and($entry->admin_id)->toBe($admin->id)
        ->and($entry->subject_user_id)->toBeNull()
        ->and($entry->meta)->toBe(['provider' => 'discord', 'field' => 'client_secret'])
        ->and($entry->created_at)->not->toBeNull()
        ->and(json_encode(AdminAuditEntry::query()->get()))->not->toContain('brand-new-secret')->not->toContain('super-secret-value');
});

it('audits enabling and disabling a provider', function (): void {
    $admin = makeSiteAdmin();
    configureDiscord($admin, false);
    configureDiscord($admin, true);

    expect(AdminAuditEntry::query()->where('action', 'provider.enabled')->count())->toBe(1);
});

it('shows the site name in shared props and page title', function (): void {
    $admin = makeSiteAdmin();
    $this->actingAs($admin)->post('/admin/settings', ['name' => 'LAN Night'])->assertRedirect();
    signOut();

    $this->get('/login')->assertSee('<title>LAN Night</title>', false)
        ->assertInertia(fn (Assert $page) => $page->where('appName', 'LAN Night')->where('site.name', 'LAN Night'));
});

it('rejects a malformed terms or privacy url', function (string $field): void {
    $this->actingAs(makeSiteAdmin())->post('/admin/settings', [$field => 'not a url'])->assertSessionHasErrors($field);
})->with(['terms_url', 'privacy_url']);

it('saves valid urls and audits the changed fields', function (): void {
    $admin = makeSiteAdmin();
    $this->actingAs($admin)->post('/admin/settings', ['terms_url' => 'https://example.com/terms', 'privacy_url' => 'https://example.com/privacy'])->assertRedirect();
    signOut();

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('site.terms_url', 'https://example.com/terms'));
    expect(AdminAuditEntry::query()->sole()->meta)->toBe(['fields' => 'terms_url,privacy_url']);
});

it('rejects bad upload types and sizes and stores good ones on the configured disk', function (): void {
    $admin = makeSiteAdmin();

    $this->actingAs($admin)->post('/admin/settings', ['logo_light' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo_light');
    $this->actingAs($admin)->post('/admin/settings', ['logo_dark' => UploadedFile::fake()->image('big.png')->size(3000)])->assertSessionHasErrors('logo_dark');
    $this->actingAs($admin)->post('/admin/settings', ['favicon' => UploadedFile::fake()->image('fav.gif')])->assertSessionHasErrors('favicon');
    Storage::disk('public')->assertDirectoryEmpty('/');

    $this->actingAs($admin)->post('/admin/settings', ['logo_light' => UploadedFile::fake()->image('logo.png'), 'favicon' => UploadedFile::fake()->image('fav.png', 32, 32)->size(10)])->assertRedirect();
    signOut();

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->whereNot('site.logo_light_url', null)->whereNot('site.favicon_url', null));
    expect(Storage::disk('public')->allFiles('site'))->toHaveCount(2);
});

it('replaces and deletes the previous upload', function (): void {
    $admin = makeSiteAdmin();
    $this->actingAs($admin)->post('/admin/settings', ['logo_light' => UploadedFile::fake()->image('a.png')])->assertRedirect();
    $this->actingAs($admin)->post('/admin/settings', ['logo_light' => UploadedFile::fake()->image('b.png')])->assertRedirect();

    expect(Storage::disk('public')->allFiles('site'))->toHaveCount(1);
});

it('requires the default party to exist and redirects visitors to it', function (): void {
    $admin = makeSiteAdmin();
    $this->actingAs($admin)->post('/admin/settings', ['default_party' => 'NOPE99'])->assertSessionHasErrors('default_party');
    $this->get('/')->assertSuccessful();

    $party = Party::withoutEvents(fn (): Party => Party::factory()->create());
    $this->actingAs($admin)->post('/admin/settings', ['default_party' => $party->code])->assertRedirect();
    signOut();

    $this->get('/')->assertRedirect('/parties/'.$party->code);

    $this->actingAs($admin)->post('/admin/settings', ['default_party' => null]);
    signOut();
    $this->get('/')->assertSuccessful();
});

it('refuses non-admins and redirects guests on the new web routes', function (string $method, string $uri): void {
    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/admin/providers'],
    ['put', '/admin/providers/discord'],
    ['get', '/admin/settings'],
    ['post', '/admin/settings'],
]);

it('refuses non-admins and rejects guests on the new api routes', function (string $method, string $uri): void {
    $this->{$method}($uri)->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    $this->{$method}($uri)->assertForbidden();
})->with([
    ['getJson', '/api/v1/admin/providers'],
    ['putJson', '/api/v1/admin/providers/discord'],
    ['getJson', '/api/v1/admin/settings'],
    ['postJson', '/api/v1/admin/settings'],
]);

it('behaves the same through the api as the web for providers', function (): void {
    Sanctum::actingAs(makeSiteAdmin());

    $this->putJson('/api/v1/admin/providers/discord', ['enabled' => true, 'settings' => ['client_id' => 'abc', 'client_secret' => 'api-secret']])
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.fields.1.value', ProviderCatalogue::MASK);

    $this->getJson('/api/v1/admin/providers')->assertOk()->assertJsonCount(5, 'data')->assertJsonMissing(['value' => 'api-secret']);
    expect(AdminAuditEntry::query()->where('action', 'provider.credential_changed')->count())->toBe(2);

    $this->putJson('/api/v1/admin/providers/discord', ['enabled' => 'maybe'])->assertJsonValidationErrors('enabled');
});

it('behaves the same through the api as the web for site settings', function (): void {
    Sanctum::actingAs(makeSiteAdmin());

    $this->postJson('/api/v1/admin/settings', ['name' => 'API Party', 'terms_url' => 'nope'])->assertJsonValidationErrors('terms_url');
    $this->postJson('/api/v1/admin/settings', ['name' => 'API Party', 'favicon' => UploadedFile::fake()->image('f.png')->size(10)])
        ->assertOk()
        ->assertJsonPath('data.name', 'API Party');
    $this->getJson('/api/v1/admin/settings')->assertOk()->assertJsonPath('data.name', 'API Party');

    expect(AdminAuditEntry::query()->sole()->meta)->toBe(['fields' => 'name,favicon']);
});
