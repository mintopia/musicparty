<?php

use App\Enums\PartyModerationType;
use App\Enums\Purpose;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyModeration;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMapper;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Requests\RequestRules;
use App\Services\Requests\Rules\ContextPlaylistTrackRule;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\SpotifyWebAPIException;
use Tests\Support\FakeSpotify;

function reqParty(array $attributes = []): Party
{
    $party = Party::factory()->create($attributes);

    return $party->refresh();
}

function reqMember(Party $party, string $role = 'user'): PartyMember
{
    $factory = PartyMember::factory()->for($party)->for(User::factory());

    return match ($role) {
        'vip' => $factory->vip()->create(),
        'owner' => $factory->owner()->create(),
        'banned' => $factory->banned()->create(),
        default => $factory->create(),
    };
}

function reqTrack(string $id = 'track1', array $overrides = []): object
{
    $track = FakeSpotify::track($id, $overrides['name'] ?? 'Test Track');
    foreach ($overrides as $key => $value) {
        $track->{$key} = $value;
    }

    return $track;
}

function reqSpotifyTrack(object $payload): Track
{
    return SpotifyMapper::track($payload);
}

function reqCheck(Party $party, PartyMember $member, object $track): object
{
    return app(RequestRules::class)->check($party->refresh(), $member->refresh(), reqSpotifyTrack($track));
}

function reqUseSpotifyProvider(): void
{
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
}

function reqModeration(Party $party, PartyModerationType $type, string $value, bool $regex = false): void
{
    $moderation = new PartyModeration;
    $moderation->party()->associate($party);
    $moderation->type = $type;
    $moderation->value = $value;
    $moderation->regex = $regex;
    $moderation->save();
}

function reqWaiting(Party $party, User $user, string $spotifyId = 'other', string $name = 'Other Track'): UpcomingSong
{
    $song = Song::fromTrack(SpotifyMapper::track(FakeSpotify::track($spotifyId, $name)));

    return UpcomingSong::factory()->for($party)->for($song)->for($user)->waiting()->create();
}

function reqThrowingSpotify(Throwable $exception): void
{
    $fake = new class($exception) extends FakeSpotify
    {
        public function __construct(private Throwable $exception) {}

        public function getTrack(string $trackId, array|object $options = []): array|object
        {
            throw $this->exception;
        }
    };
    app()->bind(SpotifyWebAPI::class, fn () => $fake);
}

function reqPost(Party $party, User $user, string $spotifyId)
{
    Sanctum::actingAs($user);

    return test()->postJson(route('api.v1.parties.upcomingsongs.store', [$party]), ['provider_track_id' => $spotifyId]);
}

beforeEach(fn () => reqUseSpotifyProvider());

