<?php

use App\Exceptions\ProviderPremiumRequiredException;
use App\Models\Song;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\Playlist;
use App\Services\Playback\StartPlayback;
use App\Services\Spotify\SpotifyBackoffException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;

require_once __DIR__.'/../../Support/DashboardFixtures.php';

beforeEach(function () {
    $this->fake = dashboardFake()->withQueueDepth(1);
    $this->party = dashboardParty();
    $this->request = dashboardRequest($this->party);
    Sanctum::actingAs($this->party->user);
});

function startPlayback(mixed $test, array $body = []): TestResponse
{
    return $test->postJson(route('api.v1.parties.playback.start', $test->party), $body);
}

it('creates the context playlist when missing and plays the next request in it', function () {
    startPlayback($this)->assertOk()->assertJsonPath('target.kind', 'select')->assertJsonPath('target.trackId', $this->request->song->provider_track_id);

    $created = $this->fake->callsTo('createPrivatePlaylist');
    $play = $this->fake->callsTo('playInContext');
    expect($created)->toBe([['Music Party Context', 'Used by Music Party to start playback. Leave it empty.']])
        ->and($this->party->fresh()->context_playlist_id)->toBe('fake-owned-1')
        ->and($play[0][0])->toBe('fake-owned-1')
        ->and($play[0][1]->id)->toBe($this->request->song->provider_track_id)
        ->and($play[0][3])->toBe(0);
});

it('reuses an existing playlist with the same name', function () {
    $this->fake->withOwnedPlaylist(new Playlist('existing', 'Music Party Context', 'Fake Owner', 0));

    startPlayback($this)->assertOk();

    expect($this->fake->callsTo('createPrivatePlaylist'))->toBeEmpty()
        ->and($this->party->fresh()->context_playlist_id)->toBe('existing');
});

it('reuses the stored playlist when the host still owns it', function () {
    $this->fake->withOwnedPlaylist(new Playlist('mine', 'Music Party Context', 'Fake Owner', 0));
    $this->party->forceFill(['context_playlist_id' => 'mine'])->saveQuietly();

    startPlayback($this)->assertOk();

    expect($this->fake->callsTo('findOwnedPlaylistByName'))->toBeEmpty()
        ->and($this->fake->callsTo('createPrivatePlaylist'))->toBeEmpty()
        ->and($this->fake->callsTo('playInContext')[0][0])->toBe('mine');
});

it('replaces a stored playlist that is owned but not named Music Party Context', function () {
    $this->fake->withOwnedPlaylist(new Playlist('road-trip', 'Road Trip', 'Fake Owner', 40));
    $this->party->forceFill(['context_playlist_id' => 'road-trip'])->saveQuietly();

    startPlayback($this)->assertOk();

    expect($this->party->fresh()->context_playlist_id)->toBe('fake-owned-2')
        ->and($this->fake->callsTo('playInContext')[0][0])->toBe('fake-owned-2');
});

it('weighted parties start the track they were shown', function () {
    $this->party->forceFill(['weighted' => true])->saveQuietly();
    foreach (range(1, 6) as $i) {
        dashboardRequest($this->party, ['score' => $i]);
    }
    $shown = app(StartPlayback::class)->target($this->party->fresh(), null);

    startPlayback($this)->assertOk()->assertJsonPath('target.trackId', $shown->track->id);
});

it('replaces a stored playlist the host does not own', function () {
    $this->party->forceFill(['context_playlist_id' => 'foreign'])->saveQuietly();

    startPlayback($this)->assertOk();

    expect($this->party->fresh()->context_playlist_id)->toBe('fake-owned-1');
});

it('picks the device', function (array $devices, array $partyAttributes, array $body, string $expected) {
    $this->fake->withDevices(array_map(fn (array $d): Device => new Device($d[0], $d[0], 'Speaker', $d[1] ?? false, $d[2] ?? false), $devices));
    $this->party->forceFill($partyAttributes)->saveQuietly();

    startPlayback($this, $body)->assertOk()->assertJsonPath('deviceId', $expected);

    expect($this->fake->callsTo('playInContext')[0][2])->toBe($expected)
        ->and($this->party->fresh()->recent_device_id)->toBe($expected);
})->with([
    'active device' => [[['d1'], ['d2', true]], [], [], 'd2'],
    'party device' => [[['d1'], ['d2'], ['d3']], ['device_id' => 'd3'], [], 'd3'],
    'recent device' => [[['d1'], ['d2'], ['d3']], ['recent_device_id' => 'd2'], [], 'd2'],
    'first unrestricted' => [[['d1', false, true], ['d2']], [], [], 'd2'],
    'explicit id wins' => [[['d1'], ['d2', true]], [], ['deviceId' => 'd1'], 'd1'],
]);

it('rejects bad devices', function (array $devices, array $body, int $status, string $code) {
    $this->fake->withDevices(array_map(fn (array $d): Device => new Device($d[0], $d[0], 'Speaker', false, $d[1] ?? false), $devices));

    startPlayback($this, $body)->assertStatus($status)->assertJsonPath('code', $code);

    expect($this->fake->callsTo('playInContext'))->toBeEmpty();
})->with([
    'restricted' => [[['d1', true]], ['deviceId' => 'd1'], 409, 'device_restricted'],
    'unknown' => [[['d1']], ['deviceId' => 'nope'], 422, 'device_not_found'],
    'no devices' => [[], [], 409, 'no_device'],
    'all restricted' => [[['d1', true]], [], 409, 'no_device'],
]);

