<?php

use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Play;
use App\Models\Rating;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->startOfHour()->addMinutes(30));
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->user = User::factory()->create();
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
    Sanctum::actingAs($this->user);
});

function playIn(Party $party, array $attributes = []): Play
{
    return Play::factory()->for($party)->create($attributes);
}

function ratingUrl(Play $play, string $code = 'ABCD'): string
{
    return "/api/v1/parties/{$code}/plays/{$play->id}/rating";
}

it('lists plays oldest first with counts and my rating', function () {
    $older = playIn($this->party, ['title' => 'Older', 'played_at' => now()->subHour()]);
    $newer = playIn($this->party, ['title' => 'Newer', 'played_at' => now()->subMinute()]);
    $others = PartyMember::factory()->for($this->party)->count(3)->create();
    Rating::factory()->for($newer)->for($others[0], 'member')->create(['value' => 1]);
    Rating::factory()->for($newer)->for($others[1], 'member')->create(['value' => 1]);
    Rating::factory()->for($newer)->for($others[2], 'member')->create(['value' => -1]);
    Rating::factory()->for($newer)->for($this->member, 'member')->create(['value' => -1]);
    playIn(Party::factory()->create(), ['title' => 'Elsewhere']);

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $older->id)
        ->assertJsonPath('data.0.likes', 0)
        ->assertJsonPath('data.0.my_rating', 0)
        ->assertJsonPath('data.1.id', $newer->id)
        ->assertJsonPath('data.1.track.title', 'Newer')
        ->assertJsonPath('data.1.likes', 2)
        ->assertJsonPath('data.1.dislikes', 2)
        ->assertJsonPath('data.1.my_rating', -1);
});

it('breaks ties on played time by id ascending', function () {
    $at = now()->subMinute();
    $first = playIn($this->party, ['played_at' => $at]);
    $second = playIn($this->party, ['played_at' => $at]);

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.1.id', $second->id);
});

it('names the requester and leaves fallback plays anonymous', function () {
    $requester = PartyMember::factory()->for($this->party)->for(User::factory()->create(['nickname' => 'Alex']))->create();
    $request = TrackRequest::factory()->for($this->party)->create();
    playIn($this->party, ['party_member_id' => $requester->id, 'track_request_id' => $request->id, 'played_at' => now()->subMinute()]);
    playIn($this->party, ['played_at' => now()->subHour()]);

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()
        ->assertJsonPath('data.0.requested_by.name', null)
        ->assertJsonPath('data.1.requested_by.name', 'Alex');
});

it('returns an empty history', function () {
    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()->assertJsonCount(0, 'data');
});

it('paginates the history', function () {
    Play::factory()->for($this->party)->count(30)->create();

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()->assertJsonCount(25, 'data');
    $this->getJson('/api/v1/parties/ABCD/history?page=2')->assertOk()->assertJsonCount(5, 'data');
});

it('lets a banned member view the history read-only', function () {
    $this->member->forceFill(['banned' => true])->save();
    playIn($this->party);

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()->assertJsonCount(1, 'data');
});

it('refuses the history to non-members and anonymous visitors', function () {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);
    $this->getJson('/api/v1/parties/ABCD/history')->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', '')->getJson('/api/v1/parties/ABCD/history')->assertUnauthorized();
});

it('reports votes, score and requested time from the originating request', function () {
    $request = TrackRequest::factory()->for($this->party)->create(['created_at' => now()->subHours(2)]);
    $voters = PartyMember::factory()->for($this->party)->count(3)->create();
    RequestVote::factory()->for($request, 'request')->for($voters[0], 'member')->create(['value' => 1]);
    RequestVote::factory()->for($request, 'request')->for($voters[1], 'member')->create(['value' => 1]);
    RequestVote::factory()->down()->for($request, 'request')->for($voters[2], 'member')->create();
    $played = playIn($this->party, ['track_request_id' => $request->id, 'played_at' => now()->subMinute()]);
    $fallback = playIn($this->party, ['played_at' => now()->subHour()]);

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()
        ->assertJsonPath('data.1.id', $played->id)
        ->assertJsonPath('data.1.votes', 3)
        ->assertJsonPath('data.1.score', 1)
        ->assertJsonPath('data.1.requested_at', $request->created_at->toIso8601String())
        ->assertJsonPath('data.0.id', $fallback->id)
        ->assertJsonPath('data.0.votes', 0)
        ->assertJsonPath('data.0.score', 0)
        ->assertJsonPath('data.0.requested_at', $fallback->played_at->toIso8601String());
});

