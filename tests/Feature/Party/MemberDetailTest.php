<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->moderator = PartyMember::factory()->for($this->party)->moderator()->create();
    $this->target = PartyMember::factory()->for($this->party)->create();
});

function castMemberVote(PartyMember $member, string $title, string $createdAt, int $value = 1): RequestVote
{
    $request = TrackRequest::factory()->for($member->party)->create(['title' => $title]);

    return RequestVote::factory()->create([
        'track_request_id' => $request->id,
        'party_member_id' => $member->id,
        'value' => $value,
        'created_at' => $createdAt,
    ]);
}

it('shows a member detail to a moderator over the api', function () {
    $request = TrackRequest::factory()->for($this->party)->create(['party_member_id' => $this->target->id, 'title' => 'Requested Song']);
    Sanctum::actingAs($this->moderator->user);

    $this->getJson("/api/v1/parties/ABCD/members/{$this->target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->target->id)
        ->assertJsonPath('data.role', 'guest')
        ->assertJsonPath('data.banned', false)
        ->assertJsonStructure(['data' => ['id', 'nickname', 'avatar', 'role', 'banned', 'is_you', 'joined_at', 'requests' => [['id', 'title', 'artists', 'status', 'requested_at']]]])
        ->assertJsonPath('data.requests.0.id', $request->id)
        ->assertJsonPath('data.requests.0.title', 'Requested Song');
});

it('lists votes newest first with the api payload shape', function () {
    castMemberVote($this->target, 'Old', '2026-01-01 10:00:00');
    castMemberVote($this->target, 'New', '2026-03-01 10:00:00', -1);
    Sanctum::actingAs($this->moderator->user);

    $this->getJson("/api/v1/parties/ABCD/members/{$this->target->id}/votes")
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'value', 'voted_at', 'request_id', 'title', 'artists']], 'links', 'meta'])
        ->assertJsonPath('data.0.title', 'New')
        ->assertJsonPath('data.0.value', -1)
        ->assertJsonPath('data.1.title', 'Old');
});

it('searches votes by track title and artist and escapes wildcards', function () {
    castMemberVote($this->target, 'Blue Monday', '2026-01-01 10:00:00');
    castMemberVote($this->target, '100% Pure', '2026-01-02 10:00:00');
    $byArtist = castMemberVote($this->target, 'Other', '2026-01-03 10:00:00');
    assert($byArtist->request !== null);
    $byArtist->request->update(['artists' => ['Daft Punk']]);
    Sanctum::actingAs($this->moderator->user);

    $titles = fn (string $query): array => array_column($this->getJson("/api/v1/parties/ABCD/members/{$this->target->id}/votes?search={$query}")->assertOk()->json('data'), 'title');

    expect($titles('monday'))->toBe(['Blue Monday'])
        ->and($titles('Daft'))->toBe(['Other'])
        ->and($titles(urlencode('%')))->toBe(['100% Pure'])
        ->and($titles('zzz'))->toBe([]);
});

it('pages votes', function () {
    foreach (range(1, 5) as $i) {
        castMemberVote($this->target, "Song {$i}", "2026-01-0{$i} 10:00:00");
    }
    Sanctum::actingAs($this->moderator->user);

    $this->getJson("/api/v1/parties/ABCD/members/{$this->target->id}/votes?per_page=2&page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('data.0.title', 'Song 3')
        ->assertJsonPath('data.1.title', 'Song 2');

    $this->getJson("/api/v1/parties/ABCD/members/{$this->target->id}/votes?per_page=101")->assertUnprocessable();
});

it('only lists votes cast by that member', function () {
    castMemberVote($this->target, 'Mine', '2026-01-01 10:00:00');
    castMemberVote($this->moderator, 'Theirs', '2026-01-02 10:00:00');
    Sanctum::actingAs($this->moderator->user);

    $this->getJson("/api/v1/parties/ABCD/members/{$this->target->id}/votes")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Mine');
});

it('refuses another party\'s member on every surface', function (string $path) {
    $other = PartyMember::factory()->for(Party::factory()->live()->create(['code' => 'WXYZ']))->create();
    Sanctum::actingAs($this->moderator->user);

    $this->getJson(str_replace('{id}', (string) $other->id, "/api/v1/parties/ABCD/members/{id}{$path}"))->assertNotFound();
    $this->get("/parties/ABCD/members/{$other->id}")->assertNotFound();
})->with(['detail' => [''], 'votes' => ['/votes']]);

it('refuses guests, vips and anonymous callers', function () {
    $paths = ["/api/v1/parties/ABCD/members/{$this->target->id}", "/api/v1/parties/ABCD/members/{$this->target->id}/votes"];

    foreach ($paths as $path) {
        $this->getJson($path)->assertUnauthorized();
    }

    foreach ([$this->target, PartyMember::factory()->for($this->party)->vip()->create()] as $member) {
        Sanctum::actingAs($member->user);
        foreach ($paths as $path) {
            $this->getJson($path)->assertForbidden();
        }
        $this->get("/parties/ABCD/members/{$this->target->id}")->assertForbidden();
    }
});

it('renders the member page for a moderator with exact props', function () {
    TrackRequest::factory()->for($this->party)->create(['party_member_id' => $this->target->id, 'title' => 'Asked']);
    castMemberVote($this->target, 'Voted', '2026-01-01 10:00:00');

    $this->actingAs($this->moderator->user)->get("/parties/ABCD/members/{$this->target->id}?search=Vot")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Party/Member')
            ->where('party', ['code' => 'ABCD', 'name' => $this->party->name])
            ->where('member.id', $this->target->id)
            ->where('member.role', 'guest')
            ->where('member.banned', false)
            ->has('requests', 1)
            ->where('requests.0.title', 'Asked')
            ->has('votes.data', 1)
            ->where('votes.data.0.title', 'Voted')
            ->where('filters', ['search' => 'Vot'])
            ->where('abilities', ['canChangeRoles' => false, 'canBan' => true]));
});

it('offers role changes to the host and withholds actions on moderators and the host', function () {
    $host = PartyMember::factory()->for($this->party)->host()->create();
    $moderator = PartyMember::factory()->for($this->party)->moderator()->create();

    $this->actingAs($host->user)->get("/parties/ABCD/members/{$this->target->id}")
        ->assertInertia(fn (Assert $page): Assert => $page->where('abilities', ['canChangeRoles' => true, 'canBan' => true]));

    $this->actingAs($this->moderator->user)->get("/parties/ABCD/members/{$moderator->id}")
        ->assertInertia(fn (Assert $page): Assert => $page->where('abilities.canBan', false));

    $this->actingAs($this->moderator->user)->get("/parties/ABCD/members/{$host->id}")
        ->assertInertia(fn (Assert $page): Assert => $page->where('abilities', ['canChangeRoles' => false, 'canBan' => false]));
});

it('shows banned members to moderators', function () {
    $banned = PartyMember::factory()->for($this->party)->banned()->create();

    $this->actingAs($this->moderator->user)->get("/parties/ABCD/members/{$banned->id}")
        ->assertInertia(fn (Assert $page): Assert => $page->where('member.banned', true));
});