it('resumes a paused track from its position', function () {
    $current = Song::factory()->create(['provider_id' => 'spotify', 'provider_track_id' => 'cur']);
    $this->party->song()->associate($current)->saveQuietly();
    $this->fake->withTrack(dashboardTrack('cur'))->withPlayback(dashboardPlaying('cur', playing: false, progressMs: 72000));

    startPlayback($this)->assertOk()->assertJsonPath('target.kind', 'resume')->assertJsonPath('target.positionMs', 72000);

    $play = $this->fake->callsTo('playInContext')[0];
    expect($play[1]->id)->toBe('cur')->and($play[3])->toBe(72000);
});

it('plays in context then skips when the target was already sent to Spotify', function () {
    $this->request->forceFill(['queued_at' => now()])->save();

    startPlayback($this)->assertOk()->assertJsonPath('target.kind', 'next');

    $methods = array_column($this->fake->calls(), 'method');
    expect(array_search('playInContext', $methods, true))->toBeLessThan(array_search('next', $methods, true))
        ->and($this->fake->callsTo('playInContext')[0][1]->id)->toBe($this->request->song->provider_track_id);
});

it('stamps a selected request as sent and appends it to the history playlist', function () {
    $this->party->forceFill(['history_playlist_id' => 'HIST'])->saveQuietly();

    startPlayback($this)->assertOk();

    $history = $this->fake->callsTo('appendToPlaylist');
    expect($this->request->fresh()->queued_at)->not->toBeNull()
        ->and($history)->toHaveCount(1)
        ->and($history[0][0])->toBe('HIST')
        ->and($history[0][1][0]->id)->toBe($this->request->song->provider_track_id);
});

it('backfills from the fallback playlist when the queue is empty', function () {
    $this->request->delete();
    FakeFallbackPlaylist::seed(['bk1', 'bk2'], 'backup');
    $this->party->forceFill(['backup_playlist_id' => 'backup'])->saveQuietly();

    startPlayback($this)->assertOk()->assertJsonPath('target.kind', 'select');

    expect(['bk1', 'bk2'])->toContain($this->fake->callsTo('playInContext')[0][1]->id);
});

it('reports nothing to play', function () {
    $this->request->delete();

    startPlayback($this)->assertStatus(409)->assertJsonPath('code', 'nothing_to_play');
});

it('restarts the current track from the beginning', function () {
    $current = Song::factory()->create(['provider_id' => 'spotify', 'provider_track_id' => 'cur']);
    $this->party->song()->associate($current)->saveQuietly();
    $this->fake->withTrack(dashboardTrack('cur'))->withPlayback(dashboardPlaying('cur', progressMs: 90000));

    startPlayback($this, ['mode' => 'restart'])->assertOk()->assertJsonPath('target.positionMs', 0);

    $play = $this->fake->callsTo('playInContext')[0];
    expect($play[1]->id)->toBe('cur')->and($play[3])->toBe(0)
        ->and($this->request->fresh()->queued_at)->toBeNull();
});

it('flags the party and answers 409 when Spotify requires Premium', function () {
    $this->fake->withPlaybackFailure(new ProviderPremiumRequiredException('Premium required'));

    startPlayback($this)->assertStatus(409)->assertJsonPath('code', 'premium_required');

    expect(Cache::get("party.{$this->party->id}.premium-required"))->toBeTrue();
});

it('clears the premium flag after a successful start', function () {
    Cache::put("party.{$this->party->id}.premium-required", true, 600);

    startPlayback($this)->assertOk();

    expect(Cache::get("party.{$this->party->id}.premium-required"))->toBeNull();
});

it('answers 503 with Retry-After when Spotify is rate limiting', function () {
    $this->fake->withPlaybackFailure(new SpotifyBackoffException(12));

    startPlayback($this)->assertStatus(503)->assertHeader('Retry-After', '12')
        ->assertJson(['code' => 'rate_limited', 'retryAfter' => 12]);
});

it('answers 409 when the Spotify link must be renewed', function () {
    $this->fake->withRelinkRequired();

    startPlayback($this)->assertStatus(409)->assertJsonPath('code', 'relink_required');
});

it('answers 409 busy while another sync holds the lock', function () {
    config(['musicparty.prevent_overlapping_updates' => true]);
    $lock = Cache::lock("party-sync:{$this->party->id}", 30);
    $lock->get();

    startPlayback($this)->assertStatus(409)->assertJsonPath('code', 'busy');

    expect($this->fake->callsTo('playInContext'))->toBeEmpty();
    $lock->release();
});

it('syncs the party after starting', function () {
    startPlayback($this)->assertOk();

    $methods = array_column($this->fake->calls(), 'method');
    expect(array_search('currentPlayback', array_reverse($methods, true), true))->toBeGreaterThan(array_search('playInContext', $methods, true));
});

it('keeps non-managers out', function () {
    Sanctum::actingAs(dashboardUser($this->party, 'vip'));

    startPlayback($this)->assertForbidden();
});