it('loads the history without per-row queries', function () {
    foreach (range(1, 6) as $i) {
        $request = TrackRequest::factory()->for($this->party)->create();
        playIn($this->party, ['track_request_id' => $request->id, 'party_member_id' => $this->member->id]);
    }

    DB::enableQueryLog();
    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()->assertJsonCount(6, 'data');
    $withSix = count(DB::getQueryLog());
    DB::flushQueryLog();
    foreach (range(1, 6) as $i) {
        $request = TrackRequest::factory()->for($this->party)->create();
        playIn($this->party, ['track_request_id' => $request->id, 'party_member_id' => $this->member->id]);
    }
    DB::flushQueryLog();
    app()->forgetScopedInstances();
    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()->assertJsonCount(12, 'data');

    expect(count(DB::getQueryLog()))->toBe($withSix);
});

it('filters the history by name, artist, album and type', function () {
    $request = TrackRequest::factory()->for($this->party)->create();
    $a = playIn($this->party, ['title' => 'Rewind the Night', 'artists' => ['The Lowlands'], 'album' => 'Static Years', 'track_request_id' => $request->id, 'played_at' => now()->subMinutes(3)]);
    $b = playIn($this->party, ['title' => 'Glass Cathedral', 'artists' => ['Velvet Echo', 'Guest'], 'album' => 'Echoes', 'played_at' => now()->subMinutes(2)]);

    $ids = fn (string $query): array => collect($this->getJson("/api/v1/parties/ABCD/history?{$query}")->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('name=rewind'))->toBe([$a->id])
        ->and($ids('artist=guest'))->toBe([$b->id])
        ->and($ids('album=static'))->toBe([$a->id])
        ->and($ids('type=requested'))->toBe([$a->id])
        ->and($ids('type=fallback'))->toBe([$b->id])
        ->and($ids('type=sent'))->toBe([$a->id, $b->id])
        ->and($ids('name=glass&artist=velvet&album=echo&type=fallback'))->toBe([$b->id])
        ->and($ids('name=glass&artist=lowlands'))->toBe([])
        ->and($ids('name=nothing-matches'))->toBe([]);
});

it('treats like wildcards in filters literally', function () {
    playIn($this->party, ['title' => '100% Pure']);
    playIn($this->party, ['title' => 'Plain']);

    $this->getJson('/api/v1/parties/ABCD/history?name=%25')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/parties/ABCD/history?name=_lain')->assertOk()->assertJsonCount(0, 'data');
});

it('keeps filters in the pagination links and totals', function () {
    Play::factory()->for($this->party)->count(30)->create(['title' => 'Same']);
    Play::factory()->for($this->party)->count(3)->create(['title' => 'Other']);

    $this->getJson('/api/v1/parties/ABCD/history?name=Same')->assertOk()
        ->assertJsonPath('meta.total', 30)
        ->assertJsonPath('meta.from', 1)
        ->assertJsonPath('meta.to', 25)
        ->assertJsonCount(25, 'data');
    expect($this->getJson('/api/v1/parties/ABCD/history?name=Same')->json('links.next'))->toContain('name=Same');
});

