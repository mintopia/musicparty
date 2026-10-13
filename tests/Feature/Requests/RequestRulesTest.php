<?php

use App\Enums\PartyModerationType;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyModeration;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestMode;
use App\Services\Requests\RequestOutcome;
use App\Services\Requests\RequestRule;
use App\Services\Requests\RequestRules;
use App\Services\Requests\TrackEligibility;
use Illuminate\Support\Facades\DB;

function rulesTrack(string $id = 'fake-track-1'): Track
{
    return app(FakeMusicProvider::class)->getTrack($id) ?? throw new RuntimeException("No fake track {$id}");
}

function rulesVariant(Track $track, array $changes): Track
{
    $values = array_merge(get_object_vars($track), $changes);

    return new Track(...$values);
}

function rulesParty(array $attributes = []): Party
{
    return Party::factory()->create(array_merge(['provider_id' => 'fake', 'allow_requests' => true, 'explicit' => true], $attributes))->refresh();
}

function rulesMember(Party $party, string $role = 'user'): PartyMember
{
    $factory = PartyMember::factory()->for($party)->for(User::factory());

    return match ($role) {
        'vip' => $factory->vip()->create(),
        'owner' => $factory->owner()->create(),
        'banned' => $factory->banned()->create(),
        default => $factory->create(),
    };
}

function rulesCheck(Party $party, PartyMember $member, Track $track): array
{
    $decision = app(RequestRules::class)->check($party->refresh(), $member->refresh(), $track);

    return [$decision->allowed, $decision->reason];
}

function rulesWaiting(Party $party, Track $track, ?User $user = null): UpcomingSong
{
    $factory = UpcomingSong::factory()->for($party)->for(Song::fromTrack($track))->waiting();

    return ($user ? $factory->for($user) : $factory)->create();
}

function rulesModeration(Party $party, PartyModerationType $type, string $value, bool $regex = false): void
{
    $moderation = new PartyModeration;
    $moderation->party()->associate($party);
    $moderation->type = $type;
    $moderation->value = $value;
    $moderation->regex = $regex;
    $moderation->save();
}

it('allows a plain track', function () {
    $party = rulesParty();

    expect(rulesCheck($party, rulesMember($party), rulesTrack()))->toBe([true, null]);
});

it('rejects the context playlist track as unavailable', function () {
    $party = rulesParty();
    app(FakeMusicProvider::class)->withReservedTrack('reserved-track');
    $track = rulesVariant(rulesTrack(), ['id' => 'reserved-track']);

    expect(rulesCheck($party, rulesMember($party), $track))->toBe([false, 'This song is not available']);
});

it('rejects every track when the party is not accepting requests', function () {
    $party = rulesParty(['allow_requests' => false]);

    expect(rulesCheck($party, rulesMember($party), rulesTrack()))->toBe([false, 'Party is not accepting requests']);
});

describe('too many requests', function () {
    it('rejects a member at the limit counting only unqueued requests', function () {
        $party = rulesParty(['max_requests' => 2]);
        $member = rulesMember($party);
        rulesWaiting($party, rulesTrack('fake-track-2'), $member->user);
        rulesWaiting($party, rulesTrack('fake-track-3'), $member->user);
        UpcomingSong::factory()->for($party)->for(Song::fromTrack(rulesTrack('fake-track-4')))->for($member->user)->sentToPlayer()->create();

        expect(rulesCheck($party, $member, rulesTrack()))->toBe([false, 'You are not allowed to request any more songs']);
    });

    it('allows a member under the limit', function () {
        $party = rulesParty(['max_requests' => 2]);
        $member = rulesMember($party);
        rulesWaiting($party, rulesTrack('fake-track-2'), $member->user);
        UpcomingSong::factory()->for($party)->for(Song::fromTrack(rulesTrack('fake-track-3')))->for($member->user)->sentToPlayer()->create();

        expect(rulesCheck($party, $member, rulesTrack())[0])->toBeTrue();
    });

    it('exempts vips and managers', function (string $role) {
        $party = rulesParty(['max_requests' => 1]);
        $member = rulesMember($party, $role);
        rulesWaiting($party, rulesTrack('fake-track-2'), $member->user);

        expect(rulesCheck($party, $member, rulesTrack())[0])->toBeTrue();
    })->with(['vip', 'owner']);

    it('exempts the party owner user', function () {
        $party = rulesParty(['max_requests' => 1]);
        $member = PartyMember::factory()->for($party)->for($party->user)->create();
        rulesWaiting($party, rulesTrack('fake-track-2'), $party->user);

        expect(rulesCheck($party, $member, rulesTrack())[0])->toBeTrue();
    });

    it('has no limit when max requests is not set', function () {
        $party = rulesParty(['max_requests' => null]);
        $member = rulesMember($party);
        rulesWaiting($party, rulesTrack('fake-track-2'), $member->user);

        expect(rulesCheck($party, $member, rulesTrack())[0])->toBeTrue();
    });
});

