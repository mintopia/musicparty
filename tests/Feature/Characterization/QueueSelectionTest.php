<?php

use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\Vote;
use App\Services\FallbackPlaylist;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use App\Services\QueueSelector;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\FakeFallbackPlaylist;
use Tests\Support\FakeSpotify;

function selectionParty(array $attributes = []): array
{
    $fake = FakeSpotify::install();
    $party = Party::factory()->create(array_merge(['active' => true, 'weighted' => false, 'backup_playlist_id' => 'BACKUP'], $attributes));
    Cache::flush();
    $fake->calls = [];
    app()->forgetInstance(FakeMusicProvider::class);

    return [$party, $fake];
}

function waitingRequest(Party $party, string $spotifyId, int $score = 0, array $attributes = []): UpcomingSong
{
    $song = Song::factory()->create(['spotify_id' => $spotifyId]);

    return UpcomingSong::factory()->for($party)->create(array_merge([
        'song_id' => $song->id,
        'score' => $score,
        'queued_at' => null,
    ], $attributes));
}

function seedRandomness(int $seed): void
{
    $randomizer = new Randomizer(new Mt19937($seed));
    app()->instance(Randomizer::class, $randomizer);
}

function queueWith(FakeSpotify $fake, array $trackIds): void
{
    $fake->respond('getMyQueue', (object) [
        'currently_playing' => null,
        'queue' => array_map(fn (string $id) => FakeSpotify::track($id), $trackIds),
    ]);
}

function sentIds(?FakeSpotify $fake = null): array
{
    return array_map(fn (Track $track) => $track->id, app(FakeMusicProvider::class)->sentToPlayer());
}

beforeEach(function () {
    Cache::flush();
    $this->freezeTime();
});

it('sends the highest scored waiting request first when not weighted, ties broken by created_at then id', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'LOW', 1, ['created_at' => now()->subMinutes(10)]);
    waitingRequest($party, 'TIEB', 5, ['created_at' => now()->subMinutes(5)]);
    waitingRequest($party, 'TIEA', 5, ['created_at' => now()->subMinutes(8)]);
    waitingRequest($party, 'TOP', 9, ['created_at' => now()->subMinutes(1)]);

    $party->updateState();

    expect(sentIds($fake))->toBe(['TOP', 'TIEA']);
});

it('sends the bare Spotify track id to the queue endpoint and stamps queued_at on the Request', function () {
    [$party, $fake] = selectionParty();
    $request = waitingRequest($party, 'ONE');

    $party->updateState();

    expect(sentIds($fake))->toBe(['ONE'])
        ->and($request->fresh()->queued_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('sends nothing and does not call queue when there are no waiting requests', function () {
    [$party, $fake] = selectionParty();

    $party->updateState();

    expect(sentIds($fake))->toBe([]);
});

it('does not resend a Request that already has queued_at set', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'DONE', 9, ['queued_at' => now()->subMinute()]);
    waitingRequest($party, 'WAIT', 1);

    $party->updateState();

    expect(sentIds($fake))->toBe(['WAIT']);
});

it('also sends requests with a zero or negative score when not weighted', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'NEG', -3);
    waitingRequest($party, 'ZERO', 0);

    $party->updateState();

    expect(sentIds($fake))->toBe(['ZERO', 'NEG']);
});

it('excludes a future not_before and includes a past one on the non-weighted path (E5 R10)', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'FUTURE', 5, ['not_before' => '2999-01-01 00:00:00']);
    waitingRequest($party, 'PAST', 1, ['not_before' => '2020-01-01 00:00:00']);

    $party->updateState();

    expect(sentIds($fake))->toBe(['PAST']);
});

it('does nothing when the party is inactive', function () {
    [$party, $fake] = selectionParty(['active' => false]);
    waitingRequest($party, 'ONE');

    $party->updateState();

    expect(sentIds($fake))->toBe([])
        ->and($fake->callsTo('getMyQueue'))->toBe([]);
});

it('sends two requests when the Spotify queue is empty because QUEUE_LENGTH minus (unique - 1) is two', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'A', 3);
    waitingRequest($party, 'B', 2);
    waitingRequest($party, 'C', 1);

    $party->updateState();

    expect(QueueSelector::QUEUE_LENGTH)->toBe(1)
        ->and(sentIds($fake))->toBe(['A', 'B']);
});