it('rejects invalid history filters', function (array $query, string $field) {
    $this->getJson('/api/v1/parties/ABCD/history?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'unknown type' => [['type' => 'bogus'], 'type'],
    'array name' => [['name' => ['x']], 'name'],
    'long artist' => [['artist' => str_repeat('a', 101)], 'artist'],
    'bad page' => [['page' => 0], 'page'],
]);

it('keeps filters off other parties', function () {
    playIn(Party::factory()->create(), ['title' => 'Rewind elsewhere']);

    $this->getJson('/api/v1/parties/ABCD/history?name=Rewind')->assertOk()->assertJsonCount(0, 'data');
});

it('applies the filters on the party page and echoes them', function () {
    playIn($this->party, ['title' => 'Rewind the Night']);
    playIn($this->party, ['title' => 'Glass Cathedral']);

    $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD/history?name=glass&type=fallback')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('history.data', 1)
            ->where('history.data.0.track.title', 'Glass Cathedral')
            ->where('history.meta.total', 1)
            ->where('filters', ['name' => 'glass', 'artist' => '', 'album' => '', 'type' => 'fallback']));
});

it('rejects invalid filters on the party page', function () {
    $this->actingAs($this->user)->from('/parties/ABCD/history')
        ->get('/parties/ABCD/history?type=bogus')
        ->assertRedirect('/parties/ABCD/history')
        ->assertSessionHasErrors('type');
});

it('keeps votes, score and requested time on the rating response', function () {
    $request = TrackRequest::factory()->for($this->party)->create(['created_at' => now()->subHours(2)]);
    $voters = PartyMember::factory()->for($this->party)->count(2)->create();
    RequestVote::factory()->for($request, 'request')->for($voters[0], 'member')->create(['value' => 1]);
    RequestVote::factory()->down()->for($request, 'request')->for($voters[1], 'member')->create();
    $play = playIn($this->party, ['track_request_id' => $request->id, 'played_at' => now()->subMinute()]);

    $this->putJson(ratingUrl($play), ['value' => 'up'])->assertOk()
        ->assertJsonPath('data.votes', 2)
        ->assertJsonPath('data.score', 0)
        ->assertJsonPath('data.requested_at', $request->created_at->toIso8601String());
    $this->deleteJson(ratingUrl($play))->assertOk()
        ->assertJsonPath('data.votes', 2)
        ->assertJsonPath('data.requested_at', $request->created_at->toIso8601String());
});

it('likes a play and reports the counts', function () {
    $play = playIn($this->party);

    $this->putJson(ratingUrl($play), ['value' => 'up'])->assertOk()
        ->assertJsonPath('data.likes', 1)
        ->assertJsonPath('data.dislikes', 0)
        ->assertJsonPath('data.my_rating', 1);

    expect(Rating::query()->where('play_id', $play->id)->where('party_member_id', $this->member->id)->value('value'))->toBe(1);
});

it('keeps a single rating when changing from like to dislike', function () {
    $play = playIn($this->party);
    $this->putJson(ratingUrl($play), ['value' => 'up'])->assertOk();

    $this->putJson(ratingUrl($play), ['value' => 'down'])->assertOk()
        ->assertJsonPath('data.likes', 0)
        ->assertJsonPath('data.dislikes', 1)
        ->assertJsonPath('data.my_rating', -1);

    expect(Rating::query()->where('party_member_id', $this->member->id)->count())->toBe(1);
});

it('treats a repeated identical rating as a no-op', function () {
    $play = playIn($this->party);
    $this->putJson(ratingUrl($play), ['value' => 'up'])->assertOk();

    $this->putJson(ratingUrl($play), ['value' => 'up'])->assertOk()->assertJsonPath('data.likes', 1);

    expect(Rating::query()->count())->toBe(1);
});

it('retracts a rating and is idempotent when none exists', function () {
    $play = playIn($this->party);
    $this->putJson(ratingUrl($play), ['value' => 'down'])->assertOk();

    $this->deleteJson(ratingUrl($play))->assertOk()
        ->assertJsonPath('data.dislikes', 0)
        ->assertJsonPath('data.my_rating', 0);
    $this->deleteJson(ratingUrl($play))->assertOk()->assertJsonPath('data.my_rating', 0);

    expect(Rating::query()->count())->toBe(0);
});

it('refuses a banned member rating and does not store anything', function () {
    $play = playIn($this->party);
    $this->member->forceFill(['banned' => true])->save();

    $this->putJson(ratingUrl($play), ['value' => 'up'])->assertForbidden();
    $this->deleteJson(ratingUrl($play))->assertForbidden();

    expect(Rating::query()->count())->toBe(0);
});

