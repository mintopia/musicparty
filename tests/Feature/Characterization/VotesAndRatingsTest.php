<?php

use App\Exceptions\VoteException;
use App\Models\DownvoteLog;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\PlayedSong;
use App\Models\SongRating;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function voteParty(array $attributes = []): Party
{
    return Party::factory()->create($attributes);
}

function voteSong(Party $party, int $adjustment = 0): UpcomingSong
{
    return UpcomingSong::factory()->for($party)->create(['score_adjustment' => $adjustment]);
}

function voteMember(Party $party, ?string $state = null): User
{
    $user = User::factory()->create();
    $factory = PartyMember::factory()->for($party)->for($user);
    $state === 'banned' ? $factory->banned()->create() : $factory->create();

    return $user;
}

function castVote(Party $party, UpcomingSong $song, User $user, int|string $value)
{
    Sanctum::actingAs($user);

    return test()->postJson(route('api.v1.parties.upcomingsongs.vote.store', [$party, $song]), ['vote' => $value]);
}

function castRating(Party $party, PlayedSong $played, User $user, int|string $value)
{
    Sanctum::actingAs($user);

    return test()->postJson(route('api.v1.parties.playedsongs.rate.store', [$party, $played]), ['rating' => $value]);
}

describe('votes', function () {
    it('leaves the correct score when two stale models cast votes in interleaved order', function () {
        $party = voteParty();
        $song = voteSong($party);
        $first = UpcomingSong::findOrFail($song->id);
        $second = UpcomingSong::findOrFail($song->id);

        $first->upvote(voteMember($party));
        $second->upvote(voteMember($party));

        expect($song->fresh()->score)->toBe(2);
    });

    it('keeps the stored score_adjustment when a stale model casts a vote', function () {
        $party = voteParty();
        $song = voteSong($party);
        $stale = UpcomingSong::findOrFail($song->id);
        UpcomingSong::findOrFail($song->id)->increment('score_adjustment', 5);

        $stale->upvote(voteMember($party));

        expect($song->fresh()->score)->toBe(6)
            ->and($song->fresh()->score_adjustment)->toBe(5);
    });

    it('recomputes the score in one UPDATE with no separate sum read', function () {
        $party = voteParty();
        $song = voteSong($party);
        Vote::factory()->create(['upcoming_song_id' => $song->id, 'user_id' => voteMember($party)->id, 'value' => 1]);

        DB::enableQueryLog();
        $song->updateScore();
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => stripos($sql, 'sum(') !== false);
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1)
            ->and($queries->first())->toMatch('/^update [`"]upcoming_songs[`"]/');
    });

    it('recomputes the rating in one UPDATE with no separate sum read', function () {
        $party = voteParty();
        $played = PlayedSong::factory()->for($party)->create();
        SongRating::factory()->create(['played_song_id' => $played->id, 'user_id' => voteMember($party)->id, 'value' => 1]);

        DB::enableQueryLog();
        $played->updateRating();
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => stripos($sql, 'sum(') !== false);
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1)
            ->and($queries->first())->toMatch('/^update [`"]played_songs[`"]/');
    });

    it('leaves the correct rating when two stale models rate in interleaved order', function () {
        $party = voteParty();
        $played = PlayedSong::factory()->for($party)->create();
        $first = PlayedSong::findOrFail($played->id);
        $second = PlayedSong::findOrFail($played->id);

        $first->like(voteMember($party));
        $second->like(voteMember($party));

        expect($played->fresh()->rating)->toBe(2);
    });

    it('records an upvote and updates the score', function () {
        $party = voteParty();
        $song = voteSong($party);
        $user = voteMember($party);

        $vote = $song->upvote($user);

        expect($vote->value)->toBe(1)
            ->and($song->fresh()->score)->toBe(1);
    });

    it('records a downvote and updates the score when downvotes are enabled', function () {
        $party = voteParty(['downvotes' => true]);
        $song = voteSong($party);

        $vote = $song->downvote(voteMember($party));

        expect($vote->value)->toBe(-1)
            ->and($song->fresh()->score)->toBe(-1);
    });

    it('ignores a downvote and returns null when the party disables downvotes', function () {
        $party = voteParty(['downvotes' => false]);
        $song = voteSong($party);

        expect($song->downvote(voteMember($party)))->toBeNull()
            ->and(Vote::count())->toBe(0)
            ->and($song->fresh()->score)->toBe(0);
    });

    it('changes an existing vote in place rather than adding a second row', function () {
        $party = voteParty();
        $song = voteSong($party);
        $user = voteMember($party);

        $song->upvote($user);
        $song->downvote($user);

        expect(Vote::count())->toBe(1)
            ->and(Vote::first()->value)->toBe(-1)
            ->and($song->fresh()->score)->toBe(-1);
    });

    it('re-casting the same vote leaves one row and an unchanged score', function () {
        $party = voteParty();
        $song = voteSong($party);
        $user = voteMember($party);

        $song->upvote($user);
        $song->upvote($user);

        expect(Vote::count())->toBe(1)
            ->and($song->fresh()->score)->toBe(1);
    });

    it('sums votes from several users and adds score_adjustment', function () {
        $party = voteParty();
        $song = voteSong($party, 5);

        $song->upvote(voteMember($party));
        $song->upvote(voteMember($party));
        $song->downvote(voteMember($party));

        expect($song->fresh()->score)->toBe(6);
    });

    it('recalculates the score when a vote is deleted', function () {
        $party = voteParty();
        $song = voteSong($party);
        $vote = $song->upvote(voteMember($party));
        $song->upvote(voteMember($party));

        $vote->delete();

        expect($song->fresh()->score)->toBe(1);
    });

    it('lets a user who is not a party member vote and enrols them as a user member', function () {
        $party = voteParty();
        $song = voteSong($party);
        $outsider = User::factory()->create();
        PartyMemberRole::query()->forceCreate(['code' => 'user', 'name' => 'User']);

        $song->upvote($outsider);

        expect(Vote::count())->toBe(1)
            ->and($party->members()->whereUserId($outsider->id)->first()->role->code)->toBe('user');
    });

    it('rejects a banned member upvote and downvote with a VoteException and no vote row', function (string $method) {
        $party = voteParty(['downvotes' => true]);
        $song = voteSong($party);
        $banned = voteMember($party, 'banned');

        expect(fn () => $song->{$method}($banned))->toThrow(VoteException::class, 'You are banned from voting for songs');
        expect(Vote::count())->toBe(0);
    })->with(['upvote', 'downvote']);

    it('keeps a banned member existing vote and score when they try to change it', function () {
        $party = voteParty();
        $song = voteSong($party);
        $user = voteMember($party);
        $song->upvote($user);
        PartyMember::query()->whereUserId($user->id)->update([
            'role_id' => PartyMember::factory()->banned()->make()->role_id,
        ]);

        expect(fn () => $song->fresh()->downvote($user))->toThrow(VoteException::class);
        expect(Vote::first()->value)->toBe(1)
            ->and($song->fresh()->score)->toBe(1);
    });

    it('rejects a duplicate vote row for one user on one request', function () {
        $party = voteParty();
        $song = voteSong($party);
        $user = voteMember($party);

        Vote::factory()->up()->create(['upcoming_song_id' => $song->id, 'user_id' => $user->id]);

        expect(fn () => Vote::factory()->up()->create(['upcoming_song_id' => $song->id, 'user_id' => $user->id]))
            ->toThrow(UniqueConstraintViolationException::class)
            ->and(Vote::count())->toBe(1);
    });
});

