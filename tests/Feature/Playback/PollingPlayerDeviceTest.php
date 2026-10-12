<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Music\Providers\SpotifyMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Playback\Contracts\PlaybackClient;
use App\Domain\Playback\Jobs\StartPlayback;
use App\Domain\Playback\Spotify\SpotifyPlaybackClient;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Spotify\SpotifyFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['musicparty.music_providers.spotify' => ['label' => 'Spotify', 'class' => SpotifyMusicProvider::class]]);
    config(['services.spotify' => ['client_id' => 'id', 'client_secret' => 'secret']]);
    app()->bind(PlaybackClient::class, SpotifyPlaybackClient::class);
    $this->owner = User::factory()->create();
    $this->party = Party::factory()->live()->create([
        'code' => 'DEVC', 'player_kind' => 'polling', 'music_provider' => 'spotify', 'user_id' => $this->owner->id,
        'playback_device_id' => 'dev-1', 'playback_device_name' => 'Kitchen Speaker',
    ]);
    LinkedAccount::factory()
        ->for($this->owner)
        ->create([
            'social_provider_id' => SocialProvider::factory()->create(['code' => 'spotify'])->id,
            'access_token' => 'host-token',
            'scopes' => 'playlist-read-private user-read-playback-state user-modify-playback-state',
        ]);
});

function deviceLogEntries(): int
{
    return PartyLogEntry::query()->where('action', 'player.device_missing')->count();
}

it('sends the chosen device_id with play', function () {
    Sanctum::actingAs($this->owner);
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response('', 204)]);

    $this->postJson('/api/v1/parties/DEVC/playback/play')->assertOk();

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/me/player/play?device_id=dev-1'));
});

it('sends no device_id when none is chosen', function () {
    Sanctum::actingAs($this->owner);
    $this->party->forceFill(['playback_device_id' => null, 'playback_device_name' => null])->save();
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response('', 204)]);

    $this->postJson('/api/v1/parties/DEVC/playback/seek', ['position_ms' => 1000])->assertOk();

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/me/player/seek?position_ms=1000'));
});

it('names the device in a conflict and logs once a minute', function () {
    Sanctum::actingAs($this->owner);
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response(['error' => ['status' => 404, 'message' => 'Device not found']], 404)]);

    $this->postJson('/api/v1/parties/DEVC/playback/play')->assertStatus(409)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Kitchen Speaker'));
    $this->postJson('/api/v1/parties/DEVC/playback/play')->assertStatus(409);

    expect(deviceLogEntries())->toBe(1);
    $entry = PartyLogEntry::query()->where('action', 'player.device_missing')->firstOrFail();
    expect($entry->system_actor)->not->toBeNull()->and($entry->user_id)->toBeNull();
});

it('logs again after the throttle window', function () {
    Sanctum::actingAs($this->owner);
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response(['error' => ['status' => 404, 'message' => 'Device not found']], 404)]);

    $this->postJson('/api/v1/parties/DEVC/playback/pause')->assertStatus(409);
    $this->travel(61)->seconds();
    $this->postJson('/api/v1/parties/DEVC/playback/pause')->assertStatus(409);

    expect(deviceLogEntries())->toBe(2);
});

it('keeps the no-active-device conflict separate from a missing device', function () {
    Sanctum::actingAs($this->owner);
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response(['error' => ['status' => 404, 'reason' => 'NO_ACTIVE_DEVICE']], 404)]);

    $this->postJson('/api/v1/parties/DEVC/playback/play')->assertStatus(409)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'no active device'));

    expect(deviceLogEntries())->toBe(0);
});

it('lists devices for the Host on the API and web', function (string $url) {
    $this->actingAs($this->owner);
    Sanctum::actingAs($this->owner);
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/devices' => Http::response(['devices' => [
        ['id' => 'dev-1', 'name' => 'Kitchen Speaker', 'type' => 'Speaker', 'is_active' => true],
    ]])]);

    $this->getJson($url)->assertOk()->assertExactJson(['data' => [
        ['id' => 'dev-1', 'name' => 'Kitchen Speaker', 'type' => 'Speaker', 'is_active' => true],
    ]]);
})->with(['api' => '/api/v1/parties/DEVC/player/devices', 'web' => '/parties/DEVC/player/devices']);