it('refuses a non-member rating', function () {
    $play = playIn($this->party);
    Sanctum::actingAs(User::factory()->create());

    $this->putJson(ratingUrl($play), ['value' => 'up'])->assertForbidden();
});

it('requires authentication to rate', function () {
    $play = playIn($this->party);

    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', '')->putJson(ratingUrl($play), ['value' => 'up'])->assertUnauthorized();
});

it('returns not found for a play from another party', function () {
    $foreign = playIn(Party::factory()->create(['code' => 'WXYZ']));

    $this->putJson(ratingUrl($foreign), ['value' => 'up'])->assertNotFound();
    $this->deleteJson(ratingUrl($foreign))->assertNotFound();
});

it('rejects an invalid rating value', function (mixed $value) {
    $play = playIn($this->party);

    $this->putJson(ratingUrl($play), ['value' => $value])->assertUnprocessable()->assertJsonValidationErrors('value');
})->with(['missing' => [null], 'unknown' => ['sideways'], 'number' => [5]]);

it('lists and rates plays created by the fake player playback', function () {
    $party = livePlaybackParty();
    $player = useFakePlayer($party, FeedMode::Ahead);
    $member = PartyMember::factory()->for($party)->for($this->user)->create();
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r1', 'title' => 'First', 'created_at' => now()->subMinutes(2)]);
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r2', 'created_at' => now()->subMinute()]);
    app(PlaybackCoordinator::class)->startIfIdle($party);
    $player->advance();
    $play = Play::query()->where('party_id', $party->id)->where('title', 'First')->sole();

    $this->putJson(ratingUrl($play, $party->code), ['value' => 'up'])->assertOk();

    $this->getJson("/api/v1/parties/{$party->code}/history")->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.track.title', 'First')
        ->assertJsonPath('data.0.likes', 1)
        ->assertJsonPath('data.0.my_rating', 1);
    expect(Rating::query()->where('party_member_id', $member->id)->count())->toBe(1);
});

it('renders history props on the party page', function () {
    $play = playIn($this->party, ['title' => 'Song']);
    Rating::factory()->for($play)->for($this->member, 'member')->create(['value' => 1]);

    $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD/history')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Party/Show')
            ->where('section', 'history')
            ->has('history.data', 1)
            ->where('history.data.0.track.title', 'Song')
            ->where('history.data.0.likes', 1)
            ->where('history.data.0.my_rating', 1)
            ->has('history.links'));
});

it('renders an empty history on the party page', function () {
    $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD/history')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('history.data', 0));
});

it('rates and retracts through the web routes', function () {
    $play = playIn($this->party);

    $this->actingAs($this->user)->put("/parties/ABCD/plays/{$play->id}/rating", ['value' => 'down'])->assertRedirect();
    expect(Rating::query()->sole()->value)->toBe(-1);

    $this->actingAs($this->user)->delete("/parties/ABCD/plays/{$play->id}/rating")->assertRedirect();
    expect(Rating::query()->count())->toBe(0);
});

it('surfaces web rating refusals as errors', function () {
    $play = playIn($this->party);
    $this->member->forceFill(['banned' => true])->save();

    $this->actingAs($this->user)->from('/parties/ABCD/history')
        ->put("/parties/ABCD/plays/{$play->id}/rating", ['value' => 'up'])
        ->assertRedirect('/parties/ABCD/history')
        ->assertSessionHasErrors('rating');

    expect(Rating::query()->count())->toBe(0);
});

it('returns not found for a foreign play through the web routes', function () {
    $foreign = playIn(Party::factory()->create());

    $this->actingAs($this->user)->put("/parties/ABCD/plays/{$foreign->id}/rating", ['value' => 'up'])->assertNotFound();
});

it('validates the web rating value', function () {
    $play = playIn($this->party);

    $this->actingAs($this->user)->put("/parties/ABCD/plays/{$play->id}/rating", ['value' => 'x'])->assertSessionHasErrors('value');
});