it('rejects a banned member', function () {
    $party = rulesParty();

    expect(rulesCheck($party, rulesMember($party, 'banned'), rulesTrack()))->toBe([false, 'You are not allowed to make requests']);
});

describe('song length', function () {
    it('rejects a track that is too short', function () {
        $party = rulesParty(['min_song_length' => 200]);

        expect(rulesCheck($party, rulesMember($party), rulesVariant(rulesTrack(), ['durationMs' => 199999])))->toBe([false, 'Song is too short']);
    });

    it('rejects a track that is too long', function () {
        $party = rulesParty(['max_song_length' => 200]);

        expect(rulesCheck($party, rulesMember($party), rulesVariant(rulesTrack(), ['durationMs' => 200001])))->toBe([false, 'Song is too long']);
    });

    it('allows tracks exactly on the limits', function () {
        $party = rulesParty(['min_song_length' => 200, 'max_song_length' => 200]);

        expect(rulesCheck($party, rulesMember($party), rulesVariant(rulesTrack(), ['durationMs' => 200000]))[0])->toBeTrue();
    });
});

describe('explicit', function () {
    it('rejects explicit tracks when the party disallows them', function () {
        $party = rulesParty(['explicit' => false]);

        expect(rulesCheck($party, rulesMember($party), rulesTrack('fake-track-7')))->toBe([false, 'Explicit songs are not allowed']);
    });

    it('allows clean tracks, and explicit tracks when permitted', function () {
        $strict = rulesParty(['explicit' => false]);
        $loose = rulesParty(['explicit' => true]);

        expect(rulesCheck($strict, rulesMember($strict), rulesTrack('fake-track-1'))[0])->toBeTrue()
            ->and(rulesCheck($loose, rulesMember($loose), rulesTrack('fake-track-7'))[0])->toBeTrue();
    });
});

describe('moderation', function () {
    it('rejects each matching rule type with its message', function (PartyModerationType $type, string $value, string $reason) {
        $party = rulesParty();
        rulesModeration($party, $type, $value);
        $track = rulesVariant(rulesTrack(), ['isrc' => 'USABC1234567']);

        expect(rulesCheck($party, rulesMember($party), $track))->toBe([false, $reason]);
    })->with([
        'name' => [PartyModerationType::mtName, 'fake track *', 'Track is not allowed'],
        'id' => [PartyModerationType::mtId, 'fake-track-1', 'Track is not allowed'],
        'isrc' => [PartyModerationType::mtISRC, 'USABC1234567', 'Track is not allowed'],
        'artist' => [PartyModerationType::mtArtist, 'fake artist 1', 'Artist is not allowed'],
        'artist id' => [PartyModerationType::mtArtistId, 'fake-artist-1', 'Artist is not allowed'],
        'album' => [PartyModerationType::mtAlbum, 'Fake Album 1', 'Album is not allowed'],
        'album id' => [PartyModerationType::mtAlbumId, 'fake-album-1', 'Album is not allowed'],
    ]);

    it('ignores non matching rules, other parties rules and tracks without an isrc', function () {
        $party = rulesParty();
        rulesModeration($party, PartyModerationType::mtArtistId, 'someone-else');
        rulesModeration($party, PartyModerationType::mtISRC, 'USABC1234567');
        rulesModeration(rulesParty(), PartyModerationType::mtName, 'Fake Track 1');

        expect(rulesCheck($party, rulesMember($party), rulesTrack())[0])->toBeTrue();
    });

    it('matches ids case sensitively and names case insensitively', function () {
        $party = rulesParty();
        rulesModeration($party, PartyModerationType::mtId, 'FAKE-TRACK-1');

        expect(rulesCheck($party, rulesMember($party), rulesTrack())[0])->toBeTrue();
    });

    it('matches any of several artists', function () {
        $party = rulesParty();
        rulesModeration($party, PartyModerationType::mtArtist, 'Guest');
        $track = rulesVariant(rulesTrack(), ['artists' => [new ArtistRef('a', 'Main'), new ArtistRef('b', 'Guest')]]);

        expect(rulesCheck($party, rulesMember($party), $track))->toBe([false, 'Artist is not allowed']);
    });
});

