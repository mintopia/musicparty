<?php

use App\Models\Party;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\PartyTimezone;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function timezoneAdmin(): User
{
    $user = User::factory()->withSpotify()->create();
    $user->forceFill(['first_login' => false])->save();
    $user->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));

    return $user;
}

function storeInstanceTimezone(?string $value): void
{
    $setting = Setting::whereCode('timezone')->firstOrFail();
    $setting->value = $value;
    $setting->save();
    Setting::query()->get()->each->clearCache();
}

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    Setting::query()->get()->each->clearCache();
});

it('defaults the instance and Party timezone to the app timezone', function () {
    config(['app.timezone' => 'Europe/London']);
    $party = Party::factory()->create();

    expect(app(PartyTimezone::class)->instance())->toBe('Europe/London')
        ->and(app(PartyTimezone::class)->for($party))->toBe('Europe/London');
});

it('uses the instance timezone for Parties without an override', function () {
    storeInstanceTimezone('America/New_York');

    expect(app(PartyTimezone::class)->for(Party::factory()->create()))->toBe('America/New_York');
});

it('prefers the Party timezone over the instance timezone', function () {
    storeInstanceTimezone('America/New_York');
    $party = Party::factory()->create(['timezone' => 'Asia/Tokyo']);

    expect(app(PartyTimezone::class)->for($party))->toBe('Asia/Tokyo')
        ->and(app(PartyTimezone::class)->dateTimeZone($party)->getName())->toBe('Asia/Tokyo');
});

it('falls back when a stored timezone is invalid', function () {
    config(['app.timezone' => 'UTC']);
    storeInstanceTimezone('Not/AZone');
    $party = Party::factory()->create(['timezone' => 'Also/Bogus']);

    expect(app(PartyTimezone::class)->for($party))->toBe('UTC');
});

it('saves a valid instance timezone and rejects an invalid one', function () {
    $admin = timezoneAdmin();

    $this->actingAs($admin)->put(route('admin.settings.update'), ['name' => 'Music Party', 'timezone' => 'Australia/Sydney'])
        ->assertRedirect()->assertSessionHasNoErrors();
    Setting::query()->get()->each->clearCache();
    expect(app(PartyTimezone::class)->instance())->toBe('Australia/Sydney');

    $this->actingAs($admin)->put(route('admin.settings.update'), ['name' => 'Music Party', 'timezone' => 'Mars/Olympus'])
        ->assertSessionHasErrors('timezone');
});

it('saves, clears and rejects the Party timezone on the form', function () {
    $admin = timezoneAdmin();
    $party = Party::factory()->create(['user_id' => $admin->id]);
    $payload = ['name' => $party->name, 'backup_playlist_id' => 'other', 'custom_backup_playlist_id' => 'abc'];

    $this->actingAs($admin)->put(route('parties.update', $party->code), $payload + ['timezone' => 'Asia/Tokyo'])
        ->assertSessionHasNoErrors();
    expect($party->fresh()->timezone)->toBe('Asia/Tokyo');

    $this->actingAs($admin)->put(route('parties.update', $party->code), $payload + ['timezone' => 'Nope/Nowhere'])
        ->assertSessionHasErrors('timezone');
    expect($party->fresh()->timezone)->toBe('Asia/Tokyo');

    $this->actingAs($admin)->put(route('parties.update', $party->code), $payload + ['timezone' => ''])
        ->assertSessionHasNoErrors();
    expect($party->fresh()->timezone)->toBeNull();
});