it('forbids devices for moderators and non-hosts', function (string $url, string $role) {
    $user = User::factory()->create();

    if ($role !== 'stranger') {
        PartyMember::factory()->for($this->party)->for($user)->create(['role' => $role === 'moderator' ? PartyRole::Moderator : PartyRole::Guest]);
    }

    $this->actingAs($user);
    Sanctum::actingAs($user);
    SpotifyFake::hostApi();

    $this->getJson($url)->assertForbidden();
    Http::assertNothingSent();
})->with([
    'api' => ['/api/v1/parties/DEVC/player/devices'],
    'web' => ['/parties/DEVC/player/devices'],
])->with(['moderator', 'guest', 'stranger']);

it('asks the Host to re-link when scopes are missing', function (string $url) {
    $this->actingAs($this->owner);
    Sanctum::actingAs($this->owner);
    LinkedAccount::query()->update(['scopes' => 'playlist-read-private']);
    SpotifyFake::hostApi();

    $this->getJson($url)->assertStatus(409)->assertJsonPath('relink_required', true);
})->with(['api' => '/api/v1/parties/DEVC/player/devices', 'web' => '/parties/DEVC/player/devices']);

it('stores and clears the device when the player changes', function () {
    Sanctum::actingAs($this->owner);

    $this->putJson('/api/v1/parties/DEVC/player', ['player_kind' => 'polling', 'device_id' => 'dev-2', 'device_name' => 'Lounge'])->assertOk();
    expect($this->party->fresh()->playback_device_id)->toBe('dev-2')->and($this->party->fresh()->playback_device_name)->toBe('Lounge');

    $this->putJson('/api/v1/parties/DEVC/player', ['player_kind' => 'polling'])->assertOk();
    expect($this->party->fresh()->playback_device_id)->toBe('dev-2');

    $this->putJson('/api/v1/parties/DEVC/player', ['player_kind' => 'polling', 'device_id' => null])->assertOk();
    expect($this->party->fresh()->playback_device_id)->toBeNull();

    $this->party->forceFill(['playback_device_id' => 'dev-3', 'playback_device_name' => 'X'])->save();
    $this->putJson('/api/v1/parties/DEVC/player', ['player_kind' => 'soloist'])->assertOk();
    expect($this->party->fresh()->playback_device_id)->toBeNull()->and($this->party->fresh()->playback_device_name)->toBeNull();
});

it('updates the player through the web route for the Host only', function () {
    $this->actingAs($this->owner)->put('/parties/DEVC/player', ['player_kind' => 'polling', 'device_id' => 'dev-9', 'device_name' => 'Den'])->assertRedirect();
    expect($this->party->fresh()->playback_device_id)->toBe('dev-9');

    $mod = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($mod)->create(['role' => PartyRole::Moderator]);
    $this->actingAs($mod)->put('/parties/DEVC/player', ['player_kind' => 'polling', 'device_id' => 'evil'])->assertForbidden();
    expect($this->party->fresh()->playback_device_id)->toBe('dev-9');
});

it('adds nullable device columns and no force column', function () {
    expect(Schema::hasColumns('parties', ['playback_device_id', 'playback_device_name']))->toBeTrue()
        ->and(Schema::hasColumn('parties', 'force'))->toBeFalse()
        ->and(array_key_exists('party:force', Artisan::all()))->toBeFalse();
});

it('schedules nothing that starts playback for an idle Party', function () {
    $events = collect(app(Schedule::class)->events())->map(fn (Event $event): string => $event->description.' '.$event->command);

    expect($events->filter(fn (string $e): bool => str_contains($e, 'StartPlayback') || str_contains($e, 'force')))->toBeEmpty()
        ->and(class_exists(StartPlayback::class))->toBeTrue();
});
