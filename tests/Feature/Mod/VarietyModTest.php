<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\ModRegistry;
use App\Domain\Mod\ModSettings;
use App\Domain\Mod\Variety\VarietyMod;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\RankQueue;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Mods\ModFixtures;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $settings
 */
function enableVariety(Party $party, array $settings = []): void
{
    $mod = app(ModRegistry::class)->find(VarietyMod::ID) ?? throw new LogicException('Variety Mod is not registered');

    ModFixtures::enable($party, $mod, [...app(ModSettings::class)->defaults($mod), ...$settings]);
}

/**
 * @param  list<string>  $artists
 */
function varietyRequest(Party $party, PartyMember $member, array $artists, int $votes, RequestStatus $status = RequestStatus::Queued): TrackRequest
{
    $request = TrackRequest::factory()->create([
        'party_id' => $party->id,
        'party_member_id' => $member->id,
        'artists' => $artists,
        'status' => $status,
    ]);

    RequestVote::factory()->count($votes)->create([
        'track_request_id' => $request->id,
        'party_member_id' => fn () => PartyMember::factory()->for($party),
        'value' => 1,
    ]);

    return $request;
}

function varietyScore(Party $party, TrackRequest $request): int
{
    $ranked = app(RankQueue::class)($party, TrackRequest::query()->where('party_id', $party->id)->where('status', RequestStatus::Queued));

    return (int) $ranked->requests->firstWhere('id', $request->id)?->score;
}

beforeEach(function () {
    $this->party = Party::factory()->live()->create();
    $this->host = PartyMember::factory()->for($this->party)->create();
    $this->other = PartyMember::factory()->for($this->party)->create();
    $this->playing = varietyRequest($this->party, $this->host, ['The Band'], 0, RequestStatus::Playing);
});

it('registers with 50% defaults', function () {
    $mod = app(ModRegistry::class)->find('variety') ?? throw new LogicException('Variety Mod is not registered');

    expect(app(ModSettings::class)->defaults($mod))->toBe(['requester_penalty_percent' => 50, 'artist_penalty_percent' => 50]);
});

it('halves the score of the playing requester', function () {
    enableVariety($this->party);
    $request = varietyRequest($this->party, $this->host, ['Someone'], 10);

    expect(varietyScore($this->party, $request))->toBe(5);
});

it('halves the score of the playing artist, ignoring case and whitespace', function () {
    enableVariety($this->party);
    $request = varietyRequest($this->party, $this->other, ['  the BAND '], 10);

    expect(varietyScore($this->party, $request))->toBe(5);
});

it('matches any artist of a multi-artist track', function () {
    enableVariety($this->party);
    $request = varietyRequest($this->party, $this->other, ['Guest', 'The Band'], 10);

    expect(varietyScore($this->party, $request))->toBe(5);
});

it('compounds both penalties', function () {
    enableVariety($this->party);
    $request = varietyRequest($this->party, $this->host, ['The Band'], 100);

    expect(varietyScore($this->party, $request))->toBe(25);
});

it('leaves unrelated requests alone', function () {
    enableVariety($this->party);
    $request = varietyRequest($this->party, $this->other, ['Someone'], 10);

    expect(varietyScore($this->party, $request))->toBe(10);
});

it('skips a disabled penalty', function (array $settings, int $expected) {
    enableVariety($this->party, $settings);
    $request = varietyRequest($this->party, $this->host, ['The Band'], 10);

    expect(varietyScore($this->party, $request))->toBe($expected);
})->with([
    'requester off' => [['requester_penalty_percent' => 0], 5],
    'artist off' => [['artist_penalty_percent' => 0], 5],
    'both off' => [['requester_penalty_percent' => 0, 'artist_penalty_percent' => 0], 10],
]);

it('keeps a matching request positive', function () {
    enableVariety($this->party, ['requester_penalty_percent' => 100, 'artist_penalty_percent' => 100]);
    $request = varietyRequest($this->party, $this->host, ['The Band'], 3);

    expect(varietyScore($this->party, $request))->toBe(1);
});

it('does not adjust zero or negative scores', function () {
    enableVariety($this->party);
    $unvoted = varietyRequest($this->party, $this->host, ['The Band'], 0);
    $downvoted = varietyRequest($this->party, $this->host, ['The Band'], 0);
    RequestVote::factory()->create(['track_request_id' => $downvoted->id, 'party_member_id' => PartyMember::factory()->for($this->party), 'value' => -1]);

    expect(varietyScore($this->party, $unvoted))->toBe(0)
        ->and(varietyScore($this->party, $downvoted))->toBe(-1);
});

it('does nothing when no request is playing', function () {
    enableVariety($this->party);
    $this->playing->update(['status' => RequestStatus::Played]);
    $request = varietyRequest($this->party, $this->host, ['The Band'], 10);

    expect(varietyScore($this->party, $request))->toBe(10);
});

it('ignores the playing request of another party', function () {
    enableVariety($this->party);
    $this->playing->update(['status' => RequestStatus::Played]);
    $otherParty = Party::factory()->live()->create();
    varietyRequest($otherParty, PartyMember::factory()->for($otherParty)->create(), ['The Band'], 0, RequestStatus::Playing);
    $request = varietyRequest($this->party, $this->other, ['The Band'], 10);

    expect(varietyScore($this->party, $request))->toBe(10);
});

it('looks up the playing request once per rank pass', function () {
    enableVariety($this->party);
    foreach (range(1, 5) as $i) {
        varietyRequest($this->party, $this->other, ['Artist '.$i], 4);
    }

    DB::enableQueryLog();
    app(RankQueue::class)($this->party, TrackRequest::query()->where('party_id', $this->party->id)->where('status', RequestStatus::Queued));
    $lookups = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'limit 1') && in_array(RequestStatus::Playing->value, $q['bindings'], true));

    expect($lookups)->toHaveCount(1);
});
