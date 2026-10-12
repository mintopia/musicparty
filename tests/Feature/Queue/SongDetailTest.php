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

    $ids = collect($this->getJson(songDetailApiUrl($this->party, $this->song))->assertOk()->json('data.appearances'))->pluck('id')->all();

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