describe('queue and history', function () {
    it('rejects a track whose name and artists match a waiting request', function () {
        $party = rulesParty();
        rulesWaiting($party, rulesTrack());
        $remaster = rulesVariant(rulesTrack(), ['id' => 'remaster']);

        expect(rulesCheck($party, rulesMember($party), $remaster))->toBe([false, 'A similar song is already in the queue']);
    });

    it('allows a track with the same name but different artists', function () {
        $party = rulesParty();
        rulesWaiting($party, rulesTrack());
        $cover = rulesVariant(rulesTrack(), ['id' => 'cover', 'artists' => [new ArtistRef('x', 'Someone Else')]]);

        expect(rulesCheck($party, rulesMember($party), $cover)[0])->toBeTrue();
    });

    it('ignores similar songs waiting in other parties', function () {
        $party = rulesParty();
        rulesWaiting(rulesParty(), rulesTrack());

        expect(rulesCheck($party, rulesMember($party), rulesVariant(rulesTrack(), ['id' => 'remaster']))[0])->toBeTrue();
    });

    it('rejects a track played within the no repeat interval only', function () {
        $party = rulesParty(['no_repeat_interval' => 3600]);
        PlayedSong::factory()->for($party)->for(Song::fromTrack(rulesTrack('fake-track-1')))->create(['played_at' => now()->subMinutes(10)]);
        PlayedSong::factory()->for($party)->for(Song::fromTrack(rulesTrack('fake-track-2')))->create(['played_at' => now()->subHours(2)]);
        $member = rulesMember($party);

        expect(rulesCheck($party, $member, rulesTrack('fake-track-1')))->toBe([false, 'This song has been played recently'])
            ->and(rulesCheck($party, $member, rulesTrack('fake-track-2'))[0])->toBeTrue();
    });

    it('ignores play history without a no repeat interval', function () {
        $party = rulesParty(['no_repeat_interval' => null]);
        PlayedSong::factory()->for($party)->for(Song::fromTrack(rulesTrack()))->create(['played_at' => now()->subMinute()]);

        expect(rulesCheck($party, rulesMember($party), rulesTrack())[0])->toBeTrue();
    });

    it('rejects the track most recently sent to the player', function () {
        $party = rulesParty();
        UpcomingSong::factory()->for($party)->for(Song::fromTrack(rulesTrack()))->sentToPlayer()->create();
        $member = rulesMember($party);

        expect(rulesCheck($party, $member, rulesTrack()))->toBe([false, 'This song will be played next'])
            ->and(rulesCheck($party, $member, rulesTrack('fake-track-2'))[0])->toBeTrue();
    });

    it('reports played recently ahead of will be played next, and similar ahead of both', function () {
        $party = rulesParty(['no_repeat_interval' => 3600]);
        $song = Song::fromTrack(rulesTrack());
        PlayedSong::factory()->for($party)->for($song)->create(['played_at' => now()->subMinute()]);
        UpcomingSong::factory()->for($party)->for($song)->sentToPlayer()->create();
        $member = rulesMember($party);

        expect(rulesCheck($party, $member, rulesTrack())[1])->toBe('This song has been played recently');

        UpcomingSong::factory()->for($party)->for($song)->waiting()->create();

        expect(rulesCheck($party, $member, rulesTrack())[1])->toBe('A similar song is already in the queue');
    });
});

describe('precedence', function () {
    it('reports the first failing single rule in order', function () {
        $party = rulesParty(['allow_requests' => false, 'max_requests' => 1, 'explicit' => false]);
        $member = rulesMember($party, 'banned');
        rulesWaiting($party, rulesTrack('fake-track-2'), $member->user);

        expect(rulesCheck($party, $member, rulesTrack('fake-track-7'))[1])->toBe('Party is not accepting requests');

        $party->forceFill(['allow_requests' => true])->save();
        expect(rulesCheck($party, $member, rulesTrack('fake-track-7'))[1])->toBe('You are not allowed to request any more songs');

        $party->forceFill(['max_requests' => null])->save();
        expect(rulesCheck($party, $member, rulesTrack('fake-track-7'))[1])->toBe('You are not allowed to make requests');
    });

    it('reports single rules before queue rules', function () {
        $party = rulesParty(['explicit' => false]);
        UpcomingSong::factory()->for($party)->for(Song::fromTrack(rulesTrack('fake-track-7')))->sentToPlayer()->create();

        expect(rulesCheck($party, rulesMember($party), rulesTrack('fake-track-7'))[1])->toBe('Explicit songs are not allowed');
    });
});

