<?php

use App\Models\UpcomingSong;
use Illuminate\Support\Facades\Route;

it('renders the Inertia root placeholder at the home route', function () {
    $this->withoutVite()->get(route('home'))->assertOk()->assertSee('id="app"', false);
});

it('renders the root placeholder for the login page', function () {
    $this->withoutVite()->get(route('login'))->assertOk()->assertSee('id="app"', false);
});

it('boots with every route resolving to an existing controller', function () {
    $missing = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getControllerClass())
        ->filter(fn ($controller) => $controller !== null && ! class_exists($controller));

    expect($missing->all())->toBeEmpty();
});

it('keeps only the root view', function () {
    $views = collect(glob(resource_path('views/*')))->map(fn ($path) => basename($path))->all();

    expect($views)->toBe(['app.blade.php']);
});

it('no longer ships legacy classes', function (string $class) {
    expect(class_exists($class))->toBeFalse();
})->with([
    'whamageddon provider' => 'App\Mods\Whamageddon\WhammageddonServiceProvider',
    'mod model' => 'App\Models\Mod',
    'mod setting model' => 'App\Models\ModSetting',
    'party mod setting model' => 'App\Models\PartyModSetting',
    'youtube job' => 'App\Jobs\PartyPlayYouTubeVideo',
    'youtube event' => 'App\Events\Party\PlayYouTubeVideoEvent',
    'cron tick event' => 'App\Events\Cron\HourTickEvent',
]);

it('does not expose legacy fields on upcoming songs', function () {
    $song = new UpcomingSong;

    expect(array_key_exists('css_classes', $song->toArray()))->toBeFalse();
});

it('registers no web routes for removed pages', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with(['parties.youtube', 'parties.ytplayer', 'admin.dashboard', 'parties.show']);
