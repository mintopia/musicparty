<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->host = PartyMember::factory()->for($this->party)->host()->create();
    $this->song = TrackRequest::factory()->for($this->party)->create([
        'party_member_id' => $this->host->id,
        'provider_track_id' => 'trk-1',
        'isrc' => 'ISRC001',
        'title' => 'Night Drive',
    ]);
});

function songDetailMemberUser(Party $party, string $state = 'guest'): User
{
    $user = User::factory()->create();
    $factory = PartyMember::factory()->for($party)->for($user);
    $state === 'guest' ? $factory->create() : $factory->{$state}()->create();

    return $user;
}

function songDetailUrl(Party $party, TrackRequest $song): string
{
    return "/parties/{$party->code}/songs/{$song->id}";
}

function songDetailApiUrl(Party $party, TrackRequest $song): string
{
    return "/api/v1/parties/{$party->code}/requests/{$song->id}";
}

it('lets a member view the song page', function () {
    $user = songDetailMemberUser($this->party);

    $this->withoutVite()->actingAs($user)->get(songDetailUrl($this->party, $this->song))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Party/Song')
            ->where('party.code', 'ABCD')
            ->where('song.id', $this->song->id)
            ->where('song.title', 'Night Drive')
            ->where('song.can_moderate', false)
            ->where('song.moderate_url', null));
});

it('refuses non-members', function () {
    $user = User::factory()->create();

    $this->withoutVite()->actingAs($user)->get(songDetailUrl($this->party, $this->song))->assertForbidden();

    Sanctum::actingAs($user);
    $this->getJson(songDetailApiUrl($this->party, $this->song))->assertForbidden();
});

it('refuses banned members', function () {
    $user = songDetailMemberUser($this->party, 'banned');
    Sanctum::actingAs($user);

    $this->getJson(songDetailApiUrl($this->party, $this->song))->assertForbidden();
});

it('returns 404 for a track from another party', function () {
    $other = Party::factory()->live()->create(['code' => 'WXYZ']);
    $foreign = TrackRequest::factory()->for($other)->create();
    $user = songDetailMemberUser($this->party);

    $this->withoutVite()->actingAs($user)->get(songDetailUrl($this->party, $foreign))->assertNotFound();
    Sanctum::actingAs($user);
    $this->getJson(songDetailApiUrl($this->party, $foreign))->assertNotFound();
});

it('hides moderation from guest members and offers a prefilled blocklist link to staff', function (string $state, bool $canModerate) {
    Sanctum::actingAs(songDetailMemberUser($this->party, $state));

    $response = $this->getJson(songDetailApiUrl($this->party, $this->song))->assertOk()
        ->assertJsonPath('data.can_moderate', $canModerate);

    if ($canModerate) {
        $response->assertJsonPath('data.moderate_url', route('parties.blocklist', ['party' => 'ABCD']).'?type=track_id&value=trk-1&name=Night+Drive');
    } else {
        $response->assertJsonPath('data.moderate_url', null);
    }
})->with([
    'guest' => ['guest', false],
    'moderator' => ['moderator', true],
    'host' => ['host', true],
]);

it('finds other appearances by track id and by isrc', function () {
    $byTrack = TrackRequest::factory()->for($this->party)->create(['provider_track_id' => 'trk-1']);
    $byIsrc = TrackRequest::factory()->for($this->party)->create(['provider_track_id' => 'trk-other', 'isrc' => 'ISRC001']);
    TrackRequest::factory()->for($this->party)->create(['provider_track_id' => 'trk-unrelated', 'isrc' => 'ISRC999']);
    TrackRequest::factory()->for(Party::factory()->live()->create(['code' => 'WXYZ']))->create(['provider_track_id' => 'trk-1']);
    Sanctum::actingAs(songDetailMemberUser($this->party));

    $ids = collect((array) $this->getJson(songDetailApiUrl($this->party, $this->song))->assertOk()->json('data.appearances'))->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing([$byTrack->id, $byIsrc->id]);
});

it('does not match appearances on a null isrc', function () {
    $song = TrackRequest::factory()->for($this->party)->create(['provider_track_id' => 'solo', 'isrc' => null]);
    TrackRequest::factory()->for($this->party)->create(['provider_track_id' => 'else', 'isrc' => null]);
    Sanctum::actingAs(songDetailMemberUser($this->party));

    $this->getJson(songDetailApiUrl($this->party, $song))->assertOk()->assertJsonPath('data.appearances', []);
});

