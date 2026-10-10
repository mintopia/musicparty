<?php

use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\FallbackPlaylistGate;
use App\Domain\Party\PartyState;
use App\Jobs\StartPlayback;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\PartyMember;
use App\Models\Play;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Bus::fake([StartPlayback::class]));

/**
 * @param  array<string, mixed>  $overrides
 */
function fakeTrack(int $n, array $overrides = []): TrackData
{
    $values = array_merge([
        'durationMs' => 180000, 'explicit' => false, 'playable' => true,
    ], $overrides);

    return new TrackData(
        'fake', "t{$n}", "Track {$n}", [new ArtistData('a', 'Artist')], new AlbumData('al', 'Album'),
        $values['durationMs'], $values['explicit'], null, [], $values['playable'],
    );
}

/**
 * @param  list<TrackData>  $tracks
 */
function bindPlaylist(array $tracks): void
{
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider(playlistTracks: ['pl' => $tracks]));
}

function bindEnoughTracks(int $count = 20): void
{
    bindPlaylist(array_map(fn (int $n): TrackData => fakeTrack($n), range(1, $count)));
}

function lifecycleParty(string $state = 'paused', string $role = 'host', bool $playlist = true): array
{
    $party = Party::factory()->create([
        'code' => 'ABCD', 'state' => $state, 'fallback_playlist_id' => $playlist ? 'pl' : null,
    ]);
    $user = User::factory()->create();
    $member = PartyMember::factory()->for($party)->for($user);
    ($role === 'guest' ? $member : $member->{$role}())->create();

    return [$party, $user];
}

function recordPlay(Party $party, string $providerTrackId, DateTimeInterface $playedAt): void
{
    Play::factory()->for($party)->create(['provider_track_id' => $providerTrackId, 'played_at' => $playedAt]);
}

function gateCheck(Party $party): array
{
    $check = app(FallbackPlaylistGate::class)->check($party->fresh());

    return [$check->playable, $check->required, $check->passes()];
}

it('walks the full lifecycle via the web routes and writes log entries', function () {
    bindEnoughTracks();
    [$party, $host] = lifecycleParty();

    $this->actingAs($host)->post(route('parties.live', 'ABCD'))->assertRedirect();
    expect($party->fresh()->state)->toBe(PartyState::Live);

    $this->actingAs($host)->post(route('parties.pause', 'ABCD'))->assertRedirect();
    expect($party->fresh()->state)->toBe(PartyState::Paused);

    $this->actingAs($host)->post(route('parties.end', 'ABCD'))->assertRedirect();
    expect($party->fresh()->state)->toBe(PartyState::Ended);

    $this->actingAs($host)->post(route('parties.reopen', 'ABCD'))->assertRedirect();
    expect($party->fresh()->state)->toBe(PartyState::Paused);

    $entries = PartyLogEntry::query()->orderBy('id')->get();
    expect($entries->pluck('action')->all())->toBe(['party.went_live', 'party.paused', 'party.ended', 'party.reopened'])
        ->and($entries[0]->details)->toBe(['old' => 'paused', 'new' => 'live'])
        ->and($entries[2]->details)->toBe(['old' => 'paused', 'new' => 'ended'])
        ->and($entries[3]->details)->toBe(['old' => 'ended', 'new' => 'paused'])
        ->and($entries[0]->user_id)->toBe($host->id);
});

it('walks the full lifecycle via the API', function () {
    bindEnoughTracks();
    [$party, $host] = lifecycleParty();
    Sanctum::actingAs($host);

    $this->postJson(route('api.v1.parties.live', 'ABCD'))->assertOk()->assertJsonPath('data.state', 'live');
    $this->postJson(route('api.v1.parties.pause', 'ABCD'))->assertOk()->assertJsonPath('data.state', 'paused');
    $this->postJson(route('api.v1.parties.live', 'ABCD'))->assertOk();
    $this->postJson(route('api.v1.parties.end', 'ABCD'))->assertOk()->assertJsonPath('data.state', 'ended');
    $this->postJson(route('api.v1.parties.reopen', 'ABCD'))->assertOk()->assertJsonPath('data.state', 'paused');

    expect(PartyLogEntry::query()->count())->toBe(5);
});

it('refuses non-hosts on every transition', function (string $role, string $action, string $state) {
    bindEnoughTracks();
    [$party, $user] = lifecycleParty($state, $role);

    $this->actingAs($user)->post(route("parties.{$action}", 'ABCD'))->assertForbidden();
    Sanctum::actingAs($user);
    $this->postJson(route("api.v1.parties.{$action}", 'ABCD'))->assertForbidden();

    expect($party->fresh()->state->value)->toBe($state)->and(PartyLogEntry::query()->count())->toBe(0);
})->with(['moderator', 'guest'])->with([
    ['live', 'paused'], ['pause', 'live'], ['end', 'live'], ['reopen', 'ended'],
]);

