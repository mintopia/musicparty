<?php

use App\Events\FlaggedRequestResolved;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Fake\FakeMusicProvider;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeFallbackPlaylist;

beforeEach(function () {
    app(FakeMusicProvider::class)->withTrack(FakeFallbackPlaylist::track('t1'));
    $this->party = Party::factory()->create(['provider_id' => 'fake', 'active' => true]);
    $this->withoutVite();
    User::query()->update(['first_login' => false]);
    $this->manager = $this->party->user->fresh();
});

function deleteRequestVia(string $entry, Party $party, UpcomingSong $request, User $user)
{
    test()->actingAs($user, 'sanctum');

    return $entry === 'web'
        ? test()->delete(route('parties.songs.destroy', [$party->code, $request->id]))
        : test()->deleteJson(route('api.v1.parties.upcomingsongs.destroy', [$party, $request]));
}

function scenarioRequest(Party $party, array $attributes = []): UpcomingSong
{
    return UpcomingSong::factory()->for($party)->for(Song::factory()->create(['provider_id' => 'fake']))->for(User::factory()->create())->create(array_merge(['queued_at' => null, 'flagged_at' => null], $attributes));
}

it('deletes a request and only broadcasts Resolved when it was flagged', function (string $entry, bool $flagged) {
    Event::fake([FlaggedRequestResolved::class]);
    $request = scenarioRequest($this->party, ['flagged_at' => $flagged ? now() : null]);

    deleteRequestVia($entry, $this->party, $request, $this->manager)->assertStatus($entry === 'web' ? 302 : 204);

    expect(UpcomingSong::find($request->id))->toBeNull();
    $flagged
        ? Event::assertDispatched(FlaggedRequestResolved::class, fn ($event) => $event->broadcastWith() === ['id' => $request->id, 'flaggedCount' => 0])
        : Event::assertNotDispatched(FlaggedRequestResolved::class);
})->with(['web', 'api'])->with([true, false]);

it('refuses to delete a request sent to the Player and keeps the Played Track link', function (string $entry) {
    Event::fake([FlaggedRequestResolved::class]);
    $request = scenarioRequest($this->party, ['queued_at' => now(), 'flagged_at' => now()]);
    $played = PlayedSong::factory()->create(['party_id' => $this->party->id, 'song_id' => $request->song_id, 'upcoming_song_id' => $request->id]);

    deleteRequestVia($entry, $this->party, $request, $this->manager)->assertStatus(409);

    expect(UpcomingSong::find($request->id))->not->toBeNull()
        ->and($played->fresh()->upcoming_song_id)->toBe($request->id);
    Event::assertNotDispatched(FlaggedRequestResolved::class);
})->with(['web', 'api']);

it('forbids a non-moderator from deleting through either entry point', function (string $entry) {
    $request = scenarioRequest($this->party);

    deleteRequestVia($entry, $this->party, $request, User::factory()->create())->assertForbidden();

    expect(UpcomingSong::find($request->id))->not->toBeNull();
})->with(['web', 'api']);