it('returns the documented payload shape', function () {
    $voter = PartyMember::factory()->for($this->party)->create();
    RequestVote::factory()->create(['track_request_id' => $this->song->id, 'party_member_id' => $voter->id]);
    $play = Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $this->song->id]);
    Rating::factory()->dislike()->create(['play_id' => $play->id, 'party_member_id' => $voter->id]);
    Sanctum::actingAs(songDetailMemberUser($this->party));

    $this->getJson(songDetailApiUrl($this->party, $this->song))->assertOk()
        ->assertJsonStructure(['data' => [
            'id', 'title', 'artists', 'album', 'artwork_url', 'duration_ms', 'explicit', 'provider_track_id', 'isrc',
            'status', 'requested_at', 'sent_at', 'spotify_url', 'requester' => ['id', 'name'],
            'votes' => [['member' => ['id', 'name'], 'direction', 'voted_at']],
            'ratings' => [['member' => ['id', 'name'], 'direction', 'rated_at']],
            'appearances', 'can_moderate', 'moderate_url',
        ]])
        ->assertJsonPath('data.votes.0.direction', 'up')
        ->assertJsonPath('data.ratings.0.direction', 'down')
        ->assertJsonPath('data.ratings.0.member.id', $voter->id);
});

it('runs a constant number of queries regardless of votes, ratings and appearances', function () {
    $user = songDetailMemberUser($this->party);
    $play = Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $this->song->id]);
    $seed = function (int $n) use ($play): void {
        foreach (range(1, $n) as $_) {
            $member = PartyMember::factory()->for($this->party)->create();
            RequestVote::factory()->create(['track_request_id' => $this->song->id, 'party_member_id' => $member->id]);
            Rating::factory()->create(['play_id' => $play->id, 'party_member_id' => $member->id]);
            TrackRequest::factory()->for($this->party)->create(['provider_track_id' => 'trk-1']);
        }
    };
    $measure = fn () => $this->withoutVite()->actingAs($user)->get(songDetailUrl($this->party, $this->song))->assertOk();
    $api = function () use ($user): void {
        Sanctum::actingAs($user);
        $this->getJson(songDetailApiUrl($this->party, $this->song))->assertOk();
    };

    $seed(1);
    $measure();
    $api();
    $fewWeb = countQueries($measure);
    $fewApi = countQueries($api);

    $seed(48);

    expect(countQueries($measure))->toBe($fewWeb)
        ->and(countQueries($api))->toBe($fewApi);
});

it('gives the host the moderate url in the Inertia props', function () {
    $this->withoutVite()->actingAs($this->host->user)->get(songDetailUrl($this->party, $this->song))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('song.can_moderate', true)
            ->where('song.moderate_url', route('parties.blocklist', ['party' => 'ABCD']).'?type=track_id&value=trk-1&name=Night+Drive'));
});

it('refuses a banned member on the web page', function () {
    $this->withoutVite()->actingAs(songDetailMemberUser($this->party, 'banned'))
        ->get(songDetailUrl($this->party, $this->song))
        ->assertForbidden();
});

function playDetailUrl(Party $party, Play $play): string
{
    return "/parties/{$party->code}/plays/{$play->id}";
}

function playDetailApiUrl(Party $party, Play $play): string
{
    return "/api/v1/parties/{$party->code}/plays/{$play->id}";
}

it('shows a fallback play page and payload', function () {
    $play = Play::factory()->create(['party_id' => $this->party->id, 'provider_track_id' => 'fb-1', 'title' => 'Fallback Song', 'played_at' => '2026-10-09 12:00:00']);
    $rater = PartyMember::factory()->for($this->party)->create();
    Rating::factory()->create(['play_id' => $play->id, 'party_member_id' => $rater->id]);
    $user = songDetailMemberUser($this->party);

    $this->withoutVite()->actingAs($user)->get(playDetailUrl($this->party, $play))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Party/Song')
            ->where('song.kind', 'play')
            ->where('song.id', $play->id)
            ->where('song.title', 'Fallback Song'));

    Sanctum::actingAs($user);
    $this->getJson(playDetailApiUrl($this->party, $play))->assertOk()
        ->assertJsonPath('data.kind', 'play')
        ->assertJsonPath('data.play_id', $play->id)
        ->assertJsonPath('data.requester', null)
        ->assertJsonPath('data.votes', [])
        ->assertJsonPath('data.status', 'played')
        ->assertJsonPath('data.isrc', null)
        ->assertJsonPath('data.ratings.0.member.id', $rater->id)
        ->assertJsonPath('data.sent_at', $play->played_at->toIso8601String())
        ->assertJsonStructure(['data' => [
            'kind', 'id', 'play_id', 'title', 'artists', 'album', 'artwork_url', 'duration_ms', 'explicit', 'provider_track_id', 'isrc',
            'status', 'requested_at', 'sent_at', 'played_at', 'spotify_url', 'requester', 'votes', 'ratings', 'appearances', 'can_moderate', 'moderate_url',
        ]]);
});

it('reuses the request detail for a play that has a request', function () {
    $voter = PartyMember::factory()->for($this->party)->create();
    RequestVote::factory()->create(['track_request_id' => $this->song->id, 'party_member_id' => $voter->id]);
    $play = Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $this->song->id, 'provider_track_id' => 'trk-1']);
    Sanctum::actingAs(songDetailMemberUser($this->party));

    $this->getJson(playDetailApiUrl($this->party, $play))->assertOk()
        ->assertJsonPath('data.kind', 'request')
        ->assertJsonPath('data.id', $this->song->id)
        ->assertJsonPath('data.play_id', $play->id)
        ->assertJsonPath('data.votes.0.direction', 'up')
        ->assertJsonPath('data.appearances', []);
});