it('sends one request when the Spotify queue holds exactly one unique track', function () {
    [$party, $fake] = selectionParty();
    queueWith($fake, ['X']);
    waitingRequest($party, 'A', 3);
    waitingRequest($party, 'B', 2);

    $party->updateState();

    expect(sentIds($fake))->toBe(['A']);
});

it('treats a queue that repeats its context as empty-ish and counts only unique tracks', function () {
    [$party, $fake] = selectionParty();
    queueWith($fake, array_fill(0, 20, 'CTX'));
    waitingRequest($party, 'A', 3);
    waitingRequest($party, 'B', 2);

    $party->updateState();

    expect(sentIds($fake))->toBe(['A']);
});

it('sends nothing when the Spotify queue already holds two unique tracks', function () {
    [$party, $fake] = selectionParty();
    queueWith($fake, ['X', 'Y', 'X', 'Y']);
    waitingRequest($party, 'A', 3);

    $party->updateState();

    expect(sentIds($fake))->toBe([])
        ->and(UpcomingSong::query()->whereNotNull('queued_at')->count())->toBe(0);
});

it('does not de-duplicate a waiting request against tracks already in the Spotify queue', function () {
    [$party, $fake] = selectionParty();
    queueWith($fake, ['A']);
    waitingRequest($party, 'A', 3);

    $party->updateState();

    expect(sentIds($fake))->toBe(['A']);
});

it('adds each sent track to the history playlist when one is configured', function () {
    [$party, $fake] = selectionParty(['history_playlist_id' => 'HIST']);
    waitingRequest($party, 'A', 3);
    waitingRequest($party, 'B', 2);

    $party->updateState();

    expect(array_map(fn (array $call) => [$call[0], array_map(fn (Track $track) => $track->id, $call[1])], app(FakeMusicProvider::class)->callsTo('appendToPlaylist')))
        ->toBe([['HIST', ['A']], ['HIST', ['B']]]);
});

it('does not touch a history playlist when none is configured', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'A');

    $party->updateState();

    expect(app(FakeMusicProvider::class)->callsTo('appendToPlaylist'))->toBe([]);
});

it('sends another waiting request on every run while Spotify still reports an empty queue (E5 R3)', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'A', 5);
    waitingRequest($party, 'B', 4);
    waitingRequest($party, 'C', 3);
    waitingRequest($party, 'D', 2);

    $party->updateState();
    $party->updateState();

    expect(sentIds($fake))->toBe(['A', 'B', 'C', 'D']);
});

it('reads the stored score column and ignores score_adjustment until updateScore runs', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'PLAIN', 1);
    waitingRequest($party, 'ADJUSTED', 0, ['score_adjustment' => 10]);

    $party->updateState();

    expect(sentIds($fake))->toBe(['PLAIN', 'ADJUSTED']);
});

it('derives the score from votes plus score_adjustment in updateScore and then prefers the adjusted request', function () {
    [$party, $fake] = selectionParty();
    $plain = waitingRequest($party, 'PLAIN', 1);
    $adjusted = waitingRequest($party, 'ADJUSTED', 0, ['score_adjustment' => 10]);
    Vote::factory()->create(['upcoming_song_id' => $adjusted->id, 'value' => -2]);

    $adjusted->updateScore();
    $plain->updateScore();

    expect($adjusted->fresh()->score)->toBe(8)
        ->and($plain->fresh()->score)->toBe(0);

    $party->updateState();

    expect(sentIds($fake))->toBe(['ADJUSTED', 'PLAIN']);
});

it('binds a datetime rather than the string NOW() when comparing not_before (E5 R10)', function () {
    [$party, $fake] = selectionParty(['weighted' => true]);
    waitingRequest($party, 'A', 1);
    seedRandomness(1);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    $party->updateState();

    $matching = array_filter($queries, fn (array $q) => str_contains($q['sql'], 'not_before" <= ?'));
    expect($matching)->not->toBeEmpty()
        ->and(collect($matching)->flatMap(fn (array $q) => $q['bindings'])->contains('NOW()'))->toBeFalse();
});

