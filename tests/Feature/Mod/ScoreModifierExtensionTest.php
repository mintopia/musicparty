<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Queue\Actions\SelectUpNext;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\Randomizer;
use App\Domain\Queue\SelectionMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Mods\ModFixtures;
use Tests\Fixtures\Mods\ScoreBoostMod;

uses(RefreshDatabase::class);

function modScored(Party $party, int $votes, ?PartyMember $member = null): TrackRequest
{
    $member ??= PartyMember::factory()->for($party)->create();
    $request = TrackRequest::factory()->create(['party_id' => $party->id, 'party_member_id' => $member->id]);

    RequestVote::factory()->count($votes)->create([
        'track_request_id' => $request->id,
        'party_member_id' => fn () => PartyMember::factory()->for($party),
        'value' => 1,
    ]);

    return $request;
}

beforeEach(function () {
    $this->party = Party::factory()->live()->create();
    $this->trusted = PartyMember::factory()->for($this->party)->create();
    $this->leader = modScored($this->party, 2);
    $this->boosted = modScored($this->party, 1, $this->trusted);
});

it('selects by votes alone when no Mod is enabled', function () {
    expect(app(SelectUpNext::class)($this->party)->is($this->leader))->toBeTrue();
});

it('lets a +3 adjustment change the selection and records the effective score and Mod', function () {
    ModFixtures::enable($this->party, new ScoreBoostMod(memberId: $this->trusted->id));

    $selected = app(SelectUpNext::class)($this->party);

    expect($selected->is($this->boosted))->toBeTrue()
        ->and($this->boosted->fresh()->selection_score)->toBe(4);
    $entry = PartyLogEntry::query()->where('action', 'mod.score_adjusted')->sole();
    expect($entry->system_actor)->toBe('mod:score-boost')
        ->and($entry->details)->toMatchArray(['mod' => 'Score-boost Mod', 'adjustment' => 3, 'request_id' => $this->boosted->id]);
});

it('reverts when the Mod is disabled', function () {
    $mod = ModFixtures::enable($this->party, new ScoreBoostMod(memberId: $this->trusted->id));
    ModFixtures::disable($this->party, $mod);

    expect(app(SelectUpNext::class)($this->party)->is($this->leader))->toBeTrue()
        ->and(PartyLogEntry::query()->where('action', 'mod.score_adjusted')->count())->toBe(0);
});

it('does not apply to another party', function () {
    $partyB = Party::factory()->live()->create();
    $b1 = modScored($partyB, 2);
    modScored($partyB, 1);
    ModFixtures::enable($this->party, new ScoreBoostMod(boost: 10));

    expect(app(SelectUpNext::class)($partyB)->is($b1))->toBeTrue();
});

it('sums adjustments from several Mods', function () {
    ModFixtures::enable($this->party, new ScoreBoostMod('boost-a', $this->trusted->id, 1));
    ModFixtures::enable($this->party, new ScoreBoostMod('boost-b', $this->trusted->id, 1));

    $selected = app(SelectUpNext::class)($this->party);

    expect($selected->is($this->boosted))->toBeTrue()
        ->and($selected->fresh()->selection_score)->toBe(3)
        ->and(PartyLogEntry::query()->where('action', 'mod.score_adjusted')->count())->toBe(2);
});

it('keeps the score_changed_at tie-breaker between equal effective scores', function () {
    $this->leader->forceFill(['score_changed_at' => now()->subHour()])->save();
    ModFixtures::enable($this->party, new ScoreBoostMod(memberId: $this->trusted->id, boost: 1));

    expect(app(SelectUpNext::class)($this->party)->is($this->leader))->toBeTrue();
});

it('ignores a failing modifier and logs it', function () {
    ModFixtures::enable($this->party, new ScoreBoostMod(failing: true));

    expect(app(SelectUpNext::class)($this->party)->is($this->leader))->toBeTrue()
        ->and(PartyLogEntry::query()->where('action', 'mod.score_failed')->count())->toBe(2);
});

it('uses effective scores for weighted selection', function () {
    $this->party->forceFill(['selection_mode' => SelectionMode::Weighted])->save();
    ModFixtures::enable($this->party, new ScoreBoostMod(memberId: $this->trusted->id, boost: 100));
    app()->instance(Randomizer::class, new class implements Randomizer
    {
        public function between(int $min, int $max): int
        {
            return $min;
        }
    });

    expect(app(SelectUpNext::class)($this->party->fresh())->is($this->boosted))->toBeTrue();
});
