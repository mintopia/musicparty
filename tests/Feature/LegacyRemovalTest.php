<?php

use App\Models\Song;
use App\Models\UpcomingSong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

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
    'party member role model' => 'App\Models\PartyMemberRole',
    'youtube job' => 'App\Jobs\PartyPlayYouTubeVideo',
    'youtube event' => 'App\Events\Party\PlayYouTubeVideoEvent',
    'cron tick event' => 'App\Events\Cron\HourTickEvent',
]);

it('does not expose css_classes in the upcoming song API payload', function () {
    $song = Mockery::mock(Song::class);
    $song->shouldReceive('toApi')->andReturn(['id' => 1]);
    $upcoming = new UpcomingSong;
    $upcoming->setRelation('song', $song);
    $upcoming->setRelation('user', null);

    expect($upcoming->toApi())->not->toHaveKey('css_classes');
});

it('drops the css_classes column in a migration', function () {
    $migrations = collect(glob(database_path('migrations/*drop_css_classes*.php')));

    expect($migrations)->toHaveCount(1)
        ->and(file_get_contents($migrations->first()))->toContain("dropColumn('css_classes')");
});

it('registers no web routes for removed pages', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with(['parties.youtube', 'parties.ytplayer', 'admin.dashboard']);

it('defines the reverb config the bootstrap script reads', function () {
    $this->withoutVite()->get(route('home'))
        ->assertSee('window.pusherConfig', false)
        ->assertSee('"appKey"', false)
        ->assertSee('"scheme"', false);
});

it('drops the mod tables in a migration', function () {
    $migration = file_get_contents(database_path('migrations/2026_10_09_000001_drop_mod_tables.php'));

    foreach (['party_mod_setting_events', 'party_mod_settings', 'mod_settings', 'mods'] as $table) {
        expect($migration)->toContain("dropIfExists('{$table}')");
    }
});