it('treats a past not_before as eligible in the weighted pass', function () {
    [$party, $fake] = selectionParty(['weighted' => true]);
    waitingRequest($party, 'PAST', 5, ['not_before' => '2020-01-01 00:00:00']);
    seedRandomness(1);

    $party->updateState();

    expect(sentIds($fake))->toBe(['PAST']);
});

it('excludes a future not_before from the weighted pass and its fallback (E5 R10)', function () {
    [$party, $fake] = selectionParty(['weighted' => true]);
    waitingRequest($party, 'FUTURE', 5, ['not_before' => '2999-01-01 00:00:00']);
    seedRandomness(1);

    $party->updateState();

    expect(sentIds($fake))->toBe([]);
});

it('casts not_before to a Carbon instance', function () {
    [$party] = selectionParty();
    $request = waitingRequest($party, 'A', 0, ['not_before' => '2999-01-01 00:00:00']);

    expect($request->fresh()->not_before)->toBeInstanceOf(Carbon::class);
});

it('excludes zero and negative scored requests from the weighted draw and fills from them in score order', function () {
    [$party, $fake] = selectionParty(['weighted' => true]);
    waitingRequest($party, 'ZERO', 0, ['created_at' => now()->subMinutes(2)]);
    waitingRequest($party, 'NEG', -4, ['created_at' => now()->subMinutes(3)]);
    waitingRequest($party, 'POS', 3, ['created_at' => now()->subMinutes(1)]);
    seedRandomness(7);

    $party->updateState();

    expect(sentIds($fake))->toBe(['POS', 'ZERO']);
});

it('weights the draw by score with exact results for a seeded randomizer', function (int $seed, array $expected) {
    [$party, $fake] = selectionParty(['weighted' => true]);
    foreach (['A' => 1, 'B' => 2, 'C' => 3, 'D' => 10] as $id => $score) {
        waitingRequest($party, $id, $score);
    }
    seedRandomness($seed);

    $party->updateState();

    expect(sentIds($fake))->toBe($expected);
})->with([
    'seed 1' => [1, ['D', 'C']],
    'seed 2' => [2, ['B', 'D']],
    'seed 3' => [3, ['D', 'A']],
    'seed 42' => [42, ['D', 'C']],
]);

it('weighted draw is repeatable: the same seed yields the same sends', function () {
    $runs = [];
    foreach ([1, 2] as $run) {
        Cache::flush();
        DB::table('upcoming_songs')->delete();
        DB::table('songs')->delete();
        [$party, $fake] = selectionParty(['weighted' => true]);
        foreach (['A' => 1, 'B' => 2, 'C' => 3, 'D' => 10] as $id => $score) {
            waitingRequest($party, $id, $score);
        }
        seedRandomness(99);
        $party->updateState();
        $runs[] = sentIds($fake);
    }

    expect($runs[0])->toBe($runs[1])->and($runs[0])->toHaveCount(2);
});

it('fills a short weighted draw from the remaining requests ordered by score, created_at then id', function () {
    [$party, $fake] = selectionParty(['weighted' => true]);
    waitingRequest($party, 'ONLY', 5);
    waitingRequest($party, 'FILL', 0);
    seedRandomness(5);

    $party->updateState();

    expect(sentIds($fake))->toBe(['ONLY', 'FILL']);
});

it('tops up to five waiting requests from the Fallback Playlist after sending, so the first run sends nothing and the second sends the topped-up requests', function () {
    [$party, $fake] = selectionParty();
    FakeFallbackPlaylist::seed(['F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7']);
    seedRandomness(11);

    $party->updateState();

    $waiting = UpcomingSong::query()->where('party_id', $party->id)->whereNull('queued_at')->with('song')->get();
    expect(sentIds($fake))->toBe([])
        ->and($waiting)->toHaveCount(Party::MINIMUM_UPCOMING)
        ->and($waiting->pluck('song.spotify_id')->all())->toBe(['F7', 'F2', 'F3', 'F6', 'F1']);

    $party->updateState();

    expect(sentIds($fake))->toBe(['F7', 'F2']);
});

it('tops up after sending, so a freshly sent request counts as recently queued and is not duplicated from the Fallback Playlist', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'F1', 0);
    waitingRequest($party, 'F2', 0);
    FakeFallbackPlaylist::seed(['F1', 'F2', 'F3', 'F4', 'F5', 'F6']);
    seedRandomness(3);

    $party->updateState();

    $waiting = UpcomingSong::query()->where('party_id', $party->id)->whereNull('queued_at')->with('song')->get();
    expect(sentIds($fake))->toBe(['F1', 'F2'])
        ->and($waiting->pluck('song.spotify_id')->sort()->values()->all())->toBe(['F3', 'F4', 'F5', 'F6']);
});