describe('checkMany', function () {
    it('returns a decision per track keyed by track id', function () {
        $party = rulesParty(['explicit' => false]);
        UpcomingSong::factory()->for($party)->for(Song::fromTrack(rulesTrack('fake-track-3')))->sentToPlayer()->create();

        $decisions = app(RequestRules::class)->checkMany($party->refresh(), rulesMember($party), [
            rulesTrack('fake-track-1'), rulesTrack('fake-track-3'), rulesTrack('fake-track-7'),
        ]);

        expect(array_keys($decisions))->toBe(['fake-track-1', 'fake-track-3', 'fake-track-7'])
            ->and($decisions['fake-track-1']->allowed)->toBeTrue()
            ->and($decisions['fake-track-3']->reason)->toBe('This song will be played next')
            ->and($decisions['fake-track-7']->reason)->toBe('Explicit songs are not allowed');
    });

    it('does not run more queries for more tracks', function () {
        $party = rulesParty(['no_repeat_interval' => 3600, 'max_requests' => 5]);
        $member = rulesMember($party);
        $rules = app(RequestRules::class);
        $queries = function (array $tracks) use ($rules, $party, $member): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $rules->checkMany($party->refresh(), $member->refresh(), $tracks);

            return count(DB::getQueryLog());
        };

        $one = $queries([rulesTrack('fake-track-1')]);
        $many = $queries(array_map(rulesTrack(...), ['fake-track-1', 'fake-track-2', 'fake-track-3', 'fake-track-4', 'fake-track-5']));

        expect($many)->toBe($one);
    });
});

describe('checkById', function () {
    it('checks the track the provider returns', function () {
        $party = rulesParty(['explicit' => false]);
        $provider = app(FakeMusicProvider::class);
        $member = rulesMember($party);

        $ok = app(RequestRules::class)->checkById($party, $member, $provider, 'fake-track-1');
        $explicit = app(RequestRules::class)->checkById($party, $member, $provider, 'fake-track-7');

        expect($ok->allowed)->toBeTrue()->and($explicit->reason)->toBe('Explicit songs are not allowed');
    });

    it('reports an unknown id as not found', function () {
        $party = rulesParty();

        $decision = app(RequestRules::class)->checkById($party, rulesMember($party), app(FakeMusicProvider::class), 'missing');

        expect($decision->allowed)->toBeFalse()->and($decision->reason)->toBe('Song is not found in Spotify');
    });
});

describe('TrackEligibility', function () {
    it('annotates queue state, vote, requester and eligibility per track', function () {
        $party = rulesParty(['explicit' => false]);
        $member = rulesMember($party);
        $requester = User::factory()->create();
        $upcoming = rulesWaiting($party, rulesTrack('fake-track-2'), $requester);
        Vote::factory()->create(['upcoming_song_id' => $upcoming->id, 'user_id' => $member->user_id, 'value' => -1]);
        Vote::factory()->create(['upcoming_song_id' => $upcoming->id, 'user_id' => User::factory()->create()->id, 'value' => 1]);

        $result = app(TrackEligibility::class)->annotate($party->refresh(), $member, [
            rulesTrack('fake-track-1'), rulesTrack('fake-track-2'), rulesTrack('fake-track-7'),
        ]);

        expect($result)->toHaveCount(3)
            ->and($result[0]->upcoming)->toBeFalse()
            ->and($result[0]->score)->toBe(0)
            ->and($result[0]->vote)->toBeNull()
            ->and($result[0]->user)->toBeNull()
            ->and($result[0]->allowed)->toBeTrue()
            ->and($result[1]->upcoming)->toBeTrue()
            ->and($result[1]->upcomingId)->toBe($upcoming->id)
            ->and($result[1]->score)->toBe($upcoming->refresh()->score)
            ->and($result[1]->vote)->toBe(-1)
            ->and($result[1]->user)->toBe($requester->nickname)
            ->and($result[2]->allowed)->toBeFalse()
            ->and($result[2]->reason)->toBe('Explicit songs are not allowed');
    });

    it('returns nothing for no tracks', function () {
        $party = rulesParty();

        expect(app(TrackEligibility::class)->annotate($party, rulesMember($party), []))->toBe([]);
    });

    it('exposes the legacy search result shape for the view', function () {
        $party = rulesParty();
        $result = app(TrackEligibility::class)->annotate($party, rulesMember($party), [rulesTrack('fake-track-1')]);

        expect($result[0]->toSearchResult())->toMatchArray([
            'id' => 'fake-track-1',
            'name' => 'Fake Track 1',
            'duration_ms' => 181000,
            'external_url' => null,
            'provider_name' => 'Fake',
            'album' => ['id' => 'fake-album-1', 'name' => 'Fake Album 1', 'images' => [['url' => 'https://fake.test/albums/1.jpg']]],
            'artists' => [['id' => 'fake-artist-1', 'name' => 'Fake Artist 1']],
            'upcoming' => false,
            'allowed' => true,
            'reason' => null,
        ]);
    });
});

