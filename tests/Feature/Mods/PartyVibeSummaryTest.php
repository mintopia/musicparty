<?php

use App\Jobs\RefreshPartyVibeSummary;
use App\Models\Artist;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\SongRating;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use App\Mods\AiRequestReview\PartyVibeSummary;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

function vibePlayed(Party $party, string $title, string $artist, int $rating = 0, ?Carbon\Carbon $at = null): PlayedSong
{
    $song = Song::factory()->create(['name' => $title]);
    $song->artists()->sync([(Artist::where('name', $artist)->first() ?? Artist::factory()->create(['name' => $artist]))->id]);

    return PlayedSong::factory()->for($party)->for($song)->create(['rating' => $rating, 'played_at' => $at ?? now()]);
}

beforeEach(function () {
    Queue::fake();
});

it('summarises recent, best rated and downvoted tracks, artists and stats', function () {
    $party = Party::factory()->create();
    vibePlayed($party, 'Mr. Brightside', 'The Killers', 5, now()->subHour());
    vibePlayed($party, 'Last Christmas', 'Wham!', -3, now()->subMinutes(30));
    vibePlayed($party, 'Human', 'The Killers', 0, now());

    $text = app(PartyVibeSummary::class)->text($party);

    expect($text)->toContain('Tracks played: 3')
        ->toContain('Party running time: 60 minutes')
        ->toContain('Most recently played (newest first): Human - The Killers; Last Christmas - Wham!; Mr. Brightside - The Killers')
        ->toContain('Highest rated by guests: Mr. Brightside - The Killers')
        ->toContain('Most downvoted by guests: Last Christmas - Wham!')
        ->toContain('Most played artists: The Killers (2 plays); Wham! (1 plays)');
});

it('contains no user data', function () {
    $user = User::factory()->create(['nickname' => 'SecretNick']);
    $party = Party::factory()->for($user)->create(['name' => 'Private Party Name']);
    $played = vibePlayed($party, 'Human', 'The Killers', 1);
    SongRating::factory()->for($played, 'song')->for($user)->create(['value' => 1]);

    $json = json_encode(app(PartyVibeSummary::class)->build($party));

    expect($json)->not->toContain('SecretNick')->not->toContain('Private Party Name')->not->toContain($party->code);
});

it('only counts the party it is built for', function () {
    $party = Party::factory()->create();
    vibePlayed(Party::factory()->create(), 'Other Song', 'Other Artist', 9);

    expect(app(PartyVibeSummary::class)->text($party))->not->toContain('Other')->toContain('Tracks played: 0');
});

it('structures tracks and artists as objects', function () {
    $party = Party::factory()->create();
    vibePlayed($party, 'Human', 'The Killers', 1);

    $data = app(PartyVibeSummary::class)->build($party);

    expect($data['recent'])->toBe([['title' => 'Human', 'artist' => 'The Killers']])
        ->and($data['artists'])->toBe([['name' => 'The Killers', 'plays' => 1]]);
});

it('caps list lengths and the total size without truncating titles', function () {
    $party = Party::factory()->create();
    foreach (range(1, 40) as $i) {
        vibePlayed($party, str_repeat("Long title {$i} ", 20), str_repeat('Artist ', 20).$i, $i, now()->subMinutes(100 - $i));
    }

    $data = app(PartyVibeSummary::class)->build($party);

    expect($data['recent'])->toHaveCount(PartyVibeSummary::RECENT)
        ->and($data['top_rated'])->toHaveCount(PartyVibeSummary::TOP_RATED)
        ->and($data['artists'])->toHaveCount(PartyVibeSummary::ARTISTS)
        ->and($data['recent'][0]['title'])->toBe(str_repeat('Long title 40 ', 20))
        ->and($data['artists'][0]['name'])->toStartWith('Artist Artist')->not->toEndWith('...')
        ->and(mb_strlen($data['text']))->toBeLessThanOrEqual(PartyVibeSummary::MAX_CHARS);
});

it('builds on a miss, serves from the cache without queries on a hit, and refreshes on demand', function () {
    $party = Party::factory()->create();
    vibePlayed($party, 'First Song', 'Artist One');
    $summary = app(PartyVibeSummary::class);
    expect(Cache::has(PartyVibeSummary::cacheKey($party)))->toBeFalse();

    expect($summary->text($party))->toContain('First Song');
    vibePlayed($party, 'Second Song', 'Artist Two');

    DB::enableQueryLog();
    $cached = $summary->text($party);
    expect(DB::getQueryLog())->toBeEmpty()->and($cached)->not->toContain('Second Song');

    $summary->refresh($party);
    expect($summary->text($party))->toContain('Second Song');

    $summary->forget($party);
    expect(Cache::has(PartyVibeSummary::cacheKey($party)))->toBeFalse();
});