it('rejects invalid transitions with a validation error', function (string $state, string $action) {
    bindEnoughTracks();
    [$party, $host] = lifecycleParty($state);

    $this->actingAs($host)->from('/parties/ABCD')->post(route("parties.{$action}", 'ABCD'))
        ->assertRedirect('/parties/ABCD')->assertSessionHasErrors('state');

    Sanctum::actingAs($host);
    $this->postJson(route("api.v1.parties.{$action}", 'ABCD'))->assertUnprocessable()->assertJsonValidationErrors('state');

    expect($party->fresh()->state->value)->toBe($state)->and(PartyLogEntry::query()->count())->toBe(0);
})->with([
    ['live', 'live'], ['ended', 'pause'], ['ended', 'live'], ['ended', 'end'], ['paused', 'pause'], ['paused', 'reopen'], ['live', 'reopen'],
]);

it('refuses to go live when the Fallback Playlist is short and reports the counts', function () {
    bindEnoughTracks(19);
    [$party, $host] = lifecycleParty();

    $this->actingAs($host)->post(route('parties.live', 'ABCD'))
        ->assertSessionHasErrors(['fallback_playlist_id' => 'The Fallback Playlist has only 19 of 20 required playable Tracks.']);

    Sanctum::actingAs($host);
    $this->postJson(route('api.v1.parties.live', 'ABCD'))->assertUnprocessable()
        ->assertJsonValidationErrors('fallback_playlist_id');

    expect($party->fresh()->state)->toBe(PartyState::Paused)->and(PartyLogEntry::query()->count())->toBe(0);
});

it('refuses to go live without a Fallback Playlist', function () {
    bindEnoughTracks();
    [$party, $host] = lifecycleParty(playlist: false);

    $this->actingAs($host)->post(route('parties.live', 'ABCD'))
        ->assertSessionHasErrors(['fallback_playlist_id' => 'The Fallback Playlist has only 0 of 20 required playable Tracks.']);
});

it('reopening keeps the party history', function () {
    bindEnoughTracks();
    [$party, $host] = lifecycleParty('ended');
    recordPlay($party, 't1', now()->subDay());

    $this->actingAs($host)->post(route('parties.reopen', 'ABCD'))->assertRedirect();

    expect($party->fresh()->state)->toBe(PartyState::Paused)->and(Play::query()->where('party_id', $party->id)->count())->toBe(1);
});

it('passes the gate with exactly 20 playable Tracks and fails with 19', function () {
    [$party] = lifecycleParty();

    bindEnoughTracks(20);
    expect(gateCheck($party))->toBe([20, 20, true]);

    bindEnoughTracks(19);
    expect(gateCheck($party))->toBe([19, 20, false]);
});

it('does not count unplayable Tracks', function () {
    [$party] = lifecycleParty();
    bindPlaylist([...array_map(fn (int $n): TrackData => fakeTrack($n), range(1, 20)), fakeTrack(21, ['playable' => false])]);

    expect(gateCheck($party))->toBe([20, 20, true]);

    bindPlaylist([...array_map(fn (int $n): TrackData => fakeTrack($n), range(1, 19)), fakeTrack(21, ['playable' => false])]);
    expect(gateCheck($party))->toBe([19, 20, false]);
});

it('applies the explicit filter', function () {
    [$party] = lifecycleParty();
    bindPlaylist([...array_map(fn (int $n): TrackData => fakeTrack($n), range(1, 20)), ...array_map(fn (int $n): TrackData => fakeTrack($n, ['explicit' => true]), range(21, 25))]);

    $party->forceFill(['explicit' => true])->save();
    expect(gateCheck($party)[0])->toBe(25);

    $party->forceFill(['explicit' => false])->save();
    expect(gateCheck($party)[0])->toBe(20);
});

it('applies the min and max song length in seconds', function () {
    [$party] = lifecycleParty();
    bindPlaylist([
        fakeTrack(1, ['durationMs' => 60000]),
        fakeTrack(2, ['durationMs' => 120000]),
        fakeTrack(3, ['durationMs' => 240000]),
        fakeTrack(4, ['durationMs' => 360000]),
    ]);

    $party->forceFill(['min_song_length' => 120, 'max_song_length' => null])->save();
    expect(gateCheck($party)[0])->toBe(3);

    $party->forceFill(['min_song_length' => null, 'max_song_length' => 240])->save();
    expect(gateCheck($party)[0])->toBe(3);

    $party->forceFill(['min_song_length' => 100, 'max_song_length' => 300])->save();
    expect(gateCheck($party)[0])->toBe(2);
});

