<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Actions\ApplyScoreModifiers;
use App\Domain\Mod\Actions\RunScheduledActions;
use App\Domain\Mod\Trust\CalculateTrustScores;
use App\Domain\Mod\Trust\Models\TrustScore;
use App\Domain\Mod\Trust\TrustScoreMod;
use App\Domain\Mod\Trust\TrustSettings;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\Mods\ModFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Queue::fake();
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->host = PartyMember::factory()->for($this->party)->host()->create();
    $this->a = PartyMember::factory()->for($this->party)->create();
    $this->b = PartyMember::factory()->for($this->party)->create();
});

function trustRequestBy(PartyMember $member): TrackRequest
{
    return TrackRequest::factory()->create(['party_id' => $member->party_id, 'party_member_id' => $member->id]);
}

function trustVote(PartyMember $voter, TrackRequest $request, int $value = 1): void
{
    RequestVote::factory()->create(['track_request_id' => $request->id, 'party_member_id' => $voter->id, 'value' => $value]);
}

function trustBonus(Party $party, TrackRequest $request): int
{
    return app(ApplyScoreModifiers::class)($party, [$request])[$request->id][TrustScoreMod::ID]['value'] ?? 0;
}

function trustScores(Party $party): array
{
    return TrustScore::query()->where('party_id', $party->id)->pluck('score', 'party_member_id')->all();
}

function trustEnable(Party $party, array $settings = []): void
{
    ModFixtures::enable($party, app(TrustScoreMod::class), $settings);
}

it('raises the member the anchor upvotes over an otherwise equal member', function () {
    trustVote($this->host, trustRequestBy($this->a));
    trustVote($this->b, trustRequestBy($this->b));
    trustVote($this->a, trustRequestBy($this->b));

    app(CalculateTrustScores::class)($this->party, new TrustSettings([]));
    $scores = trustScores($this->party);

    expect($scores[$this->host->id])->toEqualWithDelta(1.0, 1e-9)
        ->and($scores[$this->a->id])->toBeGreaterThan($scores[$this->b->id]);
});

it('counts positive ratings on plays and ignores downvotes, self votes and votes outside the window', function () {
    $play = Play::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->a->id]);
    Rating::factory()->create(['play_id' => $play->id, 'party_member_id' => $this->host->id, 'value' => 1]);
    trustVote($this->host, trustRequestBy($this->b), -1);
    trustVote($this->b, trustRequestBy($this->b));
    $old = trustRequestBy($this->b);
    trustVote($this->host, $old);
    RequestVote::query()->where('track_request_id', $old->id)->update(['created_at' => now()->subDays(8)]);

    app(CalculateTrustScores::class)($this->party, new TrustSettings(['window_days' => 7]));
    $scores = trustScores($this->party);

    expect($scores[$this->a->id])->toEqualWithDelta(0.85, 1e-4)
        ->and($scores[$this->b->id])->toBe(0.0);
});

it('honours a configured anchor member and recalculates in place', function () {
    trustVote($this->a, trustRequestBy($this->b));

    app(CalculateTrustScores::class)($this->party, new TrustSettings(['anchor_member_id' => $this->a->id]));
    app(CalculateTrustScores::class)($this->party, new TrustSettings(['anchor_member_id' => $this->a->id]));

    expect(TrustScore::query()->count())->toBe(3)
        ->and(trustScores($this->party)[$this->a->id])->toEqualWithDelta(1.0, 1e-9)
        ->and(trustScores($this->party)[$this->b->id])->toEqualWithDelta(0.85, 1e-4);
});

it('logs every hourly run in the Party Log', function () {
    trustEnable($this->party);

    Cache::flush();
    app(RunScheduledActions::class)($this->party);

    $entry = PartyLogEntry::query()->where('party_id', $this->party->id)->where('action', 'mod.trust_scores_calculated')->sole();

    expect($entry->system_actor)->toBe('mod:trust-score')
        ->and($entry->details['members'])->toBe(3)
        ->and(trustScores($this->party))->toHaveCount(3);
});

it('adds the weighted bonus only when weighting is on', function (array $settings, int $expected) {
    trustEnable($this->party, $settings);
    TrustScore::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->a->id, 'score' => 0.75]);
    $request = trustRequestBy($this->a);

    expect(trustBonus($this->party, $request))->toBe($expected);
})->with([
    'off' => [['weighting' => false, 'max_bonus' => 4], 0],
    'on rounds' => [['weighting' => true, 'max_bonus' => 4], 3],
    'on zero cap' => [['weighting' => true, 'max_bonus' => 0], 0],
]);

it('gives system Requests, unscored members and a disabled Mod no bonus', function () {
    TrustScore::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->a->id, 'score' => 1.0]);
    $system = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => null]);

    expect(trustBonus($this->party, trustRequestBy($this->a)))->toBe(0);

    trustEnable($this->party, ['weighting' => true, 'max_bonus' => 5]);

    expect(trustBonus($this->party, $system))->toBe(0)
        ->and(trustBonus($this->party, trustRequestBy($this->b)))->toBe(0)
        ->and(trustBonus($this->party, trustRequestBy($this->a)))->toBe(5);
});

it('shows trust to the Host and Moderators on the Members page only', function () {
    trustEnable($this->party);
    TrustScore::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->a->id, 'score' => 0.5]);
    $moderator = PartyMember::factory()->for($this->party)->moderator()->create();

    foreach ([$this->host, $moderator] as $staff) {
        $this->actingAs($staff->user)->get('/parties/ABCD/members')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('trust.'.$this->a->id, 0.5)
            ->where('enabled_mods', ['trust-score']));
    }

    $this->actingAs($this->a->user)->get('/parties/ABCD/members')->assertForbidden();
});

it('omits trust when the Mod is disabled', function () {
    TrustScore::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->a->id, 'score' => 0.5]);

    $this->actingAs($this->host->user)->get('/parties/ABCD/members')->assertInertia(fn (Assert $page) => $page->where('trust', null));
});

it('never exposes trust to Guests, public pages or the public API', function () {
    trustEnable($this->party, ['weighting' => true]);
    TrustScore::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->a->id, 'score' => 0.5]);
    trustRequestBy($this->a);

    $this->actingAs($this->b->user)->get('/parties/ABCD')->assertOk()->assertInertia(fn (Assert $page) => $page->missing('trust'));
    expect($this->actingAs($this->b->user)->get('/parties/ABCD')->getContent())->not->toContain('"trust"');

    $this->actingAs($this->b->user)->getJson('/api/v1/parties/ABCD/queue')->assertOk()->assertDontSee('trust');
    $this->actingAs($this->b->user)->getJson('/api/v1/parties/ABCD/members')->assertForbidden();
});
