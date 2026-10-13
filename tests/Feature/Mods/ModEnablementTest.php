<?php

use App\Enums\Purpose;
use App\Events\Cron\HourTickEvent;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Mod;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use App\Mods\Foo\FooMod;
use App\Mods\ModRegistry;
use App\Mods\PartyMods;
use App\Mods\Whamageddon\Listeners\HourTickListener;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use Illuminate\Support\Carbon;
use Tests\Support\FooModFixture;
use Tests\Support\WhamageddonFixtures;

beforeEach(function () {
    FooModFixture::bindRegistry();
    $this->artisan('mods:sync')->assertSuccessful();
    $this->partyA = Party::factory()->create();
    $this->partyB = Party::factory()->create();
    $this->mods = app(PartyMods::class);
});

afterEach(fn () => Carbon::setTestNow());

it('enables a mod for one party without affecting another', function () {
    $this->mods->enable($this->partyA, 'foo');

    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeTrue()
        ->and($this->mods->isEnabled($this->partyB, 'foo'))->toBeFalse();

    $this->mods->disable($this->partyA, 'foo');

    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeFalse();
});

it('invokes the onEnable and onDisable hooks once per transition', function () {
    $this->mods->enable($this->partyA, 'foo');
    $this->mods->enable($this->partyA, 'foo');
    $this->mods->disable($this->partyA, 'foo');
    $this->mods->disable($this->partyA, 'foo');

    expect(FooMod::$calls)->toBe(["enable:{$this->partyA->id}", "disable:{$this->partyA->id}"]);
});

it('sync enables listed mods and disables the rest', function () {
    $this->mods->enable($this->partyA, 'foo');
    FooMod::$calls = [];

    $this->mods->sync($this->partyA, []);
    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeFalse();

    $this->mods->sync($this->partyA, ['foo', 'unknown']);
    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeTrue()
        ->and(FooMod::$calls)->toBe(["disable:{$this->partyA->id}", "enable:{$this->partyA->id}"]);
});

it('enable is a no-op for unknown and removed mods', function () {
    $this->mods->enable($this->partyA, 'unknown');
    Mod::whereCode('foo')->update(['removed_at' => now()]);
    $this->mods->enable($this->partyA, 'foo');

    expect($this->partyA->mods()->count())->toBe(0)->and(FooMod::$calls)->toBe([]);
});

it('treats a removed mod as not enabled', function () {
    $this->mods->enable($this->partyA, 'foo');
    Mod::whereCode('foo')->update(['removed_at' => now()]);

    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeFalse();
});

it('has no effect on the hourly tick while disabled and does once enabled', function () {
    app()->instance(ModRegistry::class, tap(new ModRegistry(app_path('Mods')))->discover());
    WhamageddonFixtures::fake();
    WhamageddonFixtures::install();
    Carbon::setTestNow('2026-12-24 12:00:00');
    $party = WhamageddonFixtures::party();
    $upcoming = WhamageddonFixtures::upcoming($party);

    (new HourTickListener)->handle(new HourTickEvent);
    expect($upcoming->fresh()->score_adjustment)->toBe(0);

    app(PartyMods::class)->enable($party, 'whamageddon');
    (new HourTickListener)->handle(new HourTickEvent);
    expect($upcoming->fresh()->score_adjustment)->toBe(1);
});

describe('party form', function () {
    beforeEach(function () {
        $this->withoutVite();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->owner = User::factory()->withSpotify()->create();
        $this->owner->forceFill(['first_login' => false])->save();
        $this->owner->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
        $provider = new class extends FakeMusicProvider
        {
            public function listPlaylists(): array
            {
                return [new Playlist('sp-list', 'Spotify List', 'me', 1)];
            }
        };
        app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => $provider);
        $this->party = Party::factory()->for($this->owner)->create(['provider_id' => 'spotify']);
    });

    function updatePayload(array $extra = []): array
    {
        return array_merge(['name' => 'Test', 'backup_playlist_id' => 'sp-list'], $extra);
    }

    it('toggles a mod through mods_enabled[]', function () {
        $this->actingAs($this->owner)->put(route('parties.update', $this->party->code), updatePayload(['mods_enabled' => ['foo']]))->assertSessionHasNoErrors()->assertRedirect();
        expect(app(PartyMods::class)->isEnabled($this->party, 'foo'))->toBeTrue();

        $this->actingAs($this->owner)->put(route('parties.update', $this->party->code), updatePayload());
        expect(app(PartyMods::class)->isEnabled($this->party, 'foo'))->toBeFalse();
    });

    it('offers the enable checkbox on the edit form and hides removed mods', function () {
        Mod::factory()->create(['code' => 'gone', 'name' => 'Gone Mod', 'removed_at' => now()]);

        $this->actingAs($this->owner)->get(route('parties.edit', $this->party->code))
            ->assertOk()
            ->assertSee('name="mods_enabled[]" value="foo"', false)
            ->assertDontSee('Gone Mod');
    });
});