describe('RequestRules', function () {
    it('accepts a plain track from a member', function () {
        $party = reqParty(['allow_requests' => true]);

        $response = reqCheck($party, reqMember($party), reqTrack());

        expect($response->allowed)->toBeTrue()->and($response->reason)->toBeNull();
    });

    it('rejects the reserved context playlist track', function () {
        $party = reqParty(['allow_requests' => true]);

        $response = reqCheck($party, reqMember($party), reqTrack(ContextPlaylistTrackRule::TRACK_ID));

        expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('This song is not available');
    });

    it('rejects when the party is not accepting requests', function () {
        $party = reqParty(['allow_requests' => false]);

        $response = reqCheck($party, reqMember($party), reqTrack());

        expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('Party is not accepting requests');
    });

    it('rejects a banned member', function () {
        $party = reqParty(['allow_requests' => true]);

        $response = reqCheck($party, reqMember($party, 'banned'), reqTrack());

        expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('You are not allowed to make requests');
    });

    describe('max requests', function () {
        it('accepts a member under the limit', function () {
            $party = reqParty(['allow_requests' => true, 'max_requests' => 2]);
            $member = reqMember($party);
            reqWaiting($party, $member->user);

            expect(reqCheck($party, $member, reqTrack())->allowed)->toBeTrue();
        });

        it('rejects a member at the limit', function () {
            $party = reqParty(['allow_requests' => true, 'max_requests' => 1]);
            $member = reqMember($party);
            reqWaiting($party, $member->user);

            $response = reqCheck($party, $member, reqTrack());

            expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('You are not allowed to request any more songs');
        });

        it('does not count requests already sent to the player', function () {
            $party = reqParty(['allow_requests' => true, 'max_requests' => 1]);
            $member = reqMember($party);
            reqWaiting($party, $member->user)->forceFill(['queued_at' => now()])->save();

            expect(reqCheck($party, $member, reqTrack())->allowed)->toBeTrue();
        });

        it('does not count other members requests', function () {
            $party = reqParty(['allow_requests' => true, 'max_requests' => 1]);
            $member = reqMember($party);
            reqWaiting($party, reqMember($party)->user);

            expect(reqCheck($party, $member, reqTrack())->allowed)->toBeTrue();
        });

        it('exempts a vip at the limit', function () {
            $party = reqParty(['allow_requests' => true, 'max_requests' => 1]);
            $member = reqMember($party, 'vip');
            reqWaiting($party, $member->user);

            expect(reqCheck($party, $member, reqTrack())->allowed)->toBeTrue();
        });

        it('exempts the party owner user at the limit', function () {
            $party = reqParty(['allow_requests' => true, 'max_requests' => 1]);
            $member = PartyMember::factory()->for($party)->for($party->user)->owner()->create();
            reqWaiting($party, $party->user);

            expect(reqCheck($party, $member, reqTrack())->allowed)->toBeTrue();
        });

        it('imposes no limit when max requests is null', function () {
            $party = reqParty(['allow_requests' => true, 'max_requests' => null]);
            $member = reqMember($party);
            reqWaiting($party, $member->user);
            reqWaiting($party, $member->user, 'other2', 'Other Two');

            expect(reqCheck($party, $member, reqTrack())->allowed)->toBeTrue();
        });
    });

    describe('song length', function () {
        it('accepts a track within the minimum length', function () {
            $party = reqParty(['allow_requests' => true, 'min_song_length' => 100]);

            expect(reqCheck($party, reqMember($party), reqTrack('t', ['duration_ms' => 200000]))->allowed)->toBeTrue();
        });

        it('rejects a track shorter than the minimum length', function () {
            $party = reqParty(['allow_requests' => true, 'min_song_length' => 100]);

            $response = reqCheck($party, reqMember($party), reqTrack('t', ['duration_ms' => 99000]));

            expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('Song is too short');
        });

        it('accepts a track exactly at the minimum length', function () {
            $party = reqParty(['allow_requests' => true, 'min_song_length' => 100]);

            expect(reqCheck($party, reqMember($party), reqTrack('t', ['duration_ms' => 100000]))->allowed)->toBeTrue();
        });

        it('accepts a track within the maximum length', function () {
            $party = reqParty(['allow_requests' => true, 'max_song_length' => 300]);

            expect(reqCheck($party, reqMember($party), reqTrack('t', ['duration_ms' => 200000]))->allowed)->toBeTrue();
        });

        it('rejects a track longer than the maximum length', function () {
            $party = reqParty(['allow_requests' => true, 'max_song_length' => 300]);

            $response = reqCheck($party, reqMember($party), reqTrack('t', ['duration_ms' => 301000]));

            expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('Song is too long');
        });

        it('accepts a track exactly at the maximum length', function () {
            $party = reqParty(['allow_requests' => true, 'max_song_length' => 300]);

            expect(reqCheck($party, reqMember($party), reqTrack('t', ['duration_ms' => 300000]))->allowed)->toBeTrue();
        });
    });

    describe('explicit tracks', function () {
        it('accepts an explicit track when the party allows explicit', function () {
            $party = reqParty(['allow_requests' => true, 'explicit' => true]);

            expect(reqCheck($party, reqMember($party), reqTrack('t', ['explicit' => true]))->allowed)->toBeTrue();
        });

        it('rejects an explicit track when the party disallows explicit', function () {
            $party = reqParty(['allow_requests' => true, 'explicit' => false]);

            $response = reqCheck($party, reqMember($party), reqTrack('t', ['explicit' => true]));

            expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('Explicit songs are not allowed');
        });

        it('accepts a clean track when the party disallows explicit', function () {
            $party = reqParty(['allow_requests' => true, 'explicit' => false]);

            expect(reqCheck($party, reqMember($party), reqTrack('t', ['explicit' => false]))->allowed)->toBeTrue();
        });
    });

    describe('no repeat interval', function () {
        it('rejects a track played within the interval', function () {
            $party = reqParty(['allow_requests' => true, 'no_repeat_interval' => 3600]);
            $song = Song::fromTrack(SpotifyMapper::track(reqTrack('played')));
            PlayedSong::factory()->for($party)->for($song)->create(['played_at' => now()->subMinutes(10)]);

            $response = reqCheck($party, reqMember($party), reqTrack('played'));

            expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('This song has been played recently');
        });

        it('accepts a track played before the interval', function () {
            $party = reqParty(['allow_requests' => true, 'no_repeat_interval' => 3600]);
            $song = Song::fromTrack(SpotifyMapper::track(reqTrack('played')));
            PlayedSong::factory()->for($party)->for($song)->create(['played_at' => now()->subHours(2)]);

            expect(reqCheck($party, reqMember($party), reqTrack('played'))->allowed)->toBeTrue();
        });

        it('ignores play history when the interval is not set', function () {
            $party = reqParty(['allow_requests' => true, 'no_repeat_interval' => null]);
            $song = Song::fromTrack(SpotifyMapper::track(reqTrack('played')));
            PlayedSong::factory()->for($party)->for($song)->create(['played_at' => now()->subMinute()]);

            expect(reqCheck($party, reqMember($party), reqTrack('played'))->allowed)->toBeTrue();
        });

        it('ignores plays from other parties', function () {
            $party = reqParty(['allow_requests' => true, 'no_repeat_interval' => 3600]);
            $song = Song::fromTrack(SpotifyMapper::track(reqTrack('played')));
            PlayedSong::factory()->for(reqParty())->for($song)->create(['played_at' => now()->subMinute()]);

            expect(reqCheck($party, reqMember($party), reqTrack('played'))->allowed)->toBeTrue();
        });
    });

    describe('will be played next', function () {
        it('rejects the track most recently sent to the player', function () {
            $party = reqParty(['allow_requests' => true]);
            $song = Song::fromTrack(SpotifyMapper::track(reqTrack('next')));
            UpcomingSong::factory()->for($party)->for($song)->sentToPlayer()->create();

            $response = reqCheck($party, reqMember($party), reqTrack('next'));

            expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('This song will be played next');
        });

        it('accepts a track that is not the one sent to the player', function () {
            $party = reqParty(['allow_requests' => true]);
            $song = Song::fromTrack(SpotifyMapper::track(reqTrack('next')));
            UpcomingSong::factory()->for($party)->for($song)->sentToPlayer()->create();

            expect(reqCheck($party, reqMember($party), reqTrack('different'))->allowed)->toBeTrue();
        });

        it('reports a similar song rather than will be played next for a track only waiting in the queue', function () {
            $party = reqParty(['allow_requests' => true]);
            $song = Song::fromTrack(SpotifyMapper::track(reqTrack('waiting')));
            UpcomingSong::factory()->for($party)->for($song)->waiting()->create();

            expect(reqCheck($party, reqMember($party), reqTrack('waiting'))->reason)->toBe('A similar song is already in the queue');
        });

        it('prefers the played recently reason over the will be played next reason', function () {
            $party = reqParty(['allow_requests' => true, 'no_repeat_interval' => 3600]);
            $song = Song::fromTrack(SpotifyMapper::track(reqTrack('both')));
            PlayedSong::factory()->for($party)->for($song)->create(['played_at' => now()->subMinute()]);
            UpcomingSong::factory()->for($party)->for($song)->sentToPlayer()->create();

            $response = reqCheck($party, reqMember($party), reqTrack('both'));

            expect($response->reason)->toBe('This song has been played recently');
        });
    });

    describe('blocklist moderation', function () {
        $matching = [
            'track name' => [PartyModerationType::mtName, 'Test Track', 'Track is not allowed'],
            'track id' => [PartyModerationType::mtId, 'track1', 'Track is not allowed'],
            'artist name' => [PartyModerationType::mtArtist, 'Test Artist', 'Artist is not allowed'],
            'artist id' => [PartyModerationType::mtArtistId, 'artist1', 'Artist is not allowed'],
            'album name' => [PartyModerationType::mtAlbum, 'Test Album', 'Album is not allowed'],
            'album id' => [PartyModerationType::mtAlbumId, 'album-track1', 'Album is not allowed'],
        ];

        it('rejects a matching track with the rule specific reason', function (PartyModerationType $type, string $value, string $reason) {
            $party = reqParty(['allow_requests' => true]);
            reqModeration($party, $type, $value);

            $response = reqCheck($party, reqMember($party), reqTrack());

            expect($response->allowed)->toBeFalse()->and($response->reason)->toBe($reason);
        })->with($matching);

        it('accepts a track the rule does not match', function (PartyModerationType $type) {
            $party = reqParty(['allow_requests' => true]);
            reqModeration($party, $type, 'nothing-matches-this');

            expect(reqCheck($party, reqMember($party), reqTrack())->allowed)->toBeTrue();
        })->with([
            'track name' => PartyModerationType::mtName,
            'track id' => PartyModerationType::mtId,
            'artist name' => PartyModerationType::mtArtist,
            'artist id' => PartyModerationType::mtArtistId,
            'album name' => PartyModerationType::mtAlbum,
            'album id' => PartyModerationType::mtAlbumId,
        ]);

        it('rejects a matching isrc', function () {
            $party = reqParty(['allow_requests' => true]);
            reqModeration($party, PartyModerationType::mtISRC, 'USABC1234567');
            $track = reqTrack('t', ['external_ids' => (object) ['isrc' => 'USABC1234567']]);

            $response = reqCheck($party, reqMember($party), $track);

            expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('Track is not allowed');
        });

        it('accepts a non matching isrc', function () {
            $party = reqParty(['allow_requests' => true]);
            reqModeration($party, PartyModerationType::mtISRC, 'USABC1234567');
            $track = reqTrack('t', ['external_ids' => (object) ['isrc' => 'GBXYZ0000000']]);

            expect(reqCheck($party, reqMember($party), $track)->allowed)->toBeTrue();
        });

        it('accepts a track with no external ids when an isrc rule exists', function () {
            $party = reqParty(['allow_requests' => true]);
            reqModeration($party, PartyModerationType::mtISRC, 'USABC1234567');

            expect(reqCheck($party, reqMember($party), reqTrack())->allowed)->toBeTrue();
        });

        it('matches wildcards case insensitively on names', function () {
            $party = reqParty(['allow_requests' => true]);
            reqModeration($party, PartyModerationType::mtName, 'test *');

            expect(reqCheck($party, reqMember($party), reqTrack())->allowed)->toBeFalse();
        });

        it('matches ids case sensitively', function () {
            $party = reqParty(['allow_requests' => true]);
            reqModeration($party, PartyModerationType::mtId, 'TRACK1');

            expect(reqCheck($party, reqMember($party), reqTrack())->allowed)->toBeTrue();
        });

        it('matches regex rules', function () {
            $party = reqParty(['allow_requests' => true]);
            reqModeration($party, PartyModerationType::mtName, '/^test\s/i', true);

            expect(reqCheck($party, reqMember($party), reqTrack())->allowed)->toBeFalse();
        });

        it('ignores rules belonging to other parties', function () {
            $party = reqParty(['allow_requests' => true]);
            reqModeration(reqParty(), PartyModerationType::mtName, 'Test Track');

            expect(reqCheck($party, reqMember($party), reqTrack())->allowed)->toBeTrue();
        });
    });

    describe('similar song', function () {
        it('blocks a same name same artist waiting request', function () {
            $party = reqParty(['allow_requests' => true]);
            $song = Song::fromTrack(SpotifyMapper::track(FakeSpotify::track('original', 'Same Name')));
            UpcomingSong::factory()->for($party)->for($song)->waiting()->create();

            $response = reqCheck($party, reqMember($party), FakeSpotify::track('remaster', 'Same Name'));

            expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('A similar song is already in the queue');
        });

        it('accepts a differently named track', function () {
            $party = reqParty(['allow_requests' => true]);
            $song = Song::fromTrack(SpotifyMapper::track(FakeSpotify::track('original', 'Same Name')));
            UpcomingSong::factory()->for($party)->for($song)->waiting()->create();

            expect(reqCheck($party, reqMember($party), FakeSpotify::track('different', 'Another Name'))->allowed)->toBeTrue();
        });
    });

    describe('rule precedence', function () {
        it('reports not accepting requests before the banned reason', function () {
            $party = reqParty(['allow_requests' => false]);

            $response = reqCheck($party, reqMember($party, 'banned'), reqTrack());

            expect($response->reason)->toBe('Party is not accepting requests');
        });

        it('reports max requests before the banned reason', function () {
            $party = reqParty(['allow_requests' => true, 'max_requests' => 1]);
            $member = reqMember($party, 'banned');
            reqWaiting($party, $member->user);

            expect(reqCheck($party, $member, reqTrack())->reason)->toBe('You are not allowed to request any more songs');
        });
    });

    describe('checkMany', function () {
        it('returns a response per track keyed by spotify id, mixing accepted and rejected', function () {
            $party = reqParty(['allow_requests' => true, 'explicit' => false]);
            $result = app(RequestRules::class)->checkMany($party->refresh(), reqMember($party)->refresh(), [
                reqSpotifyTrack(reqTrack('clean')),
                reqSpotifyTrack(reqTrack('dirty', ['explicit' => true])),
            ]);

            expect($result['clean']->allowed)->toBeTrue()
                ->and($result['dirty']->allowed)->toBeFalse()
                ->and($result['dirty']->reason)->toBe('Explicit songs are not allowed');
        });
    });

    it('reports an unknown spotify id as not found when checking by id', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        reqThrowingSpotify(new SpotifyWebAPIException('invalid id'));

        $response = app(RequestRules::class)->checkById($party->refresh(), $member, app(MusicProviderFactory::class)->forParty($party, Purpose::Control), 'nope');

        expect($response->allowed)->toBeFalse()->and($response->reason)->toBe('Song is not found in Spotify');
    });

    it('rethrows other spotify errors when checking by id', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        reqThrowingSpotify(new SpotifyWebAPIException('Service unavailable', 503));

        app(RequestRules::class)->checkById($party->refresh(), $member, app(MusicProviderFactory::class)->forParty($party, Purpose::Control), 'nope');
    })->throws(SpotifyWebAPIException::class, 'Service unavailable');
});