it('tops up only the shortfall from the Fallback Playlist and never duplicates a waiting track', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'F1', 0);
    waitingRequest($party, 'F2', 0);
    queueWith($fake, ['X', 'Y']);
    FakeFallbackPlaylist::seed(['F1', 'F2', 'F3', 'F4', 'F5', 'F6']);
    seedRandomness(3);

    $party->updateState();

    $added = UpcomingSong::query()->where('party_id', $party->id)->whereNull('user_id')->with('song')->get();
    expect($added)->toHaveCount(3)
        ->and($added->pluck('song.spotify_id')->intersect(['F1', 'F2'])->all())->toBe([])
        ->and(UpcomingSong::query()->where('party_id', $party->id)->count())->toBe(5);
});

it('does not top up when five or more requests are already waiting', function () {
    [$party, $fake] = selectionParty();
    foreach (range(1, 5) as $n) {
        waitingRequest($party, "W{$n}");
    }
    queueWith($fake, ['X', 'Y']);
    FakeFallbackPlaylist::seed(['F1', 'F2']);

    $party->updateState();

    expect(app(FakeMusicProvider::class)->callsTo('getPlaylistTracks'))->toBe([])
        ->and(UpcomingSong::query()->count())->toBe(5);
});

it('excludes fallback tracks sent to the player in the last 30 minutes but allows older ones', function () {
    [$party, $fake] = selectionParty();
    waitingRequest($party, 'RECENT', 0, ['queued_at' => now()->subMinutes(10)]);
    waitingRequest($party, 'OLD', 0, ['queued_at' => now()->subMinutes(45)]);
    FakeFallbackPlaylist::seed(['RECENT', 'OLD']);
    queueWith($fake, ['X', 'Y']);
    seedRandomness(2);

    $party->updateState();

    $added = UpcomingSong::query()->where('party_id', $party->id)->whereNull('user_id')->with('song')->get();
    expect($added->pluck('song.spotify_id')->all())->toBe(['OLD']);
});

it('skips fallback entries that are not playable', function () {
    [$party, $fake] = selectionParty();
    app(FakeMusicProvider::class)->withPlaylist(new Playlist('BACKUP', 'Fallback', 'Owner', 2), [
        FakeFallbackPlaylist::track('NOPLAY', false),
        FakeFallbackPlaylist::track('GOOD'),
    ]);
    seedRandomness(2);

    $party->updateState();

    $added = UpcomingSong::query()->where('party_id', $party->id)->with('song')->get();
    expect($added->pluck('song.spotify_id')->all())->toBe(['GOOD']);
});

it('caches the fallback playlist for an hour so a second run does not refetch it', function () {
    [$party, $fake] = selectionParty();
    FakeFallbackPlaylist::seed(['F1']);
    seedRandomness(2);

    $party->updateState();
    $party->updateState();

    expect(app(FakeMusicProvider::class)->callsTo('getPlaylistTracks'))->toHaveCount(1);
});

it('ranks by the overridable Effective Score rather than the stored score', function () {
    [$party, $fake] = selectionParty();
    queueWith($fake, ['NOW']);
    waitingRequest($party, 'HIGH', 9);
    waitingRequest($party, 'LOW', 1);
    app()->bind(QueueSelector::class, fn () => new class(app(MusicProviderFactory::class), app(SpotifyGateway::class), app(FallbackPlaylist::class)) extends QueueSelector
    {
        public function effectiveScore(UpcomingSong $request): int
        {
            return -$request->score;
        }
    });

    $party->updateState();

    expect(sentIds())->toBe(['LOW']);
});

it('keeps fallback_override as display metadata and does not affect selection', function () {
    [$party, $fake] = selectionParty();
    queueWith($fake, ['NOW']);
    waitingRequest($party, 'A', 1, ['fallback_override' => 'Mystery Gift']);
    waitingRequest($party, 'B', 5);

    $party->updateState();

    expect(sentIds())->toBe(['B']);
});
