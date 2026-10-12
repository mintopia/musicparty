<?php

use App\Domain\Admin\Actions\SetMusicProviderEnabled;
use App\Domain\Admin\Actions\SetPlayerEnabled;
use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Admin\Models\Setting;
use App\Domain\Admin\PairingToggles;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function runDataMigration(string $direction): void
{
    $migration = require base_path('database/migrations/2026_10_12_140000_enable_default_pairing_toggles.php');

    $migration->{$direction}();
}

it('reads a missing row as disabled', function (): void {
    Setting::query()->whereIn('code', ['music_provider.spotify.enabled', 'player.browser.enabled'])->delete();

    expect(app(PairingToggles::class)->providerEnabled('spotify'))->toBeFalse()
        ->and(app(PairingToggles::class)->playerEnabled('browser'))->toBeFalse();
});

it('starts with every registered entry enabled after migrating', function (): void {
    $toggles = app(PairingToggles::class);

    expect($toggles->providerEnabled('spotify'))->toBeTrue()
        ->and($toggles->providerEnabled('fake'))->toBeTrue()
        ->and($toggles->playerEnabled('polling'))->toBeTrue()
        ->and($toggles->playerEnabled('browser'))->toBeTrue()
        ->and($toggles->playerEnabled('soloist'))->toBeTrue();
});

it('writes the row and an audit record naming the admin', function (string $action, string $id, string $meta, string $prefix): void {
    $admin = User::factory()->create();

    app($action)->handle($admin, $id, false);
    $entry = AdminAuditEntry::query()->latest('id')->firstOrFail();

    expect($entry->action)->toBe("{$prefix}.disabled")
        ->and($entry->admin_id)->toBe($admin->id)
        ->and($entry->admin_name)->toBe($admin->nickname)
        ->and($entry->meta)->toBe([$meta => $id]);

    app($action)->handle($admin, $id, true);
    expect(AdminAuditEntry::query()->latest('id')->firstOrFail()->action)->toBe("{$prefix}.enabled");
})->with([
    'provider' => [SetMusicProviderEnabled::class, 'spotify', 'music_provider', 'music_provider'],
    'player' => [SetPlayerEnabled::class, 'browser', 'player', 'player'],
]);

it('creates the row when it is missing and flips it in the same process', function (): void {
    Setting::query()->where('code', 'player.soloist.enabled')->delete();
    $toggles = app(PairingToggles::class);
    expect($toggles->playerEnabled('soloist'))->toBeFalse();

    app(SetPlayerEnabled::class)->handle(User::factory()->create(), 'soloist', true);
    expect($toggles->playerEnabled('soloist'))->toBeTrue();

    app(SetPlayerEnabled::class)->handle(User::factory()->create(), 'soloist', false);
    expect($toggles->playerEnabled('soloist'))->toBeFalse()
        ->and(Setting::query()->where('code', 'player.soloist.enabled')->count())->toBe(1);
});

it('refuses ids that are not in the registry', function (string $action, string $field): void {
    $before = Setting::query()->count();

    expect(fn () => app($action)->handle(User::factory()->create(), 'nope', true))->toThrow(ValidationException::class)
        ->and(Setting::query()->count())->toBe($before)
        ->and(AdminAuditEntry::query()->count())->toBe(0);
})->with([
    'provider' => [SetMusicProviderEnabled::class, 'music_provider'],
    'player' => [SetPlayerEnabled::class, 'player'],
]);

it('clears the cache entry when a row is deleted', function (): void {
    app(PairingToggles::class)->providerEnabled('spotify');
    expect(Cache::has('settings.music_provider.spotify.enabled'))->toBeTrue();

    Setting::query()->where('code', 'music_provider.spotify.enabled')->firstOrFail()->delete();

    expect(Cache::has('settings.music_provider.spotify.enabled'))->toBeFalse()
        ->and(app(PairingToggles::class)->providerEnabled('spotify'))->toBeFalse();
});

it('keeps existing rows and is idempotent when the migration reruns', function (): void {
    setPairingToggle('player.browser.enabled', false);
    $count = Setting::query()->count();

    runDataMigration('up');
    runDataMigration('up');

    expect(Setting::query()->count())->toBe($count);
    Cache::flush();
    expect(app(PairingToggles::class)->playerEnabled('browser'))->toBeFalse();
});

it('removes only its codes when rolled back and restores them going up', function (): void {
    Setting::query()->forceCreate(['code' => 'site.name', 'name' => 'Site name', 'value' => 'x', 'order' => 999]);

    runDataMigration('down');
    runDataMigration('down');

    expect(Setting::query()->where('code', 'like', 'music_provider.%')->orWhere('code', 'like', 'player.%')->count())->toBe(0)
        ->and(Setting::query()->where('code', 'site.name')->exists())->toBeTrue();

    runDataMigration('up');
    Cache::flush();

    expect(Setting::query()->where('code', 'like', 'music_provider.%')->orWhere('code', 'like', 'player.%')->count())->toBe(5)
        ->and(app(PairingToggles::class)->providerEnabled('fake'))->toBeTrue();
});

it('uses the helper to toggle through the admin action', function (): void {
    setPairingToggle('music_provider.fake.enabled', false);

    expect(app(PairingToggles::class)->providerEnabled('fake'))->toBeFalse()
        ->and(AdminAuditEntry::query()->where('action', 'music_provider.disabled')->count())->toBe(1);
});