describe('downvote throttle', function () {
    it('is unlimited when downvotes_per_hour is null', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => null]);
        $user = voteMember($party);

        foreach (range(1, 5) as $ignored) {
            voteSong($party)->downvote($user);
        }

        expect(Vote::where('value', -1)->count())->toBe(5);
    });

    it('blocks a downvote once the hourly limit is reached and allows it after the hour passes', function () {
        $this->freezeTime();
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 2]);
        $user = voteMember($party);

        voteSong($party)->downvote($user);
        voteSong($party)->downvote($user);
        $third = voteSong($party);

        expect(fn () => $third->downvote($user))->toThrow(VoteException::class, 'You have downvoted too many songs');

        $this->travel(59)->minutes();
        expect(fn () => $third->downvote($user))->toThrow(VoteException::class);

        $this->travel(2)->minutes();
        expect($third->downvote($user)->value)->toBe(-1);
    });

    it('does not throttle upvotes and does not count them toward the limit', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);
        $user = voteMember($party);

        voteSong($party)->upvote($user);
        voteSong($party)->upvote($user);

        expect(voteSong($party)->downvote($user)->value)->toBe(-1);
    });

    it('counts downvotes per party only', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);
        $other = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);
        $user = voteMember($party);

        voteSong($other)->downvote($user);

        expect(voteSong($party)->downvote($user)->value)->toBe(-1);
    });

    it('counts per user', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);

        voteSong($party)->downvote(voteMember($party));

        expect(voteSong($party)->downvote(voteMember($party))->value)->toBe(-1);
    });

    it('keeps counting a downvote after it is switched to an upvote', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);
        $user = voteMember($party);
        $first = voteSong($party);

        $first->downvote($user);
        $first->upvote($user);

        expect(fn () => voteSong($party)->downvote($user))->toThrow(VoteException::class);
    });

    it('keeps counting a downvote after the vote is deleted and redone', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);
        $user = voteMember($party);
        $song = voteSong($party);

        castVote($party, $song, $user, -1)->assertOk();
        castVote($party, $song, $user, 0)->assertOk();

        castVote($party, $song, $user, -1)->assertStatus(400);
        castVote($party, voteSong($party), $user, -1)->assertStatus(400);
        expect(Vote::count())->toBe(0);
    });

    it('re-sending a downvote at the limit is harmless and not counted twice', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);
        $user = voteMember($party);
        $song = voteSong($party);

        castVote($party, $song, $user, -1)->assertOk();
        castVote($party, $song, $user, -1)->assertOk()->assertJsonPath('data.score', -1);

        expect(Vote::count())->toBe(1)->and(DownvoteLog::count())->toBe(1);
    });

    it('counts a vote switched from up to down at the switch time, not the original cast time', function () {
        $this->freezeTime();
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);
        $user = voteMember($party);
        $song = voteSong($party);

        $song->upvote($user);
        $this->travel(2)->hours();
        $song->downvote($user);

        expect(Vote::first()->created_at->lt(now()->subHour()))->toBeTrue()
            ->and(Vote::first()->value_changed_at->toDateTimeString())->toBe(now()->toDateTimeString());

        expect(fn () => voteSong($party)->downvote($user))->toThrow(VoteException::class, 'You have downvoted too many songs');
    });

    it('does not move value_changed_at when the same value is cast again', function () {
        $this->freezeTime();
        $party = voteParty();
        $user = voteMember($party);
        $song = voteSong($party);

        $song->upvote($user);
        $cast = Vote::first()->value_changed_at;
        $this->travel(5)->minutes();
        $song->upvote($user);

        expect(Vote::first()->value_changed_at->equalTo($cast))->toBeTrue();
    });

    it('refuses a flip from upvote to downvote at the limit and keeps the upvote', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);
        $user = voteMember($party);
        $flipped = voteSong($party);
        $flipped->upvote($user);
        voteSong($party)->downvote($user);

        castVote($party, $flipped, $user, -1)->assertStatus(400);

        expect(Vote::query()->where('upcoming_song_id', $flipped->id)->first()->value)->toBe(1);
    });

    it('leaves at most the limit of downvotes applied across repeated attempts', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 2]);
        $user = voteMember($party);
        $songs = collect(range(1, 5))->map(fn () => voteSong($party));

        foreach ($songs as $song) {
            castVote($party, $song, $user, -1);
        }

        expect(Vote::query()->where('value', -1)->count())->toBe(2);
    });

    it('runs the downvote limit check and the write while holding the per-user lock', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 2]);
        $user = voteMember($party);
        $lockHeldDuring = [];
        DB::listen(function ($query) use ($user, &$lockHeldDuring) {
            if (preg_match('/^(select count\(\*\) as [`"]?aggregate[`"]? from|insert into) [`"](votes|downvote_logs)[`"]/', $query->sql)) {
                $lockHeldDuring[] = ! Cache::lock("vote-user:{$user->id}", 5)->get();
            }
        });

        voteSong($party)->downvote($user);

        expect($lockHeldDuring)->toHaveCount(3)->each->toBeTrue();
    });

    it('does not apply a downvote while another request holds the user lock', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 2]);
        $user = voteMember($party);
        $song = voteSong($party);
        $held = Cache::lock("vote-user:{$user->id}", 30);
        $held->get();

        try {
            expect(fn () => $song->downvote($user))->toThrow(LockTimeoutException::class);
        } finally {
            $held->release();
        }

        expect(Vote::count())->toBe(0);
    });
});

