<?php

use App\Jobs\PartyUpdate;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Spotify\SpotifyBackoffException;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Support/DashboardFixtures.php';

beforeEach(function () {
    $this->fake = dashboardFake();
    $this->party = dashboardParty(['active' => false]);
    Sanctum::actingAs($this->party->user);
});

it('lists devices with restriction, label and icon', function () {
    $this->party->forceFill(['recent_device_id' => 'enc', 'playback_source' => PlaybackSource::Encore])->saveQuietly();
    $this->fake->withDevices([
        new Device('enc', 'Encore', 'Speaker', true),
        new Device('lap', 'Laptop', 'Computer', false),
        new Device('cast', 'Chromecast', 'CastVideo', false, true),
    ]);

    $response = $this->getJson(route('api.v1.parties.devices', $this->party))->assertOk();

    expect($response->json('data'))->toBe([
        ['id' => 'enc', 'name' => 'Encore', 'type' => 'Speaker', 'isActive' => true, 'isRestricted' => false, 'label' => 'Encore · active now', 'icon' => 'ti-device-speaker'],
        ['id' => 'lap', 'name' => 'Laptop', 'type' => 'Computer', 'isActive' => false, 'isRestricted' => false, 'label' => 'Computer', 'icon' => 'ti-device-laptop'],
        ['id' => 'cast', 'name' => 'Chromecast', 'type' => 'CastVideo', 'isActive' => false, 'isRestricted' => true, 'label' => "Can't be controlled remotely", 'icon' => 'ti-device-tv'],
    ]);
});

it('answers 409 for devices when the Spotify link must be renewed', function () {
    $this->fake->withRelinkRequired();

    $this->getJson(route('api.v1.parties.devices', $this->party))->assertStatus(409)->assertJsonPath('code', 'relink_required');
});

it('answers 503 for devices while Spotify is rate limiting', function () {
    $this->fake->failingDevicesWith(new SpotifyBackoffException(7));

    $this->getJson(route('api.v1.parties.devices', $this->party))->assertStatus(503)
        ->assertHeader('Retry-After', '7')->assertJson(['code' => 'rate_limited', 'retryAfter' => 7]);
});

it('activates the party', function () {
    Queue::fake();

    $this->postJson(route('api.v1.parties.activate', $this->party))->assertNoContent();

    expect($this->party->fresh()->active)->toBeTrue();
    Queue::assertPushed(PartyUpdate::class);
});

it('keeps non-managers out of devices and activate', function (string $method, string $route) {
    Sanctum::actingAs(dashboardUser($this->party, 'vip'));

    $this->{$method}(route($route, $this->party))->assertForbidden();
    expect($this->party->fresh()->active)->toBeFalse();
})->with([
    ['getJson', 'api.v1.parties.devices'],
    ['postJson', 'api.v1.parties.activate'],
]);