it('returns 404 for a play from another party', function () {
    $foreign = Play::factory()->create(['party_id' => Party::factory()->live()->create(['code' => 'WXYZ'])->id]);
    $user = songDetailMemberUser($this->party);

    $this->withoutVite()->actingAs($user)->get(playDetailUrl($this->party, $foreign))->assertNotFound();
    Sanctum::actingAs($user);
    $this->getJson(playDetailApiUrl($this->party, $foreign))->assertNotFound();
});

it('guards the play route by membership and moderation', function () {
    $play = Play::factory()->create(['party_id' => $this->party->id, 'provider_track_id' => 'fb-2', 'title' => 'Fb']);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson(playDetailApiUrl($this->party, $play))->assertForbidden();
    Sanctum::actingAs(songDetailMemberUser($this->party, 'banned'));
    $this->getJson(playDetailApiUrl($this->party, $play))->assertForbidden();
    auth()->forgetGuards();
    $this->withoutVite()->actingAs(User::factory()->create())->get(playDetailUrl($this->party, $play))->assertForbidden();
    Sanctum::actingAs(songDetailMemberUser($this->party));
    $this->getJson(playDetailApiUrl($this->party, $play))->assertOk()->assertJsonPath('data.can_moderate', false)->assertJsonPath('data.moderate_url', null);
    Sanctum::actingAs(songDetailMemberUser($this->party, 'moderator'));
    $this->getJson(playDetailApiUrl($this->party, $play))->assertOk()->assertJsonPath('data.can_moderate', true)
        ->assertJsonPath('data.moderate_url', route('parties.blocklist', ['party' => 'ABCD']).'?type=track_id&value=fb-2&name=Fb');
});

it('lists fallback plays among appearances without duplicating requests that have plays', function () {
    $fallback = Play::factory()->create(['party_id' => $this->party->id, 'provider_track_id' => 'trk-1']);
    $byIsrc = TrackRequest::factory()->for($this->party)->create(['provider_track_id' => 'trk-other', 'isrc' => 'ISRC001']);
    $isrcPlay = Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $byIsrc->id, 'provider_track_id' => 'trk-other']);
    $trackPlay = Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $this->song->id, 'provider_track_id' => 'trk-1']);
    Play::factory()->create(['party_id' => $this->party->id, 'provider_track_id' => 'unrelated']);
    Play::factory()->create(['party_id' => Party::factory()->live()->create(['code' => 'WXYZ'])->id, 'provider_track_id' => 'trk-1']);
    Sanctum::actingAs(songDetailMemberUser($this->party));

    $fromRequest = collect((array) $this->getJson(songDetailApiUrl($this->party, $this->song))->assertOk()->json('data.appearances'));
    expect($fromRequest->map(fn (array $a): string => $a['kind'].$a['id'])->all())->toEqualCanonicalizing(['request'.$byIsrc->id, 'play'.$fallback->id])
        ->and($fromRequest->firstWhere('kind', 'play')['url'])->toBe("/parties/ABCD/plays/{$fallback->id}")
        ->and($fromRequest->firstWhere('kind', 'request')['url'])->toBe("/parties/ABCD/songs/{$byIsrc->id}");

    $fromFallback = collect((array) $this->getJson(playDetailApiUrl($this->party, $fallback))->assertOk()->json('data.appearances'));
    expect($fromFallback->map(fn (array $a): string => $a['kind'].$a['id'])->all())->toEqualCanonicalizing(['request'.$this->song->id])
        ->and($isrcPlay->id)->not->toBe($trackPlay->id);
});

it('runs a constant number of queries for the play route regardless of plays', function () {
    $user = songDetailMemberUser($this->party);
    $play = Play::factory()->create(['party_id' => $this->party->id, 'provider_track_id' => 'trk-1']);
    $seed = function (int $n) use ($play): void {
        foreach (range(1, $n) as $_) {
            $member = PartyMember::factory()->for($this->party)->create();
            Rating::factory()->create(['play_id' => $play->id, 'party_member_id' => $member->id]);
            Play::factory()->create(['party_id' => $this->party->id, 'provider_track_id' => 'trk-1']);
        }
    };
    $measure = fn () => $this->withoutVite()->actingAs($user)->get(playDetailUrl($this->party, $play))->assertOk();
    $api = function () use ($user, $play): void {
        Sanctum::actingAs($user);
        $this->getJson(playDetailApiUrl($this->party, $play))->assertOk();
    };

    $seed(1);
    $measure();
    $api();
    $fewWeb = countQueries($measure);
    $fewApi = countQueries($api);

    $seed(48);

    expect(countQueries($measure))->toBe($fewWeb)
        ->and(countQueries($api))->toBe($fewApi);
});
