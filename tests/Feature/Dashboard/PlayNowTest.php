<?php

use App\Models\PlayedSong;
use App\Models\UpcomingSong;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Support/DashboardFixtures.php';

beforeEach(function () {
    $this->fake = dashboardFake()->withQueueDepth(1);
    $this->party = dashboardParty();
    $this->sent = dashboardRequest($this->party, ['queued_at' => now()]);
    $this->other = dashboardRequest($this->party);
    Sanctum::actingAs($this->party->user);
});

function playNow(mixed $test, UpcomingSong $request, ?object $party = null): TestResponse
{
    return $test->postJson(route('api.v1.parties.upcomingsongs.play', [$party ?? $test->party, $request]));
}

function playedInOrder(mixed $test, UpcomingSong ...$requests): array
{
    return array_map(function (UpcomingSong $request) use ($test): ?int {
        $played = new PlayedSong;
        $played->song()->associate($request->song);
        $played->party()->associate($test->party);
        $played->played_at = now();
        $played->findRequestedSong();
        $played->save();

        return $played->upcoming_song_id;
    }, $requests);
}

it('skips to the request that was already sent to Spotify', function () {
    playNow($this, $this->sent)->assertNoContent();

    expect($this->fake->callsTo('next'))->toHaveCount(1)
        ->and($this->fake->callsTo('playInContext'))->toBeEmpty();
});

it('plays another request in context and slots it just before the sent one', function () {
    playNow($this, $this->other)->assertNoContent();

    $play = $this->fake->callsTo('playInContext');
    expect($play)->toHaveCount(1)
        ->and($play[0][1]->id)->toBe($this->other->song->provider_track_id)
        ->and($this->other->fresh()->queued_at->equalTo($this->sent->fresh()->queued_at->subSecond()))->toBeTrue();
});

it('keeps both requests linked to their plays once the sent request plays after', function () {
    playNow($this, $this->other)->assertNoContent();

    expect(playedInOrder($this, $this->other->fresh(), $this->sent->fresh()))->toBe([$this->other->id, $this->sent->id]);
});

it('stamps the request as sent now when nothing else was sent', function () {
    $this->sent->delete();

    playNow($this, $this->other)->assertNoContent();

    expect($this->other->fresh()->queued_at->diffInSeconds(now(), true))->toBeLessThan(5);
});

it('refuses flagged and already played requests', function () {
    $flagged = dashboardRequest($this->party, ['flagged_at' => now(), 'flag_reason' => 'x']);
    $played = dashboardRequest($this->party, ['queued_at' => now()->subMinute()]);
    dashboardPlayed($played);

    playNow($this, $flagged)->assertStatus(422)->assertJsonPath('code', 'not_playable');
    playNow($this, $played)->assertStatus(422)->assertJsonPath('code', 'not_playable');
    expect($this->fake->callsTo('playInContext'))->toBeEmpty();
});

it('does not find another party\'s request', function () {
    $elsewhere = dashboardParty();
    $foreign = dashboardRequest($elsewhere);

    playNow($this, $foreign)->assertNotFound();
});

it('keeps non-moderators out', function (string $role) {
    Sanctum::actingAs(dashboardUser($this->party, $role));

    playNow($this, $this->other)->assertForbidden();
})->with(['vip', 'admin']);

it('answers 409 busy while another sync holds the lock', function () {
    config(['musicparty.prevent_overlapping_updates' => true]);
    $lock = Cache::lock("party-sync:{$this->party->id}", 30);
    $lock->get();

    playNow($this, $this->other)->assertStatus(409)->assertJsonPath('code', 'busy');

    $lock->release();
});