describe('UpcomingSongController::store', function () {
    it('creates a request and an upvote for an accepted track', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        FakeSpotify::install()->respond('getTrack', reqTrack('accepted', ['name' => 'Accepted Track']));

        $response = reqPost($party, $member->user, 'accepted');

        $response->assertCreated()->assertJsonPath('data.vote', 1)->assertJsonPath('data.user', $member->user->nickname);
        $upcoming = UpcomingSong::firstOrFail();
        expect($upcoming->party_id)->toBe($party->id)
            ->and($upcoming->user_id)->toBe($member->user_id)
            ->and($upcoming->queued_at)->toBeNull()
            ->and(Vote::count())->toBe(1)
            ->and(Vote::first()->value)->toBe(1);
    });

    it('returns 400 with the reason in the message and provider_track_id errors for a rejected track', function () {
        $party = reqParty(['allow_requests' => true, 'explicit' => false]);
        $member = reqMember($party);
        FakeSpotify::install()->respond('getTrack', reqTrack('dirty', ['explicit' => true]));

        reqPost($party, $member->user, 'dirty')
            ->assertBadRequest()
            ->assertExactJson([
                'message' => 'Explicit songs are not allowed',
                'errors' => ['provider_track_id' => ['Explicit songs are not allowed']],
            ]);

        expect(UpcomingSong::count())->toBe(0)->and(Vote::count())->toBe(0);
    });

    it('returns 400 when the party is not accepting requests', function () {
        $party = reqParty(['allow_requests' => false]);
        $member = reqMember($party);
        FakeSpotify::install()->respond('getTrack', reqTrack());

        reqPost($party, $member->user, 'track1')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Party is not accepting requests');
    });

    it('returns 400 for a banned member', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party, 'banned');
        FakeSpotify::install()->respond('getTrack', reqTrack());

        reqPost($party, $member->user, 'track1')
            ->assertBadRequest()
            ->assertJsonPath('message', 'You are not allowed to make requests');
    });

    it('returns 400 when the member is at max requests', function () {
        $party = reqParty(['allow_requests' => true, 'max_requests' => 1]);
        $member = reqMember($party);
        reqWaiting($party, $member->user);
        FakeSpotify::install()->respond('getTrack', reqTrack());

        reqPost($party, $member->user, 'track1')
            ->assertBadRequest()
            ->assertJsonPath('message', 'You are not allowed to request any more songs');
    });

    it('returns 400 for a blocklisted track', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        reqModeration($party, PartyModerationType::mtArtist, 'Test Artist');
        FakeSpotify::install()->respond('getTrack', reqTrack());

        reqPost($party, $member->user, 'track1')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Artist is not allowed');
    });

    it('returns 400 for a track that will be played next', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        $song = Song::fromTrack(SpotifyMapper::track(reqTrack('next')));
        UpcomingSong::factory()->for($party)->for($song)->sentToPlayer()->create();
        FakeSpotify::install()->respond('getTrack', reqTrack('next'));

        reqPost($party, $member->user, 'next')
            ->assertBadRequest()
            ->assertJsonPath('message', 'This song will be played next');
    });

    it('returns 500 and creates nothing when spotify rejects the track id', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        reqThrowingSpotify(new SpotifyWebAPIException('invalid id'));

        reqPost($party, $member->user, 'missing')->assertServerError();

        expect(UpcomingSong::count())->toBe(0);
    });

    it('returns 403 for a user who is not a party member', function () {
        $party = reqParty(['allow_requests' => true]);
        FakeSpotify::install()->respond('getTrack', reqTrack());

        reqPost($party, User::factory()->create(), 'track1')->assertForbidden();

        expect(UpcomingSong::count())->toBe(0);
    });

    it('returns 422 validation error when provider_track_id is missing', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        Sanctum::actingAs($member->user);

        $this->postJson(route('api.v1.parties.upcomingsongs.store', [$party]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider_track_id');
    });

    it('returns 401 for an unauthenticated post', function () {
        $party = reqParty(['allow_requests' => true]);

        $this->postJson(route('api.v1.parties.upcomingsongs.store', [$party]), ['provider_track_id' => 'track1'])->assertUnauthorized();
    });

    it('adds a vote to an existing waiting request without re-running checks or creating a second request', function () {
        $party = reqParty(['allow_requests' => true, 'explicit' => false]);
        $requester = reqMember($party);
        $voter = reqMember($party);
        $existing = reqWaiting($party, $requester->user, 'dup', 'Dup Track');
        $fake = FakeSpotify::install()->respond('getTrack', reqTrack('dup', ['explicit' => true]));

        reqPost($party, $voter->user, 'dup')->assertSuccessful();

        expect(UpcomingSong::count())->toBe(1)
            ->and($existing->votes()->count())->toBe(1)
            ->and($existing->votes()->first()->user_id)->toBe($voter->user_id)
            ->and($fake->callsTo('getTrack'))->toBeEmpty();
    });

    it('forbids a non member voting on an existing waiting request', function () {
        $party = reqParty(['allow_requests' => true]);
        $existing = reqWaiting($party, reqMember($party)->user, 'dup', 'Dup Track');
        FakeSpotify::install();

        reqPost($party, User::factory()->create(), 'dup')->assertForbidden();

        expect($existing->votes()->count())->toBe(0);
    });

    it('rejects a banned member voting on an existing waiting request', function () {
        $party = reqParty(['allow_requests' => false]);
        $existing = reqWaiting($party, reqMember($party)->user, 'dup', 'Dup Track');
        $banned = reqMember($party, 'banned');
        FakeSpotify::install();

        reqPost($party, $banned->user, 'dup')->assertStatus(400);

        expect($existing->votes()->count())->toBe(0);
    });

    it('rejects a banned member voting on an existing waiting request while requests are open', function () {
        $party = reqParty(['allow_requests' => true]);
        $existing = reqWaiting($party, reqMember($party)->user, 'dup', 'Dup Track');
        $banned = reqMember($party, 'banned');
        FakeSpotify::install();

        reqPost($party, $banned->user, 'dup')->assertStatus(400);

        expect($existing->votes()->count())->toBe(0);
    });

    it('does not duplicate a vote when the same member posts twice', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        FakeSpotify::install()->respond('getTrack', reqTrack('twice'));

        reqPost($party, $member->user, 'twice')->assertSuccessful();
        reqPost($party, $member->user, 'twice')->assertSuccessful();

        expect(UpcomingSong::count())->toBe(1)->and(Vote::count())->toBe(1);
    });

    it('blocks a same name same artist request at the endpoint', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        $song = Song::fromTrack(SpotifyMapper::track(FakeSpotify::track('original', 'Same Name')));
        UpcomingSong::factory()->for($party)->for($song)->waiting()->create();
        FakeSpotify::install()->respond('getTrack', FakeSpotify::track('remaster', 'Same Name'));

        reqPost($party, $member->user, 'remaster');

        expect(UpcomingSong::count())->toBe(1);
    });
});

