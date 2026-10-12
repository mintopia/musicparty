<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Jobs\TickParty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    setPairingToggle('player.fake.enabled', true);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function availabilityPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Friday LAN', 'music_provider' => 'fake', 'player_kind' => 'fake'], $overrides);
}

function availabilityCreator(): User
{
    return User::factory()->withRole('create-party')->create();
}

it('rejects a disabled Music Provider on web and API and creates no Party', function () {
    configureSpotifyCredentials();
    setPairingToggle('music_provider.spotify.enabled', false);
    $payload = availabilityPayload(['music_provider' => 'spotify', 'player_kind' => 'soloist']);

    $this->actingAs(availabilityCreator())->post(route('parties.store'), $payload)->assertSessionHasErrors('music_provider');
    Sanctum::actingAs(availabilityCreator());
    $this->postJson('/api/v1/parties', $payload)->assertUnprocessable()->assertJsonValidationErrors('music_provider');

    expect(Party::query()->count())->toBe(0);
});

it('rejects an enabled Music Provider whose credentials are missing', function () {
    $payload = availabilityPayload(['music_provider' => 'spotify', 'player_kind' => 'soloist']);

    $this->actingAs(availabilityCreator())->post(route('parties.store'), $payload)->assertSessionHasErrors('music_provider');
    Sanctum::actingAs(availabilityCreator());
    $this->postJson('/api/v1/parties', $payload)->assertUnprocessable()->assertJsonValidationErrors('music_provider');

    expect(Party::query()->count())->toBe(0);
});

it('rejects a disabled Player on web and API', function () {
    setPairingToggle('player.fake.enabled', false);

    $this->actingAs(availabilityCreator())->post(route('parties.store'), availabilityPayload())->assertSessionHasErrors('player_kind');
    Sanctum::actingAs(availabilityCreator());
    $this->postJson('/api/v1/parties', availabilityPayload())->assertUnprocessable()->assertJsonValidationErrors('player_kind');

    expect(Party::query()->count())->toBe(0);
});

it('rejects the Fake Music Provider under production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    Sanctum::actingAs(availabilityCreator());
    $this->postJson('/api/v1/parties', availabilityPayload())->assertUnprocessable()->assertJsonValidationErrors('music_provider');

    expect(Party::query()->count())->toBe(0);
});

it('lists Spotify and its three Players on the create page', function () {
    configureSpotifyCredentials();

    $this->withoutVite()->actingAs(availabilityCreator())->get(route('parties.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('providers.0', ['id' => 'spotify', 'label' => 'Spotify'])
            ->where('players', fn (Collection $players): bool => $players
                ->filter(fn (array $player): bool => in_array('spotify', $player['compatibleProviders'], true))
                ->pluck('requiresControlLink', 'kind')
                ->sortKeys()

                ->all() === ['browser' => true, 'polling' => true, 'soloist' => false]));
});

it('lets a Host on a disabled Polling Player save it again and change the device', function () {
    $host = User::factory()->create();
    $party = Party::factory()->create(['user_id' => $host->id, 'music_provider' => 'spotify', 'player_kind' => 'polling']);
    configureSpotifyCredentials();
    setPairingToggle('player.polling.enabled', false);
    Sanctum::actingAs($host);

    $this->putJson(route('api.v1.parties.player.update', $party), ['player_kind' => 'polling'])->assertOk();
    $this->putJson(route('api.v1.parties.player.update', $party), ['player_kind' => 'polling', 'device_id' => 'dev-2', 'device_name' => 'Lounge'])->assertOk();

    expect($party->fresh()->playback_device_id)->toBe('dev-2');
});

it('refuses a switch to a disabled Soloist Player', function () {
    $host = User::factory()->create();
    $party = Party::factory()->create(['user_id' => $host->id, 'music_provider' => 'spotify', 'player_kind' => 'polling']);
    configureSpotifyCredentials();
    setPairingToggle('player.soloist.enabled', false);
    Sanctum::actingAs($host);

    $this->putJson(route('api.v1.parties.player.update', $party), ['player_kind' => 'soloist'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.player_kind.0', 'This player has been disabled by an admin.');

    expect($party->fresh()->player_kind)->toBe('polling');
});

it('shows the current disabled kind as (disabled) in the settings picker', function () {
    $host = User::factory()->create();
    $party = Party::factory()->create(['user_id' => $host->id, 'music_provider' => 'spotify', 'player_kind' => 'polling']);
    configureSpotifyCredentials();
    setPairingToggle('player.polling.enabled', false);

    $this->withoutVite()->actingAs($host)->get(route('parties.settings', $party))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('player_kinds', fn (Collection $kinds): bool => $kinds->contains(['value' => 'polling', 'label' => 'Polling player (disabled)'])));
});

it('keeps searching and ticking a Live Party on a disabled provider', function () {
    $party = livePlaybackParty([], ['code' => 'DISB']);
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $player = useFakePlayer($party);
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->create();
    setPairingToggle('music_provider.fake.enabled', false);
    setPairingToggle('player.fake.enabled', false);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/parties/DISB/search?q=song')->assertOk()->assertJsonCount(2, 'data');
    dispatch_sync(new TickParty($party->code));

    expect($player)->not->toBeNull();
});
