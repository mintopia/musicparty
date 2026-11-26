<?php

use App\Exceptions\PartyRelinkRequiredException;
use App\Jobs\EmptyContextPlaylist;
use App\Models\Song;
use App\Models\User;
use App\Services\Music\Data\Playlist;
use App\Services\Playback\ContextPlaylist;
use App\Services\Spotify\SpotifyBackoffException;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../../Support/DashboardFixtures.php';

beforeEach(function () {
    $this->fake = dashboardFake();
    $this->party = dashboardParty();
});

it('empties a non-empty owned context playlist and leaves an empty one alone', function (int $items, int $clears) {
    $this->fake->withOwnedPlaylist(new Playlist('ctx', 'Music Party Context', 'Fake Owner', 0), $items);
    $this->party->forceFill(['context_playlist_id' => 'ctx'])->saveQuietly();

    EmptyContextPlaylist::dispatchSync($this->party);

    expect($this->fake->callsTo('clearPlaylist'))->toHaveCount($clears)
        ->and($this->party->fresh()->context_playlist_id)->toBe('ctx');
})->with([
    'three tracks' => [3, 1],
    'empty' => [0, 0],
]);

it('forgets a context playlist the host no longer owns', function () {
    $this->party->forceFill(['context_playlist_id' => 'gone'])->saveQuietly();

    EmptyContextPlaylist::dispatchSync($this->party);

    expect($this->party->fresh()->context_playlist_id)->toBeNull()
        ->and($this->fake->callsTo('clearPlaylist'))->toBeEmpty();
});

it('releases the job on backoff and stays quiet when a relink is needed', function () {
    $context = Mockery::mock(ContextPlaylist::class);
    $context->shouldReceive('enforceEmpty')->once()->andThrow(new SpotifyBackoffException(12));
    $job = new EmptyContextPlaylist($this->party)->withFakeQueueInteractions();
    $job->handle($context);
    $job->assertReleased(12);

    $relink = Mockery::mock(ContextPlaylist::class);
    $relink->shouldReceive('enforceEmpty')->once()->andThrow(new PartyRelinkRequiredException);
    $quiet = new EmptyContextPlaylist($this->party)->withFakeQueueInteractions();
    $quiet->handle($relink);
    $quiet->assertNotReleased();
});

it('only dispatches the check for active parties that have a context playlist', function () {
    Queue::fake();
    $this->party->forceFill(['context_playlist_id' => 'ctx'])->saveQuietly();
    dashboardParty(['active' => false, 'context_playlist_id' => 'ctx']);
    dashboardParty(['active' => true]);

    $this->artisan('party:contextplaylist')->assertSuccessful();

    Queue::assertPushed(EmptyContextPlaylist::class, 1);
    Queue::assertPushed(EmptyContextPlaylist::class, fn (EmptyContextPlaylist $job) => $job->uniqueId() === (string) $this->party->id);
});

it('resets the context playlist when the host changes', function () {
    $this->party->forceFill(['context_playlist_id' => 'ctx'])->saveQuietly();

    $this->party->user_id = User::factory()->create()->id;
    $this->party->save();

    expect($this->party->fresh()->context_playlist_id)->toBeNull();
});

it('keeps the context playlist when other attributes change', function () {
    $this->party->forceFill(['context_playlist_id' => 'ctx'])->saveQuietly();

    $this->party->name = 'Renamed';
    $this->party->save();

    expect($this->party->fresh()->context_playlist_id)->toBe('ctx');
});

function contextSettingsPayload(array $extra = []): array
{
    return array_merge(['name' => 'Settings Party', 'backup_playlist_id' => 'other', 'custom_backup_playlist_id' => 'backup'], $extra);
}

it('accepts a context playlist the host owns, rejects one they do not, and clears on blank', function () {
    $this->withoutVite();
    $this->fake->withOwnedPlaylist(new Playlist('mine', 'Music Party Context', 'Fake Owner', 0));
    $url = route('parties.update', $this->party);

    $this->actingAs($this->party->user)->put($url, contextSettingsPayload(['context_playlist_id' => 'mine']))->assertSessionHasNoErrors();
    expect($this->party->fresh()->context_playlist_id)->toBe('mine');

    $this->actingAs($this->party->user)->put($url, contextSettingsPayload(['context_playlist_id' => 'theirs']))
        ->assertSessionHasErrors('context_playlist_id');
    expect($this->party->fresh()->context_playlist_id)->toBe('mine');

    $this->actingAs($this->party->user)->put($url, contextSettingsPayload(['context_playlist_id' => '']))->assertSessionHasNoErrors();
    expect($this->party->fresh()->context_playlist_id)->toBeNull();
});

it('rejects owned playlists that are not named Music Party Context', function (string $name) {
    $this->fake->withOwnedPlaylist(new Playlist('real', $name, 'Fake Owner', 50));

    $this->actingAs($this->party->user)->put(route('parties.update', $this->party), contextSettingsPayload(['context_playlist_id' => 'real']))
        ->assertSessionHasErrors('context_playlist_id');

    expect($this->party->fresh()->context_playlist_id)->toBeNull();
})->with(['Road Trip 2024', 'Fallback']);

it('never empties a stored playlist whose name is not Music Party Context', function () {
    $this->fake->withOwnedPlaylist(new Playlist('real', 'Road Trip 2024', 'Fake Owner', 50), 50);
    $this->party->forceFill(['context_playlist_id' => 'real'])->saveQuietly();

    EmptyContextPlaylist::dispatchSync($this->party);

    expect($this->fake->callsTo('clearPlaylist'))->toBeEmpty();
});

it('leaves the context playlist untouched when the form does not send it', function () {
    $this->party->forceFill(['context_playlist_id' => 'mine'])->saveQuietly();

    $this->actingAs($this->party->user)->put(route('parties.update', $this->party), contextSettingsPayload())->assertSessionHasNoErrors();

    expect($this->party->fresh()->context_playlist_id)->toBe('mine');
});

it('exposes the context playlist and its name on the edit page', function () {
    $this->withoutVite();
    $this->fake->withOwnedPlaylist(new Playlist('mine', 'Music Party Context', 'Fake Owner', 0));
    $this->party->forceFill(['context_playlist_id' => 'mine'])->saveQuietly();

    $this->actingAs($this->party->user)->get(route('parties.edit', $this->party))->assertInertia(fn ($page) => $page
        ->where('party.context_playlist_id', 'mine')
        ->where('contextPlaylistName', 'Music Party Context'));
});

it('plays forced playback inside the context playlist', function () {
    $song = Song::factory()->create(['provider_id' => 'spotify', 'provider_track_id' => 'cur']);
    $this->fake->withTrack(dashboardTrack('cur'));
    $this->party->song()->associate($song);
    $this->party->forceFill(['poll' => false, 'force' => true, 'device_id' => 'fake-device', 'last_updated_at' => now()->subMinutes(20)])->saveQuietly();

    $this->artisan('party:force', ['code' => $this->party->code])->assertSuccessful();

    $play = $this->fake->callsTo('playInContext');
    expect($play)->toHaveCount(1)
        ->and($play[0][1]->id)->toBe('cur')
        ->and($play[0][2])->toBe('fake-device')
        ->and($this->fake->callsTo('play'))->toBeEmpty();
});
