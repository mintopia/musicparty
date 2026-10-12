<?php

use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * @param  iterable<int, array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function settingsRow(iterable $rows, string $key, string $value): array
{
    foreach ($rows as $row) {
        if ($row[$key] === $value) {
            return $row;
        }
    }

    return [];
}

function settingsPageAdmin(): User
{
    $user = User::factory()->create();
    app(GrantRole::class)->handle($user, $user, 'admin');

    return $user->fresh();
}

it('lists the Spotify Search provider in settings', function (): void {
    $this->actingAs(settingsPageAdmin())->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
        ->where('providers', function ($providers): bool {
            /** @var iterable<int, array<string, mixed>> $providers */
            return collect($providers)->pluck('code')->contains('spotifysearch');
        }));
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

it('passes music provider and player availability with non-ended party counts', function (): void {
    setPairingToggle('music_provider.spotify.enabled', true);
    Party::factory()->count(2)->create(['music_provider' => 'spotify', 'player_kind' => 'browser', 'state' => PartyState::Live]);
    Party::factory()->create(['music_provider' => 'spotify', 'player_kind' => 'browser', 'state' => PartyState::Ended]);

    $this->actingAs(settingsPageAdmin())->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
        ->where('musicProviders', function ($providers): bool {
            /** @var list<array<string, mixed>> $providers */
            $spotify = settingsRow($providers, 'id', 'spotify');

            return $spotify['enabled'] === true && $spotify['parties'] === 2;
        })
        ->where('players', function ($players): bool {
            /** @var list<array<string, mixed>> $players */
            return settingsRow($players, 'kind', 'browser')['parties'] === 2;
        }));
});

it('links unconfigured providers to their settings page', function (): void {
    blankSpotifyCredential('client_id');

    $this->actingAs(settingsPageAdmin())->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
        ->where('musicProviders', function ($providers): bool {
            /** @var list<array<string, mixed>> $providers */
            $spotify = settingsRow($providers, 'id', 'spotify');

            return $spotify['configured'] === false && $spotify['missing'][0]['editUrl'] === route('admin.providers.edit', 'spotify');
        }));
});

it('omits the fake provider in production', function (): void {
    expect(array_column(app(PairingCatalogue::class)->registeredProviders(), 'id'))->toContain('fake');

    $this->app->instance('env', 'production');

    expect(array_column(app(PairingCatalogue::class)->registeredProviders(), 'id'))->not->toContain('fake');
});

it('enables and disables music providers and players with an audit record', function (string $path, string $audit): void {
    $admin = settingsPageAdmin();

    $this->actingAs($admin)->put($path, ['enabled' => false])->assertRedirect()->assertSessionHas('success');

    expect(AdminAuditEntry::query()->where('action', $audit)->exists())->toBeTrue();
})->with([
    'music provider' => ['/admin/music-providers/spotify', 'music_provider.disabled'],
    'player' => ['/admin/players/browser', 'player.disabled'],
]);

it('forbids non-admins from toggling availability', function (string $path): void {
    $this->actingAs(User::factory()->create())->put($path, ['enabled' => true])->assertForbidden();
})->with(['/admin/music-providers/spotify', '/admin/players/browser']);

it('requires a boolean enabled value', function (): void {
    $this->actingAs(settingsPageAdmin())->put('/admin/players/browser', ['enabled' => 'maybe'])->assertSessionHasErrors('enabled');
});

it('does not grow the settings page query count with the number of parties', function (): void {
    $admin = settingsPageAdmin();
    $measure = fn () => $this->actingAs($admin)->get('/admin/settings')->assertOk();

    Party::factory()->create(['music_provider' => 'spotify', 'player_kind' => 'browser']);
    $measure();
    $few = countQueries($measure);

    Party::factory()->count(20)->create(['music_provider' => 'spotify', 'player_kind' => 'browser']);

    expect(countQueries($measure))->toBe($few);
});