describe('vote endpoint', function () {
    it('upvotes, downvotes and clears a vote with vote=0', function () {
        $party = voteParty(['downvotes' => true]);
        $song = voteSong($party);
        $user = voteMember($party);

        castVote($party, $song, $user, 1)->assertOk();
        expect($song->fresh()->score)->toBe(1);

        castVote($party, $song, $user, -1)->assertOk();
        expect($song->fresh()->score)->toBe(-1);

        castVote($party, $song, $user, 0)->assertOk();
        expect(Vote::count())->toBe(0)
            ->and($song->fresh()->score)->toBe(0);
    });

    it('returns 403 for a banned member and records nothing', function () {
        $party = voteParty();
        $song = voteSong($party);

        castVote($party, $song, voteMember($party, 'banned'), 1)->assertForbidden();

        expect(Vote::count())->toBe(0);
    });

    it('creates a membership and records the vote for a non-member who is not banned', function () {
        $party = voteParty();
        $song = voteSong($party);
        PartyMemberRole::factory()->user()->create();
        $user = User::factory()->create();

        castVote($party, $song, $user, 1)->assertOk();

        expect(Vote::count())->toBe(1)->and($party->members()->whereUserId($user->id)->count())->toBe(1);
    });

    it('returns 429 when the vote lock cannot be acquired', function () {
        $party = voteParty();
        $song = voteSong($party);
        $user = voteMember($party);
        $held = Cache::lock("vote-user:{$user->id}", 30);
        $held->get();

        try {
            castVote($party, $song, $user, 1)->assertStatus(429);
        } finally {
            $held->release();
        }

        expect(Vote::count())->toBe(0);
    });

    it('rejects vote values outside -1, 0 and 1', function (mixed $value) {
        $party = voteParty(['downvotes' => true]);
        $song = voteSong($party);

        castVote($party, $song, voteMember($party), $value)->assertUnprocessable();

        expect(Vote::count())->toBe(0);
    })->with([5, -2, 'up']);

    it('returns a 4xx when downvotes are disabled instead of a null vote', function () {
        $party = voteParty(['downvotes' => false]);
        $song = voteSong($party);

        castVote($party, $song, voteMember($party), -1)->assertForbidden();

        expect(Vote::count())->toBe(0);
    });

    it('responds with the post-write score', function () {
        $party = voteParty();
        $song = voteSong($party);
        $song->upvote(voteMember($party));

        castVote($party, $song, voteMember($party), 1)->assertOk()->assertJsonPath('data.score', 2);
    });

    it('returns 400 when the downvote throttle is hit', function () {
        $party = voteParty(['downvotes' => true, 'downvotes_per_hour' => 1]);
        $user = voteMember($party);
        castVote($party, voteSong($party), $user, -1)->assertOk();

        castVote($party, voteSong($party), $user, -1)
            ->assertStatus(400)
            ->assertJsonPath('message', 'You have downvoted too many songs');
    });

    it('requires authentication', function () {
        $party = voteParty();
        $song = voteSong($party);

        $this->postJson(route('api.v1.parties.upcomingsongs.vote.store', [$party, $song]), ['vote' => 1])
            ->assertUnauthorized();
    });
});

