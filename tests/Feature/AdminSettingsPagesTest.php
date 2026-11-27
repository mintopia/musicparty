<?php

use App\Enums\SettingType;
use App\Models\ProviderSetting;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SocialProvider;
use App\Models\Theme;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $role = Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']);
    $this->admin = User::factory()->create(['first_login' => false]);
    $this->admin->roles()->attach($role);
    $this->provider = SocialProvider::factory()->create(['code' => 'discord', 'name' => 'Discord', 'can_be_renamed' => true]);
});

it('keeps non-admins and guests out of the admin settings routes', function (string $method, string $route) {
    $url = route($route, ['provider' => $this->provider]);

    $this->{$method}($url)->assertRedirect();
    $this->actingAs(User::factory()->create(['first_login' => false]))->{$method}($url)->assertForbidden();
})->with([
    ['get', 'admin.dashboard'],
    ['get', 'admin.settings.index'],
    ['patch', 'admin.settings.update'],
    ['get', 'admin.settings.socialproviders.edit'],
    ['patch', 'admin.settings.socialproviders.update'],
]);

it('renders settings, themes and providers', function () {
    $theme = Theme::factory()->create(['readonly' => true]);

    $this->actingAs($this->admin)->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Index')
            ->has('settings')
            ->where('themes.0.id', $theme->id)
            ->where('themes.0.readonly', true)
            ->where('socialProviders.0.code', 'discord')
            ->where('socialProviders.0.login_return_url', route('login.return', 'discord')));
});

it('updates settings and reports validation errors', function () {
    $text = Setting::query()->forceCreate([
        'code' => 'site_title', 'name' => 'Title', 'type' => SettingType::stString,
        'hidden' => false, 'encrypted' => false, 'validation' => 'required|max:5', 'order' => 1,
    ]);
    $flag = Setting::query()->forceCreate([
        'code' => 'site_flag', 'name' => 'Flag', 'type' => SettingType::stBoolean,
        'hidden' => false, 'encrypted' => false, 'order' => 2,
    ]);

    $this->actingAs($this->admin)->patch(route('admin.settings.update'), ['site_title' => 'toolongvalue'])
        ->assertSessionHasErrors('site_title');

    $this->actingAs($this->admin)->patch(route('admin.settings.update'), ['site_title' => 'Party', 'site_flag' => true])
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('successMessage', 'Settings have been updated');

    expect($text->fresh()->value)->toBe('Party')->and($flag->fresh()->value)->toBeTrue();
});

it('edits and updates a social provider', function () {
    $setting = new ProviderSetting;
    $setting->forceFill([
        'provider_type' => SocialProvider::class, 'provider_id' => $this->provider->id,
        'code' => 'client_id', 'name' => 'Client ID', 'type' => SettingType::stString,
        'encrypted' => false, 'validation' => 'required', 'order' => 1,
    ])->save();

    $this->actingAs($this->admin)->get(route('admin.settings.socialproviders.edit', $this->provider))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SocialProviders/Edit')
            ->where('provider.can_be_renamed', true)
            ->where('settings.0.code', 'client_id')
            ->where('settings.0.required', true));

    $this->actingAs($this->admin)->patch(route('admin.settings.socialproviders.update', $this->provider), ['name' => ''])
        ->assertSessionHasErrors(['name', 'client_id']);

    $this->actingAs($this->admin)->patch(route('admin.settings.socialproviders.update', $this->provider), [
        'name' => 'Renamed', 'client_id' => 'abc', 'enabled' => true,
    ])->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('successMessage', 'Renamed has been updated');

    expect($this->provider->fresh())->name->toBe('Renamed')->enabled->toBeTrue()->auth_enabled->toBeFalse();
});
