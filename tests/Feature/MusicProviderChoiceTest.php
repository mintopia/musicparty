<?php

use App\Enums\SettingType;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSpotify;

uses(RefreshDatabase::class);

function setProviderEnabled(string $id, bool $enabled): void
{
    $setting = Setting::query()->where('code', "provider-{$id}-enabled")->first() ?? new Setting;
    $setting->code = "provider-{$id}-enabled";
    $setting->name = $id;
    $setting->type = SettingType::stBoolean;
    $setting->value = $enabled;
    $setting->save();
}

function partyPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Test', 'backup_playlist_id' => 'other', 'custom_backup_playlist_id' => 'abc'], $overrides);
}

function adminUser(?User $user = null): User
{
    $user ??= User::factory()->withSpotify()->create();
    $user->forceFill(['first_login' => false])->save();
    $user->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));

    return $user;
}

function resetSettingCache(): void
{
    (fn () => static::$cached = [])->bindTo(null, Setting::class)();
}

afterEach(fn () => resetSettingCache());

beforeEach(function () {
    resetSettingCache();
    FakeSpotify::install();
    $this->withoutVite();
});

it('offers enabled providers and only offers fake in local', function () {
    $factory = app(MusicProviderFactory::class);
    expect($factory->selectableIds())->toBe(['spotify']);

    $this->app['env'] = 'local';
    expect($factory->selectableIds())->toBe(['spotify', 'fake']);

    setProviderEnabled('spotify', false);
    Setting::query()->get()->each->clearCache();
    expect($factory->selectableIds())->toBe(['fake']);
});

it('shows the party form with only selectable providers', function () {
    $user = adminUser();
    $search = Model::unguarded(fn () => SocialProvider::factory()->create(['code' => 'spotifysearch']));
    LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => $search->id]);

    $this->actingAs($user)->get(route('parties.create'))
        ->assertOk()
        ->assertSee('value="spotify"', false)
        ->assertDontSee('value="fake"', false);
});

it('rejects a disabled provider when creating a party', function () {
    $user = adminUser();
    setProviderEnabled('spotify', false);
    Setting::query()->get()->each->clearCache();

    $this->actingAs($user)->post(route('parties.store'), partyPayload(['provider_id' => 'spotify']))
        ->assertSessionHasErrors('provider_id');
    expect(Party::count())->toBe(0);
});

it('rejects fake outside local', function () {
    $user = adminUser();

    $this->actingAs($user)->post(route('parties.store'), partyPayload(['provider_id' => 'fake']))
        ->assertSessionHasErrors('provider_id');
});

it('rejects provider changes after creation', function () {
    $party = Party::factory()->create(['provider_id' => 'spotify']);

    $this->actingAs(adminUser($party->user))->put(route('parties.update', $party->code), partyPayload(['provider_id' => 'fake']))
        ->assertSessionHasErrors('provider_id');
    expect($party->fresh()->provider_id)->toBe('spotify');
});

it('lets admins toggle providers on the settings page', function () {
    $this->seed(SettingsSeeder::class);
    $admin = adminUser();

    $this->actingAs($admin)->get(route('admin.settings.index'))
        ->assertOk()->assertSee('provider-spotify-enabled', false);

    $this->actingAs($admin)->put(route('admin.settings.update'), ['name' => 'Music Party'])
        ->assertRedirect();
    Setting::query()->get()->each->clearCache();

    expect(app(MusicProviderFactory::class)->isEnabled('spotify'))->toBeFalse();
});