describe('played track ratings', function () {
    function ratedParty(): array
    {
        $party = voteParty();
        $played = PlayedSong::factory()->for($party)->create(['played_at' => now()]);

        return [$party, $played];
    }

    it('likes and dislikes a played song and updates its rating', function () {
        [$party, $played] = ratedParty();

        $like = $played->like(voteMember($party));
        expect($like->value)->toBe(1)
            ->and($played->fresh()->rating)->toBe(1);

        $played->dislike(voteMember($party));
        $played->dislike(voteMember($party));
        expect($played->fresh()->rating)->toBe(-1);
    });

    it('switches the rating in place when the opposite rating is sent (set semantics)', function () {
        [$party, $played] = ratedParty();
        $user = voteMember($party);

        $played->like($user);

        expect($played->dislike($user)->value)->toBe(-1)
            ->and(SongRating::count())->toBe(1)
            ->and($played->fresh()->rating)->toBe(-1);
    });

    it('keeps one row when the same rating is sent twice', function () {
        [$party, $played] = ratedParty();
        $user = voteMember($party);

        $played->like($user);
        $played->like($user);

        expect(SongRating::count())->toBe(1)
            ->and($played->fresh()->rating)->toBe(1);
    });

    it('keeps exactly one row across rapid successive ratings and the unique index blocks duplicates', function () {
        [$party, $played] = ratedParty();
        $user = voteMember($party);

        foreach ([1, -1, 1, -1] as $value) {
            $played->setRating($user, $value);
        }

        expect(SongRating::count())->toBe(1)
            ->and($played->fresh()->rating)->toBe(-1);

        expect(fn () => SongRating::factory()->create(['played_song_id' => $played->id, 'user_id' => $user->id]))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('refuses a banned member rating and records nothing', function () {
        [$party, $played] = ratedParty();

        castRating($party, $played, voteMember($party, 'banned'), 1)->assertForbidden();

        expect(SongRating::count())->toBe(0);
    });

    it('refuses a rating from a user who is not a party member', function () {
        [$party, $played] = ratedParty();

        castRating($party, $played, User::factory()->create(), 1)->assertForbidden();

        expect(SongRating::count())->toBe(0);
    });

    it('sets, switches, repeats and clears a rating through the endpoint (set semantics)', function () {
        [$party, $played] = ratedParty();
        $user = voteMember($party);

        castRating($party, $played, $user, 1)->assertOk()->assertJsonPath('data.rated', 1);
        expect($played->fresh()->rating)->toBe(1);

        castRating($party, $played, $user, 1)->assertOk();
        expect(SongRating::count())->toBe(1)->and($played->fresh()->rating)->toBe(1);

        castRating($party, $played, $user, -1)->assertOk()->assertJsonPath('data.rated', -1);
        expect(SongRating::count())->toBe(1)->and($played->fresh()->rating)->toBe(-1);

        castRating($party, $played, $user, 0)->assertOk()->assertJsonPath('data.rated', null);
        expect(SongRating::count())->toBe(0)->and($played->fresh()->rating)->toBe(0);
    });

    it('treats clearing a missing rating as a no-op', function () {
        [$party, $played] = ratedParty();

        castRating($party, $played, voteMember($party), 0)->assertOk();

        expect(SongRating::count())->toBe(0);
    });

    it('rejects ratings outside -1, 0 and 1', function (mixed $value) {
        [$party, $played] = ratedParty();

        castRating($party, $played, voteMember($party), $value)->assertUnprocessable();

        expect(SongRating::count())->toBe(0);
    })->with([5, -2, 'up']);

    it('returns 400 when rating a played song that is no longer the current one', function () {
        [$party, $played] = ratedParty();
        PlayedSong::factory()->for($party)->create(['played_at' => now()->addMinute()]);

        castRating($party, $played, voteMember($party), 1)->assertStatus(400);

        expect(SongRating::count())->toBe(0);
    });

    it('validates that rating is a required integer', function () {
        [$party, $played] = ratedParty();
        $user = voteMember($party);

        castRating($party, $played, $user, 1);
        Sanctum::actingAs($user);
        $this->postJson(route('api.v1.parties.playedsongs.rate.store', [$party, $played]), [])->assertUnprocessable();
    });

    it('requires authentication', function () {
        [$party, $played] = ratedParty();

        $this->postJson(route('api.v1.parties.playedsongs.rate.store', [$party, $played]), ['rating' => 1])
            ->assertUnauthorized();
    });
});