it('refuses rating and retracting through the api once the party has ended', function () {
    $play = playIn($this->party);
    Rating::factory()->for($play)->for($this->member, 'member')->create(['value' => 1]);
    $this->party->forceFill(['state' => 'ended'])->save();

    $this->putJson(ratingUrl($play), ['value' => 'down'])->assertUnprocessable()
        ->assertJsonPath('message', 'This party has ended, so ratings are closed.');
    $this->deleteJson(ratingUrl($play))->assertUnprocessable();

    expect(Rating::query()->sole()->value)->toBe(1);
});

it('refuses rating and retracting through the web once the party has ended', function () {
    $play = playIn($this->party);
    Rating::factory()->for($play)->for($this->member, 'member')->create(['value' => 1]);
    $this->party->forceFill(['state' => 'ended'])->save();

    $this->actingAs($this->user)->from('/parties/ABCD/history')
        ->put("/parties/ABCD/plays/{$play->id}/rating", ['value' => 'down'])
        ->assertRedirect('/parties/ABCD/history')
        ->assertSessionHasErrors(['rating' => 'This party has ended, so ratings are closed.']);
    $this->actingAs($this->user)->from('/parties/ABCD/history')
        ->delete("/parties/ABCD/plays/{$play->id}/rating")
        ->assertSessionHasErrors('rating');

    expect(Rating::query()->sole()->value)->toBe(1);
});

it('still lists the history of an ended party', function () {
    playIn($this->party);
    $this->party->forceFill(['state' => 'ended'])->save();

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()->assertJsonCount(1, 'data');
});

it('exposes the play of the currently playing request for the banner rating', function () {
    $finished = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Played, 'title' => 'Done']);
    playIn($this->party, ['track_request_id' => $finished->id, 'title' => 'Done', 'played_at' => now()->subMinute()]);
    $current = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Playing, 'title' => 'Current']);
    $currentPlay = playIn($this->party, ['track_request_id' => $current->id, 'title' => 'Current', 'played_at' => now()->subSeconds(30)]);
    Rating::factory()->for($currentPlay)->for($this->member, 'member')->create(['value' => 1]);

    $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('ratablePlay.id', $currentPlay->id)
            ->where('ratablePlay.track.title', 'Current')
            ->where('ratablePlay.likes', 1)
            ->where('ratablePlay.my_rating', 1));
});

it('has no ratable play when nothing is playing', function () {
    $finished = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Played]);
    playIn($this->party, ['track_request_id' => $finished->id]);

    $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD')
        ->assertInertia(fn (Assert $page): Assert => $page->where('ratablePlay', null));
});

it('rates the now playing play and any history play through the web route', function () {
    $current = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Playing]);
    $currentPlay = playIn($this->party, ['track_request_id' => $current->id]);
    $older = playIn($this->party, ['played_at' => now()->subHour()]);

    $this->actingAs($this->user)->put("/parties/ABCD/plays/{$currentPlay->id}/rating", ['value' => 'up'])->assertRedirect();
    $this->actingAs($this->user)->put("/parties/ABCD/plays/{$older->id}/rating", ['value' => 'down'])->assertRedirect();

    expect(Rating::query()->where('play_id', $currentPlay->id)->sole()->value)->toBe(1)
        ->and(Rating::query()->where('play_id', $older->id)->sole()->value)->toBe(-1);
});

it('creates the play when a request starts playing and not again when it finishes', function () {
    $party = livePlaybackParty();
    $player = useFakePlayer($party, FeedMode::Ahead);
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r1', 'title' => 'First', 'created_at' => now()->subMinutes(2)]);
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r2', 'title' => 'Second', 'created_at' => now()->subMinute()]);

    app(PlaybackCoordinator::class)->startIfIdle($party);

    $first = Play::query()->where('party_id', $party->id)->sole();
    expect($first->title)->toBe('First')
        ->and($first->request->status)->toBe(RequestStatus::Playing)
        ->and($first->played_at->equalTo($first->request->started_at))->toBeTrue();

    $player->advance();

    expect(Play::query()->where('party_id', $party->id)->orderBy('id')->pluck('title')->all())->toBe(['First', 'Second'])
        ->and(Play::query()->where('track_request_id', $first->track_request_id)->count())->toBe(1);
});