it('builds with a bounded number of queries regardless of party size', function () {
    $party = Party::factory()->create();
    foreach (range(1, 25) as $i) {
        vibePlayed($party, "Song {$i}", "Artist {$i}", $i % 3);
    }

    DB::enableQueryLog();
    app(PartyVibeSummary::class)->build($party);

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(15);
});

it('schedules one debounced unique refresh however many plays and ratings change', function () {
    $party = Party::factory()->create();
    $played = vibePlayed($party, 'First Song', 'Artist One');
    $played->forceFill(['rating' => 2])->save();
    vibePlayed($party, 'Second Song', 'Artist Two');

    Queue::assertPushed(RefreshPartyVibeSummary::class, 1);
    Queue::assertPushed(RefreshPartyVibeSummary::class, fn (RefreshPartyVibeSummary $job): bool => $job->delay !== null && now()->diffInSeconds($job->delay, false) > 50);
    expect(new RefreshPartyVibeSummary($party))->toBeInstanceOf(ShouldBeUnique::class)
        ->and(new RefreshPartyVibeSummary($party)->uniqueId())->toBe((string) $party->id);
});

it('refreshes the cache when the job runs', function () {
    $party = Party::factory()->create();
    $summary = app(PartyVibeSummary::class);
    $summary->text($party);
    vibePlayed($party, 'Fresh Song', 'Fresh Artist');

    new RefreshPartyVibeSummary($party)->handle($summary);

    expect(Cache::get(PartyVibeSummary::cacheKey($party))['text'])->toContain('Fresh Song');
});

function vibeRequest(Party $party, string $title, string $artist, int $net, ?Carbon\Carbon $flaggedAt = null): UpcomingSong
{
    $song = Song::factory()->create(['name' => $title]);
    $song->artists()->sync([Artist::factory()->create(['name' => $artist])->id]);
    $request = UpcomingSong::factory()->for($party)->for($song)->create(['flagged_at' => $flaggedAt]);
    foreach (range(1, abs($net)) as $ignored) {
        Vote::factory()->for($request)->create(['value' => $net <=> 0]);
    }

    return $request;
}

it('lists the most upvoted and downvoted requests by net votes', function () {
    $party = Party::factory()->create();
    vibeRequest($party, 'Loved', 'Fans', 3);
    vibeRequest($party, 'Liked', 'Some', 1);
    vibeRequest($party, 'Hated', 'Foes', -2);
    vibeRequest($party, 'Flagged Fave', 'Hidden', 5, now());
    vibeRequest(Party::factory()->create(), 'Elsewhere', 'Other', 9);
    $even = vibeRequest($party, 'Even', 'Meh', 1);
    Vote::factory()->for($even)->create(['value' => -1]);

    $data = app(PartyVibeSummary::class)->build($party);

    expect($data['most_upvoted_requests'])->toBe([['title' => 'Loved', 'artist' => 'Fans'], ['title' => 'Liked', 'artist' => 'Some']])
        ->and($data['most_downvoted_requests'])->toBe([['title' => 'Hated', 'artist' => 'Foes']])
        ->and($data['text'])->toContain('Most upvoted requests: Loved - Fans; Liked - Some')
        ->toContain('Most downvoted requests: Hated - Foes')
        ->not->toContain('Flagged Fave')->not->toContain('Elsewhere');
});

it('caps the voted request lists', function () {
    $party = Party::factory()->create();
    foreach (range(1, 8) as $i) {
        vibeRequest($party, "Up {$i}", 'A', $i);
        vibeRequest($party, "Down {$i}", 'B', -$i);
    }

    $data = app(PartyVibeSummary::class)->build($party);

    expect($data['most_upvoted_requests'])->toHaveCount(PartyVibeSummary::VOTED_REQUESTS)->and($data['most_upvoted_requests'][0])->toBe(['title' => 'Up 8', 'artist' => 'A'])
        ->and($data['most_downvoted_requests'])->toHaveCount(PartyVibeSummary::VOTED_REQUESTS)->and($data['most_downvoted_requests'][0])->toBe(['title' => 'Down 8', 'artist' => 'B']);
});

it('schedules a debounced refresh when votes are cast', function () {
    $party = Party::factory()->create();
    $upcoming = UpcomingSong::factory()->for($party)->create();

    Vote::factory()->for($upcoming)->create();

    Queue::assertPushed(RefreshPartyVibeSummary::class, fn (RefreshPartyVibeSummary $job): bool => $job->party->is($party) && $job->delay !== null);
});