describe('three-way outcome and stage order', function () {
    function stubRule(RequestDecision $decision, array &$calls, string $name): RequestRule
    {
        return new class($decision, $calls, $name) implements RequestRule
        {
            public function __construct(private RequestDecision $decision, private array &$calls, private string $name) {}

            public function decide(RequestContext $context, Track $track): RequestDecision
            {
                $this->calls[] = $this->name.':'.$context->mode->value;

                return $this->decision;
            }
        };
    }

    it('runs core, then mod, then ai rules, telling each the mode', function () {
        $party = rulesParty();
        $calls = [];
        $rules = new RequestRules(
            [stubRule(RequestDecision::allow(), $calls, 'core')],
            [stubRule(RequestDecision::allow(), $calls, 'mod')],
            [stubRule(RequestDecision::allow(), $calls, 'ai')],
        );

        $rules->check($party, rulesMember($party), rulesTrack());

        expect($calls)->toBe(['core:submit', 'mod:submit', 'ai:submit']);
    });

    it('stops later stages on reject', function () {
        $party = rulesParty();
        $calls = [];
        $rules = new RequestRules(
            [stubRule(RequestDecision::allow(), $calls, 'core')],
            [stubRule(RequestDecision::reject('No'), $calls, 'mod')],
            [stubRule(RequestDecision::allow(), $calls, 'ai')],
        );

        $decision = $rules->check($party, rulesMember($party), rulesTrack());

        expect($decision->outcome)->toBe(RequestOutcome::Reject)
            ->and($decision->reason)->toBe('No')
            ->and($calls)->toBe(['core:submit', 'mod:submit']);
    });

    it('does not call ai rules during annotation', function () {
        $party = rulesParty();
        $calls = [];
        $rules = new RequestRules(
            [stubRule(RequestDecision::allow(), $calls, 'core')],
            [stubRule(RequestDecision::allow(), $calls, 'mod')],
            [stubRule(RequestDecision::reject('AI'), $calls, 'ai')],
        );

        $decision = $rules->check($party, rulesMember($party), rulesTrack(), RequestMode::Annotate);

        expect($decision->allowed)->toBeTrue()
            ->and($calls)->toBe(['core:annotate', 'mod:annotate']);
    });

    it('keeps a flag but lets a later reject win', function () {
        $party = rulesParty(['flag_requests' => true]);
        $calls = [];
        $flagging = new RequestRules([stubRule(RequestDecision::flag('Check'), $calls, 'core')], [stubRule(RequestDecision::allow(), $calls, 'mod')]);
        $rejecting = new RequestRules([stubRule(RequestDecision::flag('Check'), $calls, 'core')], [stubRule(RequestDecision::reject('No'), $calls, 'mod')]);
        $member = rulesMember($party);

        $flagged = $flagging->check($party, $member, rulesTrack());

        expect($flagged->outcome)->toBe(RequestOutcome::Flag)
            ->and($flagged->allowed)->toBeTrue()
            ->and($flagged->reason)->toBe('Check')
            ->and($rejecting->check($party, $member, rulesTrack())->outcome)->toBe(RequestOutcome::Reject);
    });

    it('annotates a flagged track as flagged but allowed', function () {
        $party = rulesParty(['flag_requests' => true]);
        $calls = [];
        $this->app->instance(RequestRules::class, new RequestRules([stubRule(RequestDecision::flag('Check'), $calls, 'core')]));

        $result = app(TrackEligibility::class)->annotate($party, rulesMember($party), [rulesTrack()]);

        expect($result[0]->flagged)->toBeTrue()->and($result[0]->allowed)->toBeTrue()->and($result[0]->reason)->toBe('Check');
    });
});
