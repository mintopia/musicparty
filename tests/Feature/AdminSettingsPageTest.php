<?php

use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function settingsPageAdmin(): User
{
    $user = User::factory()->create();
    app(GrantRole::class)->handle($user, $user, 'admin');

    return $user->fresh();
}

it('lists the Spotify Search provider in settings', function (): void {
    $this->actingAs(settingsPageAdmin())->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
        ->where('providers', fn ($providers): bool => collect($providers)->pluck('code')->contains('spotifysearch')));
});

it('renders settings with providers, theme summary and cover image', function (): void {
    $response = $this->actingAs(settingsPageAdmin())->get('/admin/settings');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Settings')
        ->has('settings.cover_image_url')
        ->has('providers', 8)
        ->where('providers.0.code', 'discord')
        ->where('providers.0.return_urls', [route('login.return', 'discord'), route('profile.accounts.return', 'discord')])
        ->has('theme.light.primary')
        ->has('theme.dark.primary')
        ->where('theme.font', fn (string $font): bool => $font !== ''));
});

it('flashes a success message after saving settings', function (): void {
    $this->actingAs(settingsPageAdmin())->post('/admin/settings', ['name' => 'LAN Night'])->assertSessionHas('success');
});

it('forbids non-admins from the settings and provider pages', function (string $path): void {
    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
})->with(['/admin/settings', '/admin/providers', '/admin/providers/discord/edit']);