it('excludes Tracks played inside the no-repeat window', function () {
    [$party] = lifecycleParty();
    bindEnoughTracks(5);
    recordPlay($party, 't1', now()->subMinutes(5));
    recordPlay($party, 't2', now()->subHours(3));

    $party->forceFill(['no_repeat_interval' => null])->save();
    expect(gateCheck($party)[0])->toBe(5);

    $party->forceFill(['no_repeat_interval' => 3600])->save();
    expect(gateCheck($party)[0])->toBe(4);

    $party->forceFill(['no_repeat_interval' => 86400])->save();
    expect(gateCheck($party)[0])->toBe(3);
});

it('updates the new settings and logs old and new values', function () {
    bindEnoughTracks();
    [$party, $host] = lifecycleParty();

    $this->actingAs($host)->patch(route('parties.update', 'ABCD'), [
        'explicit' => false, 'min_song_length' => 60, 'max_song_length' => 300, 'no_repeat_interval' => 600,
    ])->assertRedirect()->assertSessionMissing('warningMessage');

    $party->refresh();
    expect($party->explicit)->toBeFalse()->and($party->min_song_length)->toBe(60)
        ->and($party->max_song_length)->toBe(300)->and($party->no_repeat_interval)->toBe(600);

    $entry = PartyLogEntry::query()->where('subject', 'explicit')->sole();
    expect($entry->details)->toBe(['old' => true, 'new' => false]);
});

it('warns and logs when a rules change leaves the Fallback Playlist short', function () {
    bindEnoughTracks(20);
    [$party, $host] = lifecycleParty();

    $this->actingAs($host)->patch(route('parties.update', 'ABCD'), ['min_song_length' => 200])
        ->assertRedirect()
        ->assertSessionHas('warningMessage', 'The Fallback Playlist has only 0 of 20 required playable Tracks.');

    $entry = PartyLogEntry::query()->where('action', 'party.fallback_playlist_invalid')->sole();
    expect($entry->details)->toBe(['playable' => 0, 'required' => 20]);
});

it('returns the warning in the API response meta', function () {
    bindEnoughTracks(20);
    [$party, $host] = lifecycleParty();
    Sanctum::actingAs($host);

    $this->patchJson(route('api.v1.parties.update', 'ABCD'), ['fallback_playlist_id' => 'missing'])
        ->assertOk()
        ->assertJsonPath('data.fallback_playlist_id', 'missing')
        ->assertJsonPath('meta.warnings.0', 'The Fallback Playlist has only 0 of 20 required playable Tracks.');

    $this->patchJson(route('api.v1.parties.update', 'ABCD'), ['fallback_playlist_id' => 'pl'])
        ->assertOk()->assertJsonMissingPath('meta.warnings');
});

it('rejects a minimum length above the maximum', function () {
    [$party, $host] = lifecycleParty();

    $this->actingAs($host)->patch(route('parties.update', 'ABCD'), ['min_song_length' => 300, 'max_song_length' => 100])
        ->assertSessionHasErrors('min_song_length');

    expect($party->fresh()->min_song_length)->toBeNull();
});

it('shows lifecycle controls only to the host', function (string $role, bool $canManage) {
    [$party, $user] = lifecycleParty('paused', $role);

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD')
        ->assertInertia(fn (Assert $page): Assert => $page->where('canManage', $canManage));
})->with([['host', true], ['moderator', false], ['guest', false]]);

it('rejects a minimum length above the stored maximum', function () {
    $party = Party::factory()->create(['max_song_length' => 120]);
    $host = User::factory()->create();
    PartyMember::factory()->for($party)->for($host)->host()->create();
    Sanctum::actingAs($host);

    $this->patchJson("/api/v1/parties/{$party->code}", ['min_song_length' => 300])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('min_song_length');
});

it('lets the host switch selection mode via web and API, logging the change', function () {
    [$party, $host] = lifecycleParty();

    $this->actingAs($host)->patch(route('parties.update', 'ABCD'), ['selection_mode' => 'weighted'])->assertRedirect();
    expect($party->fresh()->selection_mode->value)->toBe('weighted');

    $entry = PartyLogEntry::query()->where('subject', 'selection_mode')->sole();
    expect($entry->details)->toBe(['old' => 'deterministic', 'new' => 'weighted']);

    Sanctum::actingAs($host);
    $this->patchJson(route('api.v1.parties.update', 'ABCD'), ['selection_mode' => 'deterministic'])
        ->assertOk()->assertJsonPath('data.selection_mode', 'deterministic');
});

it('rejects an unknown selection mode', function () {
    [$party, $host] = lifecycleParty();
    Sanctum::actingAs($host);

    $this->patchJson(route('api.v1.parties.update', 'ABCD'), ['selection_mode' => 'chaos'])
        ->assertUnprocessable()->assertJsonValidationErrors('selection_mode');

    expect($party->fresh()->selection_mode->value)->toBe('deterministic');
});
