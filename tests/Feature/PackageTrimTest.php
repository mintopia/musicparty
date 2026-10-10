<?php

use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Admin\Models\Setting;
use App\Domain\Admin\ProviderCatalogue;
use App\Domain\Identity\Models\SocialProvider;
use App\Providers\AppServiceProvider;
use Fruitcake\LaravelDebugbar\ServiceProvider as DebugbarServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function requiredSocialiteProviderPackages(): array
{
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    $packages = array_filter(
        array_keys($composer['require']),
        fn (int|string $package): bool => str_starts_with((string) $package, 'socialiteproviders/') && $package !== 'socialiteproviders/manager',
    );

    return array_values(array_map(fn (int|string $package): string => substr((string) $package, strlen('socialiteproviders/')), $packages));
}

it('resolves a SocialiteProviders driver for every catalogue entry', function (string $code): void {
    config(["services.{$code}" => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/callback', 'host' => 'http://localhost']]);

    expect(Socialite::driver($code))->toBeObject();
})->with(fn (): array => ProviderCatalogue::codes());

const FIRST_PARTY_SOCIALITE_CODES = ['google', 'facebook'];

/**
 * @return list<string>
 */
function communityCatalogueCodes(): array
{
    return array_values(array_diff(ProviderCatalogue::codes(), FIRST_PARTY_SOCIALITE_CODES));
}

it('has a catalogue entry for every required socialiteproviders package', function (): void {
    expect(requiredSocialiteProviderPackages())->toEqualCanonicalizing(communityCatalogueCodes());
});

it('serves Google and Facebook from first-party Socialite drivers', function (string $code): void {
    expect(ProviderCatalogue::codes())->toContain($code)
        ->and(requiredSocialiteProviderPackages())->not->toContain($code);
})->with(FIRST_PARTY_SOCIALITE_CODES);

it('registers a listener extending Socialite for every catalogue entry', function (): void {
    expect(Event::getListeners(SocialiteWasCalled::class))->toHaveCount(count(communityCatalogueCodes()));
});

it('does not require the removed packages directly', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require'])->not->toHaveKeys(['spatie/eloquent-sortable', 'ramsey/uuid'])
        ->and(class_exists('Spatie\EloquentSortable\SortableTrait'))->toBeFalse();
});

it('does not register Debugbar outside local with debug on', function (string $environment, bool $debug): void {
    $this->app['env'] = $environment;
    config(['app.debug' => $debug]);

    new AppServiceProvider($this->app)->register();

    expect($this->app->getProvider(DebugbarServiceProvider::class))->toBeNull();
})->with([
    'production, debug off' => ['production', false],
    'production, debug on' => ['production', true],
    'local, debug off' => ['local', false],
]);

it('registers Debugbar in local with debug on', function (): void {
    $this->app['env'] = 'local';
    config(['app.debug' => true]);

    new AppServiceProvider($this->app)->register();

    expect($this->app->getProvider(DebugbarServiceProvider::class))->not->toBeNull();
});

it('disables Debugbar package auto-discovery', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['laravel']['dont-discover'])->toContain('barryvdh/laravel-debugbar');
});

it('numbers new settings sequentially and exposes an ordered scope', function (): void {
    foreach (['b', 'a', 'c'] as $code) {
        (new Setting)->forceFill(['code' => $code, 'name' => $code, 'type' => 'stString'])->save();
    }

    expect(Setting::query()->ordered()->pluck('code')->all())->toBe(['b', 'a', 'c'])
        ->and(Setting::query()->ordered()->pluck('order')->all())->toBe([1, 2, 3]);
});

it('numbers provider settings per provider', function (): void {
    $first = SocialProvider::factory()->create(['code' => 'first']);
    $second = SocialProvider::factory()->create(['code' => 'second']);

    foreach ([[$first, 'x'], [$first, 'y'], [$second, 'z']] as [$provider, $code]) {
        $setting = new ProviderSetting;
        $setting->provider()->associate($provider);
        $setting->forceFill(['code' => $code, 'name' => $code, 'type' => 'stString'])->save();
    }

    expect($first->settings()->ordered()->pluck('order', 'code')->all())->toBe(['x' => 1, 'y' => 2])
        ->and($second->settings()->ordered()->pluck('order', 'code')->all())->toBe(['z' => 1]);
});