describe('Request store lock', function () {
    it('runs the checks and insert while holding the per-party-member lock', function () {
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        $fake = new class("request-store:{$party->id}:{$member->id}") extends FakeSpotify
        {
            public ?bool $lockHeldDuringFetch = null;

            public function __construct(private string $lockKey) {}

            public function getTrack(string $trackId, array|object $options = []): object
            {
                $this->lockHeldDuringFetch = ! Cache::lock($this->lockKey, 5)->get();

                return FakeSpotify::track($trackId);
            }
        };
        app()->bind(SpotifyWebAPI::class, fn () => $fake);

        reqPost($party, $member->user, 'locked')->assertSuccessful();

        expect($fake->lockHeldDuringFetch)->toBeTrue();
        expect(Cache::lock("request-store:{$party->id}:{$member->id}", 5)->get())->toBeTrue();
    });

    it('rejects a post while the same member already has one in flight', function () {
        config(['musicparty.request_lock_wait_seconds' => 0]);
        $party = reqParty(['allow_requests' => true]);
        $member = reqMember($party);
        FakeSpotify::install()->respond('getTrack', reqTrack('racing'));
        $held = Cache::lock("request-store:{$party->id}:{$member->id}", 30);
        $held->get();

        reqPost($party, $member->user, 'racing')->assertStatus(429);

        $held->release();
        expect(UpcomingSong::count())->toBe(0);
    });

    it('does not block a different member of the same party', function () {
        $party = reqParty(['allow_requests' => true]);
        $busy = reqMember($party);
        $other = reqMember($party);
        FakeSpotify::install()->respond('getTrack', reqTrack('free'));
        $held = Cache::lock("request-store:{$party->id}:{$busy->id}", 30);
        $held->get();

        reqPost($party, $other->user, 'free')->assertSuccessful();

        $held->release();
    });

    it('enforces max_requests on back-to-back posts', function () {
        $party = reqParty(['allow_requests' => true, 'max_requests' => 1]);
        $member = reqMember($party);
        FakeSpotify::install()->respond('getTrack', reqTrack('first'));

        reqPost($party, $member->user, 'first')->assertSuccessful();
        FakeSpotify::install()->respond('getTrack', reqTrack('second', ['name' => 'Second Track']));
        reqPost($party, $member->user, 'second')->assertStatus(400)
            ->assertJsonPath('message', 'You are not allowed to request any more songs');

        expect(UpcomingSong::count())->toBe(1);
    });

    it('still exempts a vip from max_requests', function () {
        $party = reqParty(['allow_requests' => true, 'max_requests' => 1]);
        $member = reqMember($party, 'vip');
        FakeSpotify::install()->respond('getTrack', reqTrack('one'));
        reqPost($party, $member->user, 'one')->assertSuccessful();
        FakeSpotify::install()->respond('getTrack', reqTrack('two', ['name' => 'Two Track']));

        reqPost($party, $member->user, 'two')->assertSuccessful();

        expect(UpcomingSong::count())->toBe(2);
    });
});
